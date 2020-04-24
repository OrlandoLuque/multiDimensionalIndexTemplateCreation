<?php
/**
 * Evily robbed from Chamilo 1.8.8.4 mysqli database library
 * Further modifications by @OrlandoLuque since today (2020/04/01)
 */

class Database {
    /** @var $database */
    public static $database;

    #region core
    static function init(string $databaseName) {
        Database::$databaseName = $databaseName;
    }

    /**
     * This private method is to be used by the other methods in this class for
     * checking whether the input parameter $connection actually has been provided.
     * If the input parameter connection is not a resource or if it is not FALSE (in case of error)
     * then the default opened connection should be used by the called method.
     * @param mysqli|boolean $connection The checked parameter $connection.
     * @return boolean                        TRUE means that calling method should use the default connection.
     *                                        FALSE means that (valid) parameter $connection has been provided and it should be used.
     */
    private static function use_default_connection($connection) {
        return !is_resource($connection) && $connection !== false;
    }

    /**
     * Selects a database.
     * @param string $database_name				The name of the database that is to be selected.
     * @param resource $connection (optional)	The database server connection, for detailed description see the method query().
     * @return bool								Returns TRUE on success or FALSE on failure.
     */
    public static function select_db($database_name, $connection = null) {
        global $database_connection;
        $database_connection->select_db($database_name);
        return !$database_connection->errno;
        //return self::use_default_connection($connection) ? mysqli_select_db($connection, $database_name) : mysqli_select_db($connection, $database_name);
    }

    /**
     * Frees all the memory associated with the provided result identifier.
     * @return bool		Returns TRUE on success or FALSE on failure.
     * Notes: Use this method if you are concerned about how much memory is being used for queries that return large result sets.
     * Anyway, all associated result memory is automatically freed at the end of the script's execution.
     */
    public static function free_result(resour $result) {
        return $result->free_result();
    }

    #endregion

    #region connection commands
    /**
     * Opens a connection to a database server.
     * @param array $parameters (optional)        An array that contains the necessary parameters for accessing the server.
     * @return mysqli|boolean                    Returns a database connection on success or FALSE on failure.
     * Note: Currently the array could contain MySQL-specific parameters:
     * $parameters['server'], $parameters['username'], $parameters['password'],
     * $parameters['new_link'], $parameters['client_flags'], $parameters['persistent'].
     * For details see documentation about the functions mysql_connect() and mysql_pconnect().
     * @link http://php.net/manual/en/function.mysql-connect.php
     * @link http://php.net/manual/en/function.mysql-pconnect.php
     */
    public static function connect($parameters = array()) {
        global $database_connection;
        // A MySQL-specific implementation.
        if (!isset($parameters['server'])) {
            $parameters['server'] = @ini_get('mysqli.default_host');
            if (empty($parameters['server'])) {
                $parameters['server'] = 'localhost:3306';
            }
        }
        if (!isset($parameters['username'])) {
            $parameters['username'] = @ini_get('mysqli.default_user');
        }
        if (!isset($parameters['password'])) {
            $parameters['password'] = @ini_get('mysqli.default_pw');
        }
        if (!isset($parameters['persistent'])) {
            $parameters['persistent'] = false;
        }
        $database_connection = $parameters['persistent']
            ? new mysqli('p:' . $parameters['server'], $parameters['username'], $parameters['password'])
            : new mysqli($parameters['server'], $parameters['username'], $parameters['password']);
        if ($database_connection->connect_errno) {
            error_log($database_connection->connect_errno());
            return null;
        } else {
            return $database_connection;
        }
    }

    /**
     * Closes non-persistent database connection.
     * @param resource $connection (optional)    The database server connection, for detailed description see the method query().
     * @return bool                                Returns TRUE on success or FALSE on failure.
     */
    public static function close($connection = null) {
        return self::use_default_connection($connection) ? mysqli::close() : mysqli::close($connection);
    }

    #endregion

