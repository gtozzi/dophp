{{extends file='base-backend.tpl'}}
{{block name='content'}}
	<h1>{{$pageTitle}}</h1>

	<span class="error">{{_("Can't delete:")}} {{$errors[0]}}</span>
	<!--{{$errors[1]}}-->
{{/block}}
