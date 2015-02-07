<?php
// Import database settings
require_once 'config.php';

class Database {
	private $db;
	private $err;

	static function columnValuesPrefix($where) {
		$prefixed = array();
		foreach ($where as $key => $value) {
			$prefixed[":".$key] = $value;
		}
		return $prefixed;
	}

	static function columnValuesSetterString($where) {
		$setter = "";
		foreach ($where as $key => $value) {
			$setter .= $key . " = :" . $key . ", ";
		}
        $setter = rtrim($setter, ", ");
		return $setter;
	}

  static function getColumnNames($data) {
    $names = "";
    $prefixed = "";
    foreach ($data as $key => $value) {
      $names .= $key . ", ";
      $prefixed .= ":" . $key . ", ";
    }
    $names = rtrim($names, ", ");
    $prefixed = rtrim($prefixed, ", ");
    return array($names, $prefixed);
  }

	function __construct() {
		$dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8';
    try {
      $this->db = new PDO($dsn, DB_USERNAME, DB_PASSWORD, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    } catch (PDOException $e) {
      $response["status"] = "Error";
      $response["message"] = 'Connection failed: ' . $e->getMessage();
      $response["data"] = null;
      //echoResponse(200, $response);
      exit;
    }
	}

  function beginTransaction() {
    $this->db->beginTransaction();
  }

  function commit() {
    $this->db->commit();
  }

  function rollback() {
    $this->db->rollback();
  }

	function select($statement, $where){
    try{
      $stmt = $this->db->prepare($statement);
      $stmt->execute($where);

      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      if(count($rows)<=0){
        $response["status"] = "Warning";
        $response["message"] = "No data found.";
      }else{
        $response["status"] = "Success";
        $response["message"] = "Data selected from database";
      }
      $response["data"] = $rows;
    }catch(PDOException $e){
      $response["status"] = "Error";
      $response["message"] = 'Select Failed: ' .$e->getMessage();
      $response["data"] = null;
    }
    return $response;
  }

  function insert($statement, $where, $requiredColumnsArray){
  	$this->verifyRequiredParameters($where, $requiredColumnsArray);

    try{
      $stmt = $this->db->prepare($statement);
      $stmt->execute($where);

      $affected_rows = $stmt->rowCount();
      $lastInsertId = $this->db->lastInsertId();
      $response["status"] = "Success";
      $response["message"] = $affected_rows." row inserted into database";
      $response["data"] = $lastInsertId;
    }catch(PDOException $e){
      $response["status"] = "Error";
      $response["message"] = 'Insert Failed: ' .$e->getMessage();
      $response["data"] = null;
    }
    return $response;
  }

  function update($statement, $where, $requiredColumnsArray){
  	$this->verifyRequiredParameters($where, $requiredColumnsArray);

    try{
      $stmt = $this->db->prepare($statement);
      $stmt->execute($where);

      $affected_rows = $stmt->rowCount();
      $lastInsertId = $this->db->lastInsertId();
      $response["status"] = "Success";
      $response["message"] = $affected_rows." row(s) updated in database";
      $response["data"] = $lastInsertId;
    }catch(PDOException $e){
      $response["status"] = "Error";
      $response["message"] = 'Update Failed: ' .$e->getMessage();
      $response["data"] = null;
    }
    return $response;
  }

  function delete($statement, $where){	
    try{
      $stmt = $this->db->prepare($statement);
      $stmt->execute($where);

      $affected_rows = $stmt->rowCount();
      if($affected_rows<=0){
        $response["status"] = "Warning";
        $response["message"] = "No rows deleted";
      }else{
        $response["status"] = "Success";
        $response["message"] = $affected_rows." row(s) deleted from database";
      }
    }catch(PDOException $e){
      $response["status"] = "Error";
      $response["message"] = 'Delete Failed: ' .$e->getMessage();
      $response["data"] = null;
    }
    return $response;
  }

  static function verifyRequiredParameters($params, $requiredColumns) {
    $error = false;
    $errorColumns = "";
    foreach ($requiredColumns as $field) {
        $name = ":".$field;
        if (!isset($params[$name]) || strlen(trim($params[$name])) <= 0) {
            $error = true;
            $errorColumns .= $field . ', ';
        }
    }


    if ($error) {
      $response = array();
      $response["status"] = "Error";
      $response["message"] = 'Required field(s) ' . rtrim($errorColumns, ', ') . ' is missing or empty';
      echoResponse(200, $response);
      exit;
    }
  }




}
