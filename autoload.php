<?


function crossbar_autoload($class_name) 
{
	include_once str_replace('\\', '/', str_replace('_', '/', $class_name)) . '.php';
}

spl_autoload_register('crossbar_autoload', FALSE); 

?>
