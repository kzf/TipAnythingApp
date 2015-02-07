/**************
  ConfirmModal
  **************/
app.controller('ConfirmModalCtrl', function ($scope, $rootScope, $modalInstance, $location, messages, Data) {

  $scope.warning = messages.warning;
  $scope.header = messages.title;

  $scope.cancel = function () {
    $modalInstance.close(false);
  };
  
  $scope.confirm = function () {
    $modalInstance.close(true);
  };
});


/**************
  NewSheetCtrl
  **************/
app.controller('NewSheetCtrl', function ($scope, $rootScope, $modalInstance, $location, AuthService, Data) {

  $scope.sheet = {};
        
  $scope.cancel = function () {
    $modalInstance.dismiss('Close');
  };
  
  $scope.saveSheet = function () {
    AuthService.hasLoaded.then(function() {
      var sheet = $scope.sheet;
      sheet.user_id = $rootScope.auth.id.toString();
      Data.post('sheets', sheet).then(function (result) {
        Data.toast(result);
        if (result.status != 'Error') {
          $modalInstance.close();
          $location.path("/sheet/"+result.data);
        }
      });
    });
  };
});

/**************
  SelectCorrectCtrl
  **************/
app.controller('SelectCorrectCtrl', function ($scope, $rootScope, $routeParams, $modalInstance, $location, question, Data) {

  $scope.response = { actual: null };
  $scope.question = question;
        
  $scope.cancel = function () {
    $modalInstance.dismiss('Close');
  };
  
  $scope.saveActualOutcome = function () {
    var response = $scope.response;
    Data.post('question/actual/'+$scope.question.id, {
      actual: response.actual,
      sheet_id: $routeParams.id
    }).then(function (result) {
      Data.toast(result);
      if (result.status != 'Error') {
        $modalInstance.close(response.actual);
      }
    });
  };
});


/**************
  NewOwnerCtrl
  **************/
app.controller('NewOwnerCtrl', function ($scope, $rootScope, $modalInstance, $location, sheet_id, AuthService, Data) {

  $scope.owner = {};
        
  $scope.cancel = function () {
    $modalInstance.dismiss('Close');
  };
  
  $scope.saveOwner = function () {
    AuthService.hasLoaded.then(function() {
      var owner = $scope.owner;
      owner.user_id = $rootScope.auth.id.toString();
      Data.post('owner/add/'+sheet_id, owner).then(function (result) {
        Data.toast(result);
        if (result.status != 'Error') {
          $modalInstance.close(owner);
        }
      });
    });
  };
});

/**************
  NewQuestionCtrl
  **************/
app.controller('NewQuestionCtrl', function ($scope, $rootScope, $modalInstance, $location, sheet_id, AuthService, Data) {

  $scope.question = {};
        
  $scope.cancel = function () {
    $modalInstance.dismiss('Close');
  };
  
  $scope.saveOutcome = function () {
    AuthService.hasLoaded.then(function() {
      var question = $scope.question;
      question.user_id = $rootScope.auth.id.toString();
      Data.post('question/'+sheet_id, question).then(function (result) {
        Data.toast(result);
        if (result.status != 'Error') {
          question.id = result.data;
          $modalInstance.close(question);
        }
      });
    });
  };
});


/**************
  EditOptionsCtrl
  **************/
app.controller('EditOptionsCtrl', function ($scope, $rootScope, $modalInstance, $location, optionsData, Data) {

  $scope.options = optionsData.options;
  var question_id = optionsData.question_id;
        
  $scope.cancel = function () {
    $modalInstance.dismiss('Close');
  };
  
  $scope.addOption = function () {
    $scope.options.push({});
  };

  $scope.deleteOption = function(index) {
    $scope.options[index].deleted = true;
  };

  $scope.saveChanges = function () {
    $scope.options.forEach(function(option) {
      if (option.hasOwnProperty('initial_name') && option.name !== option.initial_name) {
        option.changed = true;
      }
    });
    Data.post('options/'+question_id, $scope.options).then(function (result) {
      Data.toast(result);
      var updatedIds = result.data;
      if (result.status != 'Error') {
        // Actually remove the deleted options since they have gone from the database
        var remainingOptions = [];
        $scope.options.forEach(function(option, i) {
          if (updatedIds[i] !== 0) {
            option.id = updatedIds[i];
          }
          option.initial_name = option.name;
          if (!option.deleted) {
            remainingOptions.push(option);
          }
        });
        $modalInstance.close(remainingOptions);
      }
    });
  };
});