<?php 
/**
 * Abstract dump file: provides common interface for writing
 * data to dump files. 
 */
abstract class Shuttle_Dump_File {

  static function create($filename) {

    if($filename === NULL){
      return new Shuttle_Dump_File_Browser();
		}
		return new Shuttle_Dump_File_Plaintext($filename);
	}

}

/**
 * Plain text implementation. Uses standard file functions in PHP. 
 */
class Shuttle_Dump_File_Plaintext extends Shuttle_Dump_File {
	/**
   * Location of the dump file on the disk
   */
  protected $file_location;

  function __construct($file_location) {
    $this->file_location = $file_location;
  }

  function write($string) {
		//return fwrite($this->fh, $string);
    return file_put_contents($this->file_location, $string, FILE_APPEND);
	}

}

/**
 * Plain text implementation. Uses standard file functions in PHP. 
 */
class Shuttle_Dump_File_Browser extends Shuttle_Dump_File {

  function write($string) {
    return $string;
  }

}

/**
 * MySQL insert statement builder. 
 */
class Shuttle_Insert_Statement {
	private $rows = '';
	private $table;
	private $length = 0;

        function __construct($table) {
		$this->table = $table;
	}

	function reset() {
		$this->rows = '';
	}

	function add_row($row) {
		$row = '(' . implode(",", $row) . ')';
    if($this->rows){
      $this->rows .= ",\n".$row;
    }else{
      $this->rows = $row;
    }
		$this->length += strlen($row);
	}

	function get_sql() {
		if (empty($this->rows)) {
			return false;
		}
    $this->rows = "INSERT INTO `{$this->table}` VALUES {$this->rows}; "; // more memory efficient than concat with "." (dot) operator 
    return $this->rows;
  }

	function get_length() {
		return strlen($this->rows);
	}
}

/**
 * Main facade
 */
abstract class Shuttle_Dumper {
	/**
	 * Maximum length of single insert statement
	 */
	const INSERT_THRESHOLD = 838860;
	
	
	/**
	 * To avoid MYSQL 'max_allowed_packet' bytes error
	 * 
	 */
	const INSERT_EXCEED_MAX_ALLOWED_PACKET = 16777216*8;
	
	/**
	 * Maximum execution time of one request
	 */
	public $time_limit = 30;

        /**
         * Unix timestamp  when to interuprt dump
         */
        public $time_end;

	/**
	 * @var Shuttle_DBConn
	 */	
	public $db;

	/**
	 * @var Shuttle_Dump_File
	 */
	public $dump_file;

	/**
	 * End of line style used in the dump
	 */
	public $eol = "\r\n";

	/**
	 * Specificed tables to include
	 */
	public $include_tables;

	/**
	 * Specified tables to exclude
	 */
	public $exclude_tables = array();

	/**
	 * Factory method for dumper on current hosts's configuration. 
	 */
    static function create($db_options, $localFilename) {
        $db = Shuttle_DBConn::create($db_options);
        if($db_options['charset']){
            $db->query("SET NAMES '{$db_options['charset']}'");
        }

        $dumper = new Shuttle_Dumper_Native($db);

        $dumper->dump_file = Shuttle_Dump_File::create($localFilename);

        if (isset($db_options['include_tables'])) {
            $dumper->include_tables = $db_options['include_tables'];
	}
        if (isset($db_options['exclude_tables'])) {
                $dumper->exclude_tables = $db_options['exclude_tables'];
        }
        if (isset($db_options['time_limit'])) {
                $dumper->time_limit = $db_options['time_limit'];
        }

        $dumper->time_end = time() + $dumper->time_limit;

        return $dumper;
    }

	function __construct(Shuttle_DBConn $db) {
		$this->db = $db;
	}

	public static function has_shell_access() {
		if (!is_callable('shell_exec')) {
			return false;
		}
		$disabled_functions = ini_get('disable_functions');
		return stripos($disabled_functions, 'shell_exec') === false;
	}

	public static function is_shell_command_available($command) {
		if (preg_match('~win~i', PHP_OS)) {
			/*
			On Windows, the `where` command checks for availabilty in PATH. According
			to the manual(`where /?`), there is quiet mode: 
			....
			    /Q       Returns only the exit code, without displaying the list
			             of matched files. (Quiet mode)
			....
			*/
			$output = array();
			exec('where /Q ' . $command, $output, $return_val);

			if (intval($return_val) === 1) {
				return false;
			} else {
				return true;
			}

		} else {
			$last_line = exec('which ' . $command);
			$last_line = trim($last_line);

			// Whenever there is at least one line in the output, 
			// it should be the path to the executable
			if (empty($last_line)) {
				return false;
			} else {
				return true;
			}
		}
		
	}

