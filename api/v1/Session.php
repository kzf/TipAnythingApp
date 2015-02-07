<?php

class Session {

	public function getSession(){
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

	public function destroySession(){
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