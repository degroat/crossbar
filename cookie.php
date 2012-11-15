<?

class cookie
{
	public static function set($name, $value, $expires = 0, $salt = NULL)
	{
        if($salt != NULL)
        {
		    $value = crypto::encrypt($salt, serialize($value));
        }
        else
        {
            $value = serialize($value);
        }

        if($time > 0)
        {
            $time += time();
        }

		return setcookie($name, $value, $time, '/');
	}

    public static function get($name, $salt = NULL)
    {
		if($salt != NULL)
        {
		    return @unserialize(crypto::decrypt($salt, globals::COOKIE($name)));
        }
        else
        {
		    return @unserialize(globals::COOKIE($name));
        }
    }

    public static function delete($name)
    {
        return setcookie($name, '', time() - 3600, '/');
    }

}

?>
