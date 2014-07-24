<?

class model_base
{
    private static $errors = array();
    private static $param_errors = array();

    // FIELDS
    public static function fields($method = 'create')
    {   
        if($method == 'create')
        {
            return static::$fields;
        }
        elseif($method == 'update_by_id')
        {
            $fields = static::$fields;
            foreach($fields as $field => $details)
            {
                $fields[$field]['required'] = FALSE;
            }
            $fields[static::$id] = array('required' => TRUE, 'type' => 'int');
            return $fields;
        }
        elseif($method == 'get_by_id' || $method == 'delete_by_id')
        {
            return array(static::$id => array('required' => TRUE, 'type' => 'int'));
        }
        elseif($method == 'get_by_field')
        {
            return array(
                            'field' => array(
                                            'required' => TRUE,
                                            'type' => 'text',
                                        ),
                            'value' => array(
                                            'required' => TRUE,
                                            'type' => 'text',
                                        ),
                            'sort_field' => array(
                                            'required' => FALSE,
                                            'type' => 'text',
                                        ),
                            'sort_order' => array(
                                            'required' => FALSE,
                                            'type' => 'text',
                                        ),
                            'single' => array(
                                            'required' => FALSE,
                                            'type' => 'text',
                                        ),
                        );
        }
        elseif($method == 'get_all')
        {
            return array(
                            'sort_field' => array(
                                            'required' => FALSE,
                                            'type' => 'text',
                                        ),
                            'sort_order' => array(
                                            'required' => FALSE,
                                            'type' => 'text',
                                        ),
                        );
        }
    }

    // SANITIZE 
    public static function sanitize($values)
    {
        $fields = self::fields();
        foreach($values as $var => $val)
        {
            if(!array_key_exists($var, $fields))
            {
                unset($values[$var]);
            }
            elseif($fields[$var]['type'] == "password")
            {
                $values[$var] = sha1($fields[$var]['salt'] . $values[$var]);
            }
        }
        return $values;
    }

    // VERIFY
    public static function verify()
    {
        if(empty(static::$database))
        {
            self::fatal('Database not defined in model');
        }
        if(empty(static::$table))
        {
            self::fatal('table not defined in model');
        }
        if(empty(static::$id))
        {
            self::fatal('ID not defined in model');
        }
        if(empty(static::$fields))
        {
            self::fatal('Fields not defined in model');
        }
    }

    // --
    public static function fatal($error)
    {
        $error = "Crossbar/Base_mysql_mode: " . $error;
        error_log($error);
        trigger_error($error, E_USER_ERROR);
        exit;
    }

    // --
    public static function set_error($error)
    {
        if(is_array($error))
        {
            self::$errors = array_merge(self::$errors, $error);
        }
        else
        {
            self::$errors[] = $error;
        }
    }

    // -- 
    public static function set_param_error($field, $error)
    {
        self::$param_errors[$field] = $error;
    }

    // --
    public static function get_param_errors()
    {
        return self::$param_errors;
    }

    // --
    public static function get_errors()
    {
        foreach(self::$errors as $index => $error)
        {
            if(strpos($error, 'Duplicate entry') !== FALSE && strpos($error, 'for key')  !== FALSE)
            {
                self::$errors[$index] = "Record already exists for " . substr($error, strpos($error, "'") + 1, strpos($error, "'", strpos($error, "'") + 1)-strpos($error, "'")-1);   
            }
        }
        return self::$errors;
    }

    public static function get_error()
    {
        $errors = static::get_errors();
        if(empty($errors))
        {
            return FALSE;
        }
        return array_shift($errors);
    }


    public static function validate($values)
    {
        $errors = array();

        $fields = self::fields();
        foreach($values as $param => $value)
        {
            if(!isset($fields[$param]))
            {
                continue;
            }
            $config = $fields[$param];
        
            // Set label for error reporting
            $label = ucwords($param);
            if(isset($config['label']))
            {
                $label = $config['label'];
            }

            // Check if required
            if($config['required'] === TRUE && ($value == NULL || $value == ""))
            {
                $errors[$param] = "Please enter a {$label}";
            }

            if(!empty($value))
            {
                $type = $config['type'];
                if($type != 'text' && $type != 'password')
                {
                    if(!validate::$type(trim($value)))
                    {
                        $errors[$param] = "Invalid value entered for {$label}";
                    }
                }

                if(isset($config['max_length']))
                {
                    if(strlen($value) > $config['max_length'])   
                    {
                        $errors[$param] = "{$label} exceeds maximum length of {$config['max_length']}";
                    }
                }

                if(isset($config['min_length']))
                {
                    if(strlen($value) < $config['min_length'])   
                    {
                        $errors[$param] = "{$label} has a minimum length of {$config['min_length']}";
                    }
                }
            }
        }

        if(count($errors) > 0)
        {
            foreach($errors as $field => $error)
            {
                self::set_param_error($field, $error);
            }
            return FALSE;
        }

        return TRUE;

    }


}

?>
