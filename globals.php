<?

class globals
{
    static function __callStatic($method, $arguments) 
    {
        $server_var = "_" . strtoupper($method);
        $var = $arguments[0];


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


        if($overwrite_empty_string)
        {
            if(empty($GLOBALS[$server_var][$var]))
            {
                return $default;
            }
        }
        else
        {
            if(!isset($GLOBALS[$server_var][$var]))
            {
                return $default;
            }
        }
        return $GLOBALS[$server_var][$var];
    }
}

?>
