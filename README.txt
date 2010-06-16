---------------------------------
Getting started
---------------------------------

1) Copy the contents of the sample_site directory to the location of your site

2) In htdocs/index.php, update the path to the autoload.php file to the location of your crossbar file (use relative path).

3) Add a rewrite rule either in your apache conf file or in your .htaccess file to send all non-static file traffic to the bootstrap.  Here's an example:

Options +FollowSymLinks
RewriteEngine On
RewriteBase /
RewriteRule !\.(js|ico|gif|jpg|png|css|html|pdf)$ index.php