    #region Queries
    /**
     * This method returns a resource
     * Documentation has been added by Arthur Portugal
     * Some adaptations have been implemented by Ivan Tcholakov, 2009, 2010
     * @author Olivier Brouckaert
     * @param string $query						The SQL query
     * @param mysqli $connection (optional)	The database server (MySQL) connection.
     * 											If it is not specified, the connection opened by mysql_connect() is assumed.
     * 											If no connection is found, the server will try to create one as if mysql_connect() was called with no arguments.
     * 											If no connection is found or established, an E_WARNING level error is generated.
     * @param string $file (optional)			On error it shows the file in which the error has been trigerred (use the "magic" constant __FILE__ as input parameter)
     * @param string $line (optional)			On error it shows the line in which the error has been trigerred (use the "magic" constant __LINE__ as input parameter)
     * @return resource							The returned result from the query
     * Note: The parameter $connection could be skipped. Here are examples of this method usage:
     * Database::query($query);
     * $result = Database::query($query);
     * Database::query($query, $connection);
     * $result = Database::query($query, $connection);
     * The following ways for calling this method are obsolete:
     * Database::query($query, __FILE__, __LINE__);
     * $result = Database::query($query, __FILE__, __LINE__);
     * Database::query($query, $connection, __FILE__, __LINE__);
     * $result = Database::query($query, $connection, __FILE__, __LINE__);
     */
    public static function query($query, $connection = null, $file = null, $line = null) {
        /** @var mysqli $database_connection */
        global $database_connection;
        if ($connection === null) {
            $connection = $database_connection;
        }
        /** @var bool $result */
        $result = @$connection->query($query);
        if ($database_connection->errno) {
            $backtrace = debug_backtrace(); // Retrieving information about the caller statement.
            if (isset($backtrace[0])) {
                $caller = & $backtrace[0];
            } else {
                $caller = array();
            }
            if (isset($backtrace[1])) {
                $owner = & $backtrace[1];
            } else {
                $owner = array();
            }
            if (empty($file)) {
                $file = $caller['file'];
            }
            if (empty($line) && $line !== false) {
                $line = $caller['line'];
            }
            $type = $owner['type'];
            $function = $owner['function'];
            $class = $owner['class'];
            $server_type = api_get_setting('server_type');
            if (!empty($line) && !empty($server_type) && $server_type != 'production') {
                $info = '<pre>' .
                    '<strong>DATABASE ERROR #'.self::errorNumber($connection).':</strong><br /> ' .
                    self::remove_XSS(self::errorText($connection)) . '<br />' .
                    '<strong>QUERY       :</strong><br /> ' .
                    self::remove_XSS($query) . '<br />' .
                    '<strong>FILE        :</strong><br /> ' .
                    (empty($file) ? ' unknown ' : $file) . '<br />' .
                    '<strong>LINE        :</strong><br /> ' .
                    (empty($line) ? ' unknown ' : $line) . '<br />';
                if (empty($type)) {
                    if (!empty($function)) {
                        $info .= '<strong>FUNCTION    :</strong><br /> ' . $function;
                    }
                } else {
                    if (!empty($class) && !empty($function)) {
                        $info .= '<strong>CLASS       :</strong><br /> ' . $class . '<br />';
                        $info .= '<strong>METHOD      :</strong><br /> ' . $function;
                    }
                }
                $info .= '</pre>';
                echo $info;
            }
        }
        return $result;
    }

    /**
     * Experimental useful database insert
     * @todo lot of stuff to do here
     */
    public static function insert($table_name, $attributes) {
        if (empty($attributes) || empty($table_name)) {
            return false;
        }
        $filtred_attributes = array();
        foreach($attributes as $key => $value) {
            $filtred_attributes[$key] = "'".self::escape_string($value)."'";
        }
        $params = array_keys($filtred_attributes); //@todo check if the field exists in the table we should use a describe of that table
        $values = array_values($filtred_attributes);
        if (!empty($params) && !empty($values)) {
            $sql    = 'INSERT INTO '.$table_name.' ('.implode(',',$params).') VALUES ('.implode(',',$values).')';
            $result = self::query($sql);
            return  self::get_last_insert_id();
        }
        return false;
    }

