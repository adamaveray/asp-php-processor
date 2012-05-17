<?php
abstract class ASPPHPProcessor {
	/**
	 * @var bool Whether to comment out ASP within pages. If set to false, all ASP will be deleted
	 */
	public static $comment_asp	= false;
	/**
	 * @var string The root directory to look in for files to process. If unset, the current directory will be used
	 */
	public static $root_dir;

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
						'jpg'	=> 'image/jpeg',
						'jpeg'	=> 'image/jpeg',
						'png'	=> 'image/png',
						'gif'	=> 'image/gif');
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
							'index.htm');

		foreach($indices as $index){
			$index_path	= $dir.$index;

			if(file_exists($index_path)){
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
	 * @return string		The processed file contents
	 */
	public static function parse_file($path){
		$content	= file_get_contents($path);

		if(self::$comment_asp){
			// Comment out ASP
			$content	= preg_replace('/(<\%.*?\%>)/', '<!-- $1 -->', $content);
		} else {
			// Strip all ASP
			$content	= preg_replace('/<\%.*?\%>/', '', $content);
		}
	
		$content	= preg_replace_callback('/<!--#include virtual="(.*?)"-->/i', array(__CLASS__, 'parse_callback'), $content);

		return $content;
	}

	/**
	 * The callback for the file parser's regex, replacing any
	 * include directives with the file's content.
	 *
	 * @static
	 * @param $params array	The regex matches. Index 1 is the matched URL.
	 * @return string		The processed contents of the requested file
	 */
	protected static function parse_callback($params){
		$path	= self::$root_dir.ltrim($params[1], '/');

		return self::parse_file($path);
	}
};

// Process URL
$result	= ASPPHPProcessor::parse_request($_SERVER['REQUEST_URI']);
if(!isset($result)){
	// Page not found
	header('HTTP/1.0 404 Not Found');	
	echo '<h1>Not Found</h1>'.PHP_EOL
		 .'<p>'.$result.'</p>';
	return;
}

// Page found
echo ASPPHPProcessor::parse_file($result);
?>