dojo-mama
=========

Set up
------

Install Node.js: http://nodejs.org.

Install Composer: https://getcomposer.org/download/

Install Gulp and Bower:

	$ npm install -g gulp bower

Install app dependencies:

	$ make install

Development
-----------

Compile app resources and watch for changes:

	$ gulp

Building the App
----------------

Build the project and create a release tarball:

	$ make

For additional gulp tasks, see gulpfile.js.

Server Variables
----------------

These variables are required for the multi factor auth and login sections:

The ldap server

	$ SetEnv MFA_LDAP_HOST "ldaps://ldap.server.url.com"

The ldap proxy user with rights to read the ldap attr

	$ SetEnv MFA_LDAP_USER "cn=mfa,ou=Proxy,o=user"

The password for the above user

	$ SetEnv MFA_LDAP_PASS "***********"

The root ldap container where all users reside

	$ SetEnv MFA_LDAP_SEARCH_ROOT "ou=users,ou=people,o=cuid"

The multivalued attribute that holds the multi factor auth URI's

	$ SetEnv MFA_LDAP_ATTR "MfaKeyUri"

The issuer label inside the multi factor auth URI

	$ SetEnv MFA_ISSUER_LABEL_PREFIX "com.example"

The issuer parameter inside the multi factor auth URI

	$ SetEnv MFA_ISSUER_PARAMETER "Examplei%20Corp%20Inc."


Notes on the MFA URI
--------------------

The MFA URI is built like this:

	$ "otpauth://totp/".$_SERVER['MFA_ISSUER_LABEL_PREFIX'].":".$_SESSION['username']."?secret=".$mfaSecret."&issuer=".$_SERVER['MFA_ISSUER_PARAMETER'];

And when built with the above issuer prefix and issuer parameter:

	$ otpauth://totp/com.example:bobuser?secret=EHSLXQMU2O4JNYWB&issuer=Examplei%20Corp%20Inc.

License
-------

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

