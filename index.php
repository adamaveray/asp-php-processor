<?php
error_reporting(E_ALL);
ini_set('display_errors', true);
error_reporting(E_ALL);

abstract class ASPPHPProcessor {
	const ACTION_DELETE		= 0;
	const ACTION_COMMENT	= 1;
	const ACTION_PARSE		= 2;

	/**
	 * @var int What action to take on any ASP encountered
	 */
	public static $asp_action	= self::ACTION_DELETE;
	/**
	 * @var string The root directory to look in for files to process. If unset, the current directory will be used
	 */
	public static $root_dir;
	
	protected static $replace	= array(
		'/request\((.*?)\)/i'				=> '$_REQUEST[$1]',
		'/request.servervariables\((.*?)\)/i'	=> '$_SERVER[$1]',
		'/response\.write\s*(.*?)$/i'		=> 'echo $1',
		'/response\.write\((.*?)\)$/i'		=> 'echo $1',
		'/response\.addheader\s+(.*?)$/i'	=> 'header($1)',
		'/response\.addheader\((.*?)\)$/i'	=> 'header($1)',
		'/isnull\((.*?)\)/i'				=> '!isset($1)',
		'/header\("Location",\s*?([\w_]*?)\)$/i'	=> 'header("Location: ".\\$$1)',
		'/response\.status\s*?= *?"(.*?)"/i'	=> 'header(\'HTTP/1.1 $1\')',
		'/replace\s*\((.*?),(.*?),(.*?)\)/i'	=> 'str_replace($2,$3,\$$1)',
		'/now\s*\(\)/i'						=> 'date(\'d/m/Y h:i:s A\')',
		'/Year\s*\((.*?)\)/i'				=> 'date(\'Y\')',
		'/Server.URLEncode\n*\((.*?)\)/i'	=> 'urlencode($1)',
		'/left\((.*?),\s*?(\d+)\)/i'		=> 'substr($1, 0, $2)',
		'/right\((.*?),\s*?(\d+)\)/i'		=> 'substr($1, -$2)',
		'/(.*?)\s*?&/i'						=> '$1.',
		'/([\s=\.])([\w][\w_]+[\s\.;])/i'	=> '$1\\$$2', // Final attempts at variables
		'/\(([\w][\w_]+[\s\.;\)])/i'		=> '(\\$$1' // Final attempts at variables
	);

	/**
	 * Filters the given URL, stripping the querystring and other elements,
	 * and locating directory index files where available.
	 *
	 * @static
	 * @param $request_url string	The raw page URL requested by the client
	 * @return string				The processed URL
	 */
	public static function parse_request($request_url){
		if(!isset(self::$root_dir)){
			// No root directory given
			self::$root_dir	= __DIR__.'/';
		}

		// Process URL and strip irrelevant details (eg: querystring)
		$request	= ltrim($request_url	, '/');
		$request	= str_replace('%20', ' ', $request);
		if(strstr($request, '?') !== false){
			// Has querystring - remove
			$request	= substr($request, 0, strpos($request, '?'));
		}

		if(strstr($request, '.') !== false){
			// Request has file extension
			$ext	= pathinfo(self::$root_dir.$request, PATHINFO_EXTENSION);
			$mime	= self::get_mime_for_extension($ext);

			if(isset($mime)){
				// Alternate file type
				header('HTTP1/1 200 Found');
				header('Content-Type: '.$mime);
			}
		}

		if(!file_exists($request) || is_dir($request)){
			// Cannot find file - try index files
			$request	= self::find_directory_index($request);
		}

		// File found
		return $request;
	}