    /**
     * Experimental useful database finder
     * @todo lot of stuff to do here
     */

    public static function select($columns = '*' , $table_name,  $conditions = array(), $type_result = 'all', $option = 'ASSOC') {
        $conditions = self::parse_conditions($conditions);

        //@todo we could do a describe here to check the columns ...
        $clean_columns = '';
        if (is_array($columns)) {
            $clean_columns = implode(',', $columns);
        } else {
            if ($columns == '*') {
                $clean_columns = '*';
            } else {
                $clean_columns = (string)$columns;
            }
        }


        $sql    = "SELECT $clean_columns FROM $table_name $conditions";



        $result = self::query($sql);
        $array = array();
        //if (self::num_rows($result) > 0 ) {
        if ($type_result == 'all') {
            while ($row = self::fetch_array($result, $option)) {
                if (isset($row['id'])) {
                    $array[$row['id']] = $row;
                } else {
                    $array[] = $row;
                }
            }
        } else {
            $array = self::fetch_array($result, $option);
        }
        return $array;
    }

    /**
     * Parses WHERE/ORDER conditions i.e array('where'=>array('id = ?' =>'4'), 'order'=>'id DESC'))
     * @param   array
     * @todo lot of stuff to do here
     */
    private function parse_conditions($conditions) {
        if (empty($conditions)) {
            return '';
        }
        $return_value = '';
        foreach ($conditions as $type_condition => $condition_data) {
            $type_condition = strtolower($type_condition);
            switch($type_condition) {
                case 'where':
                    $where_return = '';
                    foreach ($condition_data as $condition => $value_array) {
                        if (is_array($value_array)) {
                            $clean_values = array();
                            foreach($value_array as $item) {
                                $item = Database::escape_string($item);
                                $clean_values[]= "'$item'";
                            }
                        } else {
                            $value_array = Database::escape_string($value_array);
                            $clean_values = "'$value_array'";
                        }
                        if (!empty($condition) && !empty($clean_values)) {
                            $condition = str_replace('?','%s', $condition); //we treat everything as string
                            $condition = vsprintf($condition, $clean_values);
                            $where_return .= $condition;
                        }
                    }
                    if (!empty($where_return)) {
                        $return_value = " WHERE $where_return" ;
                    }
                    break;
                case 'order':
                    $order_array = explode(' ', $condition_data);

                    if (!empty($order_array)) {
                        if (count($order_array) > 1) {
                            $order_array[0] = self::escape_string($order_array[0]);
                            if (!empty($order_array[1])) {
                                $order_array[1] = strtolower($order_array[1]);
                                $order = 'desc';
                                if (in_array($order_array[1], array('desc', 'asc'))) {
                                    $order = $order_array[1];
                                }
                            }
                            $return_value .= ' ORDER BY '.$order_array[0].'  '.$order;
                        }  else {
                            $return_value .= ' ORDER BY '.$order_array[0].' DESC ';
                        }
                    }
                    break;

                case 'limit':
                    $limit_array = explode(',', $condition_data);
                    if (!empty($limit_array)) {
                        if (count($limit_array) > 1) {
                            $return_value .= ' LIMIT '.intval($limit_array[0]).' , '.intval($limit_array[1]);
                        }  else {
                            $return_value .= ' LIMIT '.intval($limit_array[0]);
                        }
                    }
                    break;

            }
        }
        return $return_value;
    }

    private function parse_where_conditions($coditions){
        return self::parse_conditions(array('where'=>$coditions));
    }

    /**
     * Experimental useful database update
     * @todo lot of stuff to do here
     */
    public static function delete($table_name, $where_conditions) {
        $result = false;
        $where_return = self::parse_where_conditions($where_conditions);
        $sql    = "DELETE FROM $table_name $where_return ";
        $result = self::query($sql);
        $affected_rows = self::affected_rows();
        //@todo should return affected_rows for
        return $affected_rows;
    }