	public function get_tables($table_prefix) {
		if (!empty($this->include_tables)) {
			return $this->include_tables;
		}
		
		// $tables will only include the tables and not views.
		// TODO - Handle views also, edits to be made in function 'get_create_table_sql' line 336
		//  $tables = $this->db->fetch_numeric('SHOW FULL TABLES WHERE Table_Type = "BASE TABLE" AND Tables_in_'.$this->db->name.' LIKE "' . $this->db->escape_like($table_prefix) . '%"');
    $tables = $this->db->fetch('select table_name, table_rows, data_length from information_schema.tables where table_type = \'BASE TABLE\' and table_schema = \'' . $this->db->name . '\' and table_name LIKE \'' . $this->db->escape_like($table_prefix) . '%\'');

		$tables_list = array();
		foreach ($tables as $table_row) {
			$table_name = $table_row['table_name'];
			if (!in_array($table_name, $this->exclude_tables)) {
				$tables_list[] = $table_row;
			}
		}
		return $tables_list;
	}

  static function getMaxMemoryLimit() {
    // Get the memory limit set in php.ini
    $memory_limit = ini_get('memory_limit');

    // If the memory limit is set to -1, which means no limit, return as is
    if ($memory_limit == '-1') {
      return 128 * 1014 * 1024;
    }

    // Extract the numeric value from the memory limit string
    $memory_limit_value = intval($memory_limit);

    // Check if the memory limit string contains a suffix (e.g., 'M' for megabytes)
    if (preg_match('/^(\d+)([KMG])$/', $memory_limit, $matches)) {
      $value = $matches[1];
      $unit = $matches[2];
      switch ($unit) {
        case 'K':
          $memory_limit_value *= 1024;
          break;
        case 'M':
          $memory_limit_value *= 1024 * 1024;
          break;
        case 'G':
          $memory_limit_value *= 1024 * 1024 * 1024;
          break;
      }
    }
    return $memory_limit_value;
  }
}

class Shuttle_Dumper_Native extends Shuttle_Dumper {

    public function dumpBeforeFirstData() {
        $eol = $this->eol;
        $sql = '';

        $sql .= ("-- Generation time: " . date('r') . $eol);
        $sql .= ("-- Host: " . $this->db->host . $eol);
        $sql .= ("-- DB name: " . $this->db->name . $eol);
        $sql .= ("/*!40030 SET NAMES UTF8 */;$eol");

        $sql .= ("/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;$eol");
        $sql .= ("/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;$eol");
        $sql .= ("/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;$eol");
        $sql .= ("/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;$eol");
        $sql .= ("/*!40103 SET TIME_ZONE='+00:00' */;$eol");
        $sql .= ("/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;$eol");
        $sql .= ("/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;$eol");
        $sql .= ("/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;$eol");
        $sql .= ("/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;$eol$eol");

        return $sql;
    }
    public function dumpAfterLastData() {
	$eol = $this->eol;
    
        $sql = '';
        $sql .= ("$eol$eol");
        $sql .= ("/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;$eol");
        $sql .= ("/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;$eol");
        $sql .= ("/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;$eol");
        $sql .= ("/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;$eol");
        $sql .= ("/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;$eol");
        $sql .= ("/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;$eol");
        $sql .= ("/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;$eol$eol");

        return $sql;
    }

    public function dump_table_metadata($table, $dropStatement = true) {
        $eol = $this->eol;
        $sql = '';

        if ($dropStatement) {
          $sql .= ("DROP TABLE IF EXISTS `$table`;$eol");
        }
        $create_table_sql = $this->get_create_table_sql($table);
        $sql .= ($create_table_sql . $eol . $eol);

        return $sql;
    }

    public function dump_table(
                        $table
                        ,$offset
                        ,$maxRows = null
                        ,$exportStructure = true
                        ,$exportDropStatement = true
                        ,$exportData = true) {
        $eol = $this->eol;
        $sql = '';

        if($offset == 0 && $exportStructure){
            $sql .= $this->dump_table_metadata($table, $exportDropStatement);
        }
        $data = $this->db->query("SELECT * FROM `$table` LIMIT $offset, " . ($maxRows == null ? '18446744073709551615' : (int)$maxRows));    

        $insert = new Shuttle_Insert_Statement($table);

        $row_count = 0;
        $php_memory_limit = $this->getMaxMemoryLimit();
        while ($exportData && $row = $this->db->fetch_row($data)) {
            $row_count++;
            $row_length = 0;
            foreach ($row as $i => $value) {
                $row[$i] = $this->db->escape($value);
                $row_length += strlen($row[$i]);
            }
            $insert->add_row($row);
            unset($row);

            //do not add last row, if $sql, got too big - keep it to next chunk
            if (memory_get_usage() + $row_length > $php_memory_limit && $row_count > 1) {
                return array('offset' => $offset + $row_count - 1, 'sql' => $this->dump_file->write($sql));
            } elseif (time() > $this->time_end || memory_get_usage() > $php_memory_limit/3 || $row_count >= ($maxRows == null ? PHP_INT_MAX : $maxRows)) {
                $sql .= ($insert->get_sql() . $eol);
                $insert->reset();
                return array('offset' => $offset + $row_count, 'sql' => $this->dump_file->write($sql));
            } elseif ($insert->get_length() > self::INSERT_THRESHOLD) {
                // The insert got too big: write the SQL and create
                // new insert statement	
                if($insert->get_length() < self::INSERT_EXCEED_MAX_ALLOWED_PACKET){
                    $sql .= $insert->get_sql() . $eol;
                    $insert->reset();	
                }else{
                    $insert->reset();	
                }
            }
        }
        $sql .= ($insert->get_sql() . $eol);
        $sql .= ($eol . $eol);

        return array('offset' => 0, 'sql' => $this->dump_file->write($sql));
    }

