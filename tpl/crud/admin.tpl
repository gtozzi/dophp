{{extends file='base-backend.tpl'}}
{{block name='content'}}
	<h1>{{$pageTitle}}</h1>

	{{if $this->getActions()}}
		{{$buttons=true}}
	{{else}}
		{{$buttons=false}}
	{{/if}}

	<table id="tableAdmin{{$page|ucfirst}}" class="table table-striped">
		<thead>
			<tr>
				{{foreach $cols as $h => $e}}
					<th>{{$e}}</th>
				{{/foreach}}
				{{if $buttons}}
					{{* DataTable doesn't like asymmetrical tables *}}
					<th></th>
				{{/if}}
			</tr>
		</thead>
		<tbody>
			{{foreach $items as $it}}
				<tr>
					{{foreach $cols as $h => $e}}
						<td>{{$it[$h]->format()}}</td>
					{{/foreach}}
					{{if $buttons}}
						<td>
							{{foreach $it['__actions'] as $n => $a}}
								{{strip}}
									<a href="{{$a['url']|htmlentities}}" alt="{{$a['descr']}}" {{if $a['confirm']}}onclick="return confirm('{{$a['confirm']}}')"{{/if}}>
										<span class="glyphicon glyphicon glyphicon-{{$a['icon']}}" data-toggle="tooltip" title="{{$a['descr']}}"></span>
									</a>
								{{/strip}}
							{{/foreach}}
						</td>
					{{/if}}
				</tr>
			{{/foreach}}
		</tbody>
	</table>
	<script type="text/javascript">
		$(document).ready(function() {
			$('#tableAdmin{{$page|ucfirst}}').DataTable({
				"aaSorting": [],
				'bStateSave': true,
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
			});
		});
	</script>
{{/block}}
