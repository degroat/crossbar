<?
class mixpanel {
    static private $token = NULL;
    static private $host = 'http://api.mixpanel.com/';
    static public function set_token($token){
        self::$token = $token;
    }
    static public function set_default_ip($ip){
        self::$default_ip = $ip;
    }
    static public function track($event,$properties=array()) {
        if(self::$token == NULL){
            throw Exception('mixpanel - can not call track without a token');
        }
        if(isset($_SERVER['REMOTE_ADDR']) == TRUE){
            $properties['ip'] = $_SERVER['REMOTE_ADDR'];
        }
        if($properties['ip'] == '127.0.0.1'){
            $properties['ip'] = self::$deafult_ip;
        }
        if(isset($properties['ip']) == FALSE){
            throw Exception('mixpanel - no ip');
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
