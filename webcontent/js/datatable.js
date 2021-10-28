
/**
 * Customized datatable script/wrapper
 */
class DoPhpDataTable {
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
		this.selectable = settings['selectable'];
		this.ajaxId = settings['ajaxId'];
		this.ajaxUrl = settings['ajaxUrl'];
		this.btnKey = settings['btnKey'];
		this.totKey = settings['totKey'];
		this.rbtns = settings['rbtns'];
		this.cols = settings['cols'];

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
						let cls = this.table.isRowSelected(this.table.row(row)) ? DoPhpDatathis.selClass : DoPhpDataTable.deselClass;
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
							html += `<a class="fa ${btn.icon}" href="${url}" title="${btn.label}"></a> `;
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
			this.table.updateSelectRow(this.table.row(row));
		};

		// Callback for info row redraw
		initOpts['infoCallback'] = ( settings, start, end, max, total, pre ) => {
			if( this.selectable ) {
				// Adds info about the selection
				let selInfo = ', ' +
					'<span id="data-table-sel-count">' +
					( this.table.selectedAll ? 'tutti' : this.table.selectedItems.size.toString() )
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
	}

}
