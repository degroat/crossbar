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

	public static function username($value)
	{
		// Allowed chars -- A-Z, a-z, 0-9, _
		$min_length = 3; 
		$max_length = 15; 
		
		/*	 
		if(strlen($value) < $min_length || strlen($value) > $max_length)
		{
			return FALSE;
		}
		*/
		if(!preg_match("/[^A-z0-9_\-]/", $value))
		{
			return FALSE;
		}
		return TRUE;
	}	

	public static function url($value)
	{

	}
}
?>
