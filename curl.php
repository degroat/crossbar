<?
class curl
{
    private static $user_agent = NULL; 
    private static $c = NULL;
    public static $return_transfer = true;
    public static $no_body = false;

    private static function init()
    {
        if(self::$user_agent == NULL && isset($_SERVER['HTTP_USER_AGENT']))
        {   
            self::$user_agent = $_SERVER['HTTP_USER_AGENT'];
        }

        self::$c = curl_init();
        curl_setopt(self::$c, CURLOPT_RETURNTRANSFER, self::$return_transfer);
        curl_setopt(self::$c, CURLOPT_NOBODY, self::$no_body);
        curl_setopt(self::$c, CURLOPT_USERAGENT, self::$user_agent);
        curl_setopt(self::$c, CURLOPT_FOLLOWLOCATION, TRUE);
    }


	private static function exec()
	{
		try
		{
			return curl_exec(self::$c);
		}
		catch(Exception $e)
		{
			return '';
		}
	}

    public static function get($url)
    {
        curl::init();
        curl_setopt(self::$c, CURLOPT_URL, $url);
        return self::exec();
    }

    public static function post($url, $params = array())
    {
        curl::init();
        curl_setopt(self::$c, CURLOPT_URL, $url);
        curl_setopt(self::$c, CURLOPT_POST, 1);
        curl_setopt(self::$c, CURLOPT_POSTFIELDS, $params);
        return self::exec();
    }

    public static function set_user_agent($user_agent)
    {
        self::$user_agent = $user_agent;
    }
}

?>
