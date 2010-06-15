<?

class simpledb
{
	static private $accesskey;
	static private $secretkey;
	static private $errors = array();
	static private $xml;
	static private $response = array();

	public static function config($accesskey, $secretkey)
	{
		if($accesskey == "" || $secretkey == "")
		{
			return FALSE;
		}
		
		self::$accesskey	= $accesskey;
		self::$secretkey 	= $secretkey;
		return TRUE;
	}

	public static function initialize_response()
	{
		self::$response = array();
		self::$response['success'] = TRUE;
	}

	public static function validate_keys()
	{
		if(empty(self::$accesskey) || empty(self::$secretkey))
		{
			return FALSE;
		}
		return TRUE;
	}

	public static function call($verb = 'POST', $params = array())
	{
		// This resets the response array
		self::initialize_response();

		// Verify an action exists
		if(!isset($params['Action']) || $params['Action'] == '')
		{
			self::$response['success'] = FALSE;
			self::$response['errors'][] = "Missing required parameter Action";
			return FALSE;
		}

		// Verify the Access and Secret Key
		if(!self::validate_keys())
		{
			self::$response['success'] = FALSE;
			self::$response['errors'][] = "Missing required AWS Access Key OR Secret Key";
			return FALSE;
		}

		// Add required values to the Params array to send to AWS
		$params['AWSAccessKeyId'] 	= self::$accesskey;
		$params['SignatureVersion'] 	= 2;
		$params['SignatureMethod'] 	= 'HmacSHA256';
		$params['Timestamp'] 		=  gmdate('c');
		$params['Version']		= '2009-04-15';

		// I really don't like how I did this, but my better way
		// wasn't working with the AWS Signature so I went w/ the
		// way it was done in the PHP code that amazon provides
		$query = array();
                foreach ($params as $var => $val)
                {
                        if(is_array($val))
                        {
                                foreach($val as $v)
                                {
                                        $query[] = $var . '=' . rawurlencode($v);
                                }
                        }
                        else
                        {
                                $query[] = $var.'='.rawurlencode($val);
                        }
                }

		// This section of code builds the AWS signature.  If this doesn't
		// work, nothing does
                sort($query, SORT_STRING);
                $query_string = implode('&', $query);
		$strtosign = "$verb\nsdb.amazonaws.com\n/\n".$query_string;
		$query_string .= '&Signature=' . rawurlencode(base64_encode(hash_hmac('sha256', $strtosign, self::$secretkey, true)));


		$url = "https://sdb.amazonaws.com/?" . $query_string;

		//print str_replace("&", "<br>&", "$url<br><br>");
		$curl = curl_init();
                curl_setopt($curl, CURLOPT_USERAGENT, 'SimpleDB/php');
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_TIMEOUT, 5);

