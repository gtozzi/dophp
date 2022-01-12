/**
 * Customized datatable script/wrapper
 */
class DoPhpDataTable {
	/** The data prop used to retrieve this instance from the dom element */
	static domDataPropName = 'dophpdatatable';
	/** The selected class */
	static selClass = 'fa-check-square-o';
	/** The deselected class */
	static deselClass = 'fa-square-o';

	/**
	 * Instantiate the wrapper
	 *
	 * @param element The original DataTable element as returned by $('#tableId')
	 * @param settings object:
	 *                 - selectable Whether this table is selectable
	 *                 - ajaxId
	 *                 - ajaxUrl
	 *                 - btnKey
	 *                 - totKey
	 *                 - rbtns: row button definitions
	 *                 - cols: Column definitions
	 *                 - initOpts: extra otions passed straight to DataTable
	 *                 - order: default order
	 */
	constructor(element, settings) {
		// Custom selection handling
		this.selectedItems = new Set();
		this.selectedAll = false;

		this.selectable = settings['selectable'];
		this.ajaxId = settings['ajaxId'];
		this.ajaxUrl = settings['ajaxURL'];

		this.btnKey = settings['btnKey'];
		this.totKey = settings['totKey'];
		this.rbtns = settings['rbtns'];
		this.cols = settings['cols'];
		this.dFilterDivider = settings['dFilterDivider'];

		let initOpts = settings['initOpts'];

		initOpts['ajax'] = {
			url:         this.ajaxUrl,
			type:        'POST',
			data:        prepareServerData,
		};

		initOpts['initComplete'] = (settings, json) => {
			// Save the fixed elements vertical size used to compute table height on window resize
			window.vSizeElementsOutsideTableHeight = $('body').outerHeight(true) - $('.dataTables_scrollBody').outerHeight(true);
			// Set datatable size to cover all free space
			window.resizeDatatable();
		};

		// Default order
		initOpts['order'] = settings['order'];

		initOpts['columns'] = [
			// Buttons column
			{
				orderable: false,
				data: this.btnKey,
				name: this.btnKey,
				render: ( data, type, row, meta ) => {
					if( ! type ) // Datatable requests unmodified data
						return data;

					// No buttons in total by now
					if( row[this.totKey] )
						return 'Tot';

					let html = ''
					if( this.selectable ) {
						let cls = this.isRowSelected(this.table.row(row)) ? DoPhpDataTable.selClass : DoPhpDataTable.deselClass;
						html += '<span class="fa ' + cls + ' selectbox"></span>';
					}
					for( const [name, btn] of Object.entries(this.rbtns) ) {
						if( data.includes(name) ) {
							let url = btn.url;
							for(let key in row)
								url = url.replace("{{"+key+"}}", row[key]);

							let href;
							if( btn.post )
								href = "javascript:onDTPostRowButton('" + encodeURI(name) + "', " + row.id + ")";
							else
								href = url;

							// Trailing space to separate next
							html += `<a class="fa ${btn.icon}" href="${href}" title="${btn.label}"></a> `;
						}
					}
					return html;
				},
				className: 'dt-body-nowrap data-table-bcol',
			},
			// Will append rest of the columns later
		];

		// Initial search values
		initOpts['searchCols'] = [
			null, // Buttons column
			// Will append the rest later
		];

		// Callback for custom selection
		initOpts['createdRow'] = ( row, data, dataIndex ) => {
			// Keeps selection display on redraw
			this.updateSelectRow(this.table.row(row));
		};

		// Callback for info row redraw
		initOpts['infoCallback'] = ( settings, start, end, max, total, pre ) => {
			if( this.selectable ) {
				// Adds info about the selection
				let selInfo = ', ' +
					'<span id="data-table-sel-count">' +
					( this.selectedAll ? 'tutti' : this.selectedItems.size.toString() )
					+ '</span> selezionati';
				pre += selInfo;
			}

			return pre;
		};

		// Any other column
		for( const col of this.cols ) {
			let colDef = {
				data: col.id,
				name: col.id,
				className: 'data-table-col',
				visible: col.visible,
				//width: "200px",
				render: ( data, type, row, meta ) => {
					if( ! type ) // Datatable requests unmodified data
						return data;

					// Cast data to user-friendly type

					if( data === undefined ) {
						// Server omitted the cell
						return '';
					}
					if( data === null ) {
						// Null is considered object in JS
						return '-';
					}

					let format = col.format ? col.format : typeof data;

					switch( format ) {
					case 'string':
						return data;
					case 'boolean':
					case 'number':
					case 'undefined':
						return getValueRepr(data);
					case 'object':
						return getObjectRepr(data);
					case 'currency':
						return getCurrencyRepr(data);
					default:
						return data;
					}
				},
			};
			if( col.format == 'number' || col.format == 'currency' )
				colDef['className'] = 'dt-body-right';

			initOpts['columns'].push(colDef);

			let searchDef = null;
			if( col.search )
				searchDef = { search: col.search, regex: col.regex, };
			initOpts['searchCols'].push(searchDef);
		}

		this.table = element.DataTable(initOpts);

		// Saves the current instance. Obtain it with $('#myDataTable').DoPhpDataTable()
		element.data(DoPhpDataTable.domDataPropName, this);
	}


