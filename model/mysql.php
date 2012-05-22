<?

class model_mysql extends model_base
{
    // CREATE
    public static function create($values)
    {
        self::verify();
        $values = self::sanitize($values);
        $response = mysql::insert(static::$database, static::$table, $values);
        if($response === FALSE)
        {
            self::set_error(mysql::get_errors());
            return FALSE;
        }
        return array(static::$id => $response);
    }

    // UPDATE BY ID
    public static function update_by_id($values)
    {
        self::verify();
        $id = $values[static::$id];

        $values = self::sanitize($values);
        $string_vals = array();
        foreach($values as $var => $val)
        {
            $string_vals[] = $var . " = " . mysql::quote($val);
        }

        $sql = "UPDATE ".static::$table." SET " . implode(', ', $string_vals) . " WHERE " . static::$id . " = " . mysql::quote($id);
        $response = mysql::query(static::$database, $sql);
        if($response === FALSE)
        {
            self::set_error(mysql::get_errors());
            return FALSE;
        }
        return TRUE;
    }

    // GET BY FIELD
    public static function get_by_field($values)
    {
        self::verify();
        $sql = "SELECT * FROM ".static::$table." WHERE ".mysql::escape($values['field'])." = ".mysql::quote($values['value']);
        $rows = mysql::query(static::$database, $sql);
        if($rows === FALSE)
        {
            self::set_error(mysql::get_errors());
            return FALSE;
        }
        return $rows;
    }

    // GET BY ID
    public static function get_by_id($values)
    {
        $rows = self::get_by_field(array('field' => static::$id, 'value' => $values[static::$id]));
        if($rows == FALSE)
        {
            return FALSE;
        }
        return $rows[0];
    }

    // GET ALL
    public static function get_all($values)
    {
        self::verify();
        $sql = "SELECT * FROM ".static::$table;
        if(isset($values['sort_field']))
        {
            $sql .= " ORDER BY " . $values['sort_field'];
            if(isset($values['sort_order']))
            {
                $sql .= $values['sort_order'];
            }
        }
        $rows = mysql::query(static::$database, $sql);
        if($rows === FALSE)
        {
            self::set_error(mysql::get_errors());
            return FALSE;
        }
        return $rows;
    }
}
?>
