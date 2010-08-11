<?php
/**
* This is the main class for the crossbar php framework
* @author Chris DeGroat 
**/

class crossbar
{
	public function __construct()
	{
		$this->parse_url();

		$this->view 			= $this->controller . "/" . $this->action;

		$application_root	 	= str_replace('htdocs', '', $_SERVER['DOCUMENT_ROOT']);
		$this->framework_path 		= $application_root . 'framework/';
		$this->views_path 		= $application_root . 'views/';
		$this->models_path 		= $application_root . 'models/';
		$this->utilities_path 		= $application_root . 'utilities/';
		$this->controllers_path		= $application_root . 'controllers/';
		$this->layouts_path 		= $application_root . 'layouts/';
		$this->modules_path 		= $application_root . 'modules/';
		$this->starting_include_path 	= explode(PATH_SEPARATOR, get_include_path());
		$this->custom_include_paths	= array();

		$this->set_include_path();
	}

	// ---------------------------------------
	// Public Functions
	// ---------------------------------------

	public function go()
	{
		$this->auth_error = FALSE;

		// If the controller doesn't exist, send them to index/_error
		if(!$this->set_controller_object())
		{	
			$this->controller = "index";
			$this->action = "_error";
			$this->set_controller_object();
			$this->view = $this->controller . "/" . $this->action;
		}	

		// If the action we're not looking for doesn't exist and a re-write action does exist, use the rewrite one
		if(!method_exists($this->controller_object, $this->action) && method_exists($this->controller_object, '_rewrite'))
		{
			$this->build_rewrite_params();
			$this->action =$this->controller_object->_rewrite();
			if(empty($this->action))
			{
				$this->error("_rewrite function must return value to set as action");
			}
			unset($_GET['_params']);
			$this->controller_object->action = $this->action;
			$this->view = $this->controller . "/" . $this->action;
		}

		// Verify that the action exists on this controller
		if(!method_exists($this->controller_object, $this->action))
		{
			$this->action = '_error';
			$this->controller_object->action = $this->action;
			$this->view = $this->controller . "/" . $this->action;
		}

		// Verify that the user has permissions to view this controller/action... unless it's a _error action
		if(!auth::access($this->controller, $this->action) && $this->action != '_error')
		{
			// User isn't allowed to access this controller/action, so we send them to the _error action on the same controller
			$this->auth_error = TRUE;
			$this->action = '_auth';
			$this->controller_object->action = $this->action;
			$this->view = $this->controller . "/" . $this->action;
		}

		
		$this->build_params();

		// If a _pre function is defined, call it before the action
		if(method_exists($this->controller_object, '_pre') && !$this->auth_error)
		{
			$this->controller_object->_pre();
		}

		// Validate that this is an allowed action
		if($this->action != preg_replace("/[^a-zA-Z0-9\s]/", "", $this->action) && $this->action != '_error' && $this->action != '_auth')
		{
			$this->error("Invalid Action '" . $this->action . "' in controller '" . $this->controller . "'");
		}
		$action = $this->action;
		$this->controller_object->$action();


		// If a _post function is defined, call it before the action
		if(method_exists($this->controller_object, '_post') && !$this->auth_error)
		{
			$this->controller_object->_post();
		}
		
		$this->import_controller_values();
		$this->destroy_controller();
		$this->print_layout();


	}

	public function add_to_include_path($path)
	{
		$this->custom_include_paths[] = $path;
		$this->set_include_path();
	}

	public function set_framework_path($path)
	{
		$this->framework_path = $path;
		$this->set_include_path();
	}

	public function set_view_path($path)
	{
		$this->views_path = $path;
	}

	public function set_models_path($path)
	{
		$this->models_path = $path;
		$this->set_include_path();
	}

	public function set_utilities_path($path)
	{
		$this->utilities_path = $path;
		$this->set_include_path();
	}

	public function set_controllers_path($path)
	{
		$this->controllers_path = $path;
	}

	public function set_layouts_path($path)
	{
		$this->layouts_path = $path;
	}

	public function set_modules_path($path)
	{
		$this->modules_path = $path;
	}

	// ---------------------------------------
	// Private Functions
	// ---------------------------------------

	private function parse_url()
	{
		$split_at_question = explode("\?", trim($_SERVER['REQUEST_URI']));
		$split_at_slash = explode("/", trim($split_at_question[0]));

		if(!isset($split_at_slash[1]) || $split_at_slash[1] == "")
		{
			$this->controller = "index";
			$this->action = "index";
		}
		elseif(!isset($split_at_slash[2]) || $split_at_slash[2] == "")
		{
			$this->controller = $split_at_slash[1];
			$this->action = "index";
		}
		else
		{
			$this->controller = $split_at_slash[1];
			$this->action = $split_at_slash[2];
		}
	
		if($this->controller != preg_replace("/[^a-zA-Z0-9\s]/", "", $this->controller))
		{
			$this->error("Invalid Controller '" . $this->controller . "'");
		}
	}


