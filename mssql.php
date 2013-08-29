<?

/*
THIS CLASS IS NOT COMPLETE
*/

class mssql
{
	static private $config = array();
	static private $connections = array();
	static private $selected_database = array();
	static private $errors = array();
    static private $debug_mode = FALSE;
    static private $queries = array();

	public static function database_config($alias, $host, $database, $username, $password)
	{
		self::$config[$alias] = array(
						'host'		=> $host,
						'database'	=> $database,
						'username'	=> $username,
						'password'	=> $password
					);

	}

    public static function insert($alias, $table, $values, $on_dupkey_update = array())
    {
        $sql = "INSERT INTO {$table} ( " . implode(', ', array_keys($values)) . " ) VALUES ( " . implode(', ', array_map(array('mssql','quote'),$values)) . " ) ";
        if(!empty($on_dupkey_update) && is_array($on_dupkey_update))
        {
            $sql .= "ON DUPLICATE KEY UPDATE ";
            $sql_parts = array();
            foreach($on_dupkey_update as $key => $val)
            {
                $sql_parts[] = " {$key} = " . mssql::quote($val);
            }
            $sql .= implode(", ", $sql_parts);
        }

        if(mssql::query($alias, $sql) !== FALSE)
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
			$cache_key = "mssql_cache_" . $alias . "_" . md5($sql);

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
    
		// Execute our query and get the result
		$result = mssql_query($sql, $connection);

		if(!$result)
		{
			self::set_error(mssql_error());
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
				while($row = mssql_fetch_assoc($result))
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

		return mssql_insert_id($connection);

	}

	public static function rows_affected($alias)
	{
		// Get our database connection.  This creates it necessary.  If no connection is made, it errors out
		if(!($connection = self::get_connection($alias)))
		{
			return FALSE;
		}

		return mssql_affected_rows($connection);

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
		return mssql_real_escape_string($string);
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
		if(self::$connections[$alias] = mssql_connect(self::$config[$alias]['host'], self::$config[$alias]['username'], self::$config[$alias]['password']))
		{
			return TRUE;
		}
		else
		{
			self::set_error(mssql_error());
			return FALSE;
		}
	}

	private static function select_db($alias, $database)
	{
		if(!isset(self::$selected_database[$alias]) || self::$selected_database[$alias] != $database)
		{
			mssql_select_db($database, self::$connections[$alias]);
			$selected_database[$alias] = $database;
		}
		
	}

	private static function set_error($error)
	{
		self::$errors[] = $error;
	}


}

function mssql_real_escape_string($data) {
        if ( !isset($data) or empty($data) ) return '';
        if ( is_numeric($data) ) return $data;

        $non_displayables = array(
                '/%0[0-8bcef]/',            // url encoded 00-08, 11, 12, 14, 15
                '/%1[0-9a-f]/',             // url encoded 16-31
                '/[\x00-\x08]/',            // 00-08
                '/\x0b/',                   // 11
                '/\x0c/',                   // 12
                '/[\x0e-\x1f]/'             // 14-31
        );
        foreach ( $non_displayables as $regex )
                $data = preg_replace( $regex, '', $data );
        $data = str_replace("'", "''", $data );
        return $data;
}

?>
