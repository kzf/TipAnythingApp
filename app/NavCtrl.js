app.controller('NavCtrl', function($scope, $location, $modal) {

	$scope.getClass = function(path, strict) {
    if ((!strict && $location.path().substr(0, path.length) === path) ||
    		 $location.path() === path) {
      return "active"
    } else {
      return ""
    }
	}

	$scope.open = function (p) {
    var modalInstance = $modal.open({
      templateUrl: 'partials/newSheet.html',
      controller: 'NewSheetCtrl'
    });
    modalInstance.result.then(function(newSheet) {
      // TODO: Add new Sheet to scope
    });
  };

});