    /**
     * Experimental useful database update
     * @todo lot of stuff to do here
     */
    public static function update($table_name, $attributes, $where_conditions = array()) {

        if (!empty($table_name) && !empty($attributes)) {
            $update_sql = '';
            //Cleaning attributes
            $count = 1;
            foreach ($attributes as $key=>$value) {
                $value = self::escape_string($value);
                $update_sql .= "$key = '$value' ";
                if ($count < count($attributes)) {
                    $update_sql.=', ';
                }
                $count++;
            }
            if (!empty($update_sql)) {
                //Parsing and cleaning the where conditions
                $where_return = self::parse_where_conditions($where_conditions);
                $sql    = "UPDATE $table_name SET $update_sql $where_return ";
                //echo $sql; exit;
                $result = self::query($sql);
                $affected_rows = self::affected_rows();
                return $affected_rows;
            }
        }
        return false;
    }

    #endregion

    #region obtain data from resources (query results)
    /**
     * Gets the array from a SQL result (as returned by Database::query) - help achieving database independence
     * @param resource		The result from a call to sql_query (e.g. Database::query)
     * @param string		Optional: "ASSOC","NUM" or "BOTH", as the constant used in mysqli_fetch_array.
     * @return array		Array of results as returned by php
     * @author Yannick Warnier <yannick.warnier@beeznest.com>
     */
    public static function fetch_array($result, $option = 'BOTH') {
        return ($option == 'ASSOC') ? $result->fetch_array(MYSQLI_ASSOC) : ($option == 'NUM' ? $result->fetch_array(MYSQLI_NUM) : $result->fetch_array());
    }

    /**
     * Gets an associative array from a SQL result (as returned by Database::query).
     * This method is equivalent to calling Database::fetch_array() with 'ASSOC' value for the optional second parameter.
     * @param resource $result	The result from a call to sql_query (e.g. Database::query).
     * @return array			Returns an associative array that corresponds to the fetched row and moves the internal data pointer ahead.
     */
    public static function fetch_assoc($result) {
        return $result->fetch_assoc();
    }

    /**
     * Gets the next row of the result of the SQL query (as returned by Database::query) in an object form
     * @param	resource	The result from a call to sql_query (e.g. Database::query)
     * @param	string		Optional class name to instanciate
     * @param	array		Optional array of parameters
     * @return	object		Object of class StdClass or the required class, containing the query result row
     * @author	Yannick Warnier <yannick.warnier@dokeos.com>
     */
    public static function fetch_object($result, $class = null, $params = null) {
        return !empty($class) ? (is_array($params) ? $result->fetch_object($class, $params) : $result->fetch_object($class)) : $result->fetch_object();
    }

    /**
     * Gets the array from a SQL result (as returned by Database::query) - help achieving database independence
     * @param resource		The result from a call to sql_query (see Database::query()).
     * @return array		Array of results as returned by php (mysql_fetch_row)
     */
    public static function fetch_row($result) {
        return $result->fetch_row();
    }

    /**
     * Stores a query result into an array.
     *
     * @author Olivier Brouckaert
     * @param  resource $result - the return value of the query
     * @param  option BOTH, ASSOC, or NUM
     * @return array - the value returned by the query
     */
    public static function store_result($result, $option = 'BOTH') {
        $array = array();
        if ($result !== false) { // For isolation from database engine's behaviour.
            while ($row = self::fetch_array($result, $option)) {
                $array[] = $row;
            }
        }
        return $array;
    }

    /**
     * Acts as the relative *_result() function of most DB drivers and fetches a
     * specific line and a field
     * @param resource    The database resource to get data from
     * @param integer        The row number
     * @param string        Optional field name or number
     * @result    mixed        One cell of the result, or FALSE on error
     * @return bool|null
     */
    public static function result(&$resource, $row, $field = '') {
        if (self::num_rows($resource) > 0) {
            if (!empty($field)) {
                $r = mysqli_data_seek($resource, $row);
                return $r[$field];
            } else {
                return mysqli_data_seek($resource, $row);
            }
        } else { return null; }
    }
    #endregion