		// I don't think these are necessary but were included in the
		// example provided by AWS.  Will remove if they prove to be 
		// after class if finished
               	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 1);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
                curl_setopt($curl, CURLOPT_HEADER, false);
                #curl_setopt($curl, CURLOPT_WRITEFUNCTION, 'wtf'); // array(self,'callback'));
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);


                // Request types
                switch ($verb) {
                        case 'GET': break;
                        case 'PUT': case 'POST':
                                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $verb);
                        break;
                        case 'HEAD':
                                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'HEAD');
                                curl_setopt($curl, CURLOPT_NOBODY, true);
                        break;
                        case 'DELETE':
                                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                        break;
                        default: break;
                }

		// Okay... let's get our XML from SimpleBD
		$xml_string = curl_exec($curl);

		if($xml_string)
		{
			// Convert the returned XML to simplexml and check for errors
			self::$response['xml'] = simplexml_load_string($xml_string);

			if(isset(self::$response['xml']->Errors))
			{
				foreach(self::$response['xml']->Errors->Error as $error)
				{
					$error = (array) $error;
					self::error("AWS Error: " . $error['Message']);
				}
				self::$response['success'] = FALSE;
				return FALSE;
			}

			// If we got here, we didn't find any errors in the XML response
			return TRUE;
		}
		else
		{
			// Curl encountered an error
			self::error("CURL Error: " . curl_error($curl));
			self::$response['success'] = FALSE;
			return FALSE;
		}
	}

	public static function callback($curl, $data)
	{
		print "DATA:$data";
		self::$xml = $data;	
	}

	public static function save($domain, $item, $values)
	{
		// Validate the data coming into the function
		if($domain == '')
		{
			self::error('Missing required parameter domain');
			return FALSE;
		}
		if($item == '')
		{
			self::error('Missing required parameter name');
			return FALSE;
		}
		if(!is_array($values) || count($values) == 0)
		{
			self::error('Values must be an array with at least one name/value pair');
			return FALSE;
		}
			
		// Start building the params array to send to AWS
		$params['Action'] 	= 'PutAttributes';
		$params['DomainName'] 	= $domain;
		$params['ItemName'] 	= $item;

		// This takes the values and builds the params as required by AWS
		$count = 0;
		foreach($values as $var => $val)
		{

			// Convert to array for ease of processing
			if(is_array($val))
			{
				foreach($val as $v)
				{
					if(is_string($v) && strlen($v) > 1024)
					{
						$v = substr($v, 0, 1024);
					}

					$params['Attribute.'.$count.'.Name'] = $var;
					$params['Attribute.'.$count.'.Replace'] = 'true';
					$params['Attribute.'.$count.'.Value'][] = $v;
					$count++;
				}
			}
			else
			{
				if(is_string($val) && strlen($val) > 1024)
				{
					$val = substr($val, 0, 1024);
				}

				$params['Attribute.'.$count.'.Name'] = $var;
				$params['Attribute.'.$count.'.Replace'] = 'true';
				$params['Attribute.'.$count.'.Value'] = $val;
				$count++;
			}
		}

		// This makes the call to AWS and returns either FALSE or TRUE
		if(!self::call('POST', $params))
		{
			return FALSE;
		}

		// For this function, we just return the success code on whether
		// or not the item was successfully saved -- no data to be returned here
		return self::$response['success'];	
	}


	public static function query($sql, $master = FALSE)
	{
		if($sql == '')
		{
			self::error('Missing required parameter sql');
			return FALSE;
		}
		
		// Start building the params array to send to AWS
		$params['Action'] = 'Select';
		if($master)
		{
			$params['ConsistentRead'] = 'true';
		}
		$params['SelectExpression'] = $sql;

		// This makes the call to AWS and returns either FALSE or TRUE
		if(!self::call('GET', $params))
		{
			return FALSE;
		}
	
		// Loop through the SimpleXML junk and convert it to an item keyed array to return
		$data = array();
		foreach(self::$response['xml']->SelectResult->Item as $item)
		{
			$name = (string) $item->Name;

			$data[$name] = array();

			foreach($item->Attribute as $attribute)
			{
				$var = (string) $attribute->Name;
				$val = (string) $attribute->Value;

				if(isset($data[$name][$var]))
				{
					if(is_array($data[$name][$var]))
					{
						$data[$name][$var][] = $val;
					}
					else
					{
						$tmp = $data[$name][$var];
						$data[$name][$var] = array($tmp, $val);
					}
				}
				else
				{
					$data[$name][$var] = $val;
				}
			}
		}

		// If zero rows were returned, just return false
		if(count($data) == 0)
		{
			return FALSE;
		}
		
		self::$response['data'] = $data;
		return $data;

	}

	public static function delete($domain, $item)
	{
		// Validate the data coming into the function
		if($domain == '')
		{
			self::error('Missing required parameter domain');
			return FALSE;
		}
		if($item == '')
		{
			self::error('Missing required parameter name');
			return FALSE;
		}


		$params['Action'] 	= 'DeleteAttributes';
		$params['DomainName'] 	= $domain;
		$params['ItemName'] 	= $item;

		// This makes the call to AWS and returns either FALSE or TRUE
		if(!self::call('DELETE', $params))
		{
			return FALSE;
		}

		// For this function, we just return the success code on whether
		// or not the item was successfully saved -- no data to be returned here
		return self::$response['success'];	
	}

	private static function error($string = '')
	{
		if($string == '')
		{
			return self::$errors;
		}
		else
		{
			print "STRING:";
			print_r($string);
			self::$errors[] = $string;
		}
	}

}

	function wtf($curl, $data)
	{
		print "WTF:$data:ENDWTF";
	}

?>
