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

require_once('LdapStore.php');
require_once('../login/Common/LoggingRestException.php');
require_once('Google2FA.php');
session_start();
if(!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']){
	throw new \Common\LoggingRestException('You must be authenticated.',403);
}

$url = $_SERVER['REQUEST_URI'];

$url = explode('?', $url);

$url = $url[0];

$dir = dirname($_SERVER['SCRIPT_NAME']).'/';

$restPoint = explode($dir, $url);

if(!isset($restPoint[1])){
	throw new \Common\LoggingRestException('Nope.',500);
}

$restPoint = $restPoint[1];


$store = new LdapStore();
$store->init(array(
	'host' => $_SERVER['MFA_LDAP_HOST'],
	'username' => $_SERVER['MFA_LDAP_USER'],
	'password' => $_SERVER['MFA_LDAP_PASS'],
	'searchRoot' => $_SERVER['MFA_LDAP_SEARCH_ROOT']
));

if($_SERVER['REQUEST_METHOD'] == 'GET'){

	//QUERY
	$templateJsonStr = file_get_contents("authTemplate.json");
	$templateJson = json_decode($templateJsonStr, true);

	$userInfo = $store->query(array('username' => $_SESSION['username']));

	if($userInfo === false){
		throw new \Common\LoggingRestException('Can\'t find the username '.$_SESSION['username'].'.',500);
	}

	if(!isset($userInfo[$_SERVER['MFA_LDAP_ATTR']])){
		//no mfa attached to this account
	
		//generate secret
		$mfaSecret = \Google2FA::generate_secret_key();

		//use secret to generate qrcode str
		$mfaStr = "otpauth://totp/".$_SERVER['MFA_ISSUER_LABEL_PREFIX'].":".$_SESSION['username']."?secret=".$mfaSecret."&issuer=".$_SERVER['MFA_ISSUER_PARAMETER'];

		//write qrcode string and secret out to session so that 
		//when the user actually attaches it we know what key to use
		$_SESSION['mfaSecret'] = $mfaSecret;
		$_SESSION['mfaStr'] = $mfaStr;


		$templateJson['items'][0]['data']['use2pass'] = false;
		$templateJson['items'][0]['data']['key'] = $mfaSecret;
		$templateJson['items'][0]['data']['qrcode'] = $mfaStr;

		echo json_encode($templateJson);
		return;
	}else{

		$templateJson['items'][0]['data']['use2pass'] = true;
		//if its on, dont show the key or qrcode
	}

}elseif($_SERVER['REQUEST_METHOD'] == 'POST'){
	$restParts = explode('/', $restPoint);

	$entityBody = file_get_contents('php://input');
	$requestBody = json_decode($entityBody, true);

	$len = count($restParts);
	if($len === 1){
		//CREATE

		//DISABLED
	}elseif($len === 2){
		//UPDATE

		if(isset($_SESSION['mfaSecret'])){
			//secret is in the session, probably hasnt been saved yet
			$mfaSecret = $_SESSION['mfaSecret'];
			$mfaStr = $_SESSION['mfaStr'];
		}else{
			//get the secret from the user...
			$userInfo = $store->query(array('username' => $_SESSION['username']));

			if($userInfo === false){
				throw new \Common\LoggingRestException('Can\'t find the username '.$_SESSION['username'].'.',500);
			}
			if(!isset($userInfo[$_SERVER['MFA_LDAP_ATTR']]) || count($userInfo[$_SERVER['MFA_LDAP_ATTR']]) < 1){
				//no key anywhere, can't update anything
				throw new \Common\LoggingRestException('Can\'t find a secret. Start over.',404);
			}

			$mfaStrs = $userInfo[$_SERVER['MFA_LDAP_ATTR']];
			for ($i=0,$l=count($mfaStrs);$i<$l;$i++) {
				if(strpos($mfaStrs[$i], $_SERVER['MFA_ISSUER_LABEL_PREFIX'].":".$_SESSION['username']) !== false){
					$mfaStr = $mfaStrs[$i];
					break;
				}
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
		}

		if(!isset($requestBody['code'])){
			throw new \Common\LoggingRestException('You must generate a verification code before the authenticator can be attached to your account.',500);
		}
		if(!isset($requestBody['use2pass'])){
			throw new \Common\LoggingRestException('You must turn on the authenticator.',500);	
		}


		//verify code
		if(!\Google2FA::verify_key($mfaSecret, $requestBody['code'], 1)){
			throw new \Common\LoggingRestException('The given code is invalid.',400);
		}

		//OK Go!

		if($requestBody['code'] === true){
			//save a new MFA_LDAP_ATTR
			$store->update($_SESSION['username'], array(
				'action'=> 'add',
				'attr' => $_SERVER['MFA_LDAP_ATTR'],
				'val' => $mfaStr
			));
		}else{
			//delete the MFA_LDAP_ATTR
			$store->update($_SESSION['username'], array(
				'action'=> 'delete',
				'attr' => $_SERVER['MFA_LDAP_ATTR'],
				'val' => $mfaStr
			));
		}

	}else{
		//wtf
	}
}elseif($_SERVER['REQUEST_METHOD'] == 'DELETE'){
	//DELETE

	//DISABLED
}else{
	//wtf
}

?>