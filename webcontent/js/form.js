

/**
 * Form control and handling functions
 */
var formUtil = {

	/**
	 * Handles a multiSelect field
	 */
	multiSelect: {
		/**
		 * Multiselect widget add function
		 */
		add: function(selectEl) {
			let option = selectEl.find('option:selected');
			let val = option.val();
			let optId = option.data('optid');

			$('#'+optId+'_selected').val('1');
			$('#'+optId).show();
			selectEl.val('');
			option.prop('disabled', true);
		},

		/**
		 * Multiselect widget del function
		 */
		del: function(buttonEl) {
			let id = buttonEl.data('id');
			let optId = buttonEl.data('optid');
			let selId = buttonEl.data('selid');

			$('#'+optId+'_selected').val('0');
			$('#'+optId).hide();
			$('#'+selId).find('option[value="'+id+'"]').prop('disabled', false);
		},
	},

};


/**
 * Handles a single form control
 */
formUtil.FormControlHandler = class {

	/**
	 * The constructor for the handler
	 *
	 * options are read from data- attributes
	 *
	 * @param el the HTML element
	 */
	constructor(el) {
		this.el = $(el);

		if( ! this.requireOpts(['name', 'form', 'namespace']) )
			return;

		this.name = this.el.data('name');
		this.namespace = this.el.data('namespace');
		this.form = this.el.data('form');
		this.valurl = this.el.data('valurl');

		this.valajax = null;

		console.log('adding form handler for input', this.el.attr('name'), el);
		el.data('handler', this);
		let that = this;
		el.on('input', function(ev) { return that.onInput(ev); } );
		// ag
		let __kupValidatorTout=null;
		el.on('keyup', function(ev){
			if(__kupValidatorTout){
				clearTimeout(__kupValidatorTout);
			}
			__kupValidatorTout= setTimeout(function(ev){
				return that.onTout(ev);
			},500);
		});
		el.on('blur', function(ev) { return that.onBlur(ev); });
		el.on('change', function(ev) { return that.onChange(ev); });
		el.on('focus', function(ev) { return that.onFocus(ev); });
	}

	/**
	 * Check for required options in element
	 *
	 * @param req array: list of required option names
	 * @return true if the requirements are met
	 */
	requireOpts(req) {
		for (let k in req)
			if ( typeof this.el.data(req[k]) === 'undefined' || this.el.data(req[k]) === null ) {
				console.error('missing "'+req[k]+'" option for field', this.el.attr('name'), this.el);
				return false;
			}

		return true;
	}

	onFocus(ev) {
	}

	onInput(ev) {
	}

	onTout(ev) {
		this.onBlur(ev);
	}

	onBlur(ev) {
		if( this.el.is('select') )
			return;

		this.validate();
	}

	onChange(ev) {
		if( ! this.el.is('select') )
			return;

		this.validate();
	}

	/**
	 * Contact the server to updates the field's validation classes
	 */
	validate() {
		const V_NOTNEEDED = -1;
		const V_ERROR = 0;
		const V_SUCCESS = 1;

		if ( ! this.valurl ) {
			console.warn('validation url not set for input', this.el.attr('name'), this.el);
			return;
		}

		// Cancel any previous validation request
		if( this.valajax && this.valajax.readyState != 4 )
			this.valajax.abort();

		console.log('validating field', this.el.attr('name'), '…');

		// Build the data
		let data = {};
		let l = data;
		for (let k in this.namespace) {
			l[this.namespace[k]] = {};
			l = l[this.namespace[k]];
		}
		l[this.name] = this.el.val();
		data.form = this.form;

		// And finally submit it
		let that = this;
		this.valajax = $.ajax({
			method: 'PATCH',
			url: this.valurl,
			dataType: 'json',
			data: JSON.stringify(data),
			contentType: 'application/json',

		}).done(function( msg ) {
			console.log('Validation data received', msg);
			let mydata = msg[that.el.attr('id')];

			let col = $('#' + that.el.attr('id') + '_col');
			let fb  = $('#' + that.el.attr('id') + '_feedback');

			switch (mydata.status) {
			case V_NOTNEEDED:
				col.removeClass('has-danger');
				col.removeClass('has-success');
				that.el.removeClass('is-invalid');
				that.el.removeClass('is-valid');
				fb.removeClass('invalid-feedback');
				fb.removeClass('valid-feedback');
				break;
			case V_ERROR:
				col.addClass('has-danger');
				col.removeClass('has-success');
				that.el.addClass('is-invalid');
				that.el.removeClass('is-valid');
				fb.addClass('invalid-feedback');
				fb.removeClass('valid-feedback');
				break;
			case V_SUCCESS:
				col.removeClass('has-danger');
				col.addClass('has-success');
				that.el.removeClass('is-invalid');
				that.el.addClass('is-valid');
				fb.removeClass('invalid-feedback');
				fb.addClass('valid-feedback');
				break;
			}
			that.el.data('vstatus', mydata.status);

			if (mydata.feedback) {
				fb.text(mydata.feedback);
				fb.show();
			} else {
				fb.text('');
				fb.hide();
			}

		}).fail(function( jqXHR, textStatus ) {
			console.error('error while validating field', that.el.attr('name'), textStatus);

		});
	}

};


