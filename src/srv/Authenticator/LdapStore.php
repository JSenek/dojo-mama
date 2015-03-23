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
require_once('../login/Common/LoggingRestException.php');

class LdapStore{
	private $host;
	private $username;
	private $password;
	private $searchRoot;

	public function init($config)
	{
		$this->host = $config['host'];
		$this->username = $config['username'];
		$this->password = $config['password'];
		$this->searchRoot = $config['searchRoot'];
	}

	public function query($request_data){
		if(!isset($request_data['username'])){
			throw new \Common\LoggingRestException('This store only supports query by username.',500);
		}

		$user = $this->getLdapUser($request_data['username']);
	}

	public function update($id, $request_data){
		$user = $this->getLdapUser($id);

		$entry = array($request_data['attr'] => $request_data['val']);
		if($request_data['action'] == 'add'){
			ldap_mod_add($this->conn, $user['dn'], $entry);
		}elseif($request_data['action'] == 'delete'){
			ldap_mod_del($this->conn, $user['dn'], $entry);
		}
	}

	private function connect($host, $username, $password)
	{
		$this->conn = ldap_connect($host);

		if ($this->conn === false) {
			throw new \Common\LoggingRestException('Cannot connect to ldap host.', 500, "host: $host");
		}

		if (ldap_bind($this->conn, $username, $password) === false) {
			return false;
		}

		return true;
	}
	
	private function disconnect(){
		ldap_close($this->conn);
	}

	private function getLdapUser($username){
		$this->connect($this->host, $this->username, $this->password);

		$userInfo = array(
			'username' => $username
		);

		$filter = "(&(cn=" . $username . ")(objectClass=person))";

		$attrs = array(
			"cn", "dn",
			$_SERVER['MFA_LDAP_ATTR']
		);

		$resultHandle = ldap_search($this->conn, $this->searchRoot, $filter, $attrs);

		if (!$resultHandle) {
			//ahh no search results!
			//server unreachable error here
			throw new \Common\LoggingRestException("LDAP Server is unreachable", 500);
		}
		$results = ldap_get_entries($this->conn, $resultHandle);

		if ($results['count'] !== 1) {
			//ahh 0 or multiple results
			//either way no user found error here
			return false;
		}
		$user = $results[0];

		$userInfo['id'] = $user['dn'];

		if(isset($user[ strtolower($_SERVER['MFA_LDAP_ATTR']) ]) && is_string($user[strtolower($_SERVER['MFA_LDAP_ATTR'])])){
			$userInfo['MFAKeyUri'] = $user[strtolower($_SERVER['MFA_LDAP_ATTR'])];
		}
		return $userInfo;
	}
}

?>