<?

class mysql
{
	static private $config = array();
	static private $connections = array();
	static private $selected_database = array();
	static private $errors = array();

	public static function database_config($alias, $host, $database, $username, $password)
	{
		self::$config[$alias] = array(
						'host'		=> $host,
						'database'	=> $database,
						'username'	=> $username,
						'password'	=> $password
					);

	}

	public static function query($alias, $sql, $cache = FALSE, $update_cache = FALSE)
	{
		// Verify that the alias has been set up properly
		if(!self::validate_alias($alias))
		{
			return FALSE;
		}

		
		if($cache && !$update_cache)
		{
			$cache_key = "mysql_cache_" . $alias . "_" . md5($sql);

			if($result = mc::get($cache_key))
			{
				return $result;
			}
		}

		
		// Get our database connection.  This creates it necessary.  If no connection is made, it errors out
		if(!($connection = self::get_connection($alias)))
		{
			return FALSE;
		}

		// Select the right database
		self::select_db($alias, self::$config[$alias]['database']);

		// Execute our query and get the result
		$result = mysql_query($sql, $connection);

		if(!$result)
		{
			self::set_error(mysql_error());
			return FALSE;
		}

		switch($result)
		{
			// Check for successful insert/update/delete
			case ($result === TRUE):
				return TRUE;
				break;

			// Build result set array and return
			default:
				$array_result = array();
				while($row = mysql_fetch_assoc($result))
				{
					$array_result[] = $row;
				}

				if($cache)
				{
					mc::set($cache_key, $array_result, $cache);
				}

				return $array_result;
				break;
		}
	}

	public static function query_row($alias, $sql)
	{
		if($result = self::query($alias, $sql))
		{
			return $result[0];
		}
		return FALSE;
	}

	public static function query_one($alias, $sql)
	{
		if($result = self::query($alias, $sql))
		{
			return $result[0][key($result[0])];
		}
		return FALSE;

	}

	public static function last_insert_id($alias)
	{
		// Get our database connection.  This creates it necessary.  If no connection is made, it errors out
		if(!($connection = self::get_connection($alias)))
		{
			return FALSE;
		}

		return mysql_insert_id($connection);

	}

	public static function is_error()
	{
		if(count(self::$errors) > 0)
		{
			return TRUE;
		}
		return FALSE;
	}

	public static function get_errors()
	{
		return self::$errors;
	}

	public static function escape($string)
	{
		if(count(self::$connections) == 0)
		{
			if(count(self::$config) == 0)
			{
				self::set_error("A connection must exist to use this function and one has not been configured");
				return FALSE;
			}
			
			$alias = key(self::$config);

			// Get our database connection.  This creates it necessary.  If no connection is made, it errors out
			if(!($connection = self::get_connection($alias)))
			{
				return FALSE;
			}
		}
		return mysql_real_escape_string($string);
	}

	public static function quote($string)
	{
		if(is_array($string))
		{
			foreach($string as $key => $val)
			{
				$string[$key] = "'" . self::escape($val) . "'";
			}
			return $string;
		}
		else
		{
			return "'" . self::escape($string) . "'";
		}
	}

	// ==========================================================

	private static function validate_alias($alias)
	{
		if(self::$config[$alias]['host'] == "")
		{
			self::set_error("Host cannot be left blank");
			return FALSE;
		}
		return TRUE;
	}

	private static function get_connection($alias)
	{
		if(!isset(self::$connections[$alias]))
		{
			if(!self::create_connection($alias))
			{
				return FALSE;
			}
		}

		return self::$connections[$alias];
	}

	private static function create_connection($alias)
	{
		if(self::$connections[$alias] = mysql_connect(self::$config[$alias]['host'], self::$config[$alias]['username'], self::$config[$alias]['password']))
		{
			return TRUE;
		}
		else
		{
			self::set_error(mysql_error());
			return FALSE;
		}
	}

	private static function select_db($alias, $database)
	{
		if(!isset(self::$selected_database[$alias]) || self::$selected_database[$alias] != $database)
		{
			mysql_select_db($database, self::$connections[$alias]);
			$selected_database[$alias] = $database;
		}
		
	}

	private static function set_error($error)
	{
		self::$errors[] = $error;
	}
}

?>
