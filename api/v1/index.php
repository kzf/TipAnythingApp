<?php
require '.././libs/Slim/Slim.php';
require_once 'Database.php';
require_once 'Session.php';
require_once 'server_utils.php';


\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();
$app = \Slim\Slim::getInstance();
$db = new Database();
$sess = new Session();

require_once 'authentication.php';

// Tipping Sheets
$app->get('/sheets/popular', function() {
  global $db;
  $rows = $db->select("SELECT sheets.*, UNIX_TIMESTAMP(sheets.created_on) AS timestamp, "
                      ."count(players.id) AS num_players FROM sheets "
                      ."LEFT OUTER JOIN players ON sheets.id = players.sheet_id "
                      ."GROUP BY sheets.id "
                      ."HAVING sheets.completed = 0 "
                      ."ORDER BY num_players DESC LIMIT 10", array());

  echoResponse(200, $rows);
});

// Search tipping sheets
$app->post('/sheets/search', function() use ($app) {
  global $db;
  $data = json_decode($app->request->getBody());

  $mandatory = array('query');
  Database::verifyRequiredParameters(Database::columnValuesPrefix($data), $mandatory);

  $query = $data->query;

  $rows = $db->select("SELECT sheets.*, UNIX_TIMESTAMP(sheets.created_on) AS timestamp, "
                      ."count(players.id) AS num_players FROM sheets "
                      ."LEFT OUTER JOIN players ON sheets.id = players.sheet_id "
                      ."GROUP BY sheets.id "
                      ."HAVING sheets.completed = 0 AND (sheets.title LIKE :query OR sheets.description LIKE :query) "
                      ."ORDER BY num_players DESC LIMIT 10", array(':query'=>"%".$query."%"));
  if ($rows["status"] === "Success") {
    $rows["message"] = "Search performed successfully.";
  } else if ($rows["status"] === "Warning") {
    $rows["message"] = "No results for that query";
  } else {
    $rows["message"] = "Error while searching the sheets";
  }

  echoResponse(200, $rows);
});

// Get info about a sheet along with the list of owners
$app->get('/sheets/:id', function($id) {
  global $db;
  $rows = $db->select("SELECT sheets.*, users_auth.id, users_auth.username "
                      ."FROM sheets INNER JOIN owners ON owners.sheet_id = :id "
                      ."INNER JOIN users_auth ON owners.user_id = users_auth.id "
                      ."WHERE sheets.id = :id", 
                      array(':id'=>$id));

  echoResponse(200, $rows);
});

// Get the list of outcomes for a particular sheet
$app->get('/sheets/:id/outcomes/', function($id) {
  global $db, $sess;

  $query = "SELECT outcomes.id AS outcomeid, outcomes.*, options.* "
          ."FROM outcomes LEFT OUTER JOIN options ON outcomes.id = options.outcome_id "
          ."WHERE outcomes.sheet_id = :id";
  $bound = array(':id'=>$id);

  $user_id = $sess->getSession()["userid"];
  if ($user_id !== '') {
    $query = "SELECT outcomes.id AS outcomeid, outcomes.*, options.*, responses.response "
            ."FROM outcomes LEFT OUTER JOIN options ON outcomes.id = options.outcome_id "
            ."LEFT OUTER JOIN responses ON outcomes.id = responses.outcome_id AND responses.user_id = :user_id "
            ."WHERE outcomes.sheet_id = :id";
    $bound[":user_id"] = $user_id;
  }

  $rows = $db->select($query, $bound);

  echoResponse(200, $rows);
});

// Get all sheets for a user
$app->get('/sheets/user/:id', function($id) {
  global $db;
  $rows = $db->select("SELECT sheets.*"
                      ."FROM sheets INNER JOIN players ON players.sheet_id = sheets.id "
                      ."WHERE players.user_id = :id", 
                      array(':id'=>$id));

  echoResponse(200, $rows);
});

// Get all sheets for a certain owner
$app->get('/sheets/owner/:id', function($id) {
  global $db;
  $rows = $db->select("SELECT sheets.id, sheets.title, sheets.description FROM sheets "
                      ."INNER JOIN owners ON owners.user_id = :id AND owners.sheet_id = sheets.id", 
                      array(':id'=>$id));

  echoResponse(200, $rows);
});