	// ============================= Selection methods =============================

	/**
	 * Updates select count (custom selection api)
	 */
	updateSelectCount() {
		if( this.selectedAll )
			$('#data-table-sel-count').html('tutti');
		else
			$('#data-table-sel-count').html(this.selectedItems.size.toString());
	}

	/**
	 * Updates the select all box (custom selection api)
	 */
	updateSelectAllBox() {
		let allSelected = true;
		if( this.selectedAll )
			allSelected = true;
		else
			this.table.rows().every( ( rowIdx, tableLoop, rowLoop ) => {
				let data = this.table.row(rowIdx).data();
				if( ! this.selectedItems.has(data.id) ) {
					allSelected = false;
					return false; // Not sure if this works
				}
			} );

		let box = $('#selectAllBox');
		if( allSelected ) {
			box.addClass(DoPhpDataTable.selClass);
			box.removeClass(DoPhpDataTable.deselClass);
		} else {
			box.addClass(DoPhpDataTable.deselClass);
			box.removeClass(DoPhpDataTable.selClass);
		}
	}

	/**
	 * Refresh selection for a single row
	 *
	 * @param row The row
	 */
	updateSelectRow(row) {
		let btnCell = this.table.cell(row, 0);
		let selBox = $(btnCell.node()).find('span.selectbox');

		if( this.isRowSelected(row) ) {
			$(row.node()).addClass('selected');
			selBox.removeClass(DoPhpDataTable.deselClass);
			selBox.addClass(DoPhpDataTable.selClass);
		} else {
			$(row.node()).removeClass('selected');
			selBox.removeClass(DoPhpDataTable.selClass);
			selBox.addClass(DoPhpDataTable.deselClass);
		}
	}

	/**
	 * Tells whether given row is selected (custom selection api)
	 *
	 * @param row: The datatables row object
	 */
	isRowSelected(row) {
		if( this.selectedAll )
			return true;

		let data = row.data();
		return this.selectedItems.has(data.id);
	}

	/**
	 * Selects a row (custom selection api)
	 *
	 * @param row: The datatables row object
	 */
	selectRow(row) {
		if( this.isRowSelected(row) )
			return;

		let data = row.data();
		this.selectedItems.add(data.id);
		this.updateSelectRow(row);
		console.log('Row selected id', data.id, this.selectedItems);
	}

	/**
	 * Deselects a row (custom selection api)
	 *
	 * @param row: The datatables row object
	 */
	deselectRow(row) {
		if( ! this.isRowSelected(row) )
			return;

		let data = row.data();
		this.selectedItems.delete(data.id);
		if( this.selectedAll ) {
			this.selectedAll = false;
			this.table.rows().every( ( rowIdx, tableLoop, rowLoop ) => {
				this.updateSelectRow(this.table.row(rowIdx));
			} );
		}
		this.updateSelectRow(row);
		console.log('Row unselected id', data.id, this.selectedItems);
	}

