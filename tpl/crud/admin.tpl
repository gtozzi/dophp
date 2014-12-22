{{extends file='base-backend.tpl'}}
{{block name='head' append}}
	<!-- Load the DataTables jQuery plugin -->
	<link rel="stylesheet" href="{{$config['dophp']['url']}}/datatables/media/css/jquery.dataTables.min.css">
	<script src="{{$config['dophp']['url']}}/datatables/media/js/jquery.dataTables.min.js"></script>
{{/block}}
{{block name='content'}}
	<h1>{{$pageTitle}}</h1>

	<!-- Uses DataTables script https://datatables.net/ -->
	<table id="tableAdmin{{$page|ucfirst}}" class="table table-striped"></table>
	<script type="text/javascript">
		/**
		* Called when an action button has been clicked
		* @param object el: the clicked element
		* @param string url: the destination url
		*/
		function tableAction(el, url) {
			// gets the ID from the first column
			var id = $('td:first', $(el).parents('tr')).text().trim();
			window.location.href = url.replace('__pk__', id);
		}

		$(document).ready(function() {
			$('#tableAdmin{{$page|ucfirst}}').DataTable({
				"aaSorting": [],
				'oLanguage': {
					'sEmptyTable':     "{{addslashes($strDT['sEmptyTable'])}}",
					'sInfo':           "{{addslashes($strDT['sInfo'])}}",
					'sInfoEmpty':      "{{addslashes($strDT['sInfoEmpty'])}}",
					'sInfoFiltered':   "{{addslashes($strDT['sInfoFiltered'])}}",
					"sInfoPostFix":    "",
					'sInfoThousands':  "{{addslashes($strDT['sInfoThousands'])}}",
					'sLengthMenu':     "{{addslashes($strDT['sLengthMenu'])}}",
					'sLoadingRecords': "{{addslashes($strDT['sLoadingRecords'])}}",
					'sProcessing':     "{{addslashes($strDT['sProcessing'])}}",
					'sSearch':         "{{addslashes($strDT['sSearch'])}}",
					'sZeroRecords':    "{{addslashes($strDT['sZeroRecords'])}}",
					"oPaginate": {
						'sFirst':      "{{addslashes($strDT['oPaginate']['sFirst'])}}",
						'sPrevious':   "{{addslashes($strDT['oPaginate']['sPrevious'])}}",
						'sNext':       "{{addslashes($strDT['oPaginate']['sNext'])}}",
						'sLast':       "{{addslashes($strDT['oPaginate']['sLast'])}}"
					},
					"oAria": {
						'sSortAscending':  "{{addslashes($strDT['oAria']['sSortAscending'])}}",
						'sSortDescending': "{{addslashes($strDT['oAria']['sSortDescending'])}}"
					}
				},
				'columns': [
					{{foreach $cols as $h => $e}}
						{ "title": '{{addslashes($e)}}', },
					{{/foreach}}
				],
				'data': [
					{{foreach $items as $it}}{{strip}}
						[
							{{foreach $cols as $h => $e}}
								{{json_encode($it[$h]->format())}},
							{{/foreach}}
						],{{"\n"}}
					{{/strip}}{{/foreach}}
				],
				"columnDefs": [
					{{if $this->getActions()}}
						{ "orderable": false, "targets": {{count($cols)}} },
						{
							"data": null,
							"defaultContent": '{{strip}}
								{{foreach $this->getActions() as $name => $action}}
									{{if isset($action['pk']) && $action['pk']}}
										<span {{if isset($action['descr'])}}title="{{$action['descr']}}"{{/if}} class="clickable glyphicon glyphicon glyphicon-{{$action['icon']}}" onclick="tableAction(this,\'{{addslashes(addslashes($this->actionUrl($name,'__pk__')))}}\');"></span>&nbsp;
									{{/if}}
								{{/foreach}}
							{{/strip}}',
							"targets": {{count($cols)}}
						},
					{{/if}}
				]
			});
		});
	</script>
{{/block}}
