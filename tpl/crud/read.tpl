{{extends file=$baseTpl}}
{{block name='content'}}
	<div class="formHolder">
		<h1>{{$pageTitle}}</h1>

		{{block name='fields'}}
			{{foreach $item as $i}}
				<p>
					<strong>{{$i->label()}}:</strong> {{$i->format()}}
				</p>
			{{/foreach}}
		{{/block}}
	</div>
{{/block}}
