<script type="text/javascript">

	{{if $sfilter}}
		/**
		 * Called when the superfilter changed
		 */
		function updateSFilter( formEl ) {
			// Calculate the new text
			let st = '';
			{{foreach $sfilter as $f}}
				if( st.length )
					st += ', ';
				if( ! $('#'+{{$f->getId()|json_encode}}).prop('checked') )
					st += '<del>';
				st += {{$f->getName()|json_encode}};
				if( ! $('#'+{{$f->getId()|json_encode}}).prop('checked') )
					st += '</del>';
			{{/foreach}}
			if( ! st.length )
				st = 'tutto';
			$('#sfilter-text').html(' ' + st);

			console.log('sfilter text updated, refreshing table…');
			$("#" + {{$id|json_encode}}).DoPhpDataTable().table.ajax.reload( function() {
				console.log('table refreshed');
				$('#{{$id}}_filmodal').modal('hide');
			});
		}
	{{/if}}

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
	function getValueRepr(value) {
		switch( typeof value ) {
		case 'string':
			return value;
		case 'boolean':
			return value ? 'Sì' : 'No';
		case 'number':
			return value.toLocaleString();
		case 'undefined':
			return '[err:undefined]';
		case 'object':
			return '[err:object]';
		default:
			return '[err:n/i]';
		}
	}

	function getObjectRepr(data) {
		if( data.repr )
			return data.repr;

		let html = '';
		if( data.href )
			html += '<a href="' + data.href + '">';

		if( data.value === undefined )
			html += '[err:noval]';
		else
			html += getValueRepr(data.value);

		if( data.href )
			html += '</a>';

		return html;
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
			$("#" + {{$id|json_encode}}).DoPhpDataTable().table.ajax.reload();
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
			$("#" + {{$id|json_encode}}).DoPhpDataTable().table.ajax.reload();
		});
	}

	// Sets up the table
	$(document).ready(function() {

		// Superfilters associative array
		let sfilter = {};
		{{foreach $sfilter as $f}}
			sfilter[{{$f->getId()|json_encode}}] = {{$f->getName()|json_encode}};
		{{/foreach}}

		// Create the table
		let dotable = new DoPhpDataTable($('#{{$id}}'), {
				'selectable': {{$selectable|json_encode}},
				'ajaxId': {{if isset($ajaxId)}}{{$ajaxId|json_encode}}{{else}}null{{/if}},
				'ajaxURL': {{$ajaxURL|json_encode}},
				'btnKey': {{$btnKey|json_encode}},
				'totKey': {{$totKey|json_encode}},
				'initOpts': { {{foreach $initOpts as $k => $v}} {{$k|json_encode}}: {{$v|json_encode}}, {{/foreach}} },
				'rbtns': { {{foreach $rbtns as $name => $btn}}
					{{$name|json_encode}}: {
						'url': {{$btn->getUrl()|json_encode}},
						'post': {{$btn->isPost()|json_encode}},
						'icon': {{$btn->icon|json_encode}},
						'label': {{$btn->label|json_encode}},
					},
				{{/foreach}} },
				'cols': [ {{foreach $cols as $c}} {
					'id': {{$c->id|json_encode}},
					'visible': {{$c->visible|json_encode}},
					'format': {{$c->format|json_encode}},
					'search': {{$c->search|json_encode}},
					'regex': {{$c->regex|json_encode}},
				}, {{/foreach}} ],
				'order': {{$order|json_encode}},
				'dFilterDivider': {{$dFilterDivider|json_encode}},
				'texts': {
					'exportLink': {{_('Export')|json_encode}}
				},
				'sfilter': sfilter,
		});

		// Called every time the window is resized
		$(window).on("resize", () => {
			dotable.resizeDatatable();
		});

	});

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
		let needsReload = false;

		$(formEl).find('input').each(function(){
			let input = $(this);
			let coln = input.data('coln');
			let visible = input.prop('checked');
			let previous = $("#" + {{$id|json_encode}}).DoPhpDataTable().table.column(coln).visible();

			if( previous == visible )
				return;

			console.log('Changing column visibility', coln, previous, visible);
			$("#" + {{$id|json_encode}}).DoPhpDataTable().table.column(coln).visible(visible);

			// When re-adding a column, data needs to be refreshed
			if( ! previous )
				needsReload = true;
		});

		$(formEl).closest('div.modal').modal('hide');

		if( needsReload )
			$("#" + {{$id|json_encode}}).DoPhpDataTable().table.ajax.reload( function() {
				console.log('table refreshed');
			});
	}

	function monthDiff(dateFrom, dateTo) {
		return dateTo.getMonth() - dateFrom.getMonth() +
			(12 * (dateTo.getFullYear() - dateFrom.getFullYear()))
	}

	function resetModal() {
		$(".do-date-filter-cont").removeClass("do-hide");
		$(".do-date-filter-cont .do-date-filter-form").removeClass("do-active");
		$(".do-date-filter-cont .do-date-filter-tab").removeClass("do-active");
		$(".do-date-filter-cont .do-mthUnit,.do-date-filter-cont .do-yeaUnit").removeClass("do-range");
		$(".do-date-filter-cont .do-mthUnit,.do-date-filter-cont .do-yeaUnit").removeClass("do-active");
	}

	// Set modal tab and selection, according to the DateFilter in jsonString
	function setModal(jsonString) {

		const precList = ["dmy", "my", "y"];

		let precison;
		let filterStart;
		let filterEnd;
		let currentDay;
		let currentMonth;
		let dateFilter = jQuery.parseJSON(jsonString);

		if(!dateFilter._valid)
			return;

		// Clean old modal selection
		resetModal();

		if(dateFilter._startDate != null && dateFilter._startDate._precision != null)
			precison = dateFilter._startDate._precision;
		else
			precison = dateFilter._endDate._precision;


		if(dateFilter._startDate == null || dateFilter._startDate.date == null)
			filterStart = new Date('01-01-1000');
		else
			filterStart = new Date(dateFilter._startDate.date);

		if(dateFilter._endDate == null || dateFilter._endDate.date == null)
			filterEnd = new Date();
		else
			filterEnd = new Date(dateFilter._endDate.date);

		switch(precison){
			case precList[0]:
				// Day Tab
				tabId = 1;

				if(dateFilter._startDate != null && dateFilter._startDate.date != null) {
					currentDay = ("0" + filterStart.getDate()).slice(-2);
					currentMonth = ("0" + (filterStart.getMonth() + 1)).slice(-2);
					startDate = currentDay+"-"+currentMonth+"-"+filterStart.getFullYear();
					$("#do-dfilt-start").val(startDate);
				}

				if(dateFilter._endDate != null && dateFilter._endDate.date != null) {
					currentDay = ("0" + filterEnd.getDate()).slice(-2);
					currentMonth = ("0" + (filterEnd.getMonth() + 1)).slice(-2);
					endDate = currentDay+"-"+currentMonth+"-"+filterEnd.getFullYear();
					$("#do-dfilt-end").val(endDate);
				}

			break;

			case precList[1]:
				// Month Tab
				tabId = 2

				controlDate = new Date(dateFilter._startDate.date);
				monthOfDifference = monthDiff(filterStart, filterEnd);

				// First month
				currentMonth = ("0" + (filterStart.getMonth() + 1)).slice(-2)
				$("#mthID-"+currentMonth+"-"+filterStart.getFullYear()).addClass("do-active");

				// Last month
				currentMonth = ("0" + (filterEnd.getMonth() + 1)).slice(-2)
				$("#mthID-"+currentMonth+"-"+filterEnd.getFullYear()).addClass("do-active");

				// Range
				for(i=1; i<=monthOfDifference; i++) {
					currentMonth = ("0" + (controlDate.getMonth() + 1)).slice(-2)
					$("#mthID-"+currentMonth+"-"+controlDate.getFullYear()).addClass("do-range");
					controlDate.setMonth(controlDate.getMonth() + 1);
				}
			break;

			case precList[2]:
				// Year Tab
				tabId = 3
				yearStart = filterStart.getFullYear();
				yearEnd = filterEnd.getFullYear();

				for (var i = parseInt(yearStart); i <= parseInt(yearEnd); i++)
					$("#yyID-"+i).addClass("do-range");
			break;
		}

		$(".do-date-filter-tab#tab-"+tabId).addClass("do-active");
		$(".do-date-filter-form.form-"+tabId).addClass("do-active");

	}

	function rpcDateFilter(filterValue) {
		if(filterValue == '')
			return;

		// Update table based on filter date
		let data = {
			'search': filterValue,
		};

		$.ajax({
			type: "POST",
			url: 'rpc.php?do=filterDate',
			data: data,
			success: function( res ) {
				setModal(res);
			},
			dataType: 'json',
		});
	}


	function filterShowDate(formEl){

		var jQ_formEl = $(formEl);
		var currColNo = jQ_formEl.data("coln");
		var activeTab = $(".do-date-filter-tab.do-active").attr("id").replace("tab-","");
		var filterValue = $(".ag-dt-dtFilt").val();

		$(".do-date-filter-cont").removeClass("do-hide");

		if(filterValue != null && filterValue != "")
			rpcDateFilter(filterValue);

		$(".do-date-filter-cont #do-date-filter-colNo").val(currColNo);

		$('#do-dfilt-start').data('coln', currColNo);
		$('#do-dfilt-end').data('coln', currColNo);

	}

	/**
	 * Asks for an element deletion confirmation
	 */
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