    #region Encodings and collations supported by MySQL database server
    /**
     * Checks whether a given encoding is supported by the database server.
     * @param string $encoding	The encoding (a system conventional id, for example 'UTF-8') to be checked.
     * @return bool				Returns a boolean value as a check-result.
     * @author Ivan Tcholakov
     */
    public static function is_encoding_supported($encoding) {
        static $supported = array();
        if (!isset($supported[$encoding])) {
            $supported[$encoding] = false;
            if (strlen($db_encoding = self::to_db_encoding($encoding)) > 0) {
                if (self::num_rows(self::query("SHOW CHARACTER SET WHERE Charset =  '".self::escape_string($db_encoding)."';")) > 0) {
                    $supported[$encoding] = true;
                }
            }
        }
        return $supported[$encoding];
    }

    /**
     * Constructs a SQL clause about default character set and default collation for newly created databases and tables.
     * Example: Database::make_charset_clause('UTF-8', 'bulgarian') returns
     *  DEFAULT CHARACTER SET `utf8` DEFAULT COLLATE `utf8_general_ci`
     * @param string $encoding (optional)	The default database/table encoding (a system conventional id) to be used.
     * @param string $language (optional)	Language (a system conventional id) used for choosing language sensitive collation (if it is possible).
     * @return string						Returns the constructed SQL clause or empty string if $encoding is not correct or is not supported.
     * @author Ivan Tcholakov
     */
    public static function make_charset_clause($encoding = null, $language = null) {
        if (empty($encoding)) {
            $encoding = api_get_system_encoding();
        }
        if (empty($language)) {
            $language = api_get_interface_language();
        }
        $charset_clause = '';
        if (self::is_encoding_supported($encoding)) {
            $db_encoding = Database::to_db_encoding($encoding);
            $charset_clause .= " DEFAULT CHARACTER SET `".$db_encoding."`";
            $db_collation = Database::to_db_collation($encoding, $language);
            if (!empty($db_collation)) {
                $charset_clause .= " DEFAULT COLLATE `".$db_collation."`";
            }
        }
        return $charset_clause;
    }

    /**
     * Converts an encoding identificator to MySQL-specific encoding identifictor,
     * i.e. 'UTF-8' --> 'utf8'.
     * @param string $encoding	The conventional encoding identificator.
     * @return string			Returns the corresponding MySQL-specific encoding identificator if any, otherwise returns NULL.
     * @author Ivan Tcholakov
     */
    public static function to_db_encoding($encoding) {
        static $result = array();
        if (!isset($result[$encoding])) {
            $result[$encoding] = null;
            $encoding_map = & self::get_db_encoding_map();
            foreach ($encoding_map as $key => $value) {
                if (api_equal_encodings($encoding, $key)) {
                    $result[$encoding] = $value;
                    break;
                }
            }
        }
        return $result[$encoding];
    }

    /**
     * Converts a MySQL-specific encoding identifictor to conventional encoding identificator,
     * i.e. 'utf8' --> 'UTF-8'.
     * @param string $encoding	The MySQL-specific encoding identificator.
     * @return string			Returns the corresponding conventional encoding identificator if any, otherwise returns NULL.
     * @author Ivan Tcholakov
     */
    public static function from_db_encoding($db_encoding) {
        static $result = array();
        if (!isset($result[$db_encoding])) {
            $result[$db_encoding] = null;
            $encoding_map = & self::get_db_encoding_map();
            foreach ($encoding_map as $key => $value) {
                if (strtolower($db_encoding) == $value) {
                    $result[$db_encoding] = $key;
                    break;
                }
            }
        }
        return $result[$db_encoding];
    }