	/**
	 * Finds the MIME type for the given file extension
	 *
	 * @static
	 * @param $ext string	The extension to fine the MIME type of
	 * @return string|null	The MIME type string, or NULL if not found
	 */
	protected static function get_mime_for_extension($ext){
		$mimes	= array('css'	=> 'text/css',
						'js'	=> 'text/javascript',
						// Images
						'jpg'	=> 'image/jpeg',
						'jpeg'	=> 'image/jpeg',
						'png'	=> 'image/png',
						'gif'	=> 'image/gif',
						'svg'	=> 'image/svg+xml',
						'svgz'	=> 'image/svg+xml',
						// Videos
						'mp4'	=> 'video/mp4',
						'm4v'	=> 'video/x-m4v',
						'flv'	=> 'video/x-flv',
						// Audio
						'mp3'	=> 'audio/mpeg',
						'ogg'	=> 'audio/ogg',
						'oga'	=> 'audio/ogg',
						'wav'	=> 'audio/wav',
						// Fonts
						'woff'	=> 'application/x-font-woff',
						'otf'	=> 'font/opentype',
						'eot'	=> 'application/vnd.ms-fontobject',
						'ttf'	=> 'application/octet-stream',
						// Other
						'pdf'	=> 'application/pdf',
						'swf'	=> 'application/x-shockwave-flash');
		if(isset($mimes[$ext])){
			return $mimes[$ext];
		}

		return NULL;
	}

	/**
	 * Scans the given directory for any index file, and returns the
	 * path to that file.
	 *
	 * @static
	 * @param $dir string	The directory to search
	 * @return null|string	The path to the index, or NULL if not found
	 */
	protected static function find_directory_index($dir){
		$dir	= rtrim($dir, '/').'/';

		$indices	= array('default.asp',
							'Default.asp',	// Seems to be the way people do this...
							'default.aspx',
							'default.aspx',
							'index.html',
							'index.htm',
							'index.php');

		foreach($indices as $index){
			$index_path	= $dir.$index;

			if(file_exists($index_path) && $index_path != __FILE__){
				// Index file found
				return $index_path;
			}
		}

		// No index exists
		return NULL;
	}

	/**
	 * Retrieves a local file, and processes ASP includes and tags.
	 *
	 * @static
	 * @param $path string	The local path to the file
	 * @param $top bool		Whether this is the top-level parsed file – should not be set from external calls
	 * @return string		The processed file contents
	 */
	public static function parse_file($path, $top = true){
		$extension	= strtolower(pathinfo($path, PATHINFO_EXTENSION));
		if($extension == 'php'){
			$dirs	= explode('/', $path);
			set_include_path(get_include_path().PATH_SEPARATOR.__DIR__.'/'.current($dirs));
			include($path);
			return ob_get_clean();
		}
		
		$content	= file_get_contents($path);

		if($extension != 'asp'){
			// Non-ASP file – do not parse
			return $content;
		}

		$content	= str_replace('<?', '&lt;?', $content);

		// Parse includes
		$content	= preg_replace_callback('/<!-- ?#include virtual="(.*?)" ?-->/i', array(__CLASS__, 'include_callback'), $content);

		if(self::$asp_action === self::ACTION_PARSE){
			// Attempt to interpret ASP

			// Remove any potenial PHP – will be eval'd

			// Parse ASP
			$content	= preg_replace_callback('/<\%([^%]*?)\%>/ms', array(__CLASS__, 'parse_callback'), $content);

			if($top){
				ob_start();
				echo $content;
				// $result	= eval('?'.'>'.$content);
				if($result === false){
					header('Content-Type: text/plain');
					ob_end_flush();
					echo $content;
				} else {
					$content	= ob_get_clean();
				}
			}

		} elseif(self::$asp_action === self::ACTION_COMMENT){
			// Comment out ASP
			$content	= preg_replace('/(<\%([^%]*?)\%>)/', '<!-- $1 -->', $content);
		} else {
			// Strip all ASP
			$content	= preg_replace('/<\%([^%]*?)\%>/', '', $content);
		}

		return $content;
	}

	/**
	 * The callback for the ASP parsing regex, converting ASP
	 * code to PHP code, and wrapping it in the correct tags
	 *
	 * @static
	 * @param $params array	The regex matches. Index 1 is the matched ASP code
	 * @return string		The converted PHP code
	 */
	protected static function parse_callback($params){
		$result	= self::convert_asp(trim($params[1]));
		
		return '<?php'.PHP_EOL.$result.PHP_EOL.'?>';
	}

