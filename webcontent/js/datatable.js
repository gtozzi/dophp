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
	 *                 - dFilterDivider: filter separator character
	 *                 - texts: named array of localized strings
	 *                 - sfilter: superfilters definitions (id: name)
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
		this.sfilter = settings['sfilter'];

		let initOpts = settings['initOpts'];

		initOpts['ajax'] = {
			url:         this.ajaxUrl,
			type:        'POST',
			data:        (data, settings) => {
				this.prepareServerData(data, settings);
			}
		};

		initOpts['initComplete'] = (settings, json) => {
			// Save the fixed elements vertical size used to compute table height on window resize
			window.vSizeElementsOutsideTableHeight = $('body').outerHeight(true) - $('.dataTables_scrollBody').outerHeight(true);
			// Set datatable size to cover all free space
			this.resizeDatatable();
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
		 '</span> selezionati';
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

		// Selectors
		this.table.on( 'draw', () => {
			$(".dtbl-buttons-container").html(
				'<a id="data-table-export-url" class="dtbl-buttons-itm">'
				+ '<span class="fa fa-file-excel-o"></span> ' + settings['texts']['exportLink'] + '</a>');

			this.updateDataTableUrls();
		} );

		// change opacity for table body while processing data
		this.table.on('processing.dt', (e, settings, processing) => {
			let tableID=this.table.tables().nodes().to$().attr('id');
			if (processing) {
				$("#"+tableID+"_wrapper .dataTables_scroll tbody").css("opacity","0.2");
			} else {
				$("#"+tableID+"_wrapper .dataTables_scroll tbody").css("opacity","1");
			}
		});

		// Listen to row click event
		$(this.table.table().body()).on('click', 'tr', (event) => { this.onRowClick($(event.target).closest('tr')) });

		// ADDED WP ELEMENTS

		$('.wp-dfilt-dpck').wrap('<span class="deleteicon" />').after($('<span/>').click((event) => {
			$(event.target).prev('input').val('').trigger('change');
			event.stopPropagation();
		}));

		// Reset month/year selection
		$('.wp-date-filt-univCont').after($('<button class="deleteicon" id="m-y-deleteicon"></button>').click(() => {
			this.filterResetMonthYearSelection();
		}));

		// block search when the user click on the column filter
		$(".data-table-filter").click(function(){ return false; })

		$(".wp-dfilt-dpck").datepicker({
			language: "it",
			autoclose: true,
			dateFormat: "dd.mm.yyyy",
			format: "dd.mm.yyyy",
		});

		$(".wp-date-filter-cont .wpdf_close, .wp-date-filter-head .wp-close").click(() => {
			this.wpHideDateWidget();
		});

		$(".wp-date-filter-head .wp-minimize").click(() => {
			this.toggleDateFilterWindowMinification();
		});

		$(".wp-date-fiter-tab-cont .wp-date-filter-tab").click((event) => {
			this.switchDateFilterActiveTab($(event.target));
		});

		$(".wp-date-filt-mthCont .wp-mthUnit, .wp-date-filt-yeaCont .wp-yeaUnit").click((event) => {
			this.onDateFilterRangeClick($(event.target));
		});

		// ./ADDED WP ELEMENTS

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
		let row = this.table.row(el);

		this.toggleRow(row);
		this.updateSelectCount();
		this.updateSelectAllBox();
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
			this.wpHideDateWidget();
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
		let timer = setTimeout(() => { this.updateFilter(input, val) }, updDelay);
		el.data('lastval', val);
		el.data('timer', timer);
		console.log('New timer set for col', el.data('coln'), timer);
	}

	/**
	 * Reset month/year selection
	 */
	filterResetMonthYearSelection() {
		$('.wp-mthUnit.wp-active').removeClass('wp-active');
		$('.wp-mthUnit.wp-range').removeClass('wp-range');
		$('.wp-yeaUnit.wp-active').removeClass('wp-active');
		$('.wp-yeaUnit.wp-range').removeClass('wp-range');

		var el = $('#ag-dt-dtFilt-'+$('.wp-date-filt-univCont').data('coln'));
		this.updateFilter(el, "");
		el.data('lastval', "");
	}

	/**
	 * Toggle date filter window minification
	 */
	toggleDateFilterWindowMinification() {
		if($(".wp-date-filter-cont").hasClass("wp-closed")){
			$(".wp-date-filter-cont").removeClass("wp-closed");
		}
		else{
			$(".wp-date-filter-cont").addClass("wp-closed");
		}
	}

	/**
	 * Hide date widget
	 */
	wpHideDateWidget() {
		if(!($(".wp-date-filter-cont").hasClass("do-hide")))
			$(".wp-date-filter-cont").addClass("do-hide");
	}

	/**
	 * Switch date filter active tab and form content
	 */
	switchDateFilterActiveTab(elem) {
		var elID=elem.attr("id");
		var formID = elID.replace("tab-","");

		$(".wp-date-filter-body .wp-date-filter-form").removeClass("wp-active");
		$(".wp-date-filter-body .wp-date-filter-form.form-"+formID).addClass("wp-active");

		$(".wp-date-filter-body .wp-date-filter-tab").removeClass("wp-active");
		$(".wp-date-filter-body .wp-date-filter-tab#tab-"+formID).addClass("wp-active");
	}

	/**
	 * Called when months/years container or buttons are clicked to select a range
	 */
	onDateFilterRangeClick(elem) {
		$(".wp-date-filt-mthCont .wp-mthUnit, .wp-date-filt-yeaCont .wp-yeaUnit")
			.removeClass("wp-range")

		if(elem.hasClass("wp-active")){
			elem.removeClass("wp-active");
		} else {

			// check if selected dates are more than one, if so
			// get the first and last selected items
			var currParent = elem.parents(".wp-date-filt-univCont");
			var selectedElem = currParent.children(".wp-active");

			elem.addClass("wp-active");

			if((selectedElem.length+1)>1){
				let firstElem=selectedElem.first();
				let lastElem=selectedElem.last();

				let firstElemID =$(firstElem).attr("id");
				let lastElemID =$(lastElem).attr("id");

				$(".wp-date-filt-mthCont .wp-mthUnit, .wp-date-filt-yeaCont .wp-yeaUnit")
					.removeClass("wp-range wp-active")

				// last click is after lastSelection
				if(elem.prevAll("div#"+lastElemID).length) {
					$(firstElem).addClass("wp-active");
					$(firstElem).nextUntil("#"+elem.attr("id")).addClass("wp-range");
					elem.addClass("wp-active");
				} else if(elem.prevAll("div#"+firstElemID).length) {
						$(firstElem).addClass("wp-active");
						$(firstElem).nextUntil("#"+elem.attr("id")).addClass("wp-range");
						elem.addClass("wp-active");
					} else {
						elem.addClass("wp-active");
						elem.nextUntil("#"+lastElemID).addClass("wp-range")
						$(lastElem).addClass("wp-active");
					}
			}
		}

		var filterString="";
		var monthsList=[];
		var yearsList=[];
		var activeTab = $(".wp-date-filter-tab.wp-active").attr("id").replace("tab-","");

		// fill filter_string with user input
		switch(activeTab){
			//month
			case "2":
				$(".wp-mthUnit.wp-active").each(function(){
					monthsList.push($(this).data("year-month"));
					filterString = monthsList.reverse().join(this.dFilterDivider);
				});
			break;

			//year
			case "3":
				$(".wp-yeaUnit.wp-active").each(function(){
					yearsList.push($(this).data("year"));
					filterString = yearsList.reverse().join(this.dFilterDivider);
				});
			break;

		}

		// get current colon id
		var currColNo = $(".wp-date-filter-cont #wp-date-filter-colNo").val();
		var currFilter = document.getElementById("ag-dt-dtFilt-"+currColNo);

		// fill given date filter with the search_string
		$("#ag-dt-dtFilt-"+currColNo).val(filterString);

		// store the identifier of the used date_tab for the current used filter
		$("#ag-dt-dtFilt-"+currColNo).attr("data-seltab",activeTab);

		this.updateFilter(currFilter, filterString);

	}


	// ============================= Url generation methods =============================

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


	// ============================= Datatable hooks =============================
	/**
	 * Adds custom data before it is sent to the server
	 *
	 * @see https://datatables.net/reference/option/ajax.data
	 */
	prepareServerData(data, settings) {
		// Add the global superfilter info
		data.filter = {};
		if (this.sfilter.length > 0) {
			for (var sfId in this.sfilter) {
				let sfName = this.sfilter[sfId];
				data.filter[sfName] = $('#'+sfId).prop('checked') ? '1' : '0';
			}
		}

		// Add visibility info for each column
		this.table.columns().every((index) => {
			data.columns[index].visible = this.table.column(index).visible();
		});

		if( this.ajaxId !== null ) {
			// Add ajax ID (0 is a valid value)
			data.ajaxid = this.ajaxId;
		}
		return data;
	}


	// ============================= Global events methods =============================

	/*
	* Datatable size management based on window size (as described in
	* https://stackoverflow.com/questions/7678345/datatables-change-height-of-table-not-working)
	*/
	resizeDatatable () {
		console.log("Called resizeDatatable");
		$('.dataTables_scrollBody').css('height', ($(window).height() - window.vSizeElementsOutsideTableHeight));
	}

}


/**
 * Returns the DoPhp DataTable instance when jQuery is loaded
 */
$.fn.DoPhpDataTable = function(){
	return this.data(DoPhpDataTable.domDataPropName);
};
