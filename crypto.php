<?php

class crypto
{
	private static $encrypt_type = MCRYPT_3DES;
	private static $encrypt_mode = MCRYPT_MODE_ECB;
	private static $iv_size;
	private static $iv;

	public static function encrypt($salt, $cleartext)
	{
		self::set_values();
		return self::base64url_encode(mcrypt_encrypt(self::$encrypt_type,self::pad($salt),$cleartext,self::$encrypt_mode,self::$iv));
	}

	public static function decrypt($salt, $ciphertext)
	{
		self::set_values();
		return rtrim(mcrypt_decrypt(self::$encrypt_type,self::pad($salt),self::base64url_decode($ciphertext),self::$encrypt_mode,self::$iv),"\0");
	}

	private static function set_values()
	{
		self::$iv_size = mcrypt_get_iv_size(MCRYPT_3DES, MCRYPT_MODE_ECB);
		self::$iv = mcrypt_create_iv(self::$iv_size, MCRYPT_RAND); 	
	}

	private static function base64url_encode($plainText)
	{
		$base64 = base64_encode($plainText);
		$base64url = strtr($base64, '+/=', '-_*');
		return ($base64url);   
	}

	private static function base64url_decode($base64url)
	{
		$base64 = strtr($base64url, '-_*', '+/=');
		$plainText = base64_decode($base64);
		return ($plainText);   
	}

    private static function pad($key)
    {
        // key is too large
        if(strlen($key) > 32) return false;

        // set sizes
        $sizes = array(24,32);

        // loop through sizes and pad key
        foreach($sizes as $s){
            while(strlen($key) < $s) $key = $key."\0";
            if(strlen($key) == $s) break; // finish if the key matches a size
        }

        // return
        return $key;
    }
}

?>
