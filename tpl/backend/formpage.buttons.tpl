{{foreach $buttons->buttons() as $btn}}
	{{if $btn->hidden}}
		{{continue}}
	{{/if}}
	<button type="{{$btn->type}}" class="{{$btn->id}}-button btn {{$btn->class}} btnc-{{$btn->phpclass()}}"
		{{if ! $btn->enabled}}disabled{{/if}}
		{{foreach $btn->htmldata() as $k => $v}}
			data-{{$k}}="{{htmlentities($v)}}"
		{{/foreach}}
		{{if isset($btnForm)}}form="{{$btnForm|htmlentities}}"{{/if}}
		{{if ! $text}}title="{{$btn->label|htmlentities}}"{{/if}}
	>{{strip}}
		<span class="fa {{$btn->icon}}"></span>
		{{if $text}}{{' '}}{{$btn->label|htmlentities}}{{/if}}
	{{/strip}}</button>
{{/foreach}}
