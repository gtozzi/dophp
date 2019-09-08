<script type="text/javascript">
	/** The selected class */
	var selClass = 'fa-check-square-o';
	/** The deselected class */
	var deselClass = 'fa-square-o';
	/** The data table holder */
	var table;

	{{if $sfilter}}
		/**
		 * Called when the superfilter changed
		 */
		function updateSFilter( formEl ) {
			// Calculate the new text
			let st = '';
			{{foreach $sfilter as $f}}
				if( $('#'+{{$f->getId()|json_encode}}).prop('checked') ) {
					if( st.length )
						st += ', ';
					st += {{$f->getName()|json_encode}};
				}
			{{/foreach}}
			if( ! st.length )
				st = 'tutto';
			$('#sfilter-text').html(' ' + st);

			console.log('sfilter text updated, refreshing table…');
			table.ajax.reload( function() {
				console.log('table refreshed');
				$('#{{$id}}_filmodal').modal('hide');
			})
		}
	{{/if}}

	/**
	 * Adds custom data before it is sent to the server
	 *
	 * @see https://datatables.net/reference/option/ajax.data
	 */
	function prapareServerData(data, settings) {
		data.filter = {};
		{{if $sfilter}}
			{{foreach $sfilter as $f}}
				data.filter[{{$f->getName()|json_encode}}] = $('#'+{{$f->getId()|json_encode}}).prop('checked') ? '1' : '0';
			{{/foreach}}
		{{/if}}
		return data;
	}

	/**
	 * Parses an encoded object into a JavaScript Object
	 */
	function getObjectVal(data) {
		switch( data.class ) {
		case 'dophp\\Date':
		case 'dophp\\Time':
		case 'DateTime':
			let d = Date(data.date);
			return d;
		}

		// Unsupported object will be returned as-is
		return data;
	}

	/**
	 * Parses an encoded object into a user-friendly string
	 */
	function getObjectRepr(data) {
		if( typeof data === 'undefined' ) {
			console.error('undefined', data);
			return '[err:undefined]';
		}

		if( typeof data == 'number' ) {
			return data.toLocaleString();
		}

		if( data.repr )
			return data.repr;

		// Unsupported object will be represented as [object]
		console.error('norepr', data);
		return '[err:norepr]';
	}

	/**
	 * Parses a number into a nicely formatted currency value
	 */
	function getCurrencyRepr(data) {
		let decimals = 2;

		// Determine decimal separator… a bit tricky, can be done better?
		let n = 1.1;
		let decsep = n.toLocaleString().substring(1, 2);

		let formatted = data.toLocaleString();
		let parts = formatted.split(decsep);
		if( parts.length > 1 && parts[1].length )
			if( parts[1].length >= 2 )
				return formatted;
			else
				return formatted + '0'.repeat(decimals - parts[1].length);

		return formatted + decsep + '00';
	}

	/**
	 * Handles a POST row button click
	 */
	function onDTPostRowButton(name, rowid) {
		let url;
		let data;

		switch(name) {
		{{foreach $rbtns as $name => $btn}}
			{{if $btn->isPost()}}
				case {{$name|json_encode}}:
					url = {{$btn->getUrl()|json_encode}};
					data = {{$btn->getPost()|json_encode}};
					break;
				default:
					console.error('URL for ', name, 'not found');
					return;
			{{/if}}
		{{/foreach}}
		}
		url = url.replace("{{'{{id}}'}}", rowid);
		for(let key in data)
			if( data[key] == "{{'{{id}}'}}" )
				data[key] = rowid;

		$.post(url, data, function(data) {
			table.ajax.reload();
		});
	}

	/**
	 * Handles a POST button click
	 */
	function onDTPostButton(name) {
		let url;
		let data;

		switch(name) {
		{{foreach $btns as $name => $btn}}
			{{if $btn->isPost()}}
				case {{$name|json_encode}}:
					url = {{$btn->getUrl()|json_encode}};
					data = {{$btn->getPost()|json_encode}};
					break;
				default:
					console.error('URL for ', name, 'not found');
					return;
			{{/if}}
		{{/foreach}}
		}

		$.post(url, data, function(data) {
			table.ajax.reload();
		});
	}

	// Sets up the table
	$(document).ready(function() {
		// Create the table
		table = $('#{{$id}}').DataTable( {
			ajax: {
				url:         {{$ajaxURL|json_encode}},
				type:        'POST',
				data:        prapareServerData,
			},
			processing:     true,
			serverSide:     true,
			//scrollCollapse: true,
			// Can't set to false or search will be ignored
			//bFilter:        false,
			//stateSave:      true,
			dom:            'lrtip',

			// Scroller extension
			scroller:       true,
			deferRender:    true,
			scrollY:        '400px',
			scrollX:        true,
			autoWidth:      true,

			"language": {
				"url": "{{$config['dophp']['url']}}/webcontent/DataTables/Italian.json"
			},

			ordering: true,
			// Default order
			order: {{$order|json_encode}},

			//colReorder: true,

			autoWidth : true,
			columns: [
				// Buttons column
				{
					orderable: false,
					data: '{{$btnKey}}',
					render: function( data, type, row, meta ) {
						html = ''
						{{if $selectable}}
							let cls = table.isRowSelected(table.row(row)) ? selClass : deselClass;
							html += '<span class="fa ' + cls + ' selectbox"></span>';
						{{/if}}
						{{foreach $rbtns as $name => $btn}}
							if( data.includes({{$name|json_encode}}) ) {
								let url = {{$btn->getUrl()|json_encode}};
								url = url.replace("{{'{{id}}'}}", row.id);

								{{if $btn->isPost()}}
									let href = "javascript:onDTPostRowButton('" + encodeURI({{$name|json_encode}}) + "', " + row.id + ")";
								{{else}}
									let href = url;
								{{/if}}

								html += '<a class="fa ' + {{$btn->icon|htmlentities|json_encode}}
									+ '" href="' + href + '" title="' + {{$btn->label|htmlentities|json_encode}} + '"'
									+ '></a> '; // Trailing space to separate next
							}
						{{/foreach}}
						return html;
					},
					className: 'dt-body-nowrap data-table-bcol',
				},
				// Any other column
				{{foreach $cols as $c}}
					{
						data: '{{$c->id}}',
						className: 'data-table-col',
						visible: {{$c->visible|json_encode}},
						//width: "200px",
						render: function( data, type, row, meta ) {
							// Cast data to user-friendly type

							if( data === null ) {
								// Null is considered object in JS
								return '-';
							}

							let format = {{if $c->format}}{{$c->format|json_encode}}{{else}}typeof data{{/if}};

							switch( format ) {
							case 'string':
								return data;
							case 'boolean':
								return data ? 'Sì' : 'No';
							case 'number':
							case 'undefined':
							case 'object':
								return getObjectRepr(data);
							case 'currency':
								return getCurrencyRepr(data);
							default:
								return data;
							}
						},
						{{if $c->format=='number' || $c->format=='currency'}}
							className: 'dt-body-right',
						{{/if}}
					},
				{{/foreach}}
			],

			// Initial search values
			searchCols: [
				{{foreach $cols as $c}}
					{{if $c->search}}
						{ search: {{$c->search|json_encode}}, regex: {{$c->regex|json_encode}}, },
					{{else}}
						null,
					{{/if}}
				{{/foreach}}
			],

			// Callback for custom selection
			createdRow: function( row, data, dataIndex ) {
				// Keeps selection display on redraw
				table.updateSelectRow(table.row(row));
			},

			// Callback for info row redraw
			infoCallback: function( settings, start, end, max, total, pre ) {
				{{if $selectable}}
					// Adds info about the selection
					let selInfo = ', ' +
						'<span id="data-table-sel-count">' +
						( table.selectedAll ? 'tutti' : table.selectedItems.size.toString() )
						+ '</span> selezionati';
					return pre + selInfo;
				{{else}}
					return pre;
				{{/if}}
			},
		});

		// Custom selection handling
		table.selectedItems = new Set();
		table.selectedAll = false;

		/**
		 * Updates select count (custom selection api)
		 */
		table.updateSelectCount = function() {
			if( table.selectedAll )
				$('#data-table-sel-count').html('tutti');
			else
				$('#data-table-sel-count').html(table.selectedItems.size.toString());
		};

		/**
		 * Updates the select all box (custom selection api)
		 */
		table.updateSelectAllBox = function() {
			let allSelected = true;
			if( table.selectedAll )
				allSelected = true;
			else
				table.rows().every( function( rowIdx, tableLoop, rowLoop ) {
					let data = this.data();
					if( ! table.selectedItems.has(data.id) ) {
						allSelected = false;
						return false; // Not sure if this works
					}
				} );

			let box = $('#selectAllBox');
			if( allSelected ) {
				box.addClass(selClass);
				box.removeClass(deselClass);
			} else {
				box.addClass(deselClass);
				box.removeClass(selClass);
			}
		};

		/**
		 * Refresh selection for a single row
		 *
		 * @param row The row
		 */
		table.updateSelectRow = function(row) {
			let btnCell = table.cell(row, 0);
			let selBox = $(btnCell.node()).find('span.selectbox');

			if( table.isRowSelected(row) ) {
				$(row.node()).addClass('selected');
				selBox.removeClass(deselClass);
				selBox.addClass(selClass);
			} else {
				$(row.node()).removeClass('selected');
				selBox.removeClass(selClass);
				selBox.addClass(deselClass);
			}
		}

		/**
		 * Tells whether given row is selected (custom selection api)
		 *
		 * @param row: The datatables row object
		 */
		table.isRowSelected = function(row) {
			if( table.selectedAll )
				return true;

			let data = row.data();
			return table.selectedItems.has(data.id);
		};

		/**
		 * Selects a row (custom selection api)
		 *
		 * @param row: The datatables row object
		 */
		table.selectRow = function(row) {
			if( table.isRowSelected(row) )
				return;

			let data = row.data();
			table.selectedItems.add(data.id);
			table.updateSelectRow(row);
			console.log('Row selected id', data.id, table.selectedItems);
		};

		/**
		 * Deselects a row (custom selection api)
		 *
		 * @param row: The datatables row object
		 */
		table.deselectRow = function(row) {
			if( ! table.isRowSelected(row) )
				return;

			let data = row.data();
			table.selectedItems.delete(data.id);
			if( table.selectedAll ) {
				table.selectedAll = false;
				table.rows().every( function( rowIdx, tableLoop, rowLoop ) {
					table.updateSelectRow(this);
				});
			}
			table.updateSelectRow(row);
			console.log('Row unselected id', data.id, table.selectedItems);
		};

		/**
		 * Toggle a row selection (custom selection api)
		 *
		 * @param row: The datatables row object
		 */
		table.toggleRow = function(row) {
			if( table.isRowSelected(row) )
				table.deselectRow(row);
			else
				table.selectRow(row);
		};

		// Listen to row click event
		$('#{{$id}}'+' tbody').on('click', 'tr', function () {
			{{if $selectable}}
				let tr = $(this);
				let row = table.row(this);

				table.toggleRow(row);
				table.updateSelectCount();
				table.updateSelectAllBox();
			{{/if}}
		});


		// ADDED WP ELEMENTS

		// block search when the user click on the column filter
		$(".data-table-filter").click(function(){ return false; })



		$(".wp-dfilt-dpck").datepicker({
			language: "it",
			autoclose: true,
		});

		$(".wp-date-filter-cont .wpdf_close, .wp-date-filter-head .wp-close").click(function(){
			wpHideDateWidget();
		});

		$(".wp-date-filter-head .wp-minimize").click(function(){
			if($(".wp-date-filter-cont").hasClass("wp-closed")){
				$(".wp-date-filter-cont").removeClass("wp-closed");
			}
			else{
				$(".wp-date-filter-cont").addClass("wp-closed");
			}
		});

		/**
		 * Switch date filter active tab and form content
		 *
		 */
		$(".wp-date-fiter-tab-cont .wp-date-filter-tab").click(function(){
			var elID=$(this).attr("id");
			var formID = elID.replace("tab-","");

			$(".wp-date-filter-body .wp-date-filter-form").removeClass("wp-active");
			$(".wp-date-filter-body .wp-date-filter-form.form-"+formID).addClass("wp-active");

			$(".wp-date-filter-body .wp-date-filter-tab").removeClass("wp-active");
			$(".wp-date-filter-body .wp-date-filter-tab#tab-"+formID).addClass("wp-active");

		});

		$(".wp-date-filt-mthCont .wp-mthUnit, .wp-date-filt-yeaCont .wp-yeaUnit").click(function(){

			$(".wp-date-filt-mthCont .wp-mthUnit, .wp-date-filt-yeaCont .wp-yeaUnit")
				.removeClass("wp-range")

			if($(this).hasClass("wp-active")){
				$(this).removeClass("wp-active");
			}
			else {

				// check if selected dates are more than one, if so
				// get the first and last selected items
				var currParent = $(this).parents(".wp-date-filt-univCont");
				var selectedElem = currParent.children(".wp-active");

				$(this).addClass("wp-active");

				if((selectedElem.length+1)>1){
					firstElem=selectedElem.first();
					lastElem=selectedElem.last();

					firstElemID =$(firstElem).attr("id");
					lastElemID =$(lastElem).attr("id");

					$(".wp-date-filt-mthCont .wp-mthUnit, .wp-date-filt-yeaCont .wp-yeaUnit")
						.removeClass("wp-range wp-active")


					// last click is after lastSelection
					if($(this).prevAll("div#"+lastElemID).length) {
						$(firstElem).addClass("wp-active");
						$(firstElem).nextUntil("#"+$(this).attr("id")).addClass("wp-range");
						$(this).addClass("wp-active");
					} else if($(this).prevAll("div#"+firstElemID).length) {
							$(firstElem).addClass("wp-active");
							$(firstElem).nextUntil("#"+$(this).attr("id")).addClass("wp-range");
							$(this).addClass("wp-active");
						} else {
							$(this).addClass("wp-active");
							$(this).nextUntil("#"+lastElemID).addClass("wp-range")
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
						filterString = monthsList.join("{{$dFilterDivider}}");
					});
				break;

				//year
				case "3":
					$(".wp-yeaUnit.wp-active").each(function(){
						yearsList.push($(this).data("year"));
						filterString = yearsList.join("{{$dFilterDivider}}");
					});
				break;

			}

			// get current colon id
			var currColNo = $(".wp-date-filter-cont #wp-date-filter-colNo").val();
			var currFilter = document.getElementById("ag-dt-dtFilt-"+currColNo);

			// fill given date filter with the search_string
			$("#ag-dt-dtFilt-"+currColNo).val(filterString)

			// store the identifier of the used date_tab for the current used filter
			$("#ag-dt-dtFilt-"+currColNo).attr("data-seltab",activeTab);

			updateFilter(currFilter,filterString);

		});

		// ./ADDED WP ELEMENTS

	});

	/**
	 * Called when the "select all" box has been clicked
	 *
	 * If all rows are selected, unselected them; select all otherwise
	 */
	function onSelectAllBox() {
		{{if $selectable}}
			table.selectedAll = ! table.selectedAll;

			// Apply selection loop
			table.rows().every( function( rowIdx, tableLoop, rowLoop ) {
				table.updateSelectRow(this);
			});

			// Update count
			table.updateSelectCount();
			table.updateSelectAllBox();
		{{/if}}
	}

	/**
	 * Called when it is time to update the filter
	 */
	function updateFilter(input, val) {

		console.log("filtering...");

		let el = $(input);
		el.data('timer', '');
		let coln = el.data('coln');

		el.val(val);

		console.log('Applying new filter for col', coln, val);

		table.column(coln).search(val).draw();

	}

	/**
	 * Realtime Filter Updater
	 */
	function realtimeFilter(input) {

		var newFilter = "";
		let el =  $('#ag-dt-dtFilt-'+$(input).data('coln'));

		if($('#wp-dfilt-start').val() != "")
			newFilter = $('#wp-dfilt-start').val();
		if($('#wp-dfilt-end').val() != "")
			newFilter = newFilter +"{{$dFilterDivider}}"+ $('#wp-dfilt-end').val();

		// Ignore duplicated changes
		if( el.data('lastval') == newFilter )
			return;

		updateFilter(el, newFilter);
		el.data('lastval', newFilter);
	}

	/**
	 * Called when the filter input changed
	 */
	function filterChanged(input) {

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

	/**
	 * Show column selection modal
	 */
	function selectColumns(tabid) {
		$('#'+tabid+'_colmodal').modal('show');
	}

	/**
	 * Update the column visibility
	 */
	function updateColumns(formEl) {
		$(formEl).find('input').each(function(){
			let input = $(this);
			let coln = input.data('coln');
			let visible = input.prop('checked');

			console.log('Setting column visibility', coln, visible);
			table.column(coln+1).visible(visible);
		});

		$(formEl).closest('div.modal').modal('hide');
	}


	function filterShowDate(formEl){

		$(".wp-date-filter-cont").removeClass("do-hide");

		var jQ_formEl = $(formEl);

		// store the current_filter identifier
		var currColNo = jQ_formEl.data("coln");
		$(".wp-date-filter-cont #wp-date-filter-colNo").val(currColNo);

		$('#wp-dfilt-start').data('coln', currColNo);
		$('#wp-dfilt-end').data('coln', currColNo);

		$(".wp-date-filter-cont .wp-date-filter-form").removeClass("wp-active");
		$(".wp-date-filter-cont .wp-date-filter-tab").removeClass("wp-active");
		$(".wp-date-filter-cont .wp-mthUnit,.wp-date-filter-cont .wp-yeaUnit").removeClass("wp-active wp-range");

		// if filter has been used before reopen last choosen tab otherwise open the default layout
		var usedTab = jQ_formEl.attr("data-seltab");
		restoreFilterData(currColNo,usedTab)
	}


	/**
	 * If given, restore last used tab and filter_data
	 */
	function restoreFilterData(column,tab){

		// if a tab is given make it active otherwise show default layout
		if(typeof(tab)!="undefined"&&parseInt(tab)>0){
			$(".wp-date-filter-tab#tab-"+tab).addClass("wp-active");
			$(".wp-date-filter-cont .wp-date-filter-form.form-"+tab).addClass("wp-active");

			// if a column is given try to retrieve previous filter_data
			if(typeof(column)!="undefined"&&parseInt(column)>=0){

				// get current filter value
				var currFilterString = $(".ag-dt-dtFilt#ag-dt-dtFilt-"+column);
				currFilterString = currFilterString[0];
				currFilterString = $(currFilterString).val()

				// if filter_string is not empty try to restore previous data
				if((currFilterString.trim())!=""){

					var firstElem=false;
					var lastElem=false;

					var currValues=currFilterString.trim();
					currValues=currValues.split("{{$dFilterDivider}}");
					switch(tab){

						case "1":
						break;

						case "2":
						break;

						case "3":
						break;
					}
				}
			}
		}
		else{
			$(".wp-date-filter-tab#tab-1").addClass("wp-active");
			$(".wp-date-filter-cont .wp-date-filter-form.form-1").addClass("wp-active");
		}


	}

	function wpHideDateWidget(){
		$(".wp-date-filter-cont").addClass("do-hide");
	}

	/**
	 * Asks for an element deletion confirmation
	 */
	//function confirmAndDelete(id, url) {
	function confirmAndDelete(url) {
		if( ! confirm("Confermi la cancellazione?") )
			return;

		$.ajax({
			url: url,
			type: 'DELETE',
			success: function(result) {
				window.location.reload();
			},
			error: function(result) {
				console.error(result);
				window.alert('errore');
			},
		});
	}

</script>

{{if $sfilter}}
	<!-- Filter summary -->
	<p>
		Mostra:
		<a href="#" onclick="$('#{{$id}}_filmodal').modal('show'); return false;">
			<span id="sfilter-text" class="fa fa-pencil">
				{{$first=true}}
				{{foreach $sfilter as $f}}{{strip}}
					{{if ! $f->getInternalValue()}}
						{{continue}}
					{{/if}}
					{{if ! $first}}, {{/if}}
					{{$f->getLabel()|strtolower|htmlentities}}
					{{$first=false}}
				{{/strip}}{{/foreach}}
			</span>
		</a>
	</p>
	<!-- Filter modal -->
	<div class="modal fade" id="{{$id}}_filmodal">
		<div class="modal-dialog" role="document">
			<form action="#" onsubmit="updateSFilter(this); return false;">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Mostra</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						{{foreach $sfilter as $f}}
							{{include file=$f->getTemplate() field=$f}}
						{{/foreach}}
					</div>
					<div class="modal-footer">
						<button type="submit" class="btn btn-primary">Conferma</button>
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Annulla</button>
					</div>
				</div>
			</form>
		</div>
	</div>
{{/if}}

<!-- Column selection modal -->
<div class="modal fade" id="{{$id}}_colmodal">
	<div class="modal-dialog" role="document">
		<form action="#" onsubmit="updateColumns(this); return false;">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Seleziona Colonne</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body">
					{{foreach $cols as $c}}
						<label class="custom-control custom-checkbox ag-checkbx-cust">
							<input data-coln="{{$c@index}}" type="checkbox" class="align-middle custom-control-input" {{if $c->visible}}checked{{/if}}>
							<span class="custom-control-indicator"></span>
							<span class="custom-control-description">{{$c->descr|htmlentities}}</span>
						</label>
					{{/foreach}}
				</div>
				<div class="modal-footer">
					<button type="submit" class="btn btn-primary">Conferma</button>
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Annulla</button>
				</div>
			</div>
		</form>
	</div>
</div>

<!-- Date Filter -->
<div class="wp-date-filter-cont do-hide">
	<input type="hidden" id="wp-date-filter-col" name="wp-date-filter-col" value="-1" />
	<div class="wp-date-filter-head">
		Filtra data
		<div class="wp-close">
			<img src="med/img/dfilter/dfilter_close.png" alt="" />
		</div>
		<div class="wp-minimize">
			<img src="med/img/dfilter/dfilter_minimize.png" alt="" />
		</div>
	</div>
	<div class="wp-date-filter-body">

		<div class="wp-date-fiter-tab-cont">
		    <div class="wp-date-filter-tab wp-active" id="tab-1">Data</div>
		    <div class="wp-date-filter-tab" id="tab-2">Mese</div>
		    <div class="wp-date-filter-tab" id="tab-3">Anno</div>
		    <div class="wp-date-clear"></div>
		</div>

		<div class="wp-date-filter-form form-1 wp-active">
			<div class="wp-date-filter-dpck">
				<div class="wp-pck-blck">
					<label>Da</label>
					<input id="wp-dfilt-start" class="wp-dfilt-dpck" name="wp-dfilt-start" type="text" value="" onchange="realtimeFilter(this);" readonly>
				</div>
				<div class="wp-pck-blck">
					<label>A</label>
					<input id="wp-dfilt-end" class="wp-dfilt-dpck" name="wp-dfilt-end" type="text" value="" onchange="realtimeFilter(this);" readonly>
				</div>
			</div>
		</div>

		<div class="wp-date-filter-form form-2">
			<div class="wp-date-filt-mthCont wp-date-filt-univCont">
				{{foreach from=$monthYearList item=myl_list key=myl_year}}
					<div class="wp-date-monthBlck-title">{{$myl_year}}</div>
					{{foreach from=$myl_list item=month}}
						<div id="mthID-{{$myl_year}}-{{$month["number"]}}" class="wp-mthUnit" data-year-month="{{$myl_year}}-{{$month["number"]}}">{{$month["name"]}}</div>
					{{/foreach}}
				{{/foreach}}
			</div>
		</div>

		<div class="wp-date-filter-form form-3">
			<div class="wp-date-filt-yeaCont wp-date-filt-univCont">
				{{foreach from=$yearList item=yl_list key=yl_year_range}}
					<div class="wp-date-yearBlck-title">{{$yl_year_range}}</div>
					{{foreach from=$yl_list item=year}}
						<div id="yyID-{{$year}}" class="wp-yeaUnit" data-year="{{$year}}">{{$year}}</div>
					{{/foreach}}
				{{/foreach}}
			</div>
		</div>

		<input type="hidden" name="wp-date-filter-colNo" id="wp-date-filter-colNo" value="" />
	</div>
</div>


<!-- Data Table -->
<table id="{{$id}}" class="table table-striped table-bordered nowrap data-table ag-table" style="min-width:100%">
	<thead>
		<tr>
			<th style="width: 20px" class="data-table-buthead">
				{{if $selectable}}
					<span id="selectAllBox" class="fa fa-square-o selectbox" onclick="onSelectAllBox();"></span>
				{{/if}}
				{{foreach $btns as $name => $b}}
					<a class="fa {{$b->icon}}" title="{{$b->label|htmlentities}}"
					{{if $b->isPost()}}
						href="javascript:onDTPostButton({{$name|json_encode|urlencode}});"
					{{else}}
						href="{{$b->getUrl()}}"
					{{/if}}
					></a>
				{{/foreach}}
			</th>
			{{foreach $cols as $c}}
				<th>{{$c->descr}}</th>
			{{/foreach}}
		</tr>
		<tr>
			<th style="width: 20px" class="data-table-filter"><a href="#" class="fa fa-columns" onclick="selectColumns('{{$id}}');return false;"></a></th>
			{{foreach $cols as $c}}
				<th class="data-table-filter">
					<input class="data-table-filter {{if $c->type==$customDateFilt}}ag-dt-dtFilt{{/if}}"
						type="text" placeholder="filtra - cerca" onkeyup="filterChanged(this);" onchange="filterChanged(this);"
						data-lastval="" data-timer="" data-coln="{{$c@index}}"
						{{if $c->type==$customDateFilt}}
							onfocus="filterShowDate(this);"
							data-seltab=""
							id="ag-dt-dtFilt-{{$c@index}}"
						{{else}}
							onfocus="wpHideDateWidget()" 
						{{/if}}
						{{if $c->search}}
							value="{{$c->search|htmlentities}}"
						{{/if}}
					/>
				</th>
			{{/foreach}}
		</tr>
	</thead>
</table>
