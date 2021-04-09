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
			{{if $btn->isDisabledOnFormDirty()}}disabled-on-form-dirty{{/if}}
		{{/mstrip}}"
		id="{{$btn->getId()|htmlentities}}"
		{{if ! $btn->enabled}}disabled{{/if}}
		{{foreach $btn->htmldata() as $k => $v}}
			data-{{$k|htmlentities}}="{{$v|htmlentities}}"
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
				<a id="{{$btn->getId()|htmlentities}}"
					class="dropdown-item {{if $child->post}}btnc-postbutton{{/if}}"
					{{if $child->post}}
						data-post="{{$child->post|json_encode|htmlentities}}"
						data-url="{{$child->url|htmlentities}}"
						href="javascript::dophpPostButton(this);"
					{{else}}
						href="{{$child->url|htmlentities}}"
					{{/if}}
				>
					{{$child->label|htmlentities}}
				</a>
			{{/foreach}}
		</div>
	{{/if}}
{{/foreach}}
