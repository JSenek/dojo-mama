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
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
*/
/*jshint ignore:start */
// node compatability
if (typeof define !== 'function') { define = require('amdefine')(module); }
/*jshint ignore:end */

// app configuration
define({
	networkErrorMessage: "There seems to be a network issue.",
	networkTimeout: 15000,
	modules: {
		index: {
			moduleId: 'app/layout/index/Module'
		},
		demo: {
			title: 'Demo',
			moduleId: 'app/demo/Module'
		},
		crudExample: {
			title: 'Crud Example',
			moduleId: 'app/crudExample/Module',
			modules: ['veggies', 'fruits']
		},


		/* Module Groups */
		veggies: {
			title: 'Veggies',
			moduleId: 'app/crudExample/veggies/Module',
			selectedPrimaryNavItem: 'Veggies',
			baseServiceUrl: 'srv/crudExample/'
		},

		fruits: {
			title: 'Fruits',
			moduleId: 'app/crudExample/fruits/Module',
			selectedPrimaryNavItem: 'fruits',
			baseServiceUrl: 'srv/crudExample/'
		},
		'authenticator': {
			title: "Authenticator Management",
			moduleId: 'app/authenticator/Module',
			selectedPrimaryNavItem: 'profile',
			baseServiceUrl: '/srv/Authenticator/'
		},

	}
});
