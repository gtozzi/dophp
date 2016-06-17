{{extends file='base-backend.tpl'}}
{{block name='head' append}}
	<!-- Load Select2 (https://ivaynberg.github.io/select2/) -->
	<link rel="stylesheet" href="{{$config['dophp']['url']}}/select2/dist/css/select2.min.css">
	<script src="{{$config['dophp']['url']}}/select2/dist/js/select2.min.js"></script>

	<!-- Load Select2-Bootstrap (https://fk.github.io/select2-bootstrap-css/) -->
	<link rel="stylesheet" href="{{$config['dophp']['url']}}/select2-bootstrap/select2-bootstrap.css">
{{/block}}
{{block name='content'}}
	{{block name='title'}}
		<h1>{{$pageTitle}}</h1>
	{{/block}}

	{{block name='before'}}{{/block}}

	{{block name='form'}}
		<form class="form-horizontal" role="form" action="{{$submitUrl}}" method="post" enctype="multipart/form-data">
			{{block name='fields'}}
				{{foreach $fields as $f}}
					{{include file='file:[dophp]crud/form-field.tpl' field=$f}}
				{{/foreach}}
			{{/block}}

			<div class="form-group">
				<div class="col-sm-offset-2 col-sm-10">
					<button name="submit" value="submit" type="submit" class="btn btn-primary">{{if isset($pk) && $pk}}{{$strEdit}}{{else}}{{$strInsert}}{{/if}}</button>
				</div>
			</div>
		</form>
	{{/block}}

	{{block name='after'}}{{/block}}
{{/block}}
