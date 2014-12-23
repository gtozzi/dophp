{{if $field->type()=='hidden'}}
	<input type="hidden" name="{{$field->name()}}" value="{{(string)$field}}"/>
{{else}}
	<div class="form-group">
		<label {{if $field->name()}}for="{{$field->name()}}"{{/if}}
			class="col-sm-2 control-label">{{$field->label()}}</label>
		<div class="col-sm-10 {{if $field->error()}}has-error{{/if}}">
			{{if $field->type()=='label'}}
				<input type="text" class="form-control form-label" value="{{(string)$field}}" readonly="readonly"/>
			{{elseif $field->type()=='select' || $field->type()=='multi'}}
				<select name="{{$field->name()}}{{if $field->type()=='multi'}}[]{{/if}}" class="form-control select2"
					{{if $field->type()=='multi'}}multiple{{/if}}
				>
					<option value="">{{$field->descr()}}</option>
					{{$og=null}}
					{{if $field->data()}}{{foreach $field->data() as $v}}
						{{if $v->group() !== $og}}
							{{if $og !== null}}
								</optgroup>
							{{/if}}
							<optgroup label="{{$v->group()}}">
							{{$og=$v->group()}}
						{{/if}}
						<option value="{{$v->value()}}"
							{{if ($field->type()!='multi' && ((string)$field)==$v->value()) || ($field->type()=='multi' && $field->value() && in_array($v->value(),$field->value()))}}
								selected="1"
							{{/if}}>{{$v->descr()}}</option>
						{{if $og !== null && $v@last}}
							</optgroup>
						{{/if}}
					{{/foreach}}{{/if}}
				</select>
			{{elseif $field->type()=='check'}}
				<input type="checkbox" name="{{$field->name()}}" value="1" {{if $field->value()}}checked="checked"{{/if}}>
				{{$field->descr()}}
			{{elseif $field->type()=='textarea' || $field->type()=='wysiwyg'}}
				<textarea class="form-control {{if $field->type()=='wysiwyg'}}wysiwyg{{/if}}"
					name="{{$field->name()}}">{{(string)$field}}</textarea>
			{{else}}
				{{if $field->type()=='file' && (string)$field}}
					<a href="{{(string)$field}}" target="_blank">
						<img src="{{(string)$field}}" alt="preview" class="preview">
					</a>
				{{/if}}
				<input name="{{$field->name()}}" type="{{if $field->type()=='auto'}}text{{else}}{{$field->type()}}{{/if}}"
					class="{{if $field->type()!='file'}}form-control{{/if}} {{if $field->type()=='auto'}}autocomplete{{/if}}"
					{{if $field->type()!='file'}}placeholder="{{$field->descr()}}"{{/if}}
					value="{{(string)$field}}"
					{{if $field->type()=='password'}}autocomplete="off"{{/if}}
				/>
				{{if $field->type()=='file'}}
					<p class="help-block">{{$field->descr()}}</p>
				{{/if}}
			{{/if}}
			<p class="help-block">{{$field->error()}}</p>
		</div>
	</div>
{{/if}}
