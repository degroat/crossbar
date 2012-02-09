<?
class config
{
    private static $values = array();

    public static function load($filename)
    {
        $conf = json_decode(file_get_contents($filename), TRUE);
        if(is_array($conf))
        {
            self::$values = array_merge($conf, self::$values);
        }
    }

    public static function get()
    {
        $args = func_get_args();

        $value = self::$values;
        foreach($args as $arg)
        {
            if(isset($value[$arg]))
            {
                $value = $value[$arg];
            }
            else
            {
                return FALSE;
            }
        }

        return $value;
    }

}

?>