	/**
	 * Converts a block of ASP code into PHP code
	 *
	 * @static
	 * @param $asp string	The ASP code to convert, with no wrapping tags
	 * @return string		The converted PHP code, with no wrapping tags
	 */
	public static function convert_asp($asp){
		$asp	= explode(PHP_EOL, $asp);
		
		if(count($asp) == 1 && substr($asp[0], 0, 1) == '='){
			return 'echo '.self::convert_asp_fragment(substr($asp[0], 1)).';';
		}
		
		$string	= '';
		foreach($asp as $line){
			// Ignore language definiton line, etc
			if(strpos($line, '@') === 0){
				continue;
			}
			
			// Strip comments
			if(strpos($line, '\'') !== false){
				$line	= preg_replace('/^(.+?)(\'[^"]+)$/', '$1', $line);
			}

			if(strpos($line, '\'') !== false){
				if(strpos($line, '\\\'') !== false){
					$line	= str_replace('\\\'', '\'', $line);
				} else {
					$line	= substr($line, 0, strpos($line, '\''));
				}
			}

			$line	= trim($line);

			if(strlen($line) < 1){
				// Blank line
				continue;
			}

			if(stripos($line, 'dim ') === 0){
				// Defining variables – unneccesary
				continue;
			}

			// If
			if(stripos($line, 'if ') === 0 || stripos($line, 'else if ') === 0){
				$line	= preg_replace('/((?:else )?)if (.*?) then/i', '$1if($2){', $line);
				$line	= preg_replace('/(\w+) ?([=<>])/i', '\$$1 $2', $line);
				$line	= str_replace('<>', '!=', $line);
				$line	= preg_replace('/([\w ])=([\w ])/i', '$1==$2', $line);

			} else if(stripos($line, 'end if') === 0){
				$line	= '}';
			}

			// Variable – `myVar = 1 --> $myVar = 1`
			$line	= preg_replace('/^(\w*?)[ \t]*?=[ \t]?(.*?)$/i', '\$$1 = $2;', $line);
			
			$line	= preg_replace(array_keys(self::$replace), array_values(self::$replace), $line);

			$string	.= rtrim($line,';').';'.PHP_EOL;
		}

		$string	= trim($string);
		if(strlen($string) < 1){
			return '';
		}

		return $string;
	}

	protected static function convert_asp_fragment($fragment){
		$fragment	= preg_replace(array_keys(self::$replace), array_values(self::$replace), $fragment);
		
		if(preg_match('/\w[\w_\d]*?/', $fragment)){
			if(preg_match('/\w[\w_\d]*?\(/', $fragment)){
				return $fragment;
			}
			// Variable
			return '$'.$fragment;
		}

		return '/* Unknown: '.$fragment.' */';
	}

	/**
	 * The callback for the file parser's regex, replacing any
	 * include directives with the file's content.
	 *
	 * @static
	 * @param $params array	The regex matches. Index 1 is the matched URL.
	 * @return string		The processed contents of the requested file
	 */
	protected static function include_callback($params){
		$path	= self::$root_dir.ltrim($params[1], '/');

		$result	= self::parse_file($path, false);
		if(!$result){
			return '<!-- '.$path.' -->';
		}

		return $result;
	}
};

// Process URL
$path	= explode('/', trim($_SERVER['REQUEST_URI'], '/'));
if(count($path) === 1 && $path[0] === ''){
	include('menu.php');
	exit;
}
ASPPHPProcessor::$root_dir	= str_replace('%20', ' ', __DIR__.'/'.array_shift($path).'/');

ASPPHPProcessor::$asp_action	= ASPPHPProcessor::ACTION_PARSE;
$result	= ASPPHPProcessor::parse_request($_SERVER['REQUEST_URI']);
if(!isset($result)){
	// Page not found
	header('HTTP/1.0 404 Not Found');
	echo '<h1>Not Found</h1>'.PHP_EOL
		 .'<p>'.$result.'</p>';
	return;
}

// Page found
error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);
echo ASPPHPProcessor::parse_file($result);
?>