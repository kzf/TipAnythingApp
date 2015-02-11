<?php

class Session {

  public static function setSession($user){
    global $db;

    if (!isset($_SESSION)) {
      session_start();
    }

    $_SESSION['userid'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['username'] = $user['username'];

    // Get the list of sheets that this user can modify
    $rows = $db->select("
      SELECT owners.sheet_id FROM users_auth 
      INNER JOIN owners ON owners.user_id = users_auth.id AND users_auth.id = :id
    ", array(':id'=>$user['id']));

    $owned_sheets = array();
    foreach ($rows["data"] as $row) {
      array_push($owned_sheets, $row["sheet_id"]);
    }

    $_SESSION['owned_sheets'] = $owned_sheets;
    
  }

  public static function addOwnedSheet($sheet_id) {
    if (!isset($_SESSION)) {
      session_start();
    }
    array_push($_SESSION['owned_sheets'], $sheet_id);
  }

	public static function getSession(){
    if (!isset($_SESSION)) {
      session_start();
    }

    $sess = array();

    if (isset($_SESSION['userid'])) {
      $sess["userid"] = $_SESSION['userid'];
      $sess["username"] = $_SESSION['username'];
      $sess["email"] = $_SESSION['email'];
    } else {
      $sess["userid"] = '';
      $sess["username"] = 'Guest';
      $sess["email"] = '';
    }

    return $sess;
	}

  public static function userOwnsSheet($sheet_id) {
    if (!isset($_SESSION)) {
      session_start();
    }
    foreach ($_SESSION['owned_sheets'] as $sid) {
      if ($sheet_id == $sid) {
        return true;
      }
    }
    return false;
  }

	public static function destroySession(){
    if (!isset($_SESSION)) {
    	session_start();
    }

    if(isSet($_SESSION['userid'])) {
      unset($_SESSION['userid']);
      unset($_SESSION['username']);
      unset($_SESSION['email']);

      $info = 'info';
      if (isSet($_COOKIE[$info])) {
        setcookie ($info, '', time() - 300);
      }
      $msg = "Logged Out Successfully...";
    } else {
      $msg = "Not logged in...";
    }

    return $msg;
	}
	
}

?>