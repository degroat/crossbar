<?

class globals
{
    static function __callStatic($method, $arguments) 
    {
        $server_var = "_" . strtoupper($method);

        $default = NULL;
        if(isset($arguments[1]))
        {
            $default = $arguments[1];
        }

        $overwrite_empty_string = FALSE;
        if(isset($arguments[2]) && $arguments[2] == TRUE)
        {
            $overwrite_empty_string = TRUE;
        }

        if($server_var == "_POSTGET")
        {
            $data = array_merge($_GET, $_POST);
        }
        elseif($server_var == "_GETPOST")
        {
            $data = array_merge($_POST, $_GET);
        }
        else
        {
            $data = $GLOBALS[$server_var];
        }

        if(!isset($arguments[0]))
        {
            return $data;
        }

        $var = $arguments[0];
        if($overwrite_empty_string)
        {
            if(empty($data[$var]))
            {
                return $default;
            }
        }
        else
        {
            if(!isset($data[$var]))
            {
                return $default;
            }
        }
        return $data[$var];
    }
}

?>
