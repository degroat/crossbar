<?
class mixpanel {
    static private $token = NULL;
    static private $default_ip = NULL;
    static private $host = 'http://api.mixpanel.com/';
    static public function set_token($token){
        self::$token = $token;
    }
    static public function set_default_ip($ip){
        self::$default_ip = $ip;
    }
    static public function track($event,$properties=array()) {
        if(self::$token == NULL){
            throw new Exception('mixpanel - can not call track without a token');
        }
        if(isset($_SERVER['REMOTE_ADDR']) == TRUE){
            $properties['ip'] = $_SERVER['REMOTE_ADDR'];
        }
        if(isset($properties['ip']) === FALSE && self::$default_ip !== NULL){
            $properties['ip'] = self::$default_ip;
        }
        if(isset($properties['ip']) == FALSE){
            throw new Exception('mixpanel - no ip');
        }
        if($properties['ip'] == '127.0.0.1'){
            if(isset(self::$default_ip) === TRUE)
            {
                $properties['ip'] = self::$default_ip;
            }
            else
            {
                // mixpanel doesn't track 127.0.0.1
                // so using google's IP address so we can still see that the tracking is working, even if it is all globbed together
                $properties['ip'] = '64.233.191.255';
            }
        }
        $params = array(
                'event' => $event,
                'properties' => $properties
                );
        if(!isset($params['properties']['token'])){
            $params['properties']['token'] = self::$token;
        }
        $url = self::$host . 'track/?data=' . base64_encode(json_encode($params));
        $command = "curl '" . $url . "' >/dev/null 2>&1 &";
        exec($command); 
    }
}
?>
