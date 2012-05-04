ASP to PHP Processor
====================

Basically just a way to use ASP server-side includes on a LAMP stack.

## What Is This? ##

ASP, like virtually all server-side languages, lets you include other files within your site. This is great for reusing things like a common site header and footer, or other shared elements of a page throughout a site.

All this really does is convert `<!--#include virtual="/path/to/file.asp"-->` to `<?php include('path/to/file'); ?>`. It'll do the same in all included files, too.

It does **not** convert actual ASP application logic to PHP.

**Note:** This is really just for local development and testing, _not_ production. It should go without saying, but if your production environment is PHP, maybe you should be using actual PHP, not ASP.

## Usage ##

Put the `index.php` and the `.htaccess` files into the site's root directory. That's it.

## Advanced Usage ##

The file will handle routing to standard index files, such as `Default.asp` and `index.html`. That means that requests to URLs like `http://www.example.com/path` will — assuming `/path` isn't a file – look for `/path/default.asp`, `/path/index.html`, etc, before returning a 404. The list of index files is editable, too.

You can also manually set the top level directory by setting the `ASPPHPProcessor::$root_dir` to a different directory than the current one. This can let you do things like only have one copy of this utility, providing access to a number of sites.

## License ##

There isn't one. If you want to give me credit, that's awesome, but either way feel free to do whatever you want with it. No warranties!