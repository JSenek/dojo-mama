<?php
/*
dojo-mama: a JavaScript framework
Copyright (C) 2015 Omnibomd Systems LLC

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

require_once('../Authenticator/LdapStore.php');
require_once('../login/Common/LoggingRestException.php');
require_once('../Authenticator/Google2FA.php');


$url = $_SERVER['REQUEST_URI'];

$url = explode('?', $url);

$url = $url[0];

$dir = dirname($_SERVER['SCRIPT_NAME']).'/';

$restPoint = explode($dir, $url);

if(!isset($restPoint[1])){
	throw new \Common\LoggingRestException('Nope.',500);
}

$restPoint = $restPoint[1];



//UrlValidationPattern:   https://login.clemson.edu/otp/validate?id=%s&otp=%s

//UrlExistsPattern:  https://login.clemson.edu/otp/exists?id=%s

if(!isset($_GET['id'])){
	throw new \Common\LoggingRestException("id is required.",400);
}

$store = new LdapStore();
$store->init(array(
	'host' => $_SERVER['MFA_LDAP_HOST'],
	'username' => $_SERVER['MFA_LDAP_USER'],
	'password' => $_SERVER['MFA_LDAP_PASS'],
	'searchRoot' => $_SERVER['MFA_LDAP_SEARCH_ROOT']
));

$username = $idConn->sanitize('username', $_GET['id']);

if($username === false){
	throw new \Common\LoggingRestException('invalid username.',400);
}

$userInfo = $store->query(array('username' => $_SESSION['username']));

if(!isset($userInfo[$_SERVER['MFA_LDAP_ATTR']])){
	throw new \Common\LoggingRestException('Can\'t find a suitable '.$_SERVER['MFA_LDAP_ATTR'].' containing '.$_SERVER['MFA_ISSUER_LABEL_PREFIX'].":".$_SESSION['username'],404);
}

$mfaStrs = $userInfo[$_SERVER['MFA_LDAP_ATTR']];
for ($i=0,$l=count($mfaStrs);$i<$l;$i++) {
	if(strpos($mfaStrs[$i], $_SERVER['MFA_ISSUER_LABEL_PREFIX'].":".$_SESSION['username']) !== false){
		$mfaStr = $mfaStrs[$i];
		break;
	}
}

if($restPoint == 'exists'){
	if(!isset($mfaStr)){
		throw new \Common\LoggingRestException('Can\'t find a suitable '.$_SERVER['MFA_LDAP_ATTR'].' containing '.$_SERVER['MFA_ISSUER_LABEL_PREFIX'].":".$_SESSION['username'],404);
	}else{
		header("HTTP/1.1 200 OK");
		return;
	}
}elseif($restPoint == 'validate'){
	if(!isset($_GET['otp']) || !ctype_digit($_GET['otp']) || strlen($_GET['otp'] > 15)){
		throw new \Common\LoggingRestException('invalid otp.',400);
	}

	if(!isset($mfaStr)){
		throw new \Common\LoggingRestException('Can\'t find a suitable '.$_SERVER['MFA_LDAP_ATTR'].' containing '.$_SERVER['MFA_ISSUER_LABEL_PREFIX'].":".$_SESSION['username'],404);
	}

	$paramStr = split('?',$mfaStr);
	$params = split('&',$paramStr[1]);

	for ($i=0,$l=count($params);$i<$l;$i++) {
		$params[$i] = split('=',$params[$i]);
		if( $params[$i][0] == 'secret'){
			$mfaSecret = $params[$i][1];
		}
	}
	if(!isset($mfaSecret)){
		throw new \Common\LoggingRestException('Error while parsing '.$_SERVER['MFA_LDAP_ATTR'].' for user '.$_SESSION['username'].'.',500);
	}

	//verify code
	if(!\Google2FA::verify_key($mfaSecret, $_GET['otp'], 1)){
		throw new \Common\LoggingRestException('The given code is invalid.',400);
	}

	header("HTTP/1.1 200 OK");
	return;
}else{
	throw new \Common\LoggingRestException("rest endpoint is all whack ($restPoint).",400);
}

?>