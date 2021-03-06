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

	public static function alpha($value)
	{
		if(preg_match("/[^a-zA-Z ]/", $value))
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

    public static function numeric($value)
    {
        if(!is_numeric($value))
        {
            return FALSE;
        }
        return TRUE;
    }

    public static function zip($value)
    {
        if(!validate::int($value))
        {
            return FALSE;
        }
        if(strlen($value) != 5)
        {
            return FALSE;
        }
        return TRUE;
    }

    public static function int($value)
    {
        if(!is_numeric($value))
        {
            return FALSE;
        }
        if(floor($value) != $value)
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

	public static function hexcolor($value)
	{
        if(preg_match('/^#[a-f0-9]{6}$/i', $value)) 
        {
            return TRUE;
        }
		return FALSE;
	}


    public static function json($value)
    {
        if(is_array(json_decode($value, TRUE)))
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

    public static function starts_with($value, $substring)
    {
        $check = strpos($value, $substring);

        if($check === FALSE || $check > 0)
        {
            return FALSE;
        }
        return TRUE;
    }

    public static function in_array($array, $keys)
    {
        $missing_keys = array();
        foreach($keys as $key)
        {
            // 1st part, isset checks if it array_key_exists and if the value is NULL
            // 2nd part, the value is ''
            if(isset($array[$key]) === FALSE || trim($array[$key]) === '')
            {
                $missing_keys[] = $key;
            }
        }

        if(count($missing_keys) > 0)
        {
            return array('success' => FALSE, 'missing_keys' => $missing_keys);
        }
        else
        {
            return array('success' => TRUE);
        }
    }

}
?>
