{{extends file='base-backend.tpl'}}
{{block name='content'}}
	<h1>{{$pageTitle}}</h1>

	<form class="form-horizontal" role="form" action="{{$submitUrl}}" method="post" enctype="multipart/form-data">
		{{block name='fields'}}
			{{foreach $fields as $n => $f}}
				{{include file='crud/form-field.tpl' name=$n field=$f}}
			{{/foreach}}
		{{/block}}

		<div class="form-group">
			<div class="col-sm-offset-2 col-sm-10">
				<button name="submit" value="submit" type="submit" class="btn btn-primary">{{if $pk}}{{_('Edit')}}{{else}}{{_('Insert')}}{{/if}}</button>
			</div>
		</div>
	</form>
{{/block}}