{{$doubleButtons=isset($config['datatable']['double_buttons']) && $config['datatable']['double_buttons']}}
{{if $sfilter || $doubleButtons}}
	<!-- Filter summary and double buttons -->
	<p>
		{{if $doubleButtons}}
			{{foreach $btns as $name => $b}}
				<a class="btn btn-primary"
				{{if $b->isPost()}}
					href="javascript:onDTPostButton({{$name|json_encode|urlencode}});"
				{{else}}
					href="{{$b->getUrl()}}"
				{{/if}}
				><span class="fa {{$b->icon}}"></span> {{$b->label|htmlentities}}</a>
			{{/foreach}}
		{{/if}}
		{{if $sfilter}}
			Mostra:
			<a href="#" onclick="$('#{{$id}}_filmodal').modal('show'); return false;">
				<span id="sfilter-text" class="fa fa-pencil">
					{{$first=true}}
					{{foreach $sfilter as $f}}{{strip}}
						{{if ! $first}}, {{/if}}
						{{if ! $f->getInternalValue()}}
							<del>
						{{/if}}
						{{$f->getLabel()|strtolower|htmlentities}}
						{{$first=false}}
						{{if ! $f->getInternalValue()}}
							</del>
						{{/if}}
					{{/strip}}{{/foreach}}
				</span>
			</a>
		{{/if}}
	</p>
	{{if $sfilter}}
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
							<input data-coln="{{$c@iteration}}" type="checkbox" class="align-middle custom-control-input" {{if $c->visible}}checked{{/if}}>
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
<div class="do-date-filter-cont do-hide">
	<input type="hidden" id="do-date-filter-col" name="do-date-filter-col" value="-1" />
	<div class="do-date-filter-head">
		Filtra data
		<div class="do-close">
			<img src="{{$config['dophp']['url']}}/med/img/dfilter/dfilter_close.png" alt="" />
		</div>
		<div class="do-minimize">
			<img src="{{$config['dophp']['url']}}/med/img/dfilter/dfilter_minimize.png" alt="" />
		</div>
	</div>
	<div class="do-date-filter-body">

		<div class="do-date-fiter-tab-cont">
			<div class="do-date-filter-tab do-active" id="tab-1">Data</div>
			<div class="do-date-filter-tab" id="tab-2">Mese</div>
			<div class="do-date-filter-tab" id="tab-3">Anno</div>
			<div class="do-date-clear"></div>
		</div>

		<div class="do-date-filter-form form-1 do-active">
			<div class="do-date-filter-dpck">
				<div class="do-pck-blck">
					<label>Da</label>
					<input id="do-dfilt-start" class="do-dfilt-dpck" name="do-dfilt-start" type="text" value="" onchange='$("#" + {{$id|json_encode}}).DoPhpDataTable().realtimeFilter(this);' readonly>
				</div>
				<div class="do-pck-blck">
					<label>A</label>
					<input id="do-dfilt-end" class="do-dfilt-dpck" name="do-dfilt-end" type="text" value="" onchange='$("#" + {{$id|json_encode}}).DoPhpDataTable().realtimeFilter(this);' readonly>
				</div>
			</div>
		</div>

		<div class="do-date-filter-form form-2">
			<div class="do-date-filt-mthCont do-date-filt-univCont" data-coln="{{$c@iteration}}">
				{{foreach from=$monthYearList item=myl_list key=myl_year}}
					<div class="do-date-monthBlck-title">{{$myl_year}}</div>
					{{foreach from=$myl_list item=month}}
						<div id="mthID-{{$month["number"]}}-{{$myl_year}}" class="do-mthUnit" data-year-month="{{$month["number"]}}.{{$myl_year}}">{{$month["name"]}}</div>
					{{/foreach}}
				{{/foreach}}
			</div>
		</div>

		<div class="do-date-filter-form form-3">
			<div class="do-date-filt-yeaCont do-date-filt-univCont" data-coln="{{$c@iteration}}">
				{{foreach from=$yearList item=yl_list key=yl_year_range}}
					<div class="do-date-yearBlck-title">{{$yl_year_range}}</div>
					{{foreach from=$yl_list item=year}}
						<div id="yyID-{{$year}}" class="do-yeaUnit" data-year="{{$year}}">{{$year}}</div>
					{{/foreach}}
				{{/foreach}}
			</div>
		</div>

		<input type="hidden" name="do-date-filter-colNo" id="do-date-filter-colNo" value="" />
	</div>
