<?php
/*
dojo-mama: a JavaScript framework
Copyright (C) 2015 Clemson University

This library is free software; you can redistribute it and/or
modify it under the terms of the GNU Lesser General Public
License as published by the Free Software Foundation; either
version 2.1 of the License, or (at your option) any later version.

This library is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public
License along with this library; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
*/

//load classes using the PSR-0 standard for class names
spl_autoload_register(function ($class_name) {
	$class_name = ltrim($class_name, '\\');

	$file_name = '';
	$namespace = '';
	
	if ($last_ns_pos = strripos($class_name, '\\')) {
		$namespace = substr($class_name, 0, $last_ns_pos);
		$class_name = substr($class_name, $last_ns_pos + 1);
		
		$file_name = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
	}
	
	$file_name .= str_replace('_', DIRECTORY_SEPARATOR, $class_name) . '.php';

	//so that other apps can use the base_path as well
	if(!defined('APP_BASE_PATH')){
		define("APP_BASE_PATH", __DIR__ . DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR );
	}

	$path = APP_BASE_PATH . $file_name;

	//echo $path;
	
	if (file_exists($path)) {
		include $path;
	}
	else {
		throw new ErrorException('Could not autoload class. Unable to find file ' . $path);
	}
});

function renderLogin($message){

	?>

	<html>
	<body>
	<h1>Login</h1>
	<?php if(isset($message) && is_string($message) && strlen($message) > 0){
		echo "\t<h3>$message</h3>\n";
	}
	?>
	<form method='post' action='/srv/login/index.php'>
	<label for="username">Username</label>
	<input id="username" type="text" name="u" placeholder="username" autofocus="" autocorrect="off" autocapitalize="no">
	<label for="password">Password</label>
	<input id="password" type="password" name="p" placeholder="password" autocorrect="off" autocapitalize="no">
	<button type="submit">Login</button>
	</form>
	</body>
	</html>

	<?php
}

define('ALL_GOOD',0); //default for a lot of failures including unknown user
define('BAD_PASS',1);
define('ACCOUNT_DISABLED',3); //account disabled
define('PASS_EXPIRED',4);

/*
 * Loads and news an object of given path
 * optionally will also validate that it is or inherits from a baseClass
 */
function getObjectByClass($configClassPath, $baseClassPath = null)
{
	$resultingObject = new $configClassPath();

	if ($baseClassPath !== null && (!(is_subclass_of($resultingObject, $baseClassPath) || get_class($resultingObject) == $baseClassPath))) {
		throw new \Common\LoggingRestException(get_class($resultingObject) . " (configed as $configClassPath) is not descendant from $baseClassPath.", 500);
	}
	return $resultingObject;
}

if(isset($_POST['u']) && isset($_POST['p'])){

	$idConn = getObjectByClass('\CUID\IdentityConnector', '\Common\IdentityConnector');

	$idConn->init(array(
		'returnClass' => '\Common\IdentityConnectorState',
		'host' => $_SERVER['MFA_LDAP_HOST'],
		'username' => $_SERVER['MFA_LDAP_USER'],
		'password' => $_SERVER['MFA_LDAP_PASS'],
		'searchRoot' => $_SERVER['MFA_LDAP_SEARCH_ROOT']
	));

	//use the identityConnector to sanitize inputs
	// because different data stores (SQL vs LDAP) have different special chars
	$username = $idConn->sanitize('username', $_POST['u']);
	$pass = $idConn->sanitize('password', $_POST['p']);

	if ($username === false || $pass === false) {
		renderLogin("Bad username or password");
		return;
	}

	//checkCredentials returns an instance of IdentityConnectorState
	//if it doesn't throw an error
	$result = $idConn->checkCredentials($username, $pass);
	if (!(is_subclass_of($result, 'Common\IdentityConnectorState') || get_class($result) == 'Common\IdentityConnectorState')) {
		throw new \Common\LoggingRestException(get_class($this->idConn) . "(configed as $idConnectorClass)->checkCredentials returned an object that is not descendant from IdentityConnectorState (" . get_class($result) . ").", 500);
	}

	//If the credentials passed
	if ($result->isSuccess()) {
		error_log("$username gave correct credentials.");
		//userInfo contains flags and attributes about the user
		//and communicate ideas like
		//   "Login was successful but here's some caveats: they're using grace logins..."
		//so the identityConnector and speedbumps / speedbump managers
		//have to have some knowledge of the caveats each sends / needs
		$userInfo = $result->userInfo;

	} else {
		error_log("$username gave incorrect credentials.");
		renderLogin("Bad username or password");
		return;
	}

	session_start();
	$_SESSION['authenticated'] = true;
	$_SESSION['username'] = $username;
	$_SESSION['userInfo'] = $userInfo;

	header('Location: /');
}else{
	renderLogin("");
}
?>