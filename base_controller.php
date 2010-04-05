<?

class base_controller
{
	public function __construct()
	{
		$this->included_css_files 	= array();
		$this->included_js_files 	= array();
		$this->layout_disabled 		= FALSE;
		$this->layout 			= 'default';
		$this->layout_header 		= 'header';
		$this->layout_footer 		= 'footer';
	}

	protected function include_css_file($file)
	{
		$this->included_css_files[] = $file;
	}

	protected function include_js_file($file)
	{
		$this->included_js_files[] = $file;
	}

	protected function set_view($view)
	{
		if(!strpos($view, "/"))
		{
			$this->view = $this->controller . '/' . $view;
		}
		else
		{
			$this->view = $view;
		}
	}
	
	protected function set_layout($layout)
	{
		$this->layout = $layout;
	}

	protected function set_layout_header($header)
	{
		$this->layout_header = $header;
	}

	protected function set_layout_footer($footer)
	{
		$this->layout_footer = $footer;
	}

	protected function disable_layout()
	{
		$this->layout_disabled = TRUE;
	}

	protected function set_action($action)
	{
		unset($_GET['_params']);
		$this->set_view($action);
		$this->$action();
	}

}

?>
