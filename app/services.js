app.factory("AuthService", function($rootScope, $q, Data) {

	var loaded = $q.defer();

	var auth = {
		hasLoaded: loaded.promise,
		updateSession: function() {

			loaded = $q.defer();
			this.hasLoaded = loaded.promise;

			Data.get('session').then(function (results) {
	      if (results.userid) {
	        $rootScope.authenticated = true;
	        $rootScope.auth = {
	          id: results.userid,
	          username: results.username
	        }
	      } else {
	        $rootScope.authenticated = false;
	        $rootScope.auth = { id: null, username: null };
	      }
	      loaded.resolve();
	    });
	    
	    return loaded.promise;
		}
	}

	auth.updateSession();

	return auth;
});

