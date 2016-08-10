{{extends file=$baseTpl}}
{{block name='head' append}}
	<!-- Load the DataTables jQuery plugin -->
	<link rel="stylesheet" href="{{$config['dophp']['url']}}/datatables/media/css/jquery.dataTables.min.css">
	<script src="{{$config['dophp']['url']}}/datatables/media/js/jquery.dataTables.min.js"></script>
{{/block}}
{{block name='content'}}
	<h1>{{$pageTitle}}</h1>

	<!-- Uses DataTables script https://datatables.net/ -->
	<table id="tableAdmin{{$page|ucfirst}}" class="table table-striped"></table>
	<style>
		a.table-action:hover {
			text-decoration: none;
		}
	</style>
	<script type="text/javascript">
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
					{ "title": "PK", },
					{{foreach $cols as $h => $e}}
						{ "title": '{{addslashes($e)}}', },
					{{/foreach}}
				],
				'data': [
					{{foreach $items as $it}}{{strip}}
						[
							{{foreach $cols as $h => $e}}
								{{if $e@first}}
									{{json_encode($it[$h]->value())}},
								{{/if}}
								{{json_encode($it[$h]->format())}},
							{{/foreach}}
						],{{"\n"}}
					{{/strip}}{{/foreach}}
				],
				"columnDefs": [
					{{if $this->getActions()}}
						{ "visible": false, "targets": 0 },
						{ "orderable": false, "targets": {{count($cols)}} },
						{
							"data": function(row) {
								var id = row[0];
								var url;
								var actions = '';
								{{foreach $this->getActions() as $name => $action}}{{strip}}
									{{if isset($action['pk']) && $action['pk']}}
										url = '{{addslashes($this->actionUrl($name,"__pk__"))}}'.replace('__pk__', id);
										{{"\n"}}
										actions += '<a {{if isset($action["descr"])}}title="{{$action["descr"]}}"{{/if}} class="glyphicon glyphicon glyphicon-{{$action["icon"]}} table-action" href="' + url + '"></a>&nbsp;';
										{{"\n"}}
									{{/if}}
								{{/strip}}{{/foreach}}
								return actions;
							},
							"targets": {{count($cols)+1}}
						},
					{{/if}}
				]
			});
		});
	</script>
{{/block}}
