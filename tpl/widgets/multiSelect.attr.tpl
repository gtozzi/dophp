	<!-- <label class="form-check-label" title="{{$attr->getLabel()|htmlentities}}"> -->
		<input
			name="{{$name}}"
			class="secondary"
			type="{{$attr->getRType()}}"
			{{strip}}
				{{if $attr instanceof \dophp\widgets\MultiSelectFieldAttr}}
					{{if $attr->isCheckedFor($option->getId())}}
						checked
					{{/if}}
				{{elseif $attr instanceof \dophp\widgets\MultiSelectFieldOptionAttr}}
					{{if $attr->isChecked()}}
						checked
					{{/if}}
				{{elseif $attr instanceof \dophp\widgets\MultiSelectFieldOptionNumberAttr}}

				{{/if}}
			{{/strip}}
			{{if $attr->getRType()=='radio'}}value="{{$option->getId()}}"{{/if}}
			{{if $attr->getRType()=='number'}}value="{{$attr->getValue()}}"{{/if}}
			{{if $field->isReadOnly()}}disabled{{/if}}
		>
		<span
			class="multiselect-attr-descr"
			{{if $attr->getLabel()}}title="{{$attr->getLabel()|htmlentities}}"{{/if}}
		>{{$attr->getDescr()|htmlentities}}</span>
	<!-- </label> -->
