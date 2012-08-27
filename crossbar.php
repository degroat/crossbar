<?php
/**
* This is the main class for the crossbar php framework
* @author Chris DeGroat 
**/

class crossbar
{
    public function __construct($script_mode = FALSE)
    {
        $this->start_time               = microtime(true);
        $this->apis                     = array();
        $this->api_keys                 = array();

        if(!$script_mode)
        {
            $this->parse_url();
            $this->view                 = $this->controller . "/" . $this->action;
        }

        $application_root               = str_replace('htdocs', '', $_SERVER['DOCUMENT_ROOT']);
        $this->framework_path           = $application_root . 'framework/';
        $this->views_path               = $application_root . 'views/';
        $this->models_path              = $application_root . 'models/';
        $this->utilities_path           = $application_root . 'utilities/';
        $this->controllers_path         = $application_root . 'controllers/';
        $this->layouts_path             = $application_root . 'layouts/';
        $this->modules_path             = $application_root . 'modules/';
        $this->starting_include_path    = explode(PATH_SEPARATOR, get_include_path());
        $this->custom_include_paths     = array();
        $this->missing_controller       = '';
        $this->is_rewrite               = FALSE;
        $this->debug_mode               = FALSE;
        $this->errors = array();

        $this->set_include_path();
    }

    // ---------------------------------------
    // Public Functions
    // ---------------------------------------

