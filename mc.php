<?

class mc
{
	private static $mc = NULL;
	private static $is_valid = FALSE;
	private static $is_connected = FALSE;
	private static $messages = array();

	public static function connect($server, $port = 11211)
	{
		try
		{
			self::$mc = new Memcache;

			if(@self::$mc->connect($server, $port))
			{
				self::$is_connected = TRUE;
				return TRUE;
			}
			else
			{
				self::$is_connected = FALSE;
				self::$messages[] = "could not connect to server:$server on port:$port";
				return FALSE;
			}
		}
		catch(Exception $e)
		{
			self::$messages[] = "the memcache module is not properly installed on this machine";
			return FALSE;
		}
	}

	public static function set($var, $val, $seconds = 0)
	{
		if(self::$is_connected)
		{
			try
			{
				self::$is_valid = TRUE;
				return self::$mc->set($var, array('value'=>$val), FALSE, $seconds);
			}
			catch(Exception $e)
			{
				self::$is_valid = FALSE;
				self::$messages[] = "fatal error while setting: ".var_export($var,TRUE);
				return FALSE;
			}
		}
		else
		{
			self::$messages[] = "not connected while trying to set: ".var_export($var,TRUE);
			self::$is_valid = FALSE;
			return FALSE;
		}
	}

	public static function get($var)
	{
		if(self::$is_connected)
		{
			try
			{
				$result = self::$mc->get($var);
				if(is_array($result))
				{
					self::$messages[] = "value found for: ".var_export($var,TRUE);
					self::$is_valid = TRUE;
					return $result['value'];
				}
				else
				{
					self::$messages[] = "no value found for: ".var_export($var,TRUE);
					self::$is_valid = FALSE;
					return FALSE;
				}
			}
			catch(Exception $e)
			{
				self::$messages[] = "fatal error getting: ".var_export($var,TRUE);
				self::$is_valid = FALSE;
				return FALSE;
			}
		}
		else
		{
			self::$messages[] = "not connected while trying to set: ".var_export($var,TRUE);
			self::$is_valid = FALSE;
			return FALSE;
		}
	}

	public static function delete($var)
	{
		if(self::$is_connected)
		{
			try
			{
				self::$is_valid = TRUE;
				return self::$mc->delete($var);
			}
			catch(Exception $e)
			{
				self::$messages[] = "fatal error while deleting: ".var_export($var,TRUE);
				self::$is_valid = FALSE;
				return FALSE;
			}
		}
		else
		{
			self::$messages[] = "not connected while trying to delete: ".var_export($var,TRUE);
			self::$is_valid = FALSE;
			return FALSE;
		}
	}

	public static function is_valid()
	{
		return self::$is_valid;
	}

	public static function is_connected()
	{
		return self::$is_connected;
	}

	public static function get_messages()
	{
		return self::$messages;
	}
}

?>