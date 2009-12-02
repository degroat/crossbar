<?
session_start();

// ------------------------------------------------------------------
// This file is necessary for autoloading classes from within the
// framework, models and utilities folders
// ------------------------------------------------------------------
require "../../autoload.php"; // makes ure this points to where you put crossbar

// ------------------------------------------------------------------
// OPTIONAL Connect to memcache server. If you plan on using the  
// caching functionality built into the mysql plugin, you must 
// configure this connection
// ------------------------------------------------------------------
mc::connect('mem.cache.server.com');

// ------------------------------------------------------------------
// The following config section sets up possible databse connections
// to be used by models within the application
// ------------------------------------------------------------------

mysql::database_config(
		'main',			// alias
		'mysql.server.com',	// host
		'mysqldb',		// database
		'mysqluser',		// username
		'mysqlpw'		// password
		);


// ------------------------------------------------------------------
// Create our framework object. 
// ------------------------------------------------------------------
$crossbar = new crossbar();

// ------------------------------------------------------------------
// To add a directory to the include path, use the line below
// ------------------------------------------------------------------
//$crossbar->add_to_include_path("path/to/another/directory");

// ------------------------------------------------------------------
// To override the default location of the framework directories,
// uncomment the necessary line(s) below and update with the correct
// path
// ------------------------------------------------------------------
//$crossbar->set_framework_path('/test/path');
//$crossbar->set_view_path('/testviewpath/');
//$crossbar->set_models_path('/test/models/path/');
//$crossbar->set_utilities_path('/test/utils/path/');
//$crossbar->set_controllers_path('/test/controllers/path/');
//$crossbar->set_layouts_path('/test/layouts/path');
//$crossbar->set_modules_path('/test/modules/path');

// ------------------------------------------------------------------
// This is what fires off the framework
// ------------------------------------------------------------------
$crossbar->go();

?>
