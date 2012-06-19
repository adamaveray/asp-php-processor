ASP to PHP Processor
====================

Basically just a way to use ASP server-side includes on
a LAMP stack.

## What Is This? ##

ASP, like virtually all server-side languages, lets you
include other files within your site, which is great for
reusing things like a common site header and footer or
other shared elements of a page throughout a site.

All this file really does is convert `<!--#include virtual="/path/to/file.asp"-->`
to `<?php include('path/to/file'); ?>`. It'll do the same
in all included files, too.

It will also perform **very basic** ASP to PHP code
conversion. Only a limited number of ASP language
features are implemented so far.

**Note:** This is really just for local development
and testing, _not_ production. It should go without
saying, but if your production environment is PHP,
maybe you should be using actual PHP, not ASP.


## Usage ##

Put the `index.php` and `.htaccess` files into the
site's root directory. That's it.


## Supported ASP Features ##

- ### Variables
  
  Primitive ASP variable instantiation and usage
  is supported.
  
  ```
  <%
  Dim myVar, anotherVar
  myVar			= 1
  anotherVar	= "Something"
  %>
  ```
  
  _converts to_
  
  ```
  <?php
  $myVar		= 1;
  $anotherVar	= "Something";
  ?>
  ```
  
  **Note:** Currently, combining variables is
  _not yet supported_ (eg: `<% myVar = anotherVar&" else" %>`
  is not supported).
  
- ### `if` Statements

  Basic `if` statements are available.
  
  ```
  <%
  If myVar = 4 Then
  %>
	<p>myVar is 4</p>
  <%
  End If
  %>
  ```
  
  _converts to_
  
  ```
  <?php
  if($myVar == 4){
  ?>
  	<p>myVar is 4</p>
  <?php
  }
  ?>
  ```
  
  **Note:** Currently, `else`/`else if` statements
  are _not yet supported_.
    

## Advanced Usage ##

The file will handle routing to standard index files,
such as `Default.asp` and `index.html`. That means
that requests to URLs like `http://www.example.com/path`
will — assuming `/path` isn't a file – look for
`/path/default.asp`, `/path/index.html`, etc, before
returning a 404. The list of index files is editable,
too.

You can also manually set the top level directory by
setting the `ASPPHPProcessor::$root_dir` to a different
directory than the current one. This can let you do
things like only have one copy of this utility,
providing access to a number of sites.


## License ##

There isn't one. If you want to give me credit, that's
awesome, but either way feel free to do whatever you
want with it. No warranties!