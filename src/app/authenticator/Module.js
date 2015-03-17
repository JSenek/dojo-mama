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

define(["dojo/_base/declare",
		"app/util/crud/Module",
		'app/util/crud/DisplayView',
		"app/util/crud/CreateView",
		'app/util/crud/ListView',
		"app/authenticator/EditView"
], function(declare, Module, DisplayView, CreateView, ListView, EditView) {
	"use strict";
	return declare([Module], {
		'class': 'authenticatorModule',
		// serviceUrl: String
		//     The API endpoint for CRUD data requests
		serviceUrl: 'Authenticator',
		// dataType: String
		//     The type of CRUD data, used with app/profile/crud/TextMixin
		dataType: 'Authenticator',
		listItemRightText: 'use2pass',
		
		createViews: function(options) {
			// summary:
			//     Create and registers the CRUD views
			this.rootView = new ListView(options);
			this.registerView(this.rootView);
			this.displayView = new DisplayView(options);
			this.registerView(this.displayView);
			this.editView = new EditView(options);
			this.registerView(this.editView);
			this.createView = new CreateView(options);
			this.registerView(this.createView);
		}
	});
});
