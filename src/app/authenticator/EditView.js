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

/*global QRCode*/
define(['dojo/_base/declare',
		'dojo/_base/lang',
		'dojo/dom-construct',
		'dojo/dom-class',

		'app/layout/layout',
		'app/util/BaseListItem',

		'app/util/crud/EditView'
], function(
		declare,
		lang,
		domConstruct,
		domClass,

		layout,
		BaseListItem,

		EditView
	) {
	// module:
	//     app/profile/twopass/EditView

	return declare([EditView], {
		constructor: function(){
			this.twoPassEnabled = false;
		},
		_buildFieldListItem:function(/*Object*/field,/*Object*/item,/*Object*/list){
			// summary: 
			//     Builds out a single list item
			// field:
			//     the current field object to render
			// item:
			//     the item object
			// list:
			//     the List widget to add newly created listItems to.

			if(field.getType() !== 'QRCode'){
				if(field.getId() === "use2pass"){
					this.twoPassEnabled = field.getValue();
				}
				return EditView.prototype._buildFieldListItem.apply(this,[field, item, list]);
			}

			var li, value, attr,
				label = field.getLabel();

			li = new BaseListItem({
				text: label
			});

			value = this.displayField(field);
			attr = 'rightTextNode';
			
			if (value) {
				li.set(attr, value);
			}
			li.startup();
			
			list.addChild(li);


			field.getListItem = function(){
				return li;
			};
		},

		buildItemDetailList: function(/*Object*/item){
			EditView.prototype.buildItemDetailList.apply(this,[item]);
			var text, textKey = 'toEnable';
			if(this.twoPassEnabled){
				textKey = 'toDisable';
			}

			text = this.getText(textKey);

			domConstruct.create('h3',{innerHTML: text},this.content.domNode,'first');
			layout.initializeSelects();

		},

		displayTextFieldInput: function(/*Object*/ field) {
			var node = this.inherited(arguments);

			node.onkeypress = lang.hitch(this,function(e){
				if (!e){
					e = window.event;
				}

				var keyCode = e.keyCode || e.which;
				if (keyCode == '13'){
					// Enter pressed
					this.save(e);
					return false;
				}
			});

			return node;
		},

		displayQRCodeField: function(/*Object*/field){
			this.qrdiv = domConstruct.create('div');
			this.qrcoder = new QRCode(this.qrdiv);
			this.qrcoder.makeCode(field.getValue());
			return this.qrdiv;
		},

		validateSwitchFieldInput: function(/*Object*/ itemField, /*Object*/ renderedField){
			//switches can't be invalid, they're either some string like 'on' or 'off'
			if(itemField.getValue() === renderedField.getValue()){
				var onOff = 'off';
				if(itemField.getValue() === true){
					onOff = 'on';
				}
				return "There is nothing to save. Did you want to turn "+onOff+" the Authenticator switch?";
			}
		},

		validatecodeFieldInput: function(/*Object*/ itemField, /*Object*/ renderedField){
			var value = renderedField.getValue();
			var reg = /^\d+$/;
			if(value.length != 6 || !reg.test(value)){
				return "Verification Code must be 6 numbers.";
			}
		}
	});
});
