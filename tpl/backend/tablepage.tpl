{{extends file=$baseTpl}}
{{block name='content'}}
	{{foreach $tables as $table}}
		{{$table->getHTMLStructure()}}
	{{/foreach}}
{{/block}}
