<?

class model_base
{
    private static $errors = array();

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
}

?>
