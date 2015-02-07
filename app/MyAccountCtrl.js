app.controller('MyAccountCtrl', function($scope, $rootScope, AuthService, Data) {
	$scope.ownedSheets = [];
	$scope.activeSheets = [];
	$scope.completedSheets = [];

  AuthService.hasLoaded.then(function() {

  	Data.get('sheets/owner/'+$rootScope.auth.id).then(function(data){
      $scope.ownedSheets = data.data;
    });

    Data.get('sheets/user/'+$rootScope.auth.id).then(function(data){
      var mySheets = data.data;
      // Separate into active and inactive sheets
      mySheets.forEach(function (sheet) {
      	if (sheet.completed) {
      		$scope.completedSheets.push(sheet);
      	} else {
      		$scope.activeSheets.push(sheet);
      	}
      });
    });

  });

});

app.controller('SearchCtrl', function($scope, $rootScope, Data) {
  $scope.query = {};
  $scope.results = [];

  $scope.keyPress = function(event) {
    if (event.charCode === 13) {
      $scope.search($scope.query);
    }
  };

  $scope.search = function(query) {
    Data.post('sheets/search', {
      query: query
    }).then(function(data){
      Data.toast(data);
      $scope.results = data.data;
    });
  };
  
});