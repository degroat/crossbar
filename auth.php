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
			$user = self::user();
			if($user)
			{
				self::$user_groups = array_merge($user['groups'], self::$user_groups);
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

		// Verify that the group they're asking
		// for even exists
		if(!array_key_exists($group,self::$groups))
		{
			self::error('Invalid group specified');
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

	public static function user()
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
		$decrypted_value = unserialize(crypto::decrypt($salt, $_COOKIE[self::$cookie_name]));

		if($decrypted_value === FALSE)
		{
			self::error('Failed decryption cookie (salt probably changed)');
			return FALSE;
		}

		return $decrypted_value;
	}

	private static function salt()
	{
		if(count(self::$groups) == 0)
		{
			self::error("You must configure groups to login a user");
			return FALSE;
		}

		return implode("-", array_keys(self::$groups));
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
