<?
class base_api_controller
{
    public $controller;
    public $action;

	public function __construct()
	{
	}

    public function __call($action, $no_args_will_be_here)
    {
        // If we get to this point, the controller either doesn't exist or this method didn't exist within the controller
        // So, now we are just calling model w/ the same name as the controller...
        // and the method as the name of the action
        $model = $this->controller;
        $params = globals::POSTGET();
        foreach($params as $var => $val)
        {
            if($var[0] == "_")
            {
                unset($params[$var]);
            }
        }
        $this->data = $model::$action($params);

        if($this->data === FALSE)
        {
            $this->error = $model::get_errors();
        }
        else
        {
            $this->success = 1;
        }
    }

	public function _error()
	{
	}

	public function _auth()
	{
	}
}

?>
