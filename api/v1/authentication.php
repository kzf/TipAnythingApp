<?php 
require_once 'Database.php';
require_once 'server_utils.php';

/*
 * Session Management
 */
$app->get('/session', function() {
  global $sess;
  $session = $sess->getSession();
  $response["userid"] = $session['userid'];
  $response["email"] = $session['email'];
  $response["username"] = $session['username'];
  echoResponse(200, $session);
});

$app->get('/logout', function() {
  global $sess;
  $session = $sess->destroySession();
  $response["status"] = "Info";
  $response["message"] = "Logged out successfully";
  echoResponse(200, $response);
});

/*
 * Login to user account
 */
$app->post('/login', function() use ($app) {
  global $db;
  $response = array();

  // Get the API request
  $r = json_decode($app->request->getBody());
  $password = $r->customer->password;
  $username = $r->customer->username;

  $user = $db->select("SELECT * FROM users_auth WHERE username = :username",
                              array(':username'=>$username));

  if ($user["status"] === "Success") {
    // We found a user with the specified username
    // Give the user data a shorter name
    $user = $user["data"][0];

    // Check password hashes match
    if (passwordHash::check_password($user["password"],$password)) {
      $response['status'] = "Success";
      $response['message'] = "You have been logged in.";
      $response['username'] = $user['username'];
      $response['userid'] = $user['id'];
      $response['email'] = $user['email'];
      $response['createdAt'] = $user['created'];

      if (!isset($_SESSION)) {
        session_start();
      }

      $_SESSION['userid'] = $user['id'];
      $_SESSION['email'] = $user['email'];
      $_SESSION['username'] = $user['username'];

    } else {
      $response['status'] = "Error";
      $response['message'] = "Incorrect password.";
    }
  } else {
    // Did not find a user
    $response['status'] = "Error";
    $response['message'] = "No user with that username.";
  }
  echoResponse(200, $response);
});


$app->post('/signup', function() use ($app) {
  global $db;
  $response = array();

  $r = json_decode($app->request->getBody());
  $username = $r->customer->username;
  $email = $r->customer->email;
  $password = $r->customer->password;

  $userRecord = $db->select("SELECT * FROM users_auth WHERE username=':username' or email=':email' LIMIT 1",
                              array(':username'=>$username, ':email'=>$email));
  
  if ($userRecord["status"] === "Warning") {
    $r->customer->password = passwordHash::hash($password);

    $result = $db->insert("INSERT INTO users_auth(username,email,password)"
                            ."VALUES(:username,:email,:password)",
                            array(':username'=>$username, ':email'=>$email, ':password'=>$r->customer->password),
                            array('username', 'password', 'email'));

    if ($result["status"] === "Success") {
      $response["status"] = "Success";
      $response["message"] = "Your account has been created";
      $response["userid"] = $result["data"];

      if (!isset($_SESSION)) {
        session_start();
      }

      $_SESSION['userid'] = $response["userid"];
      $_SESSION['username'] = $username;
      $_SESSION['email'] = $email;

      echoResponse(200, $response);
    } else {
      $response["status"] = "Error";
      $response["message"] = "Failed to create account";
      echoResponse(201, $response);
    }
  } else {
    $response["status"] = "Error";
    $response["message"] = "There is already a user with that username or email address";
    echoResponse(201, $response);
  }
});


?>