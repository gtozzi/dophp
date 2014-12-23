{{extends file='base-backend.tpl'}}
{{block name='head' append}}
	<!-- Load Select2 (https://ivaynberg.github.io/select2/) -->
	<link rel="stylesheet" href="{{$config['dophp']['url']}}/select2/select2.css">
	<script src="{{$config['dophp']['url']}}/select2/select2.min.js"></script>

	<!-- Load Select2-Bootstrap (https://fk.github.io/select2-bootstrap-css/) -->
	<link rel="stylesheet" href="{{$config['dophp']['url']}}/select2-bootstrap/select2-bootstrap.css">
{{/block}}
{{block name='content'}}
	<h1>{{$pageTitle}}</h1>

	<form class="form-horizontal" role="form" action="{{$submitUrl}}" method="post" enctype="multipart/form-data">
		{{block name='fields'}}
			{{foreach $fields as $f}}
				{{include file='file:[dophp]crud/form-field.tpl' field=$f}}
			{{/foreach}}
		{{/block}}

		<div class="form-group">
			<div class="col-sm-offset-2 col-sm-10">
				<button name="submit" value="submit" type="submit" class="btn btn-primary">{{if $pk}}{{$strEdit}}{{else}}{{$strInsert}}{{/if}}</button>
			</div>
		</div>
	</form>
	<script type="text/javascript">
		// Init select2 script
		$(document).ready(function() {
			$('.select2').select2();
		});
	</script>
{{/block}}
