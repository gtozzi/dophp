{{extends file='base-backend.tpl'}}
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
					"sEmptyTable":     "{{_('No data available in table')}}",
					"sInfo":           "{{_('Showing _START_ to _END_ of _TOTAL_ entries')}}",
					"sInfoEmpty":      "{{_('Showing 0 to 0 of 0 entries')}}",
					"sInfoFiltered":   "{{_('(filtered from _MAX_ total entries)')}}",
					"sInfoPostFix":    "",
					"sInfoThousands":  "{{$localeconv['thousands_sep']}}",
					"sLengthMenu":     "{{_('Show _MENU_ elements')}}",
					"sLoadingRecords": "{{_('Loading...')}}",
					"sProcessing":     "{{_('Processing...')}}",
					"sSearch":         "{{_('Search')}}:",
					"sZeroRecords":    "{{_('Search returned zero records')}}.",
					"oPaginate": {
						"sFirst":      "{{_('First')}}",
						"sPrevious":   "{{_('Previous')}}",
						"sNext":       "{{_('Next')}}",
						"sLast":       "{{_('Last')}}"
					},
					"oAria": {
						"sSortAscending":  ": {{_('sort the column in ascending order')}}",
						"sSortDescending": ": {{_('sort the column in descending order')}}"
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
								'{{addslashes($it[$h])}}',
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
