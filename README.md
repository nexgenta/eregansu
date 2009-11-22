Eregansu Hello World
====================

Change to your web server root (or wherever you want your application to
be served from).

Create directories named 'config', 'app', and 'templates'

	$ mkdir config app templates


Check out Eregansu into a directory called 'platform':

	$ git clone git://github.com/nexgenta/eregansu.git platform


Create symbolic links and copies to templates, and create a sensible
default index.php:

	$ cd templates
	$ ln -s ../platform/login/templates login
	$ cd ..
	$ cp platform/index.dist.php index.php


You will also want an .htaccess file. Start with the provided one:

	$ cp platform/htaccess.dist .htaccess


If you’re going to use the provided login applet, you’ll want Chroma-Hash:

	$ git clone git://github.com/mattt/Chroma-Hash.git


Next, configure your instance. Copy the default (empty) instance configuration
and make it the current configuration:

	$ cp platform/config.default.php config/config.myhostname.php
	$ ( cd config && ln -s config.myhostname.php config.php )

(Replace “myhostname” with the name of your host — e.g., johndev)

Now you can configure your application. Launch a PHP editor and create a new
file containing the following:

	<?php
	
	/* My application configuration */
	
	$HTTP_ROUTES = array(
		'__NONE__' => array('class' => 'Page', 'templateName' => 'hello.phtml'),
	);
	
Save this file as config/appconfig.hello.php

Make this application configuration active by symlinking it:

	$ ( cd config && ln -s appconfig.hello.php appconfig.php )

Create a dummy template:

	$ mkdir templates/default
	$ echo '<h1>Hello, World!</h1>' > templates/default/hello.phtml

Finally, launch your web browser and navigate to the server you’ve performed
all of this on. If all is well, you should see a page containing just the words
“Hello, World!”.
