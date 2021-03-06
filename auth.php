<?

class auth
{
	private static $groups = array();
	private static $user_groups = array();
	private static $cookie_name = 'crossbar';
	private static $errors = array();
	private static $enabled = FALSE;

	public static function load_user_groups()
	{
		if(count(self::$user_groups) == 0)
		{
			// Everyone gets access to the * group
			self::$user_groups = array('*');

			// Grab the logged in user, and add their groups 
			// to the groups we need to check
			$groups = self::groups();
			if($groups !== FALSE)
			{
				self::$user_groups = array_merge($groups, self::$user_groups);
			}
		}
	}

	public static function access($controller, $action)
	{
		if(!self::$enabled)
		{
			return TRUE;
		}

		// Load the user groups
		self::load_user_groups();

		// Loop through each of our groups and
		// look for that controller/action in the list
		foreach(self::$user_groups as $group)
		{
			if(isset(self::$groups[$group][$controller]))
			{
				if(in_array($action, self::$groups[$group][$controller]) || in_array('*', self::$groups[$group][$controller]))
				{
					return TRUE;
				}
			}
		}

		return FALSE;

	}

    public static function logged_in()
    {
        $user = self::user();
        if(empty($user))
        {
            return FALSE;
        }
        return TRUE;
    }

	public static function check($group)
	{
		// Everyone gets star!
		if($group == '*')
		{
			return TRUE;
		}

		// Simple validation
		if($group == "")
		{
			self::error('Group not specified');
			return FALSE;
		}

		// Load the user groups
		self::load_user_groups();

		if(in_array($group, self::$user_groups))
		{
			return TRUE;
		}

		return FALSE;
	
	}

	public static function login($user, $groups, $time = 0)
	{
		// ---------------------------------------------
		// To log the user in, we need a salt to encrypt
		// the data we're going to store in a cookie
		// ---------------------------------------------
		$salt = self::salt();
		if(!$salt)
		{		
			self::error("Invalid Salt");
			return FALSE;
		}		

		// Force groups to an array....
		if(!is_array($groups))
		{
			$groups = array($groups);
		}

		// Build the cookie value
		$cookie['user'] = $user;
		$cookie['groups'] = $groups;
		$cookie_value = crypto::encrypt($salt, serialize($cookie));

		if($time > 0)
		{
			$time += time();
		}

		// Set the cookie... even for this page load to be safe
		$_COOKIE[self::$cookie_name] = $cookie_value;
		setcookie(self::$cookie_name, $cookie_value, $time, '/');

		// Reset the user groups since they've changed
		self::$user_groups = array();
	}

	public static function logout()
	{
		setcookie(self::$cookie_name, '', time() - 3600, '/');
	}

	public static function group($group, $config)
	{
		if($group == '' || !is_array($config) || count($config) == 0)
		{
			self::error('Invalid group configuration');
			return FALSE;
		}
		self::$enabled = TRUE;
		self::$groups[$group] = $config;
		return TRUE;
	}

    
    // RETURNS this user's groups
    public static function groups()
    {
		// Check to see if user is logged in
		if(!isset($_COOKIE[self::$cookie_name]))
		{
			self::error('User not logged in');
			return FALSE;
		}
		
		// Make sure we can decrypt the data we get back
		// by generating a salt
		$salt = self::salt();
		if(!$salt)
		{		
			self::error("Invalid Salt");
			return FALSE;
		}		
		
		// Decrypt and unserialize the value in the cookie
		$decrypted_value = @unserialize(crypto::decrypt($salt, $_COOKIE[self::$cookie_name]));

		if($decrypted_value === FALSE)
		{
			self::error('Failed decryption cookie (salt probably changed)');
			return FALSE;
		}

        return $decrypted_value['groups'];
        
    }

	public static function user($key = NULL)
	{
		// Check to see if user is logged in
		if(!isset($_COOKIE[self::$cookie_name]))
		{
			self::error('User not logged in');
			return FALSE;
		}
		
		// Make sure we can decrypt the data we get back
		// by generating a salt
		$salt = self::salt();
		if(!$salt)
		{		
			self::error("Invalid Salt");
			return FALSE;
		}		
		
		// Decrypt and unserialize the value in the cookie
		$decrypted_value = @unserialize(crypto::decrypt($salt, $_COOKIE[self::$cookie_name]));

		if($decrypted_value === FALSE)
		{
			self::error('Failed decryption cookie (salt probably changed)');
			return FALSE;
		}

        $user = $decrypted_value['user'];

        // If a specific key was requested, send that value back
        if($key != NULL && isset($user[$key]))
        {
            return $user[$key];
        }
        elseif($key != NULL)
        {       
            return NULL;
        }       
        else
        {
		    return $user;
        }    
	}

	private static function salt()
	{
		if(count(self::$groups) == 0)
		{
			self::error("You must configure groups to login a user");
			return FALSE;
		}

		return substr(md5(implode("-", array_keys(self::$groups))),0,24);
	}

    public static function set_cookie_name($name)
    {
        if(!empty($name))
        {
            self::$cookie_name = $name;
        }
    }

	public static function error($error = "")
	{
		if($error == '')
		{
			return self::$errors;
		}
		else
		{
			self::$errors[] = $error;
		}
	}



}

?>