/**
 * Handles a currency form control
 */
formUtil['CurrencyFormControlHandler'] = class extends formUtil['FormControlHandler'] {

	constructor(el) {
		super(el);

		this.decSep = this.el.data('decsep');
		this.thoSep = this.el.data('thosep');
		this.decDigits = this.el.data('decdigits');

		if( ! this.decSep || ! this.thoSep || ! this.decDigits )
			console.error('"decsep", "thosep" and "decdigits" options are all required', el);

		this.allowed = new RegExp('[0-9' + this.decSep + ']', 'g');
		this.forbidden = new RegExp('[^0-9' + this.decSep + ']', 'g');
	}

	onFocus(ev) {
		// Remove thousands separator
		this.el.val(this.sanitize(this.el.val()));
	}

	onInput(ev) {
		let val = this.el.val();
		let ss = this.el[0].selectionStart;
		let left = val.slice(0, ss);
		let right = val.slice(ss);
		let sleft = this.sanitize(this.replThoWithDecSep(left));
		let sright = this.sanitize(this.replThoWithDecSep(right));
		let newval = sleft + sright;

		if (val != newval) {
			this.el.val(newval);
			this.el[0].setSelectionRange(sleft.length, sleft.length);
		}
	}

	onTout(ev) {
		// Do nothing on timeout (overrides default behavior), wait for onBlur
	}

	onBlur(ev) {
		// Re-add thousand separators
		let val = this.el.val();
		val = this.sanitize(val);

		let num = this.parse(val);

		let formatted = this.format(num);
		this.el.val(formatted);
		this.el[0].setSelectionRange(formatted.length - this.decDigits - 1, formatted.length - this.decDigits) - 1;
		this.validate();
	}

	/**
	 * Replaces thousands separator with decimal separator
	 *
	 * @return string: The replaces string
	 */
	replThoWithDecSep(str) {
		return str.replace(this.thoSep, this.decSep);
	}

	/**
	 * Remove all unecessary chars from string
	 *
	 * @param str string: The input string
	 * @return string: The cleaned string
	 */
	sanitize(str) {
		return str.replace(this.forbidden, '');
	}

	/**
	 * Parse a sanitized value into a number
	 *
	 * @param str string: The input string
	 * @return float: The parsed float (or null)
	 */
	parse(str) {
		if ( ! str.length )
			return null;
		if (typeof str != 'string') {
			console.error('Input is not a srtring but', typeof str, str);
			return null;
		}

		// Replace decimal separator with dot
		str = str.replace(this.decSep, '.');
		let num = parseFloat(str);

		if (num === NaN)
			return null;
		return num;
	}

	/**
	 * Formats a number into string
	 *
	 * @param num float: The input number
	 * @return string: The formatted string
	 */
	format(num) {
		if (num === null)
			return '';
		if (typeof num != 'number') {
			console.error('Input is not a number but', typeof num, num);
			return '';
		}

		let str = num.toFixed(this.decDigits);

		// Adds thousands separator
		let x = str.split('.');
		let rgx = /(\d+)(\d{3})/;
		while (rgx.test(x[0])) {
			x[0] = x[0].replace(rgx, '$1' + this.thoSep + '$2');
		}
		str = x[0] + this.decSep + x[1];

		return str;
	}

};


(function( $ ) {
	/**
	 * Registers the jQuery handler plugin
	 *
	 * reads the type from data-type
	 */
	$.fn.formhandle = function() {
		let type = this.data('type');
		if ( ! type ) {
			console.error('Missing type attribute for field', this.attr('name'), this);
			return;
		}

		let handler = type.charAt(0).toUpperCase() + type.substr(1) + 'FormControlHandler';
		if ( formUtil[handler] )
			new formUtil[handler](this);
		else
			new formUtil['FormControlHandler'](this);

		return this;
	};
}( jQuery ));
