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

namespace CUID;

class IdentityConnector extends \Common\IdentityConnector
{

    private $returnClass;
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
        $this->returnClass = $config['returnClass'];
    }

    /*
     * given a username and password, authenticates them against
     * some sort of backend data store.  Returns an instance of \Common\IdentityConnectorState
     * or something that inherits from it.
     */
    public function checkCredentials($username, $pass, $args = array())
    {
        if (!$this->connect($this->host, $this->username, $this->password)) {
            throw new \Common\LoggingRestException('Cannot bind to ldap host using admin credentials.', 500, "error: " . ldap_error($this->conn));
        }

        $userInfo = $this->getUserInfo($username);

        //done with this connection...
        $this->disconnect();

        //now use it to denote a state

        if ($userInfo === false) {
            //no user or multiple users found so getUserInfo returned false
            return new $this->returnClass(BAD_PASS, array());
        }

        if ($userInfo['accountExpired'] || $userInfo['disabled']) {
            //if the account is disabled by loginDisabled or loginExpirationTime
            return new $this->returnClass(ACCOUNT_DISABLED, $userInfo);
        }

        if ($userInfo['passExpired'] && $userInfo['gracesRemaining'] <= 0) {
            //if pass is expired and user is out of grace logins
            //don't check the pass because its expired...
            return new $this->returnClass(PASS_EXPIRED, $userInfo);
        }

        //attempt to validate user/pass combo
        $userBindResult = $this->connect($this->host, $userInfo['dn'], $pass);
        $errorCode = ldap_errno($this->conn);
        $this->disconnect();

        if (!$userBindResult) {
            //failed to bind using client's username and password

            if ($errorCode == 53) {
                //53 denotes locked by intruder.  error code also represents user expired and disabled
                //since we checked login expired time and logindisabled bool above
                //we can assume its locked by intruder
                $userInfo['intruderLockout'] = true;
                return new $this->returnClass(ACCOUNT_DISABLED, $userInfo);
            } elseif ($errorCode == 49) {
                //49 denotes a bad password OR [pass expired AND no more grace logins remaining]
                //since we checked the case of pass expired and no more grace logins remaining above and returned,
                //we know that this error can only be because of a bad password
                return new $this->returnClass(BAD_PASS, $userInfo);
            } elseif ($errorCode !== 0) {
                //any other errors have not been coded for
                throw new \Common\LoggingRestException("Encountered an unknown ldap error while attempting to bind with user credentials in checkCredentials (ldap err# " . $errorCode . ")", 500);
            }
        }

        //user/pass combo is valid from here on

        if ($userInfo['passExpired']) {
            //if the password is expired decrement the grace logins by 1
            //since it gets auto decremented by the tree, we dont need to write it out,
            //but we did read the value before the decrement happened
            //any negative number is bad

            if (!$userInfo['unexpiring']) {
                //graces dont matter for unexpiring users...
                $userInfo['gracesRemaining']--;
            }
        }

        return new $this->returnClass(ALL_GOOD, $userInfo);
    }

    /*
     * Resets a user's password without needing the current password.
     */
    public function adminSetPassword($username, $password, $args = array())
    {
        if (!$this->connect($this->host, $this->username, $this->password)) {
            throw new \Common\LoggingRestException('Cannot bind to ldap host using admin credentials.', 500, "error: " . ldap_error($this->conn));
        }

        $userInfo = $this->getUserInfo($username);

        //now use it to denote a state

        if ($userInfo === false) {
            //no user or multiple users found so getUserInfo returned false
            return new $this->returnClass(BAD_PASS, array());
        }

        if ($userInfo['accountExpired'] || $userInfo['disabled']) {
            //if the account is disabled by loginDisabled or loginExpirationTime
            return new $this->returnClass(ACCOUNT_DISABLED, $userInfo);
        }

        //This is where we differ from the checkCredentials logic,
        //we actually increment the grace logins if we need to,
        //whereas in checkCredentials we fail out.
        if ($userInfo['passExpired'] && $userInfo['gracesRemaining'] <= 0) {
            //if pass is expired and user is out of grace logins
            //add a grace login so that we can check the password
            $entry = array("logingraceremaining" => array(1));
            if (!ldap_modify($this->conn, $userInfo['dn'], $entry)) {
                //TODO: error out?
                //couldn't increment grace logins...
            }

        }

        //attempt to set the password
        $mod = ldap_mod_replace($this->conn, $userInfo['dn'], array('userPassword' => array($newpassword)));
        $errorCode = ldap_errno($this->conn);
        $this->disconnect();

        if ($mod) {
            //pass change was successful
            return new $this->returnClass(ALL_GOOD, $userInfo);
        }

        //error out could not set the password for whatever reason
        throw new \Common\LoggingRestException("Failed to set password for user " . $userInfo['dn'] . ".(ldap err# " . $errorCode . ")", 500);

    }

    /*
     * Sets a user's password given their current password.
     */
    public function userSetPassword($username, $oldpassword, $newpassword, $args = array())
    {
        if (!$this->connect($this->host, $this->username, $this->password)) {
            throw new \Common\LoggingRestException('Cannot bind to ldap host using admin credentials.', 500, "error: " . ldap_error($this->conn));
        }

        $userInfo = $this->getUserInfo($username);

        //now use it to denote a state

        if ($userInfo === false) {
            //no user or multiple users found so getUserInfo returned false
            return new $this->returnClass(BAD_PASS, array());
        }

        if ($userInfo['accountExpired'] || $userInfo['disabled']) {
            //if the account is disabled by loginDisabled or loginExpirationTime
            return new $this->returnClass(ACCOUNT_DISABLED, $userInfo);
        }

        //This is where we differ from the checkCredentials logic,
        //we actually increment the grace logins if we need to,
        //whereas in checkCredentials we fail out.
        if ($userInfo['passExpired'] && $userInfo['gracesRemaining'] <= 0) {
            //if pass is expired and user is out of grace logins
            //add a grace login so that we can check the password
            $entry = array("logingraceremaining" => array(1));
            if (!ldap_modify($this->conn, $userInfo['dn'], $entry)) {
                //TODO: error out?
                //couldn't increment grace logins...
            }

        }

        $this->disconnect();

        //attempt to validate user/pass combo
        $userBindResult = $this->connect($this->host, $userInfo['dn'], $oldpassword);
        $errorCode = ldap_errno($this->conn);

        if (!$userBindResult) {
            //failed to bind using client's username and password

            if ($errorCode == 53) {
                //53 denotes locked by intruder.  error code also represents user expired and disabled
                //since we checked login expired time and logindisabled bool above
                //we can assume its locked by intruder
                $userInfo['intruderLockout'] = true;
                $this->disconnect();
                return new $this->returnClass(ACCOUNT_DISABLED, $userInfo);
            } elseif ($errorCode == 49) {
                //49 denotes a bad password OR [pass expired AND no more grace logins remaining]
                //since we checked the case of pass expired and no more grace logins remaining above and returned,
                //we know that this error can only be because of a bad password
                $this->disconnect();
                return new $this->returnClass(BAD_PASS, $userInfo);
            } elseif ($errorCode !== 0) {
                //any other errors have not been coded for
                $this->disconnect();
                throw new \Common\LoggingRestException("Encountered an unknown ldap error while attempting to bind with user credentials in checkCredentials (ldap err# " . $errorCode . ")", 500);
            }
        }

        //user/pass combo is valid from here on

        //attempt to set the password
        $mod = ldap_mod_replace($this->conn, $userInfo['dn'], array('userPassword' => array($newpassword)));
        $errorCode = ldap_errno($this->conn);
        $this->disconnect();
        if ($mod) {
            //pass change was successful

            return new $this->returnClass(ALL_GOOD, $userInfo);
        }

        //error out could not set the password for whatever reason
        throw new \Common\LoggingRestException("Failed to set password for user " . $userInfo['dn'] . ".(ldap err# " . $errorCode . ")", 500);

    }

    /*
     * get some userInfo from the backend data store.
     * this info may contain things that speedbumps or authConnectors need later on
     * like graceLoginsRemaining or assuranceLevel (respectively)
     */
    public function getUserInfo($username)
    {

        // if bind successful
        // search for user
        $filter = "(&(cn=" . $username . ")(objectClass=person))";
        $attrs = array(
            "cn", "dn",
            "loginExpirationTime", "loginGraceRemaining", "loginDisabled",
            "passwordExpirationTime",
            "nspmPasswordPolicyDN", "clemsonInfoValidDate"
        );
        $userInfo = array(
            'username' => $username,
            'unexpiring' => false,
            'infoInvalid' => false,
            'passExpired' => false,
            'accountExpired' => false,
            'intruderLockout' => false,
            'disabled' => false
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

        //set up userInfo
        $userInfo['dn'] = $user['dn'];

        $now = mktime();

        if (isset($user['passwordexpirationtime'][0])) {
            // calculate the days til expiring
            $passExpDate = $this->convertFromLdapDate(substr($user['passwordexpirationtime'][0], 0, 14));

            //calculate the diff from now
            $diff = $passExpDate - time();
            $userInfo['expiringInDays'] = floor($diff / (60 * 60 * 24));

        }

        if (isset($user['loginexpirationtime'][0])) {
            //if theres an expire time set in the tree
            // calculate the timestamp that the login expires
            $loginExpDate = $this->convertFromLdapDate(substr($user['loginexpirationtime'][0], 0, 14));
            $loginDiff = $loginExpDate - time();
            if ($loginDiff <= 0) {
                // if the login is expired
                $userInfo['accountExpired'] = true;
            }
        }

        //copy graces into userinfo
        if (!isset($user['logingraceremaining'][0])) {
            $user['logingraceremaining'][0] = 0;
        }
        $userInfo['gracesRemaining'] = $user['logingraceremaining'][0];

        if (!$userInfo['unexpiring'] && isset($userInfo['expiringInDays']) && $userInfo['expiringInDays'] <= 0) {
            //if the user can expire and their pass has expired
            $userInfo['passExpired'] = true;
        }

        if (isset($user['logindisabled'][0]) && $user['logindisabled'][0] == "TRUE") {
            // if the login is disabled
            $userInfo['disabled'] = true;
        }

        //done setting up userInfo
        return $userInfo;
    }

    public function generatePassword($args = array())
    {
        //generates a random password that conforms to this connector's
        //password standards

        //args might have something like "medicade = true"
        //to denote different password policies based on the user...

        if (isset($args['ssn'])) {
            $partialPassLength = 5;
        } else {
            $partialPassLength = 10;
            $args['ssn'] = '';
        }
        //generate a password
        //first make a 40 char sha1 hash using some uniqueish strings
        $partialPass = sha1($_SERVER['REMOTE_ADDR'] . time() . "CU Temp Password Salt");

        //now pick 5 chars from that hash
        //if we were passed a SSN:
        //    the partial pass is what we send the user, but we also tell the user that
        //    their pass is xxxxx "plus the last five digits of your ssn".
        //else
        //    generate a random pass of length 10
        $partialPass = substr($partialPass, rand(0, 39 - $partialPassLength), $partialPassLength);

        //so the pass we set in the tree is actually this
        $fullPass = $partialPass . $args['ssn'];

        return $fullPass;
    }

    /*
     * sanitize a value using the attribute name.
     */
    public function sanitize($attr, $value)
    {

        //username / password strings must actually exist and be at least len 1
        //and we're also imposing a max of 200 chars for username and password...
        if (!$value || strlen($value) < 1 || strlen($value) > 200) {
            return false;
        }
        if ($attr == 'username') {
            return $this->sanitizeUsername($value);
        }

        //nothing else to sanitize if its the password
        return $value;

    }

    /*
     * convert ldap time string into php date/time
     */
    private function sanitizeUsername($username)
    {
        if (preg_match('/[()*|&@]/', $username)) {
            //shouldn't have dots in the username
            return false;
        }
        return strtolower($username);
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

    private function disconnect()
    {
        ldap_close($this->conn);
    }

    private function convertFromLdapDate($ldapDate)
    {
        //convert ldap time string into php date/time
        return mktime(substr($ldapDate, 8, 2), substr($ldapDate, 10, 2), substr($ldapDate, 12, 2), substr($ldapDate, 4, 2), substr($ldapDate, 6, 2), substr($ldapDate, 0, 4));
    }
}
