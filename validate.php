<?

class validate
{
	public static function email($value)
	{
		if (filter_var($value, FILTER_VALIDATE_EMAIL))
		{
			return TRUE;
		}
		return FALSE;
	}


	public static function md5($value)
	{
		if(strlen($value) != 32)
		{
			return FALSE;
		}
		elseif(!self::alphanumeric($value))
		{
			return FALSE;
		}
		return TRUE;

	}

	public static function alphanumeric($value)
	{
		if(preg_match("/[^a-zA-Z0-9]/", $value))
		{
			return FALSE;
		}
		return TRUE;
	}

	public static function url($value)
	{
		if (filter_var($value, FILTER_VALIDATE_URL))
		{
			return TRUE;
		}
		return FALSE;
	}

    public static function length($value, $min_chars, $max_chars)
    {
        if(strlen($value) >= $min_chars && strlen($value) <= $max_chars)
        {
            return TRUE;
        }
        return FALSE;
    }

    public static function minimum($value, $min)
    {
        if(is_numeric($value) && $value >= $min)
        {
            return TRUE;
        }
        return FALSE;
    }

    public static function date($date)
    {
        if(strtotime($date) === FALSE)
        {
            return FALSE;
        }
        return TRUE;
    }

    public static function after($date1, $date2)
    {
        if(strtotime($date1) > strtotime($date2))
        {
            return TRUE;;
        }
        return FALSE;
    }
}
?>
