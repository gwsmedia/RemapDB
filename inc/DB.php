<?php

class DB {
    private $db;
    private $errorHandler;

    function __construct($driver, $host, $dbName, $dbUser, $dbPass) {
        require_once(dirname(__FILE__).'/ErrorHandler.php');
        require_once(dirname(__FILE__).'/Logger.php');

        $this->errorHandler = new ErrorHandler();

        try {
            $dsn = "$driver:host=$host;dbname=$dbName";
            $this->db = new PDO($dsn, $dbUser, $dbPass);
        } catch (PDOException $e) {
            die($e->getMessage());
            $this->errorHandler->throw_error(ERROR_INCORRECT_DB_DETAILS);
        }
    }


    function query($query, $vars = array()) {
	    Logger::log_to_file($query, SQL_LOG_LOCATION);
        $statement = $this->db->prepare($query);
        if($statement->execute($vars)) {
          if(strpos(trim($query), 'INSERT') === 0) {
            return $this->db->lastInsertId();
          } else {
            return $statement->fetchAll();
          }
        } else {
            if(isset($statement->errorInfo()[2])) {
                $errorText = $statement->errorInfo()[2];
                echo "<div class='db-error error message'>MySQL error: $errorText</div><br>";
                Logger::log_to_file($errorText, ERROR_LOG_LOCATION);
            }
            return false;
        }
    }


    function insert($table, $insertData) {
        $columns = "(";
        $placeholders = "(";
        $values = array();
        foreach($insertData as $col => $val) {
            $columns .= "$col, ";
            $placeholders .= "?, ";
            $values[] = $val;
        }
        $columns = substr($columns, 0, -2) . ")";
        $placeholders = substr($placeholders, 0, -2) . ")";

        return $this->query("INSERT INTO $table $columns VALUES $placeholders", $values);
    }


    function update($table, $updateData, $whereCol, $whereVal) {
        $values = array();
        $updateQuery = "UPDATE $table SET ";
        foreach($updateData as $col => $val) {
            $values[] = $val;
            $updateQuery .= "$col = ?, ";
        }
        $values[] = $whereVal;
        $updateQuery = substr($updateQuery, 0, -2) . " WHERE $whereCol = ?";

        return $this->query($updateQuery, $values);
    }


    function version_check($minVersion, $dbVersion = null) {
        if(is_null($dbVersion)) $dbVersion = $this->query('SELECT version();')[0];

        preg_match('/^(\d+).(\d+).(\d+)/', $minVersion, $versionNums);
        preg_match('/^(\d+).(\d+).(\d+)/', $dbVersion, $dbVersionNums);

        foreach($versionNums as $i => $part) {
            if($i === 0) continue;
            else if(intval($part) > $dbVersionNums[$i]) {
                return false;
            } else if(intval($part) < $dbVersionNums[$i]) {
                return true;
            }
        }

        return true;
    }


    // Currently only accepts 'MariaDB' or 'MySQL' as $dbType
    function requirements_check($dbType, $minDbVersion) {
        $dbType = strtolower($dbType);
        $dbVersion = $this->query('SELECT version();')[0][0];

        if(stripos($dbVersion, 'mariadb') === false) {
            // If looking for MariaDB but it's not, or it's Postgres, return false (Postgres versions done differently)
            if($dbType == 'mariadb' || stripos($dbVersion, 'postgres') !== false) return false;
        }

        return $this->version_check($minDbVersion, $dbVersion);
    }


    function column_names($table) {
        $columns = $this->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = ? AND table_name = ?;", array(DB_NAME, $table));
        $columns = array_column($columns, 'COLUMN_NAME');
        return array_combine($columns, $columns);
    }


    function convert_date_string($oldFormat, $newFormat, $string) {
        $dateObj = DateTime::createFromFormat($oldFormat, $string, new DateTimeZone('UTC'));
        if($dateObj === false) $this->errorHandler->throw_error(ERROR_INCORRECT_DATE_FORMAT);

        // Set date time to 00:00:00
        $mysqlDate = $dateObj->format('Y-m-d');
        $dateObj = DateTime::createFromFormat('Y-m-d H:i:s', "$mysqlDate 00:00:00", new DateTimeZone('UTC'));

        return $dateObj->format($newFormat);
    }


    function table_created($tableName = '') {
        $vars = array(DB_NAME);

        if(empty($tableName)) {
            $tableFilter = "";
        } else {
            $tableFilter = "AND table_name = ?";
            array_push($vars, $tableName);
        }

        $tables = $this->query("SELECT * FROM information_schema.tables WHERE table_schema = ? $tableFilter LIMIT 1;", $vars);

        return $tables !== false && count($tables) > 0;
    }


    function get_table_primary_keys($tableName) {
       $keyData = $this->query("SHOW KEYS FROM $tableName WHERE Key_name = 'PRIMARY'");
       if(count($keyData) == 0) return false;
       else return array_column($keyData, 'Column_name');
    }
}
