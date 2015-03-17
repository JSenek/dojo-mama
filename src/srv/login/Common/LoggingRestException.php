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

class LoggingRestException extends \Exception
{

	public function __construct($errorMessage,$httpStatusCode, $identity = null)
	{
		$message = "";
		
		if(isset($httpStatusCode)){
			$message .= 'RestException code: "'.$httpStatusCode.'"';
		}

		if($errorMessage){
			$message = $message . '; Message: "'. $errorMessage.'"';
		}

		if($identity){
			$message = $message . '; Identity: "'.$identity.'"';
		}

		error_log($message);

		parent::__construct ( $errorMessage, $httpStatusCode );
	}
}
?>