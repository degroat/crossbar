<?

class mgo
{
	static private $config = array();
	static private $connections = array();
	static private $errors = array();

	public static function config($alias, $host, $username = NULL, $password = NULL)
	{
		self::$config[$alias] = array(
						'host'		=> $host,
						'username'	=> $username,
						'password'	=> $password
					);
    }

	public static function query($alias, $dbname, $colname, $query=array(), $limit=NULL, $sort=array())
	{
		// Verify that the alias has been set up properly
		if(!self::validate_alias($alias))
		{
			return FALSE;
		}

        // clearing out previous errors before every query
        self::$errors = array();


		// Get our database connection.  This creates it necessary.  If no connection is made, it errors out
		if(!($connection = self::get_connection($alias)))
		{
			return FALSE;
		}

        $db = $connection->$dbname;
        $col = $db->$colname;


        $result = $col->find($query)->sort($sort)->limit($limit);
        $list = array();
        while($result->hasNext())
        {
            $list[] = $result->getNext();
        }
        return $list;
    }

	public static function insert($alias, $dbname, $colname, $object, $safe=True)
	{
		// Verify that the alias has been set up properly
		if(!self::validate_alias($alias))
		{
			return FALSE;
		}

        // clearing out previous errors before every query
        self::$errors = array();


		// Get our database connection.  This creates it necessary.  If no connection is made, it errors out
		if(!($connection = self::get_connection($alias)))
		{
			return FALSE;
		}

        $db = $connection->$dbname;
        $col = $db->$colname;

        try
        {
            $result = $col->insert($object,array('safe'=>$safe));
            //print_r($result);
        }
        catch (Exception $e)
        {
            return FALSE;
        }
        return TRUE;
    }

	public static function update($alias, $dbname, $colname, $query, $object, $safe=True, $upsert=False, $multiple=False)
	{
		// Verify that the alias has been set up properly
		if(!self::validate_alias($alias))
		{
			return FALSE;
		}

        // clearing out previous errors before every query
        self::$errors = array();


		// Get our database connection.  This creates it necessary.  If no connection is made, it errors out
		if(!($connection = self::get_connection($alias)))
		{
			return FALSE;
		}

        $db = $connection->$dbname;
        $col = $db->$colname;

        try
        {
            $result = $col->update($query,$object,array('safe'=>$safe,'upsert'=>$upsert,'multiple'=>$multiple));
        }
        catch (Exception $e)
        {
            error_log($e->getMessage());
            error_log(var_export($query,true));
            error_log(var_export($object,true));
            return FALSE;
        }
        return TRUE;
    }

	public static function remove($alias, $dbname, $colname, $query, $safe=True, $just_one=True)
	{
		// Verify that the alias has been set up properly
		if(!self::validate_alias($alias))
		{
			return FALSE;
		}

        // clearing out previous errors before every query
        self::$errors = array();


		// Get our database connection.  This creates it necessary.  If no connection is made, it errors out
		if(!($connection = self::get_connection($alias)))
		{
			return FALSE;
		}

        $db = $connection->$dbname;
        $col = $db->$colname;

        try
        {
            $result = $col->remove($query,array('safe'=>$safe,'justOne'=>$just_one));
        }
        catch (Exception $e)
        {
            return FALSE;
        }
        return TRUE;
    }

    public static function close_connections()
    {
        foreach(self::$connections as $alias => &$connection)
        {
            $connection->close();
        }
    }

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
        $options = array();
        if(self::$config[$alias]['username'] != NULL) 
        {
            $options['username'] = self::$config[$alias]['username'];
        }
        if(self::$config[$alias]['password'] != NULL) 
        {
            $options['password'] = self::$config[$alias]['password'];
        }


		if(self::$connections[$alias] = new Mongo(self::$config[$alias]['host'], $options))
		{
			return TRUE;
		}
		else
		{
			self::set_error('Unable to create connection');
			return FALSE;
		}
	}

	private static function set_error($error)
	{
		self::$errors[] = $error;
	}

	public static function get_errors()
	{
		return self::$errors;
	}
}

?>