// Insert a sheet
$app->post('/sheets', function() use ($app) { 
  global $db, $sess;
  // Insert a new sheet into the database.
  // We also need to add the used creating the sheet as an owner.
  $data = json_decode($app->request->getBody());

  $mandatory = array('title', 'description');
  Database::verifyRequiredParameters(Database::columnValuesPrefix($data), $mandatory);

  $title = $data->title;
  $description = $data->description;
  $user_id = $data->user_id;

  $db->beginTransaction();

  if ($sess->getSession()["userid"] !== $user_id) {
    echoResponse(201, array("status"=>"Error", "message"=>"Unauthorised action"));
    return;
  }

  // Insert new sheet row
  $rows = $db->insert("INSERT INTO sheets(title, description)"
                      ."VALUES(:title, :description)",
                      array(':title'=>$title, ':description'=>$description), $mandatory);
  
  $rows["message"] = "Sheet creation failed";
  if($rows["status"] === "Success") {
    // Now add the author as an owner

    $ownerResult = $db->insert("INSERT INTO owners(user_id, sheet_id)"
                              ."VALUES(:user_id, :sheet_id)",
                              array(':user_id'=>$user_id, ':sheet_id'=>intval($rows["data"])), array());

    if($ownerResult["status"] === "Success") {
      $rows["message"] = "Sheet added successfully.";
      $db->commit();
    } else {
      $rows["status"] = "Sheet creation failed";
      $db->rollback();
    }
  } else {
    $db->rollback();
  }

  echoResponse(200, $rows);
});

// Join a tipping sheet
$app->post('/join', function() use ($app) {
  global $db, $sess;
  // Join a user to a sheet
  $data = json_decode($app->request->getBody());

  $mandatory = array('sheet_id', 'user_id');
  Database::verifyRequiredParameters(Database::columnValuesPrefix($data), $mandatory);

  $sheet_id = $data->sheet_id;
  $user_id = $data->user_id;

  if ($sess->getSession()["userid"] !== $user_id) {
    echoResponse(201, array("status"=>"Error", "message"=>"Unauthorised action"));
    return;
  }

  // Insert new sheet row
  $rows = $db->insert("INSERT INTO players(user_id, sheet_id)"
                      ."VALUES(:user_id, :sheet_id)",
                      array(':user_id'=>$user_id, ':sheet_id'=>$sheet_id), $mandatory);

  if($rows["status"] === "Success") {
    $rows["message"] = "Joined tipping sheet";
  } else {
    $rows["message"] = "Failed to join tipping sheet";
  }

  echoResponse(200, $rows);

});

// Setting my responses to a tipping sheet
$app->post('/respond/:sheet_id', function($sheet_id) use ($app) {
  global $db, $sess;
  // Add responses
  $data = json_decode($app->request->getBody());

  $user_id = $data->user_id;

  if ($sess->getSession()["userid"] !== $user_id) {
    echoResponse(201, array("status"=>"Error", "message"=>"Unauthorised action"));
    return;
  }

  $db->beginTransaction();

  foreach ($data->responses as $outcome_id=>$response) {
    if ($response->changed) {
      // Updated changed reponse
      $rows = $db->update("UPDATE responses SET response = :response WHERE outcome_id = :outcome_id AND user_id = :user_id",
                          array(":response"=>$response->response, ":outcome_id"=>$outcome_id, ":user_id"=>$user_id), array());
      if ($rows["status"] !== "Success") {
        $rows["message"] = "Tips were not saved";
        echoResponse(200, $rows);
        $db->rollback();
        return;
      }
    } else {
      // Needs to be inserted
      $rows = $db->insert("INSERT INTO responses(user_id, outcome_id, response)"
                          ."VALUES(:user_id, :outcome_id, :response)",
                          array(":user_id"=>$user_id, ':outcome_id'=>$outcome_id, ':response'=>$response->response), array());
      if ($rows["status"] !== "Success") {
        $rows["message"] = "Tips were not saved";
        echoResponse(200, $rows);
        $db->rollback();
        return;
      }
    }
  }

  $db->commit();

  echoResponse(200, array(
    "status"=>"Success",
    "message"=>"Tips saved succesfully"
  ));
});

