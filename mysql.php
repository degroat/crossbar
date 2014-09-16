<?

class mysql
{
	static private $config = array();
	static private $connections = array();
	static private $selected_database = array();
	static private $errors = array();
    static private $debug_mode = FALSE;
    static private $queries = array();
    static private $last_query = NULL;

	public static function database_config($alias, $host, $database, $username, $password)
	{
		self::$config[$alias] = array(
						'host'		=> $host,
						'database'	=> $database,
						'username'	=> $username,
						'password'	=> $password
					);

	}

    public static function insert($alias, $table, $values, $on_dupkey_update = array(), $auto_inc_column = FALSE)
    {
        $sql = "INSERT INTO {$table} ( " . implode(', ', array_keys($values)) . " ) VALUES ( " . implode(', ', array_map(array('mysql','quote'),$values)) . " ) ";
        if(!empty($on_dupkey_update) && is_array($on_dupkey_update))
        {
            $sql .= "ON DUPLICATE KEY UPDATE ";
            $sql_parts = array();
            foreach($on_dupkey_update as $key => $val)
            {
                $sql_parts[] = " {$key} = " . mysql::quote($val);
            }
            if($auto_inc_column)
            {
                $sql_parts[] = "{$auto_inc_column} = LAST_INSERT_ID( {$auto_inc_column})";
            }
            $sql .= implode(", ", $sql_parts);
        }

        if(mysql::query($alias, $sql) !== FALSE)
        {
            return self::last_insert_id($alias);
        }

        return FALSE;
    }

	public static function query($alias, $sql, $cache = FALSE, $update_cache = FALSE)
	{
		// Verify that the alias has been set up properly
		if(!self::validate_alias($alias))
		{
			return FALSE;
		}

        // clearing out previous errors before every query
        self::$errors = array();
	
		if($cache !== FALSE && !$update_cache)
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

        if(self::$debug_mode === TRUE)
        {   
            self::$queries[] = $sql;
        }       
        self::$last_query = $sql;
    
		// Execute our query and get the result
		$result = mysqli_query($connection, $sql);

		if(!$result)
		{
			self::set_error(mysqli_error($connection));
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
				while($row = mysqli_fetch_assoc($result))
				{
					$array_result[] = $row;
				}

				if($cache !== FALSE)
				{
					mc::set($cache_key, $array_result, $cache);
				}

				return $array_result;
				break;
		}
	}

	public static function query_row($alias, $sql,  $cache = FALSE, $update_cache = FALSE)
	{
		$result = self::query($alias, $sql, $cache, $update_cache);
        if($result !== FALSE)
		{
            if(count($result) == 0)
            {
                return array();
            }
            else
            {
			    return $result[0];
            }
		}
		return FALSE;
	}

	public static function query_one($alias, $sql, $cache = FALSE, $update_cache = FALSE)
	{
		if($result = self::query($alias, $sql, $cache, $update_cache))
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

		return mysqli_insert_id($connection);

	}

	public static function rows_affected($alias)
	{
		// Get our database connection.  This creates it necessary.  If no connection is made, it errors out
		if(!($connection = self::get_connection($alias)))
		{
			return FALSE;
		}

		return mysqli_affected_rows($connection);

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
		return mysqli_real_escape_string(self::get_connection(key(self::$connections)), $string);
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
		elseif(is_null($string))
		{
			return "NULL";
		}
		else
		{
			return "'" . self::escape($string) . "'";
		}
	}

    public static function enable_debug_mode()
    {
        self::$debug_mode = TRUE;
    }

    public static function get_queries()
    {
        return self::$queries;
    }       

    public static function get_last_query()
    {
        return self::$last_query;
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
		if(self::$connections[$alias] = mysqli_connect(self::$config[$alias]['host'], self::$config[$alias]['username'], self::$config[$alias]['password']))
		{
			return TRUE;
		}
		else
		{
			self::set_error(mysqli_error());
			return FALSE;
		}
	}

	private static function select_db($alias, $database)
	{
		if(!isset(self::$selected_database[$alias]) || self::$selected_database[$alias] != $database)
		{
			mysqli_select_db(self::$connections[$alias], $database);
			$selected_database[$alias] = $database;
		}
		
	}

	private static function set_error($error)
	{
		self::$errors[] = $error;
	}


}

?>
