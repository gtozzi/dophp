{{extends file='base-backend.tpl'}}
{{block name='content'}}
	<div class="formHolder">

		<h1>{{$pageTitle}}</h1>

		{{foreach $item as $i}}
			<p>
				<strong>{{$i->label()}}:</strong> {{$i->format()}}
			</p>
		{{/foreach}}
	</div>
{{/block}}