    /**
     * Chooses the default MySQL-specific collation from given encoding and language.
     * @param string $encoding				A conventional encoding id, i.e. 'UTF-8'
     * @param string $language (optional)	A conventional for the system language id, i.e. 'bulgarian'. If it is empty, the chosen collation is the default server value corresponding to the given encoding.
     * @return string						Returns a suitable default collation, for example 'utf8_general_ci', or NULL if collation was not found.
     * @author Ivan Tcholakov
     */
    public static function to_db_collation($encoding, $language = null) {
        static $result = array();
        if (!isset($result[$encoding][$language])) {
            $result[$encoding][$language] = null;
            if (self::is_encoding_supported($encoding)) {
                $db_encoding = self::to_db_encoding($encoding);
                if (!empty($language)) {
                    $lang = api_purify_language_id($language);
                    $res = self::check_db_collation($db_encoding, $lang);
                    if (empty($res)) {
                        $db_collation_map = & self::get_db_collation_map();
                        if (isset($db_collation_map[$lang])) {
                            $res = self::check_db_collation($db_encoding, $db_collation_map[$lang]);
                        }
                    }
                    if (empty($res)) {
                        $res = self::check_db_collation($db_encoding, null);
                    }
                    $result[$encoding][$language] = $res;
                } else {
                    $result[$encoding][$language] = self::check_db_collation($db_encoding, null);
                }
            }
        }
        return $result[$encoding][$language];
    }

    /**
     * This private method encapsulates a table with relations between
     * conventional and MuSQL-specific encoding identificators.
     * @author Ivan Tcholakov
     */
    private static function & get_db_encoding_map() {
        static $encoding_map = array(
            'ARMSCII-8'    => 'armscii8',
            'BIG5'         => 'big5',
            'BINARY'       => 'binary',
            'CP866'        => 'cp866',
            'EUC-JP'       => 'ujis',
            'EUC-KR'       => 'euckr',
            'GB2312'       => 'gb2312',
            'GBK'          => 'gbk',
            'ISO-8859-1'   => 'latin1',
            'ISO-8859-2'   => 'latin2',
            'ISO-8859-7'   => 'greek',
            'ISO-8859-8'   => 'hebrew',
            'ISO-8859-9'   => 'latin5',
            'ISO-8859-13'  => 'latin7',
            'ISO-8859-15'  => 'latin1',
            'KOI8-R'       => 'koi8r',
            'KOI8-U'       => 'koi8u',
            'SHIFT-JIS'    => 'sjis',
            'TIS-620'      => 'tis620',
            'US-ASCII'     => 'ascii',
            'UTF-8'        => 'utf8',
            'WINDOWS-1250' => 'cp1250',
            'WINDOWS-1251' => 'cp1251',
            'WINDOWS-1252' => 'latin1',
            'WINDOWS-1256' => 'cp1256',
            'WINDOWS-1257' => 'cp1257'
        );
        return $encoding_map;
    }

    /**
     * A helper language id translation table for choosing some collations.
     * @author Ivan Tcholakov
     */
    private static function & get_db_collation_map() {
        static $db_collation_map = array(
            'german' => 'german2',
            'simpl_chinese' => 'chinese',
            'trad_chinese' => 'chinese',
            'turkce' => 'turkish'
        );
        return $db_collation_map;
    }

    /**
     * Constructs a MySQL-specific collation and checks whether it is supported by the database server.
     * @param string $db_encoding	A MySQL-specific encoding id, i.e. 'utf8'
     * @param string $language		A MySQL-compatible language id, i.e. 'bulgarian'
     * @return string				Returns a suitable default collation, for example 'utf8_general_ci', or NULL if collation was not found.
     * @author Ivan Tcholakov
     */
    private static function check_db_collation($db_encoding, $language) {
        if (empty($db_encoding)) {
            return null;
        }
        if (empty($language)) {
            $result = self::fetch_array(self::query("SHOW COLLATION WHERE Charset = '".self::escape_string($db_encoding)."' AND  `Default` = 'Yes';"), 'NUM');
            return $result ? $result[0] : null;
        }
        $collation = $db_encoding.'_'.$language.'_ci';
        $query_result = self::query("SHOW COLLATION WHERE Charset = '".self::escape_string($db_encoding)."';");
        while ($result = self::fetch_array($query_result, 'NUM')) {
            if ($result[0] == $collation) {
                return $collation;
            }
        }
        return null;
    }
    #endregion

