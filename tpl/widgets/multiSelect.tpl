{{strip}}
	{{if $field->getVStatus() == \dophp\widgets\Field::V_SUCCESS}}
		{{$pvclass='has-success'}}
		{{$fclass='form-control-success'}}
	{{elseif $field->getVStatus() == \dophp\widgets\Field::V_ERROR}}
		{{$pvclass='has-danger'}}
		{{$fclass='form-control-danger'}}
	{{else}}
		{{$pvclass=null}}
		{{$fclass=null}}
	{{/if}}
{{/strip}}<div class="container">
	<div class="row" id="ms{{$field->getName()}}Row">
		{{foreach $field->getOptions() as $o}}
			{{include file='widgets/multiSelect.option.tpl' option=$o colw=6}}
		{{/foreach}}
	</div>
	{{if ! $field->isReadOnly() }}
		<div class="form-group row">
			<label class="col-sm-2 col-form-label">Aggiungi</label>
			<div class="col-sm-10 {{$pvclass}}">
				<select id="{{$field->getId()}}_add" class="form-control {{$fclass}}" onchange="formUtil.multiSelect.add($(this))">
					<option></option>
					{{foreach $field->getOptions() as $o}}
						<option
							value="{{$o->getId()}}"
							data-optid="{{$field->getId()}}_opt_{{$o->getId()}}"
							{{if $o->isSelected()}}disabled{{/if}}>{{$o->getListDescr()}}
						</option>
					{{/foreach}}
				</select>
			</div>
		</div>
	{{/if}}
</div>
