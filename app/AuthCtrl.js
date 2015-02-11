app.controller('AuthCtrl', function ($scope, $rootScope, $routeParams, $location, $http, AuthService, Data) {
  //initially set those objects to null to avoid undefined error
  $scope.login = {};
  $scope.signup = {};

  $scope.doLogin = function (user) {
    Data.post('login', user)
    .then(function (results) {
      Data.toast(results);
      if (results.status === "Success") {
        AuthService.updateSession().then(function() {
          $location.path('/myaccount');
        });
      }
    });
  };

  $scope.signup = {email:'',password:'',username:''};
  $scope.register = function (user) {
    Data.post('signup', user)
    .then(function (results) {
      Data.toast(results);
      if (results.status == "Success") {
        AuthService.updateSession().then(function() {
          $location.path('/myaccount');
        });
      }
    });
  };
  
  $scope.logout = function () {
    Data.get('logout').then(function (results) {
      Data.toast(results);
      $rootScope.authenticated = false;
      $rootScope.auth = null;
      $location.path('login');
    });
  }
});