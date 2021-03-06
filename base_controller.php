<?
class base_controller
{
	public function __construct()
	{
		$this->included_css_files 	= array();
		$this->included_js_files 	= array();
		$this->layout_disabled 		= FALSE;
		$this->layout 			    = 'default';
		$this->layout_header 		= 'header';
		$this->layout_footer 		= 'footer';
        $this->title_separator      = ' | ';
        $this->title_parts_post     = array();
        $this->title_parts_pre      = array();
	}

    protected function title_append($val)
    {
        if($val != "")
        {
            $this->title_parts_post[] = $val;
        }
    }

    protected function title_prepend($val)
    {
        if($val != "")
        {
            array_unshift($this->title_parts_pre, $val);
        }
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

	public function _error()
	{
		$error = "$this->controller" . "_controller is missing _error function to catch crossbar errors";
		error_log($error);
		trigger_error($error, E_USER_ERROR);
	}

	public function _auth()
	{
		$error = "$this->controller" . "_controller is missing _auth function to catch crossbar authentication errors";
		error_log($error);
		trigger_error($error, E_USER_ERROR);
	}

}

?>