	/**
	 * Called when the "select all" box has been clicked
	 *
	 * If all rows are selected, unselected them; select all otherwise
	 */
	onSelectAllBox() {
		if( this.selectable ) {
			this.selectedAll = ! this.selectedAll;

			// Apply selection loop
			this.table.rows().every( ( rowIdx, tableLoop, rowLoop ) => {
				this.updateSelectRow(this.table.row(rowIdx));
			} );

			// Update count
			this.updateSelectCount();
			this.updateSelectAllBox();
		}
	}

	/**
	 * Toggle a row selection (custom selection api)
	 *
	 * @param row: The datatables row object
	 */
	toggleRow(row) {
		if( this.isRowSelected(row) )
			this.deselectRow(row);
		else
			this.selectRow(row);
	}

	/**
	 * Listen to row click event
	 */
	onRowClick (el) {
		if( ! this.selectable )
			return;
		let row = table.row(el);

		this.toggleRow(row);
		this.updateSelectCount();
		this.updateSelectAllBox();
	}

	/**
	 * Updates the export url when filter is changed
	 */
	 updateDataTableUrls() {
		// Read the filter and put it in the $_GET url
		let filters = {};
		let iter = 0;
		let nFilters = $('input.data-table-filter').length
		$('input.data-table-filter').each(function() {
			iter++;
			if (iter > nFilters/2)
				return false;
			let el = $(this);
			let coln = el.data('coln');
			let val = encodeURIComponent(el.val());
			if( coln && val)
				filters[coln] = val;
		});
		let filterargs = '';
		for( let coln in filters ) {
			let val = filters[coln];
			filterargs += `&columns[${coln}][search][value]=${val}`;
		}

		let href = this.ajaxUrl + '&export=xlsx';
		if( this.ajaxId !== null )
			href += `&ajaxid=${this.ajaxId}`;
		href += filterargs;
		$('#data-table-export-url').attr('href', href);
	}

	// ============================= Column filters methods =============================


	/**
	 * Called when it is time to update the filter
	 */
	 updateFilter(input, val) {

		let el = $(input);
		let coln = el.data('coln');
		let type = el.data('type');

		if(coln == undefined)
			return;

		// Convert search values
		if( type == 'boolean' ) {
			val = val.toLowerCase();
			if (['s', 'si', 'sì', 'y', 'ye', 'yes'].includes(val))
				val = '1';
			else if (['n', 'no'].includes(val))
				val = '0';
		}

		el.data('timer', '');
		console.log("filtering...", val);

		console.log('Applying new filter for col', coln, val);

		this.table.column(coln).search(val).draw();
	}

	/**
	 * Realtime Filter Updater
	 */
	realtimeFilter(input) {

		var newFilter = "";
		let el =  $('#ag-dt-dtFilt-'+$(input).data('coln'));

		if($('#wp-dfilt-start').val() != "")
			newFilter = $('#wp-dfilt-start').val();
		if($('#wp-dfilt-end').val() != "")
			newFilter = newFilter + this.dFilterDivider + $('#wp-dfilt-end').val();

		// Ignore duplicated changes
		if( el.data('lastval') == newFilter )
			return;

		el.val(newFilter);
		el.data('lastval', newFilter);
		this.updateFilter(el, newFilter);
	}

	filterKeyUp(event){
		var code = event.keyCode || event.which;
		// Avoid key that not have to activate the filter (9 = TAB, 16 = SHIFT, 20 = CAPS LOCK)
		if (code != '9' && code != '16' && code != '20')
		{
			this.filterChanged(event.target);
			wpHideDateWidget();
		}
	}

	/**
	 * Called when the filter input changed
	 */
	 filterChanged(input) {
		// Gestisce i filtri inseriti da tastiera
		let updDelay = 300;

		let el = $(input);
		let val = el.val();

		// Ignore duplicated changes
		if( el.data('lastval') == val )
			return;

		// Remove old timer
		if( el.data('timer') ) {
			console.log('Clearing previous filter timer', el.data('timer'));
			clearTimeout(el.data('timer'));
		}

		// Process the change
		let timer = setTimeout(updateFilter, updDelay, input, val);
		el.data('lastval', val);
		el.data('timer', timer);
		console.log('New timer set for col', el.data('coln'), timer);
	}

}


/**
 * Returns the DoPhp DataTable instance when jQuery is loaded
 */
$.fn.DoPhpDataTable = function(){
	return this.data(DoPhpDataTable.domDataPropName);
};