    public function go()
    {
        $this->auth_error = FALSE;

        // If the controller doesn't exist, send them to index/_rewrite (if _rewrite does not exist, they'll be routed to _error below)
        $missing_controller = 0;
        if(!$this->set_controller_object())
        {    
            $this->missing_controller = $this->controller;
            $this->controller = "index";
            $this->action = "_rewrite";
            $this->set_controller_object();
            $this->view = $this->controller . "/" . $this->action;
            $missing_controller = 1;
        }    

        // If the action we're not looking for doesn't exist and a re-write action does exist, use the rewrite one
        // OR... if we  have encountered a missing controller, let's see if the index/_rewrite exists
        //if(!method_exists($this->controller_object, $this->action) && method_exists($this->controller_object, '_rewrite') || $missing_controller)
        if(($this->action == "_rewrite" || !method_exists($this->controller_object, $this->action)) && method_exists($this->controller_object, '_rewrite'))
        {
            $this->is_rewrite = TRUE;
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
        $pre_response = NULL;
        if(method_exists($this->controller_object, '_pre') && !$this->auth_error)
        {
            $pre_response = $this->controller_object->_pre();
        }

        // Validate that this is an allowed action
        if($this->action != preg_replace("/[^a-zA-Z0-9_\s]/", "", $this->action) && $this->action != '_error' && $this->action != '_auth')
        {
            $this->error("Invalid Action '" . $this->action . "' in controller '" . $this->controller . "'");
        }
        $action = $this->action;

        if($pre_response !== FALSE) // if the _pre function returns a false, we don't execute the action
        {
            $this->controller_object->$action();
        }


        // If a _post function is defined, call it before the action
        if(method_exists($this->controller_object, '_post') && !$this->auth_error)
        {
            $this->controller_object->_post();
        }
        
        $this->import_controller_values();
        $this->destroy_controller();
        $this->print_layout();
    }

    public function api()
    {
        $this->success = 0;
        require_once 'base_api_controller.php'; 

        if(globals::POSTGET('_debug') != NULL)
        {
            mysql::enable_debug_mode();
        }

        if($this->controller == 'index' && $this->action == 'index')
        {
            $this->api_forms();
            return;
        }

        if(!$this->set_api_controller_object())
        {    
            $this->error("Error creating controller object");
        }    

        $this->build_params();

        // Validate the API KEY
        $key = globals::POSTGET('_key');
        if($key == NULL)
        {
            $this->api_error("Missing required paramter _key");
        }
        if(!array_key_exists($key, $this->api_keys))
        {
            $this->api_error("Invalid API Key");
            $key_details = $this->api_keys[$key];
        }

        // Verify this API was configured
        if(!isset($this->apis[$this->controller][$this->action]))
        {
            $this->api_error("Invalid API URL");
        }


        // If a _pre function is defined, call it before the action
        $pre_response = NULL;
        if(method_exists($this->controller_object, '_pre'))
        {
            $pre_response = $this->controller_object->_pre();
        }

        // Validate the params
        $api = $this->apis[$this->controller][$this->action];
        $errors = array();
        foreach($api['params'] as $param => $config)
        {
            $label = ucwords($param);
            if(isset($config['label']))
            {
                $label = $config['label'];
            }

            $value = globals::POSTGET($param);
            if($config['required'] === TRUE && ($value == NULL || $value == ""))
            {
                $errors[$param] = "Please enter a {$label}";
            }

            if(!empty($value))
            {
                $type = $config['type'];
                if($type != 'text' && $type != 'password')
                {
                    if(!validate::$type(trim($value)))
                    {
                        $errors[$param] = "Invalid value entered for {$label}";
                    }
                }

                if(isset($config['max_length']))
                {
                    if(strlen($value) > $config['max_length'])   
                    {
                        $errors[$param] = "{$label} exceeds maximum length of {$config['max_length']}";
                    }
                }

                if(isset($config['min_length']))
                {
                    if(strlen($value) < $config['min_length'])   
                    {
                        $errors[$param] = "{$label} has a minimum length of {$config['min_length']}";
                    }
                }
            }
        }

        if(count($errors) > 1)
        {
            $this->api_error("Sorry! We've encountered some errors...", $errors);
        }
        elseif(count($errors) > 0)
        {
            $this->api_error("Sorry! We've encountered an error...", $errors);
        }


        if($pre_response !== FALSE) // if the _pre function returns a false, we don't execute the action
        {
            $action = $this->action;
            $this->controller_object->$action();
        }



        // If a _post function is defined, call it before the action
        if(method_exists($this->controller_object, '_post'))
        {
            $this->controller_object->_post();
        }
        
        $this->import_controller_values();
        $this->destroy_controller();
        $this->print_api_response();
    }

    public function api_forms()
    {
        ksort($this->apis);
        require "api_forms.php";
    }

    public function register_standard_apis($models)
    {
        $methods = array('create', 'update_by_id', 'get_by_field', 'get_by_id', 'delete_by_id', 'get_all');
        foreach($models as $model)
        {
            foreach($methods as $method)
            {
                $this->register_api($model, $method, $model::fields($method));
            }
        }
    }

    public function register_api($controller, $action, $params)
    {   
        if(isset($this->apis[$controller][$action]))
        {
            $this->error("Duplicate API configuration for {$controller}/{$action}");
        }
        $this->apis[$controller][$action]['params'] = $params;
    }   

    public function register_api_keys($keys)
    {
        $this->api_keys = $keys;
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

    public function enable_debug_mode()
    {
        $this->debug_mode = TRUE;
    }

    // ---------------------------------------
    // Private Functions
    // ---------------------------------------

    private function parse_url()
    {
        $split_at_question = explode('?', trim($_SERVER['REQUEST_URI']));

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
    
        if($this->controller != preg_replace("/[^a-zA-Z0-9\s-]/", "", $this->controller))
        {
            $this->error("Invalid Controller '" . $this->controller . "'");
        }
    }


    private function build_params()
    {
        // If this is a re-write, we shouldn't do this
        if($this->is_rewrite)
        {
            return;
        }

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

        if(count($_POST) > 0)
        {
            foreach($_POST as $var => $val)
            {
                $var_new = str_replace('amp;', '', $var);
                if($var_new != $var)
                {
                    $_POST[$var_new] = $val;
                    unset($_POST[$var]);
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
        
        if($this->missing_controller != "")
        {
            array_unshift($_GET['_params'], $this->missing_controller);
        }
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
                            $application_autoinclude_folders,
                            $this->custom_include_paths,
                            $this->starting_include_path
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

    private function set_api_controller_object()
    {
        $controller_filename = $this->controller . ".php";
        $controller_class_name = $this->controller . "_controller";

        if(file_exists($this->controllers_path . $controller_filename))
        {
            require_once $this->controllers_path . $controller_filename;
            $this->controller_object = new $controller_class_name;
        }
        else
        {
            $this->controller_object = new base_api_controller();
        }

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

    private function print_api_response()
    {
        $format = globals::POSTGET('_format', 'json');

        $response = array();
        if(!isset($this->success))
        {
            $this->error('API Mode: Missing required value $this->success');
        }
        elseif($this->success !== 1 && $this->success !== 0)
        {
            $this->error('API Mode: $this->success must be 1 or 0 (integers, not booleans)');
        }
        else
        {
            $response['success'] = $this->success;
        }

        if(isset($this->data))
        {
            if(is_array($this->data))
            {
                array_walk_recursive($this->data, array($this, 'encode_array_items'));
            }
            else
            {
                $this->data = utf8_encode($this->data);
            }
            $response['data'] = $this->data;
        }

        if(isset($this->error))
        {
            $response['error'] = $this->error;
            if(isset($this->param_errors))
            {
                $response['param_errors'] = $this->param_errors;
            }
        }
        elseif($response['success'] == 0)
        {
            $this->error('API Mode: Missing required value $this->error (when success=0)');
        }

        if(globals::POSTGET('_debug') != NULL)
        {
            $response['debug']['execution_time']    = number_format((microtime(true) - $this->start_time) , 4) . ' seconds';
            $response['debug']['memory_usage']      = round(memory_get_usage()/1048576,3)." mb"; 
            $response['debug']['queries']           = mysql::get_queries();
            $response['debug']['request']['url']    = $_SERVER['REQUEST_URI'];
            $response['debug']['request']['_GET']   = globals::GET();
            $response['debug']['request']['_POST']  = globals::POST();
        }

        header('Content-type: text/javascript');
        print json_encode($response);
    }

    private function encode_array_items(&$item, $key)
    {
            $item = utf8_encode($item);
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
            if($this->is_mobile())
            {
                $temp_layout_filename = $this->layouts_path . $this->layout . ".mobile.phtml";
                if(file_exists($temp_layout_filename))
                {
                    $layout_filename = $temp_layout_filename;
                }
            }


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
        if($this->is_mobile())
        {
            $temp_view_filename = $this->views_path . $this->view . '.mobile.phtml';
            if(file_exists($temp_view_filename))
            {
                $view_filename = $temp_view_filename;
            }
        }

        if(!file_exists($view_filename))
        {
            $this->error('Missing view ' . $this->view . '.phtml');
        }
        require_once $view_filename;
    }

    private function print_module($module, $values = array())
    {
        if(!is_array($values))
        {
            $this->error('Second paramater to print_module must be an array');
        }
        extract($values);

        $module_filename = $this->modules_path . $module . '.phtml';
        if(!file_exists($module_filename))
        {
            $this->error('Missing module ' . $module . '.phtml');
        }
        require $module_filename;
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

    private function is_mobile()
    {
        if(isset($_GET['_mobile']))
        {
            return TRUE;
        }
        elseif(!empty($_SERVER['HTTP_USER_AGENT']))
        {
            $useragent = $_SERVER['HTTP_USER_AGENT'];
            if(preg_match('/android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|e\-|e\/|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(di|rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|xda(\-|2|g)|yas\-|your|zeto|zte\-/i',substr($useragent,0,4)))
            {
                return TRUE;
            }
        }
        return FALSE;
    }

    private function title($default_title)
    {
        if($default_title != "")
        {
            array_unshift($this->title_parts, $default_title);
        }
        return implode($this->title_separator, $this->title_parts);
    }

    private function error($error)
    {
        $error = "Crossbar: " . $error;
        error_log($error);
        trigger_error($error, E_USER_ERROR);
        exit;
    }

    private function api_error($error, $param_errors = array())
    {
        $this->error = $error;
        $this->param_errors = $param_errors;
        $this->print_api_response();
        exit;
    }

    
}

?>
