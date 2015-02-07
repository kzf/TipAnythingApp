var app = angular.module('myApp', ['ngRoute', 'ui.bootstrap', 'ngAnimate', 'toaster']);

app.config(['$routeProvider',
  function($routeProvider) {
    $routeProvider
    .when('/', {
      title: 'Popular',
      templateUrl: 'partials/popular.html',
      controller: 'PopularCtrl'
    })
    .when('/search', {
      title: 'Search',
      templateUrl: 'partials/search.html',
      controller: 'SearchCtrl'
    })
    .when('/login', {
      title: 'Login',
      templateUrl: 'partials/login.html',
      controller: 'AuthCtrl'
    })
    .when('/sheet/:id', {
      title: 'Sheet',
      templateUrl: 'partials/sheet.html',
      controller: 'SheetViewCtrl'
    })
    .when('/sheet/:id/edit', {
      title: 'Sheet',
      templateUrl: 'partials/sheet.html',
      controller: 'SheetEditCtrl'
    })
    .when('/register', {
      title: 'Register',
      templateUrl: 'partials/register.html',
      controller: 'AuthCtrl'
    })
    .when('/logout', {
      title: 'Logout',
      templateUrl: 'partials/login.html',
      controller: 'AuthCtrl'
    })
    .when('/myaccount', {
      title: 'My Account',
      templateUrl: 'partials/myaccount.html',
      controller: 'MyAccountCtrl'
    })
    .otherwise({
      redirectTo: '/'
    });;
}]);

app.run(function ($rootScope, $location, Data, AuthService) {
  $rootScope.authenticated = false;
  AuthService.updateSession();

  $rootScope.$on("$routeChangeSuccess", function(event, current, previous) {
    $rootScope.title = current.$$route.title;
  });

  $rootScope.$on("$routeChangeStart", function (event, next, current) {
    // Prevent logged in users from accessing the signup or login page
    var toPath = next.$$route ? next.$$route.originalPath : '/';
    if ($rootScope.authenticated) {
      if (toPath == '/signup' || toPath == '/login') {
        $location.path('/');
      }
    }
  });
});;
    