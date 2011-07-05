<?

class globals
{
    static function __callStatic($method, $arguments) 
    {
        $server_var = "_" . strtoupper($method);
        $var = $arguments[0];

        if(empty($GLOBALS[$server_var][$var]))
        {
            if(!empty($arguments[1]))
            {
                return $arguments[1];
            }
            return NULL;
        }
        return $GLOBALS[$server_var][$var];
    }
}

?>
