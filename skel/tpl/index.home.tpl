{{extends file='base.tpl'}}
{{block name='content'}}
	{{if $user && $user->getUid()}}
		{{include file='index.home.auth.tpl'}}
	{{else}}
		{{include file='index.home.anon.tpl'}}
	{{/if}}
{{/block}}