</div>


<!-- Data Table -->
<div class="container-fluid ag-datatable-container">
	<table id="{{$id}}" class="table table-striped table-bordered nowrap data-table ag-table" style="min-width:100%">
		<thead>
			<tr>
				<th style="width: 20px" class="data-table-buthead">
					{{if $selectable}}
						<span id="selectAllBox" class="fa fa-square-o selectbox" onclick='$("#" + {{$id|json_encode}}).DoPhpDataTable().onSelectAllBox();'></span>
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
					<th {{if $c->tooltip}}class="tooltipped" title="{{$c->tooltip|htmlentities}}"{{/if}}>{{$c->descr|htmlentities}}</th>
				{{/foreach}}
			</tr>
			<tr>
				<th style="width: 20px" class="data-table-filter">
					<a href="#" title="seleziona colonne" class="fa fa-columns" onclick="selectColumns('{{$id}}');return false;"></a>&nbsp;
					<a href="#" title="pulisci filtro" class="fa fa-eraser" onclick='$(":input.data-table-filter").val("");$("#" + {{$id|json_encode}}).DoPhpDataTable().table.columns().search("").draw();return false;'></a>
				</th>
				{{foreach $cols as $c}}
					<th class="data-table-filter">
						{{if $c->filter}}
							<input
								class="data-table-filter {{if $c->type == \dophp\Table::DATA_TYPE_DATE}}ag-dt-dtFilt{{/if}}"
								type="text" placeholder="filtra - cerca" onkeyup='$("#" + {{$id|json_encode}}).DoPhpDataTable().filterKeyUp(event);' onchange='$("#" + {{$id|json_encode}}).DoPhpDataTable().filterChanged(this);'
								data-timer="" data-coln="{{$c@iteration}}" data-type="{{$c->type|htmlentities}}"
								{{if $c->type == \dophp\Table::DATA_TYPE_DATE}}
									onfocus="filterShowDate(this);"
									data-seltab=""
									id="ag-dt-dtFilt-{{$c@iteration}}"
								{{else}}
									onfocus='$("#" + {{$id|json_encode}}).DoPhpDataTable().wpHideDateWidget()'
								{{/if}}
								{{if $c->search}}
									value="{{$c->search|htmlentities}}"
									data-lastval="{{$c->search|htmlentities}}"
								{{else}}
									data-lastval=""
								{{/if}}
							/>
						{{/if}}
					</th>
				{{/foreach}}
			</tr>
		</thead>
		<tbody></tbody>
	</table>
</div>