    #region get connection metadata
    /**
     * Returns information about the type of the current connection and the server host name.
     * @param resource $connection (optional)	The database server connection, for detailed description see the method query().
     * @return string/boolean					Returns string data on success or FALSE on failure.
     */
    public function get_host_info($connection = null) {
        return self::use_default_connection($connection) ? mysqli::mysqli_get_host_info() : mysqli::mysqli_get_host_info($connection);
    }

    /**
     * Retrieves database client/server protocol version.
     * @param resource $connection (optional)	The database server connection, for detailed description see the method query().
     * @return int/boolean						Returns the protocol version on success or FALSE on failure.
     */
    public function get_proto_info($connection = null) {
        return self::use_default_connection($connection) ? mysqli::mysqli_get_proto_info() : mysqli::mysqli_get_proto_info($connection);
    }

    /**
     * Returns the database client library version.
     * @return strung		Returns a string that represents the client library version.
     */
    public function get_client_info() {
        return mysqli_get_client_info();
    }

    /**
     * Retrieves the database server version.
     * @param resource $connection (optional)	The database server connection, for detailed description see the method query().
     * @return string/boolean					Returns the MySQL server version on success or FALSE on failure.
     */
    public function get_server_info($connection = null) {
        return self::use_default_connection($connection) ? mysqli::mysqli_get_server_info() : mysqli::mysqli_get_server_info($connection);
    }

    #endregion

    #region get queries metadata
    /**
     * Gets the ID of the last item inserted into the database
     * @param resource $connection (optional)	The database server connection, for detailed description see the method query().
     * @return int								The last ID as returned by the DB function
     * @comment This should be updated to use ADODB at some point
     */
    public static function insert_id($connection = null) {
        global $database_connection;
        return $database_connection->insert_id;
    }

    /**
     * @deprecated Use Database::insert_id() instead.
     */
    public static function get_last_insert_id() {
        global $database_connection;
        return $database_connection->insert_id($database_connection);
    }
    /**
     * Gets the number of rows from the last query result - help achieving database independence
     * @param resource		The result
     * @return integer		The number of rows contained in this result
     * @author Yannick Warnier <yannick.warnier@dokeos.com>
     **/
    public static function num_rows($result) {
        return is_a($result,'mysqli_result') ? $result->num_rows : false;
    }

    /**
     * Returns the number of affected rows in the last database operation.
     * @param resource $connection (optional)    The database server connection, for detailed description see the method query().
     * @return int                                Returns the number of affected rows on success, and -1 if the last query failed.
     */
    public static function affected_rows($connection = null) {
        if (null !== $connection) {
            return $connection->affected_rows;
        } else {
            global $database_connection;
            return $database_connection->affected_rows;
        }
    }

    /**
     * Returns the error number from the last operation done on the database server.
     * @param resource $connection (optional)    The database server connection, for detailed description see the method query().
     * @return int                                Returns the error number from the last database (operation, or 0 (zero) if no error occurred.
     */
    public static function errorNumber($connection = null) {
        return self::use_default_connection($connection) ? mysqli::mysqli_errno() : mysqli::mysqli_errno($connection);
    }

    /**
     * Returns the error text from the last operation done on the database server.
     * @param resource $connection (optional)    The database server connection, for detailed description see the method query().
     * @return string                            Returns the error text from the last database operation, or '' (empty string) if no error occurred.
     */
    public static function errorText($connection = null) {
        return self::use_default_connection($connection) ? mysqli::mysqli_error() : mysqli::mysqli_error($connection);
    }
    #endregion

    #region get database structure metadata
    /**
     * Returns a list of databases created on the server. The list may contain all of the
     * available database names or filtered database names by using a pattern.
     * @param string $pattern (optional)		A pattern for filtering database names as if it was needed for the SQL's LIKE clause, for example 'chamilo_%'.
     * @param resource $connection (optional)	The database server connection, for detailed description see the method query().
     * @return array							Returns in an array the retrieved list of database names.
     */
    public static function get_databases($pattern = '', $connection = null) {
        $result = array();
        $query_result = Database::query(!empty($pattern) ? "SHOW DATABASES LIKE '".self::escape_string($pattern, $connection)."'" : "SHOW DATABASES", $connection);
        while ($row = Database::fetch_row($query_result)) {
            $result[] = $row[0];
        }
        return $result;
    }