	private function build_params()
	{
		$split_at_question = explode("\?", trim($_SERVER['REQUEST_URI']));
		$split_at_slash = explode("/", trim($split_at_question[0]));

		for($i = 3; $i <= count($split_at_slash)-1; $i += 2)
		{
			if(isset($split_at_slash[$i]))
			{
				$param = $split_at_slash[$i];

				$value = "";
				if(isset($split_at_slash[$i+1]))
				{
					$value = $split_at_slash[$i+1];
				}
	
				if(!isset($_GET[$param]))
				{
					$_GET[$param] = urldecode($value);
				}
				elseif(is_array($_GET[$param]))
				{
					$_GET[$param][] = urldecode($value);
				}
				else
				{
					$_GET[$param] = array($_GET[$param], urldecode($value));
				}
			}
		}
	}

	private function build_rewrite_params()
	{
		$split_at_question = explode("\?", trim($_SERVER['REQUEST_URI']));
		$_GET['_params'] = array_map('urldecode', explode("/", trim($split_at_question[0])));
		array_shift($_GET['_params']);
		array_shift($_GET['_params']);
	}

	private function set_include_path()
	{
		$application_autoinclude_folders = array(
			$this->framework_path,
			$this->models_path,
			$this->utilities_path
		);


		set_include_path(
					implode(
						PATH_SEPARATOR, 
						array_merge (
							$this->starting_include_path, 
							$application_autoinclude_folders,
							$this->custom_include_paths
							)
						)
		
				);
	}

	private function set_controller_object()
	{
		$controller_filename = $this->controller . ".php";
		$controller_class_name = $this->controller . "_controller";

		if(!file_exists($this->controllers_path . $controller_filename))
		{
			return FALSE;
		}
		require_once 'base_controller.php'; // have to manually include this because the underscore breaks the autoload
		require_once $this->controllers_path . $controller_filename;
		$this->controller_object = new $controller_class_name;
		$this->controller_object->controller = $this->controller;
		$this->controller_object->action = $this->action;

		return TRUE;
	}

	/*
	* This function takes the values that were set in the controller and makes
	* them available in the framework. This makes the values accessible in the
	* layouts, views, and modules
	*/
	private function import_controller_values()
	{
		$reserved_params = array('application_root', 'controller', 'action');

		foreach($this->controller_object as $var => $val)
		{
			if(!in_array($var, $reserved_params))
			{
				$this->$var = $val;
			}
			unset($this->controller_object->$var); // Reduces memory as values are copied into framework
		}
	}

	private function destroy_controller()
	{
		unset($this->controller_object);
	}

	/*
	* This includes the layout file unless the layout is disabled
	*/
	private function print_layout()
	{
		if($this->layout_disabled == TRUE)
		{
			$this->print_view();
		}
		else
		{
			$layout_filename = $this->layouts_path . $this->layout . ".phtml";
			if(!file_exists($layout_filename))
			{
				$this->error('Missing layout ' . $this->layout . '.phtml');
			}
			require_once $layout_filename;
		}
	}

	private function print_view()
	{
		$view_filename = $this->views_path . $this->view . '.phtml';
		if(!file_exists($view_filename))
		{
			$this->error('Missing view ' . $this->view . '.phtml');
		}
		require_once $view_filename;
	}

	private function print_module($module)
	{
		$module_filename = $this->modules_path . $module . '.phtml';
		if(!file_exists($module_filename))
		{
			$this->error('Missing module ' . $module . '.phtml');
		}
		require_once $module_filename;
	}

	private function include_js_files()
	{
		if(isset($this->included_js_files) && is_array($this->included_js_files))
		{
			foreach($this->included_js_files as $file)
			{
				?>
				<script type="text/javascript" src="<?=$file ?>"></script>
				<?
			}
		}
	}

	private function include_css_files()
	{
		if(isset($this->included_css_files) && is_array($this->included_css_files))
		{
			foreach($this->included_css_files as $file)
			{
				?>
				<link href="<?=$file ?>" media="screen" rel="stylesheet" type="text/css" />
				<?
			}
		}
	}

	private function error($error)
	{
		$error = "Crossbar: " . $error;
		error_log($error);
		trigger_error($error, E_USER_ERROR);
		exit;
	}

	
}



?>