        public function get_create_table_sql($table) {
            $create_table_sql = $this->db->fetch('SHOW CREATE TABLE `' . $table . '`');
            return $create_table_sql[0]['Create Table'] . ';';
        }
    }

class Shuttle_DBConn {
	public $host;
	public $username;
	public $password;
	public $name;

	protected $connection;
        
        /*
         * @return Shuttle_DBConn_Mysqli
         */
        function getConnection(){
            return $this->connection;
        }
        
	function __construct($options) {
		$this->host = $options['host'];
		if (empty($this->host)) {
			$this->host = '127.0.0.1';
		}
		$this->username = $options['username'];
		$this->password = $options['password'];
		$this->name = $options['database'];
	}

	static function create($options) {
		if (class_exists('mysqli')) {
			$class_name = "Shuttle_DBConn_Mysqli";
		} else {
			$class_name = "Shuttle_DBConn_Mysql";
		}

		return new $class_name($options);
	}

  static function is_binary($data) {
    // Check for null bytes
    if (strpos($data, "\x00") !== false) {
      return true;
    }
    // Attempt to decode as UTF-8
    if (mb_check_encoding($data, 'UTF-8')) {
      return false;
    } else {
      return true;
    }
  }
}

class Shuttle_DBConn_Mysql extends Shuttle_DBConn {
	function connect() {
		$this->connection = @mysql_connect($this->host, $this->username, $this->password);
		if (!$this->connection) {
			throw new Shuttle_Exception("Couldn't connect to the database: " . mysql_error());
		}

		$select_db_res = mysql_select_db($this->name, $this->connection);
		if (!$select_db_res) {
			throw new Shuttle_Exception("Couldn't select database: " . mysql_error($this->connection));
		}

		return true;
	}

	function query($q) {
		if (!$this->connection) {
			$this->connect();
		}
		$res = mysql_query($q);
		if (!$res) {
			throw new Shuttle_Exception("SQL error: " . mysql_error($this->connection));
		}
		return $res;
	}

	function fetch_numeric($query) {
		return $this->fetch($query, MYSQL_NUM);
	}

	function fetch($query, $result_type=MYSQL_ASSOC) {
		$result = $this->query($query, $this->connection);
		$return = array();
		while ( $row = mysql_fetch_array($result, $result_type) ) {
			$return[] = $row;
		}
		return $return;
	}

	function escape($value) {
    if (is_null($value)) {
      return "NULL";
    } elseif ($this->is_binary($value)) {
      return "X'" . bin2hex($value) . "'";
    } else {
		return "'" . mysql_real_escape_string($value) . "'";
    }
  }

  function escape_like($search) {
		return str_replace(array('_', '%'), array('\_', '\%'), $search);
	}

	function get_var($sql) {
		$result = $this->query($sql);
		$row = mysql_fetch_array($result);
		return $row[0];
	}

	function fetch_row($data) {
		return mysql_fetch_assoc($data);
	}
}


class Shuttle_DBConn_Mysqli extends Shuttle_DBConn {
	function connect() {
		$this->connection = @new MySQLi($this->host, $this->username, $this->password, $this->name);
                if ($this->connection->connect_error) {
			throw new Shuttle_Exception("Couldn't connect to the database: " . $this->connection->connect_error);
		}

		return true;
	}

	function query($q) {
		if (!$this->connection) {
			$this->connect();
		}
                $res = $this->connection->query($q, MYSQLI_USE_RESULT);
		
		if (!$res) {
			throw new Shuttle_Exception("SQL error: " . $this->connection->error);
		}
		
		return $res;
	}

	function fetch_numeric($query) {
		return $this->fetch($query, MYSQLI_NUM);
	}

	function fetch($query, $result_type=MYSQLI_ASSOC) {
		$result = $this->query($query, $this->connection);
		$return = array();
		while ( $row = $result->fetch_array($result_type) ) {
			$return[] = $row;
		}
		return $return;
	}

	function escape($value) {
		if (is_null($value)) {
			return "NULL";
    }elseif($this->is_binary($value)){
      return "X'" . bin2hex($value) . "'";
    }else{
  		return "'" . $this->connection->real_escape_string($value) . "'";
    }
	}

	function escape_like($search) {
		return str_replace(array('_', '%'), array('\_', '\%'), $search);
	}

	function get_var($sql) {
		$result = $this->query($sql);
		$row = $result->fetch_array($result, MYSQLI_NUM);
		return $row[0];
	}

	function fetch_row($data) {
		return $data->fetch_array(MYSQLI_ASSOC);
	}
}

class Shuttle_Exception extends Exception {};
