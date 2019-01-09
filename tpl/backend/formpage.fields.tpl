{{$currow=null}}
{{foreach $container->fields() as $f}}
	{{if $ungroupedOnly && $f->getGroup()}}
		{{continue}}
	{{/if}}

	{{$dopts=$f->getDisplayOptions()}}
	{{if isset($dopts['row'])}}{{$row=$dopts['row']}}{{else}}{{$row=null}}{{/if}}
	{{$norow=(bool)$row}}

	{{if $currow && $row != $currow}}
		</div>
	{{/if}}
	{{if $row && $row != $currow}}
		<div id="row_{{$row|htmlentities}}" class="form-group row">
	{{/if}}
	{{$currow=$row}}
		{{include file=$f->getTemplate() field=$f norow=$norow}}
	{{if $currow && $f@last}}
		</div>
	{{/if}}
{{/foreach}}
