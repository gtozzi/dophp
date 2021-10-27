{{strip}}
	{{if ! isset($colw) }}
		{{$colw=4}}
	{{/if}}
	{{$fname=htmlentities($field->getHtmlName())}}
	{{$oname=htmlentities($option->getId())}}
{{/strip}}
<div id="{{$field->getId()}}_opt_{{$option->getId()}}" class="col-sm-{{$colw}} sel-root ag-sel-spin" {{if ! $o->isSelected()}}style="display: none;"{{/if}}>
	<div class="input-group">
		<input type="hidden"
			name="{{$fname}}[options][{{$oname}}][selected]"
			id="{{$field->getId()}}_opt_{{$option->getId()}}_selected"
			value="{{if $option->isSelected()}}1{{else}}0{{/if}}">
		<input type="text" class="form-control"
			value="{{$option->getSelectedDescr()}}"
			data-id="{{$option->getId()}}" readonly>
		{{foreach $field->getAttrs() as $a}}
			{{$n=htmlentities($a->getName())}}
			{{$n="`$fname`[attrs][`$n`]"}}
			{{include file='widgets/multiSelect.attr.tpl' attr=$a name=$n}}
		{{/foreach}}
		{{foreach $option->getAttrs() as $a}}
			{{$n=htmlentities($a->getName())}}
			{{$n="`$fname`[options][`$oname`][attrs][`$n`]"}}
			{{include file='widgets/multiSelect.attr.tpl' attr=$a name=$n}}
		{{/foreach}}
		{{if ! $field->isReadOnly()}}
			<span class="input-group-btn">
				<button class="btn btn-warning ag-addon-button" type="button" onclick="formUtil.multiSelect.del($(this))"
					data-id="{{$option->getId()}}"
					data-optid="{{$field->getId()}}_opt_{{$option->getId()}}"
					data-selid="{{$field->getId()}}_add"
				>
					<span class="fa fa-trash"></span>
				</button>
			</span>
		{{/if}}
	</div>
</div>
