{{extends file=$baseTpl}}
{{block name='content'}}
	<h1>{{$pageTitle}}</h1>

	<span class="error">{{$strCantDelete}}: {{$errors[0]}}</span>
	<!--{{$errors[1]}}-->
{{/block}}
