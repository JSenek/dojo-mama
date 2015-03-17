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
namespace Common;
class IdentityConnectorState{

	private $state = -1;
	private $success = false;
	public $userInfo;  //to store graces remainig, days til expire, supplimental messages etc

	public function __construct ( $s, $i = array()){
		$this->state = $s;
		$this->userInfo = $i;
		switch($s){
			case ALL_GOOD:

				$this->success = true;
			break;
			case BAD_PASS:

				$this->success = false;
			break;
			case ACCOUNT_DISABLED:

				$this->success = false;
			break;
			case PASS_EXPIRED:

				$this->success = false;
			break;
			case PASS_ALMOST_EXPIRED:

				$this->success = false;
			break;
			default:
				throw new \Common\LoggingRestException("Unknown state ".$state, 500);
			break;
		}

	}

	public function isSuccess(){
		return $this->success;
	}

	public function getState(){
		return $this->state;
	}

}
?>