app.controller('PopularCtrl', function ($scope, $modal, $filter, $location, Data) {
  $scope.popular = {};
  
  Data.get('sheets/popular').then(function(data){
    $scope.popular = data.data;
  });

  $scope.viewSheet = function(id) {
    $location.path('/sheet/'+id);
  }

});

app.controller('SheetViewCtrl', function($scope, $rootScope, $routeParams, $window, AuthService, $modal, Data) {
  // Initialise scope variables to defaults
  $scope.sheet = {};
  $scope.questions = {};
  $scope.leaderboard = [];
  $scope.owners = [];
  $scope.editing = false;
  $scope.takingPart = false;
  $scope.loggedIn = false;

  AuthService.hasLoaded.then(function() {
    $scope.loggedIn = $rootScope.authenticated;
  })

  $scope.sheet_id = $routeParams.id;
  
  Data.get('sheets/'+$routeParams.id).then(function(data){
    $scope.sheet = data.data[0];
    // Get the list of owners
    data.data.forEach(function(owner) {
      $scope.owners.push(owner);
      if (owner.id === $rootScope.auth.id) {
        $scope.editing = true;
      }
    });
  });

  Data.get('sheets/'+$routeParams.id+'/outcomes').then(function(data){
    data.data.forEach(function (outcome_row) {
      if (!$scope.questions[outcome_row.outcomeid]) {
        $scope.questions[outcome_row.outcomeid] = {
          options: []
        };
      }
      var question = $scope.questions[outcome_row.outcomeid];

      question.id = outcome_row.outcomeid;
      question.question = outcome_row.question;
      question.response = outcome_row.response;
      question.initial_response = outcome_row.response;
      question.closed = outcome_row.closed;
      question.scored = outcome_row.scored;
      question.correct_id = outcome_row.correct_id;

      // Collect all options into the options array
      // (We must check for null because the question might have no options yet)
      if (outcome_row.name !== null) {
        question.options.push({
          name: outcome_row.name,
          initial_name: outcome_row.name,
          id: outcome_row.id
        });
      }
      if (outcome_row.id === question.correct_id) {
        question.actual = outcome_row.name;
      }
    });
  });

  $scope.updateLeaderboard = function() {
    Data.get('leaderboard/'+$routeParams.id).then(function(data){
      $scope.leaderboard = data.data;
      $scope.leaderboard.forEach(function(user) {
        if (user.id === $rootScope.auth.id) {
          $scope.takingPart = true;
        }
      });
    });
  }

  AuthService.hasLoaded.then(function () {
    $scope.updateLeaderboard();
  });

  $scope.joinSheet = function(id) {
    AuthService.hasLoaded.then(function() {
      Data.post('join', {
        'user_id': $rootScope.auth.id.toString(),
        'sheet_id': id
      }).then(function (result) {
        $scope.leaderboard.push({
          'username': $rootScope.auth.username,
          'id': $rootScope.auth.id,
          'score': 0
        })
        $scope.takingPart = true;
        Data.toast(result);
      });
    });
  };

  // Launch a modal to select the correct to the sheet
  $scope.selectCorrectResponse = function (question) {
    var modalInstance = $modal.open({
      templateUrl: 'partials/selectCorrect.html',
      controller: 'SelectCorrectCtrl',
      resolve: {
        question: function () {
          return question;
        }
      }
    });
    modalInstance.result.then(function(correct_id) {
      //$window.location.reload();
      // TODO: Update manually instead of reloading page
      question.scored = true;
      question.correct_id = correct_id;
      // Find name of actual attribute
      question.options.forEach(function(option) {
        if (option.id === correct_id) {
          question.actual = option.name;
        }
      });
      $scope.updateLeaderboard();
    });
  };

  // Launch a modal to add a new question to the sheet
  $scope.addNewQuestion = function () {
    var modalInstance = $modal.open({
      templateUrl: 'partials/newOutcome.html',
      controller: 'NewQuestionCtrl',
      resolve: {
        sheet_id: function () {
          return $routeParams.id;
        }
      }
    });
    modalInstance.result.then(function(newQuestion) {
      newQuestion.options = [];
      $scope.questions[newQuestion.id] = newQuestion;
    });
  };

  // Launch a modal to add a new owner to the sheet
  $scope.addNewOwner = function () {
    var modalInstance = $modal.open({
      templateUrl: 'partials/newOwner.html',
      controller: 'NewOwnerCtrl',
      resolve: {
        sheet_id: function () {
          return $routeParams.id;
        }
      }
    });
    modalInstance.result.then(function(newOwner) {
      $scope.owners.push(newOwner);
    });
  };

  // Launch a modal to check whether to freeze
  $scope.confirmFreeze = function (question) {
    var modalInstance = $modal.open({
      templateUrl: 'partials/confirmModal.html',
      controller: 'ConfirmModalCtrl',
      resolve: {
        messages: function() {
          return {
            warning: 'Are you sure you want to freeze this question? Players will no longer be able to change their picks for this question.',
            title: 'Confirm Question Freeze'
          }
        }
      }
    });
    modalInstance.result.then(function(confirm) {
      if (confirm) {
        question.closed = true;
        Data.post('question/close/'+question.id, {
          'question_id': question.id.toString()
        }).then(function (result) {
          Data.toast(result);
        });
      }
    });
  };

  // Launch a modal to check whether to freeze
  $scope.closeSheet = function (sheet) {
    var modalInstance = $modal.open({
      templateUrl: 'partials/confirmModal.html',
      controller: 'ConfirmModalCtrl',
      resolve: {
        messages: function() {
          return {
            warning: 'Are you sure you want to close this sheet?',
            title: 'Confirm Sheet Closure'
          }
        }
      }
    });
    modalInstance.result.then(function(confirm) {
      if (confirm) {
        question.closed = true;
        Data.post('sheet/close/'+sheet.id, {
          'question_id': question.id.toString()
        }).then(function (result) {
          Data.toast(result);
        });
      }
    });
  };

  // Launch a modal to add a new owner to the sheet
  $scope.editOptions = function (question) {
    var modalInstance = $modal.open({
      templateUrl: 'partials/editOptions.html',
      controller: 'EditOptionsCtrl',
      resolve: {
        optionsData: function () {
          return {
            question_id: question.id,
            options: question.options
          };
        }
      }
    });
    modalInstance.result.then(function(options) {
      question.options = options;
    });
  };

  $scope.saveTips = function() {
    var changed_responses = {};
    for (var key in $scope.questions) {
      if ($scope.questions.hasOwnProperty(key)) {
        var question = $scope.questions[key];
        if (question.response !== question.initial_response) {
          changed_responses[key] = {
            response: question.response,
            changed: question.initial_response !== null
          }
        }
      }
    }
    Data.post('respond/'+$routeParams.id, {
      'user_id': $rootScope.auth.id.toString(),
      responses: changed_responses
    }).then(function (result) {
      Data.toast(result);
    });
  };

});


