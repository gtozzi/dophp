{{extends file=$baseTpl}}

{{block name='content'}}

	{{block name='buttonbar'}}
		<div class="form-group ag-quick-btn-bar" style="margin-top:5px; margin-left: 20px;">
			{{include file='backend/formpage.buttons.tpl' text=false btnForm='mainForm'}}
		</div>
	{{/block}}

	{{block name='afterbread'}}{{/block}}

	{{block name='navtabs'}}
		{{if isset($tabs) && $tabs && $id}}
			<ul class="nav nav-tabs ag-form-tabs">
				{{foreach $tabs->getChilds() as $tab}}
					<li class="nav-item">
						<a
							class="nav-link {{if $tab->isActive()}}active{{/if}}"
							href="{{$tab->getUrl()|htmlentities}}&amp;id={{$id}}{{foreach $getArgs as $k=>$v}}&amp;{{$k|urlencode}}={{$v|urlencode}}{{/foreach}}"
						>{{$tab->getLabel()|htmlentities}}</a>
					</li>
				{{/foreach}}
			</ul>
		{{/if}}
	{{/block}}

	{{block name='tab'}}
		{{if ! $form->isValid()}}
			<div class="alert alert-danger" role="alert">
				<strong>Errore.</strong> Correggi i campi evidenziati e riprova.
				<br>
				<small>{{strip}}(
					{{foreach $form->invalidFields() as $fn}}
						{{$fn->getLabel()|htmlentities}}
						{{if ! $fn@last}}
							,{{' '}}
						{{/if}}
						<!-- {{$fn->getName()|htmlentities}} -->
					{{/foreach}}
				){{/strip}}</small>
			</div>
		{{/if}}
		<div class="container ag-form-container">
			<form id="mainForm" method="post" action="{{$form->action()->asString()|htmlentities}}">
				<input type="hidden" name="{{$formkey|htmlentities}}" value="{{$form->getId()|htmlentities}}">
				{{block name='form'}}
					{{* Grouped fields *}}
					{{foreach $form->fieldGroups() as $fg}}
						<h5 id="{{$fg->getId()|htmlentities}}" class="ag-section-title ag-collapse-handler" data-target="{{$fg->getId()|htmlentities}}_fieldDiv">
							{{$fg->getLabel()|htmlentities}}
						</h5>
						<div id="{{$fg->getId()|htmlentities}}_fieldDiv" class="collapse show">
							{{foreach $fg->fields() as $f}}
								{{include file=$f->getTemplate() field=$f}}
							{{/foreach}}
						</div>
					{{/foreach}}

					{{* Ungrouped fields *}}
					{{foreach $form->fields() as $f}}
						{{if $f->getGroup()}}
							{{continue}}
						{{/if}}
						{{include file=$f->getTemplate() field=$f}}
					{{/foreach}}
				{{/block}}

				{{block name='bottombuttons'}}
					<div class="form-group row">
						<div class="offset-sm-2 col-sm-10">
							{{block name='beforebottombuttons'}}{{/block}}
							<!-- <div class="ag-main-form-btn"> -->
								{{include file='backend/formpage.buttons.tpl' text=true}}
							<!-- </div> -->
							{{block name='afterbottombuttons'}}{{/block}}
						</div>
					</div>
				{{/block}}
			</form>
		</div>

		<!-- Saving… modal -->
		<div class="modal fade" id="processingModal">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h5 id="processingModalText" class="modal-title">Salvataggio in corso…</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="progress">
							<div id="processingModalProgress" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100" style="width: 100%"></div>
						</div>
					</div>
				</div>
			</div>
		</div>
	{{/block}}
	<script>
		/**
		 * Warns the user before leaving the page if form has been modified
		 */
		$(document).ready(function() {
			var formModified = new Set();
			var formEls = $('#mainForm').find('input, textarea, select');

			$('input').on('input', formChanged);
			$('textarea').on('input', formChanged);
			$('select').on('change', formChanged);

			window.onbeforeunload = confirmExit;

			function formChanged(ev) {
				let field = ev.target;
				let $field = $(field);

				// Clean validation classes
				$field.parent().removeClass('has-success');
				$field.removeClass('form-control-success');
				$field.parent().removeClass('has-danger');
				$field.removeClass('form-control-danger');

				// update the set of modified fields
				if( $field.val() == $field.data('hardvalue') ) {
					// This element has NOT been modified
					formModified.delete(field);
					//$field.parent().removeClass('has-success');
					//$field.removeClass('form-control-success');
				} else {
					formModified.add(field);
					//$field.parent().addClass('has-success');
					//$field.addClass('form-control-success');
				}
				//console.log('Modifief fields', formModified);

				// Update save button's class
				if( formModified.size ) {
					// form has been modified
					$(".save-button").removeClass('btn-secondary');
					$(".save-button").addClass('btn-primary');
					$(".cancel-button").removeClass('btn-secondary');
					$(".cancel-button").addClass('btn-warning');
				} else {
					$(".save-button").removeClass('btn-primary');
					$(".save-button").addClass('btn-secondary');
					$(".cancel-button").removeClass('btn-warning');
					$(".cancel-button").addClass('btn-secondary');
				}
			}

			function confirmExit() {
				if( formModified.size )
					return "Ci sono modifiche non salvate. Sei sicuro di voler abbandonare?";
			}

			function confirmDelete() {
				{{$letter=($whatGender=='f')?'a':'o'}}
				if( window.confirm('Confermi di voler eliminare definitivamente quest{{$letter}} '+{{$what|json_encode}}+'?') )
				$.ajax({
					url: {{$form->action()->asString()|json_encode}},
					type: 'DELETE',
					success: function(result) {
						console.log(result);
				 	}
				});
			}

			$(".save-button").click(function() {
				formModified = new Set();
				$('#processingModalText').text({{$savemessage|json_encode}});
				$('#processingModalProgress').removeClass('bg-warning');
				$('#processingModal').modal('show')
			});
			$(".cancel-button").click(function() {
				formModified = new Set();
				$('#processingModalText').text({{$cancelmessage|json_encode}});
				$('#processingModalProgress').addClass('bg-warning');
				$('#processingModal').modal('show')
				window.location.reload();
			});
			$(".delete-button").click(confirmDelete);

			// URL button
			$('.btnc-linkbutton').click(function() {
				let btn = $(this);
				let url = btn.data('url');
				let newtab = Boolean(btn.data('newtab'));

				if (! url) {
					window.alert('Software error: missing URL');
					return;
				}

				if (newtab)
					window.open(url, '_blank');
				else
					window.location.href=url;
			});
		});
	</script>
	{{block name='finalscripts'}}{{/block}}
{{/block}}