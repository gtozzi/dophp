{{foreach $buttons->buttons() as $btn}}
	{{if $btn->hidden}}
		{{continue}}
	{{/if}}

	<button type="{{$btn->type}}" class="{{mstrip}}
			{{$btn->id}}-button btn
			{{$btn->class}}
			btnc-{{$btn->phpclass()}}
			{{if isset($size) && $size}}btn-{{$size}}{{/if}}
			{{if $btn->isDropdown()}}dropdown-toggle{{/if}}
		{{/mstrip}}"
		id="{{$btn->getId()|htmlentities}}"
		{{if ! $btn->enabled}}disabled{{/if}}
		{{foreach $btn->htmldata() as $k => $v}}
			data-{{$k}}="{{htmlentities($v)}}"
		{{/foreach}}
		{{if isset($btnForm)}}form="{{$btnForm|htmlentities}}"{{/if}}
		{{if ! $text}}title="{{$btn->label|htmlentities}}"{{/if}}
		{{if $btn->isDropdown()}}
			data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
		{{/if}}
	>{{strip}}
		<span class="fa {{$btn->icon}}"></span>
		{{if $text}}{{' '}}{{$btn->label|htmlentities}}{{/if}}
	{{/strip}}</button>

	{{if $btn->isDropdown()}}
		<div class="dropdown-menu" aria-labelledby="{{$btn->getId()|htmlentities}}">
			{{foreach $btn->childs() as $child}}
				<a id="{{$btn->getId()|htmlentities}}" class="dropdown-item" href="{{$child->url|htmlentities}}">
					{{$child->label|htmlentities}}
				</a>
			{{/foreach}}
		</div>
	{{/if}}
{{/foreach}}