// Set the correct answer to a question
$app->post('/question/actual/:question_id', function($question_id) use ($app) {
  global $db, $sess;
  
  $data = json_decode($app->request->getBody());

  $mandatory = array('actual', 'sheet_id');
  Database::verifyRequiredParameters(Database::columnValuesPrefix($data), $mandatory);

  $actual = $data->actual;
  $sheet_id = $data->sheet_id;

  $db->beginTransaction();

  // Set scored flag to true. If scored is already true we don't want to do this again so
  // we only select where scored = 0 to ensure this step errors in such a case
  $rows = $db->update("UPDATE outcomes SET scored = 1, correct_id = :actual WHERE id = :question_id AND scored = 0",
                      array(":actual"=>$actual, ":question_id"=>$question_id), array());
  if ($rows["status"] !== "Success") {
    $rows["message"] = "Error updating question.";
    echoResponse(200, $rows);
    $db->rollback();
    return;
  }

  // Now increment the score of everyone who got this question correct
  $rows = $db->update("UPDATE players INNER JOIN responses ON responses.user_id = players.user_id "
                      ."SET players.score = players.score + 1  "
                      ."WHERE responses.outcome_id = :question_id "
                      ."AND responses.response = :actual "
                      ."AND players.sheet_id = :sheet_id",
                      array(":question_id"=>$question_id, ":actual"=>$actual, ":sheet_id"=>$sheet_id), array());
  if ($rows["status"] !== "Success") {
    $rows["message"] = "Error updating question.";
    echoResponse(200, $rows);
    $db->rollback();
    return;
  }

  $db->commit();

  echoResponse(200, array(
    "status"=>"Success",
    "message"=>"Correct outcome saved successfully"
  ));
});

// Adding a question
$app->post('/question/:id', function($id) use ($app) { 
  global $db, $sess;
  // Insert a new question into the database
  $data = json_decode($app->request->getBody());

  $mandatory = array('question');
  Database::verifyRequiredParameters(Database::columnValuesPrefix($data), $mandatory);

  $question = $data->question;

  // Insert new sheet row
  $rows = $db->insert("INSERT INTO outcomes(question, sheet_id)"
                      ."VALUES(:question, :sheet_id)",
                      array(':question'=>$question, ':sheet_id'=>$id), $mandatory);
  
  
  if($rows["status"] === "Success") {
    $rows["message"] = "Question added successfully";
  } else {
    $rows["message"] = "Question creation failed";
  }

  echoResponse(200, $rows);
});

// Closing a question
$app->post('/question/close/:id', function($id) use ($app) { 
  global $db, $sess;
  // Insert a new question into the database
  $data = json_decode($app->request->getBody());

  $mandatory = array('question_id');
  Database::verifyRequiredParameters(Database::columnValuesPrefix($data), $mandatory);

  $question_id = $data->question_id;

  // Insert new sheet row
  $rows = $db->update("UPDATE outcomes "
                      ."SET closed = 1 WHERE id = :question_id",
                      array(':question_id'=>$question_id), $mandatory);
  
  if($rows["status"] === "Success") {
    $rows["message"] = "Question successfully frozen";
  } else {
    $rows["message"] = "Question was not frozen";
  }

  echoResponse(200, $rows);
});

// Adding an owner to a sheet
$app->post('/owner/add/:id', function($id) use ($app) { 
  global $db, $sess;
  // Insert a new question into the database
  $data = json_decode($app->request->getBody());

  $mandatory = array('username');
  Database::verifyRequiredParameters(Database::columnValuesPrefix($data), $mandatory);

  $username = $data->username;

  // Find user id for this username;
  $user_record = $db->select("SELECT * FROM users_auth WHERE username = :username", 
                            array(':username'=>$username));

  if ($user_record["status"] !== "Success") {
    echoResponse(201, array("status"=>"Error", "message"=>"No user with that username"));
    return;
  }

  $user_id = $user_record["data"][0]["id"];

  // Insert new sheet row
  $rows = $db->insert("INSERT INTO owners(user_id, sheet_id)"
                      ."VALUES(:user_id, :sheet_id)",
                      array(':user_id'=>$user_id, ':sheet_id'=>$id), array());
  
  if($rows["status"] === "Success") {
    $rows["message"] = "Ownwer successfully added";
  } else {
    $rows["message"] = "Owner was not added";
  }

  echoResponse(200, $rows);
});

