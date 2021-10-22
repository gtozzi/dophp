<!DOCTYPE html>
<html lang="it">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="generator" content="DoPhp Framework">
	<link rel="shortcut icon" href="{{$med}}/img/favicon.ico" type="image/x-icon"/>

	<title>{{$config['site']['name']}}{{if isset($pageTitle)}} - {{$pageTitle}}{{/if}}</title>

	<!-- Own styles and files -->
	<link rel="stylesheet" href="{{$med}}/css/base.css">
	<script src="{{$med}}/js/base.js"></script>

	{{block name='head'}}{{/block}}
</head>
<body>
{{block name='body'}}

	<div class="container">
		{{block name='content'}}{{/block}}
	</div>

	<footer class="footer">
		<div class="container text-muted navbar-dark">
			{{$config['site']['name']}} ver.{{$config['site']['version']}}
			<br/>Copyright &copy; 2018
			<a href="#">
				DoPhp
			</a>
		</div>
	</footer>
{{/block}}

{{block name='foot'}}{{/block}}

</body>
</html>
