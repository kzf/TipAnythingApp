<?php 
require_once 'Database.php';
require_once 'server_utils.php';


$app->get('/session', function() {
  global $sess;
  $session = Session::getSession();

  $data["userid"] = $session['userid'];
  $data["email"] = $session['email'];
  $data["username"] = $session['username'];

  echoResponse(200, $response = responseArray("Success", "Session details retrieved", $data));
});

$app->get('/logout', function() {
  global $sess;
  $session = Session::destroySession();
  echoResponse(200, responseArray("Success", "Logged out successfully"));
});

/*
 * Login to user account
 */
$app->post('/login', function() use ($app) {
  global $db, $sess;
  $response = array();

  // Get the request data
  $data = dataArrayFromResponse($app->request->getBody());

  $user = $db->select("SELECT * FROM users_auth WHERE username = :username",
                              array(':username'=>$data["username"]));

  if ($user["status"] === "Success") {
    $user = $user["data"][0];

    // Check password hashes match
    if (passwordHash::check_password($user["password"], $data["password"])) {
      $response = responseArray("Success", "Logged in successfully");

      Session::setSession($user);
    } else {
      $response = responseArray("Error", "Password incorrect");
    }
  } else {
    $response = responseArray("Error", "No user with that username");
  }

  echoResponse(200, $response);
});

/*
 * Sign up a new user account
 */
$app->post('/signup', function() use ($app) {
  global $db;
  $response = array();

  $data = dataArrayFromResponse($app->request->getBody());

  // Get users with the same username or email address to make sure they don't
  // already have an account
  $userRecord = $db->select("SELECT * FROM users_auth WHERE username=':username' or email=':email' LIMIT 1",
                              array(':username'=>$data["username"], ':email'=>$data["email"]));
  
  if ($userRecord["status"] === "Warning") {
    $data["password"] = passwordHash::hash($data["password"]);

    // Insert the
    $result = $db->insert("
      INSERT INTO users_auth(username,email,password)
      VALUES(:username,:email,:password)
    ",
                array(':username'=>$data["username"], ':email'=>$data["email"], ':password'=>$data["password"]),
                array('username', 'password', 'email'));

    if ($result["status"] === "Success") {
      $response = responseArray("Success", "User account created");

      Session::setSession(array("id"=>$result["data"], "email"=>$data["email"], "username"=>$data["username"]));

    } else {
      $response = responseArray("Error", "Failed to create user");
    }
  } else {
    $response = responseArray("Error", "There is already a user with that email address or username");
  }

  echoResponse(200, $response);
});


?>