// Editing options
$app->post('/options/:outcome_id', function($outcome_id) use ($app) {
  global $db, $sess;
  // Join a user to a sheet
  $data = json_decode($app->request->getBody());

  $option_ids = array();

  $db->beginTransaction();

  foreach ($data as $index=>$option) {
    if (!isset($option->name) || $option->name === "") {
      echoResponse(201, array(
        "status"=>"Error",
        "message"=>"Options cannot be blank"
      ));
      return;
    }
    $option_ids[$index] = "0";
    if (isset($option->deleted)) {
      // Needs to be deleted
      $rows = $db->delete("DELETE FROM options WHERE id = :id", 
                          array(':id'=>$option->id));
      if ($rows["status"] !== "Success") {
        $rows["message"] = "Options editing failed";
        echoResponse(200, $rows);
        $db->rollback();
        return;
      }
    } else if (!isset($option->id)) {
      // Needs to be inserted
      $rows = $db->insert("INSERT INTO options(outcome_id, name)"
                          ."VALUES(:outcome_id, :name)",
                          array(':outcome_id'=>$outcome_id, ':name'=>$option->name), array());
      if ($rows["status"] !== "Success") {
        $rows["message"] = "Options editing failed";
        echoResponse(200, $rows);
        $db->rollback();
        return;
      }
      $option_ids[$index] = $rows["data"];
    } else if(isset($option->changed)) {
      // Needs to be updated
      $rows = $db->update("UPDATE options SET name = :name WHERE id = :option_id",
                          array(":name"=>$option->name, ":option_id"=>$option->id), array());
      if ($rows["status"] !== "Success") {
        $rows["message"] = "Options editing failed";
        echoResponse(200, $rows);
        $db->rollback();
        return;
      }
      
    }
  }

  $db->commit();

  echoResponse(200, array(
    "status"=>"Success",
    "message"=>"Options updated succesfully",
    "data"=>$option_ids
  ));
});


// Leaderboard
$app->get('/leaderboard/:id', function($id) {
  global $db, $sess;
  // Find the top 10 users as well as including the current user
  // if they are taking part
  
  $query = "";
  $bound = array(':id'=>$id);

  $user_id = $sess->getSession()["userid"];
  if ($user_id !== '') {
    $query = "SELECT users_auth.username, users_auth.id, players.score FROM users_auth "
             ."INNER JOIN players ON players.user_id = users_auth.id AND players.sheet_id = :id "
             ."WHERE users_auth.id = :user_id UNION ";
    $bound[":user_id"] = $user_id;
  }


  $rows = $db->select("SELECT * FROM (" . $query . "SELECT users_auth.username, users_auth.id, players.score FROM users_auth "
                      ."INNER JOIN players ON players.user_id = users_auth.id "
                      ."AND players.sheet_id = :id LIMIT 10) leaders ORDER BY leaders.score DESC ", 
                      $bound);

  echoResponse(200, $rows);
});

// Owners
$app->get('/owners/:id', function($id) {
  global $db;
  $rows = $db->select("SELECT users_auth.username, users_auth.id FROM users_auth "
                      ."INNER JOIN owners ON owners.user_id = users_auth.id AND owners.sheet_id = :id LIMIT 10", 
                      array(':id'=>$id));

  echoResponse(200, $rows);
});

// Products
$app->get('/products', function() { 
  global $db;
  $rows = $db->select("SELECT id,sku,name,description,price,mrp,stock,image,packing,status FROM products", array());

  echoResponse(200, $rows);
});

$app->post('/products', function() use ($app) { 
  $data = json_decode($app->request->getBody());
  $mandatory = array('name');
  list($colNames, $prefixedColNames) = Database::getColumnNames($data);
  global $db;
  $rows = $db->insert("INSERT INTO products($colNames)"
                      ."VALUES($prefixedColNames)",
                      Database::columnValuesPrefix($data), $mandatory);
  if($rows["status"]=="Success")
      $rows["message"] = "Product added successfully.";
  echoResponse(200, $rows);
});

$app->put('/products/:id', function($id) use ($app) {
  $data = json_decode($app->request->getBody());
  $condition = array(':id'=>$id);
  $data->id = $id;
  global $db;
  $rows = $db->update("UPDATE products SET "
                      .Database::columnValuesSetterString($data)
                      ." WHERE id = :id",
                      Database::columnValuesPrefix($data), array());
  if($rows["status"]=="Success")
      $rows["message"] = "Product information updated successfully.";
  echoResponse(200, $rows);
});

$app->delete('/products/:id', function($id) { 
  global $db;
  $rows = $db->delete("DELETE FROM products WHERE id = :id", array(':id'=>$id));
  if($rows["status"]=="success")
      $rows["message"] = "Product removed successfully.";
  echoResponse(200, $rows);
});

$app->run();
?>