    /**
     * Returns a list of tables within a database. The list may contain all of the
     * available table names or filtered table names by using a pattern.
     * @param string $database (optional)		The name of the examined database. If it is omited, the current database is assumed, see Database::select_db().
     * @param string $pattern (optional)		A pattern for filtering table names as if it was needed for the SQL's LIKE clause, for example 'access_%'.
     * @param resource $connection (optional)	The database server connection, for detailed description see the method query().
     * @return array							Returns in an array the retrieved list of table names.
     */
    public static function get_tables($database = '', $pattern = '', $connection = null) {
        $result = array();
        $query = "SHOW TABLES";
        if (!empty($database)) {
            $query .= " FROM `".self::escape_string($database, $connection)."`";
        }
        if (!empty($pattern)) {
            $query .= " LIKE '".self::escape_string($pattern, $connection)."'";
        }
        $query_result = Database::query($query, $connection);
        while ($row = Database::fetch_row($query_result)) {
            $result[] = $row[0];
        }
        return $result;
    }

    /**
     * Returns a list of the fields that a given table contains. The list may contain all of the available field names or filtered field names by using a pattern.
     * By using a special option, this method is able to return an indexed list of fields' properties, where field names are keys.
     * @param string $table						This is the examined table.
     * @param string $pattern (optional)		A pattern for filtering field names as if it was needed for the SQL's LIKE clause, for example 'column_%'.
     * @param string $database (optional)		The name of the targeted database. If it is omited, the current database is assumed, see Database::select_db().
     * @param bool $including_properties (optional)	When this option is true, the returned result has the followong format:
     * 												array(field_name_1 => array(0 => property_1, 1 => property_2, ...), fieald_name_2 => array(0 => property_1, ...), ...)
     * @param resource $connection (optional)	The database server connection, for detailed description see the method query().
     * @return array							Returns in an array the retrieved list of field names.
     */
    public static function get_fields($table, $pattern = '', $database = '', $including_properties = false, $connection = null) {
        $result = array();
        $query = "SHOW COLUMNS FROM `".self::escape_string($table, $connection)."`";
        if (!empty($database)) {
            $query .= " FROM `".self::escape_string($database, $connection)."`";
        }
        if (!empty($pattern)) {
            $query .= " LIKE '".self::escape_string($pattern, $connection)."'";
        }
        $query_result = Database::query($query, $connection);
        if ($including_properties) {
            // Making an indexed list of the fields and their properties.
            while ($row = Database::fetch_row($query_result)) {
                $result[$row[0]] = $row;
            }
        } else {
            // Making a plain, flat list.
            while ($row = Database::fetch_row($query_result)) {
                $result[] = $row[0];
            }
        }
        return $result;
    }

    /**
     * Counts the number of rows in a table
     * @param string $table The table of which the rows should be counted
     * @return int The number of rows in the given table.
     */
    public static function count_rows($table) {
        $obj = self::fetch_object(self::query("SELECT COUNT(*) AS n FROM $table"));
        return $obj->n;
    }

    #endregion
    #
    #region utils and security
    /**
     * Escapes a string to insert into the database as text
     * @param string                            The string to escape
     * @param resource $connection (optional)    The database server connection, for detailed description see the method query().
     * @return string                            The escaped string
     * @author Yannick Warnier <yannick.warnier@dokeos.com>
     * @author Patrick Cool <patrick.cool@UGent.be>, Ghent University
     */
    public static function escape_string($string, $connection = null) {
        global $database_connection;
        return get_magic_quotes_gpc()
            ? ($database_connection->escape_string(stripslashes($string)))
            : ($database_connection->escape_string($string));
    }





    #endregion
}
