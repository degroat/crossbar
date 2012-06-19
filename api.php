<?

class api
{
    private static $base_url = NULL;
    private static $key = NULL;
    private static $secret = NULL;
    public static $error = NULL;
    public static $response = NULL;

    public static function set_base_url($url)
    {
        self::$base_url = $url;
    }

    public static function set_api_key($key, $secret)
    {
        self::$key = $key;
        self::$secret = $secret;
    }

    public static function get($controller, $action, $params)
    {
        $params['_key'] = self::$key;
        $url = self::$base_url . "/{$controller}/{$action}?" . http_build_query($params);
        return self::process_response(curl::get($url));
    }

    public static function post($controller, $action, $params)
    {
        $params['_key'] = self::$key;
        $url = self::$base_url . "/{$controller}/{$action}";
        return self::process_response(curl::post($url, $params));
    }

    public static function process_response($response)
    {
        $response = json_decode($response, TRUE);
        self::$response = $response;
        if($response == NULL)
        {
            self::$error = "Invalid JSON returned";   
            return FALSE;
        }
        elseif($response['success'] == 0)
        {
            self::$error = $response['error'];
            return FALSE;
        }
        else
        {
            return $response['data'];
        }
    }
}

?>
