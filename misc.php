<?

class misc 
{
    public static function slug($text)
    {
        $slug = trim($text);
        $slug = preg_replace('/[^A-Za-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', "-", $slug);   // collapse multiple dashes to one
        $slug = preg_replace('/^-/', '', $slug);    // strip off preceding dash
        $slug = preg_replace('/-$/', '', $slug);    // strip off trailing dash
        return $slug;
    }

    public static function user_ip()
    {
        return $_SERVER['REMOTE_ADDR'];
    }

    public static function reindex($array, $field, $as_object = FALSE)
    {
        $new_array = array();
        $array = (array) $array;
        foreach($array as $a)
        {
            if(!$as_object)
            {
                $a = (array) $a;
                $new_array[$a[$field]] = $a;
            }
            else
            {
                $new_array[$a->$field] = $a;
            }
        }
        return $new_array;
    }

    public static function get_between($string, $before, $after, $trim_whitespace = TRUE)
    {
        $starts_at = strpos($string, $before) + strlen($before);
        $ends_at = strpos($string, $after, $starts_at);
        $result = substr($string, $starts_at, $ends_at - $starts_at);
        if($trim_whitespace)
        {
            return trim($result);
        }
        return $result;
    }

    public static function alphanumeric($string)
    {
        return preg_replace("/[^A-Za-z0-9 ]/", '', $string);
    }

    public static function numeric($string)
    {
        return preg_replace("/(^\s+)|(\s+$)/us", "", preg_replace("/[^0-9\.]/", '', $string));
    }
}

?>
