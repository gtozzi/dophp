{{* Generic form input widget *}}
{{strip}}
	{{if ! isset($field)}}
		Error: $field is not defined
	{{/if}}

	{{$id=$field->getId()}}
	{{$type=$field->getType()}}

	{{$name=$field->getHTMLName()}}
	{{$prepend=$field->getPrependText()}}

	{{$value=$field->getDisplayValue()}}
	{{if ! isset($label)}}
		{{$label=$field->getLabel()}}
	{{/if}}
	{{if $type != 'hidden' && $type != 'label'}}
		{{$readonly=$field->isReadOnly()}}
	{{else}}
		{{$readonly=true}}
	{{/if}}

	{{if method_exists($field, 'getPlaceholder')}}
		{{$placeholder=$field->getPlaceholder()}}
	{{else}}
		{{$placeholder=null}}
	{{/if}}


	{{if method_exists($field, 'getMaxLen')}}
		{{$maxlen=$field->getMaxLen()}}
	{{else}}
		{{$maxlen=null}}
	{{/if}}

	{{if $type=='number' || $type=='currency'}}
		{{$min=$field->getMin()}}
		{{$max=$field->getMax()}}
		{{$step=$field->getStep()}}
	{{/if}}

	{{if $field->getVStatus() == \dophp\widgets\Field::V_SUCCESS}}
		{{$pvclass='has-success'}}
		{{$fclass='is-valid'}}
		{{$fvclass='valid-feedback'}}
	{{elseif $field->getVStatus() == \dophp\widgets\Field::V_ERROR}}
		{{$pvclass='has-danger'}}
		{{$fclass='is-invalid'}}
		{{$fvclass='invalid-feedback'}}
	{{else}}
		{{$pvclass=null}}
		{{$fclass=null}}
		{{$fvclass=null}}
	{{/if}}

	{{$vfeedback=$field->getVFeedback()}}

	{{* Include/display options *}}
	{{$dopts=$field->getDisplayOptions()}}
	{{if ! isset($colw)}}
		{{if isset($dopts['colw'])}}
			{{$colw=$dopts['colw']}}
		{{else}}
			{{$colw=10}}
		{{/if}}
	{{/if}}
	{{if ! isset($labelw)}}
		{{if isset($dopts['labelw'])}}
			{{$labelw=$dopts['labelw']}}
		{{else}}
			{{$labelw=2}}
		{{/if}}
	{{/if}}
	{{if ! isset($nolabel)}}
		{{if isset($dopts['nolabel'])}}
			{{$nolabel=$dopts['nolabel']}}
		{{else}}
			{{$nolabel=false}}
		{{/if}}
	{{/if}}
	{{if ! isset($norow)}}
		{{if isset($dopts['norow'])}}
			{{$norow=$dopts['norow']}}
		{{else}}
			{{$norow=false}}
		{{/if}}
	{{/if}}

	{{$required=$field->isRequired()}}
	{{$softrequired=$field->isSoftRequired()}}
	{{if $required===true}}
		{{$reqCls='required'}}
	{{elseif $softrequired===true}}
		{{$reqCls='softrequired'}}
	{{else}}
		{{$reqCls=''}}
	{{/if}}

	{{$agSelectParent_=false}}
	{{if isset($agSelectParent)}}
		{{$agSelectParent_=$agSelectParent}}
	{{/if}}

	{{if $type == 'hidden' || $type == 'label'}}
		{{$linkurl=null}}
	{{else}}
		{{$linkurl=$field->getLinkUrl()}}
	{{/if}}
{{/strip}}
{{if $type=='hidden'}}
	<input type="hidden" id="{{$id}}" name="{{$name|htmlentities}}" value="{{$value|htmlentities}}">
{{elseif $type=='label'}}
	{{$value|htmlentities}}
{{else}}
	{{if ! $norow}}
		<div id="{{$id}}_group" class="form-group row">
	{{/if}}
		{{block name='label'}}
			{{if ! $nolabel}}
				{{if $type=='checkbox'}}
					<div class="col-sm-{{$labelw}}"></div>
				{{else}}
					{{mstrip}}<label
						id="{{$id}}_label"
						for="{{$id}}"
						class="col-sm-{{$labelw}} col-form-label {{$reqCls}}"
					>{{/mstrip}}{{$label}}</label>
				{{/if}}
			{{/if}}
		{{/block}}
		<div id="{{$id}}_col" class="inputcol col-sm-{{$colw}} {{$pvclass}}">
			{{block name='pre-input'}}
				{{if $type=='date' || $type=='asyncFile' || $type=='currency' || $linkurl || $prepend}}
					<div class="input-group">
				{{/if}}
				{{if $type=='asyncFile'}}
					<input id="{{$id}}_txt" type="text" class="form-control">
					<input id="{{$id}}_hid" name="{{$name|htmlentities}}" type="hidden" class="ag-asyncupl-hid-fld" value="{{$value|htmlentities}}">
					<span class="input-group-btn">
						<button id="{{$id}}_btn" type="button" class="btn btn-secondary ag-calender-button">
							Seleziona
						</button>
					</span>
				{{elseif $prepend}}
					<div class="input-group-prepend">
						<span class="input-group-text">{{$prepend|htmlentities}}</span>
					</div>
				{{/if}}
				{{if $type=='checkbox'}}
					<label for="{{$id}}" class="custom-control custom-checkbox ag-checkbx-cust">
				{{/if}}
			{{/block}}
			{{block name='input'}}
				{{mstrip}}<{{if $type=='select'||$type=='textarea'}}{{$type}}{{else}}input{{/if}}
					id="{{$id}}"
					class="{{if $type=='checkbox'}}align-middle custom-control-input{{else}}form-control{{/if}} {{$reqCls}} {{$fclass}}"
					{{if $type!='asyncFile'}}
						name="{{$name|htmlentities}}"
					{{/if}}
					data-hardvalue="{{$value|htmlentities}}"
					data-vstatus="{{$field->getVStatus()}}"

					{{* Formhandle fields *}}
					data-type="{{$type|htmlentities}}"
					{{if $field->getForm()}}
						data-form="{{$field->getForm()->getId()|htmlentities}}"
						data-valurl="{{$field->getForm()->action()->asString()|htmlentities}}"
					{{/if}}
					data-name="{{$field->getName()|htmlentities}}"
					data-namespace="{{$field->getNameSpace()|json_encode|htmlentities}}"
					{{if $type=='currency'}}
						data-decsep="{{$field->getDecSep()|htmlentities}}"
						data-thosep="{{$field->getThoSep()|htmlentities}}"
						data-decdigits="{{$field->getDecDigits()|htmlentities}}"
					{{/if}}
					{{if $type=='duration'}}
						data-sep="{{$field->getSep()|htmlentities}}"
					{{/if}}

					{{if $type=='textarea'}}
						rows="{{$field->getRows()}}"
					{{/if}}
					{{if isset($maxlen)}}maxlength="{{$maxlen}}"{{/if}}
					{{if $type=='asyncFile'}}
						type="file"
					{{elseif $type!='select' && $type!='textarea'}}
						type="{{if $type=='date' || $type=='currency'}}text{{else}}{{$type}}{{/if}}"
						value="{{if $type=='checkbox'}}1{{else}}{{$value|htmlentities}}{{/if}}"
					{{/if}}
					{{if $type=='password' && $field->getAutocomplete()}}
						autocomplete="{{$field->getAutocomplete()}}"
					{{/if}}
					{{if $type!='select'}}
						{{if isset($placeholder)}}placeholder="{{$placeholder|htmlentities}}"{{/if}}
					{{/if}}
					{{if $type=='number'}}
						{{if isset($min)}}min="{{$min|formatCFloat}}"{{/if}}
						{{if isset($max)}}max="{{$min|formatCFloat}}"{{/if}}
						{{if isset($step)}}step="{{$step|formatCFloat}}"{{/if}}
					{{/if}}
					{{if $type=='checkbox'}}
						{{if $value}}checked{{/if}}
					{{/if}}
					{{if $type=='asyncFile'}}
						style="display:none;"
					{{/if}}
					{{if $readonly}}
						{{if $type=='select' || $type=='checkbox'}}disabled{{else}}readonly{{/if}}
					{{/if}}
				>{{/mstrip}}{{strip}}
				{{if $type=='select'}}
					{{if isset($placeholder)}}
						{{"\n\t"}}<option value="" disabled selected>{{$placeholder|htmlentities}}</option>
					{{/if}}
					{{foreach $field->getOptions() as $o}}
						{{if ! $field->isAjax() || $o->isSelected()}}
							{{"\n\t"}}<option
								{{' '}}value="{{$o->getId()|htmlentities}}"
								{{if $o->isSelected()}} selected {{/if}}
							>{{$o->getDescr()|htmlentities}}</option>
						{{/if}}
					{{/foreach}}
				{{elseif $type=='textarea'}}
					{{$value|htmlentities}}
				{{elseif $type=='checkbox'}}
					<span class="custom-control-description">{{$label|htmlentities}}</span>
				{{/if}}
				{{if $type=='select'||$type=='textarea'}}
					</{{$type}}>
				{{/if}}
				{{/strip}}
			{{/block}}
			{{block name='after-input'}}
				{{if $linkurl}}
						<span class="input-group-append">
							<button class="btn btn-info" type="button" onclick="window.location.href={{$linkurl|json_encode|htmlentities}}" title="Apri"><span class="fa fa-external-link-square"></span></button>
						</span>
					</div><!-- input group end -->
				{{/if}}

				{{if $type=='date'}}
					<span class="input-group-btn">
						<button class="btn btn-secondary ag-calender-button" type="button" tabindex="-1" onclick="$('#{{$id}}').datepicker('show')" {{if $readonly}}disabled{{/if}}><span class="fa fa-calendar"></span></button>
					</span>
				</div><!-- Input group end -->
					<script>
						$("#{{$id}}").datepicker({
							language: 'it',
							format: 'dd.mm.yyyy',
						});
					</script>
				{{elseif $type=='asyncFile'}}
					</div><!-- Input group end -->
					<script>
						// File upload asincrono
						$('#{{$id}}_btn').click(function() {
							$('#{{$id}}').click();
						});
						$('#{{$id}}').change(function() {

							// check if upload input is inside a sheetList_form_section
							// and get linked doc ID
							var sheetID=0;
							var formSectCont=$(this).parents(".ag-sheetlist-frm-sect");
							if(formSectCont.length==1){
								var formID=$(formSectCont).attr("data-frmid");
								if(parseInt(formID)>0){
									sheetID=formID;
								}
							}


							$('#{{$id}}_txt').val( $(this).val() );

							$(".ag-prog-itm_{{$id}}").css("display","flex");
							var currForm = $(this).parents("form");
							$(currForm).find(".save-button").prop("disabled",true);

							var agForm_{{$id}} = new FormData();
							agForm_{{$id}}.append("file_{{$id}}", $('#{{$id}}')[0].files[0]);

							var startTime = new Date();
							startTime = ( startTime.getTime() / 1000 );

							$.ajax({
								type:"POST",
								contentType: false,
								cache: false,
								processData:false,
								data: agForm_{{$id}},
								url: '?do=fileUpload',
								xhr: function(){
									var currHXR= $.ajaxSettings.xhr();
									if(currHXR.upload){
										currHXR.upload.addEventListener("progress",function(e){
											if(e.lengthComputable){

												// show progress bar
												$(".ag-progress-line_{{$id}}").css("display","block");
												$(".ag-upl-feedback.itm_{{$id}}").css("display","none");

												var nowTime = new Date();
												nowTime = ( nowTime.getTime() / 1000 );
												var elapsedTime = nowTime - startTime;
												var secondsBeforeETACalc = 3;

												// if given time is elapsed show ETA info
												if( elapsedTime > secondsBeforeETACalc){

													// show ETA info
													$(".progress-ETA").css("display","block");

													var slices = (e.total / e.loaded);
													var totalTime = slices * elapsedTime;
													var ETA = totalTime - elapsedTime;

													var timeUnit= 60;
													var missingMinutes = parseInt(ETA) / timeUnit;
													var missingSeconds = parseInt(ETA) % timeUnit;

													if(parseInt(missingMinutes)> 59){
														var ETAString = "Più di un'ora al termine";
														$(".progress-ETA-itm_{{$id}}").html(ETAString);
													}
													else{
														if(parseInt(missingSeconds)>0){

															var ETAString = "Circa ";
															if(parseInt(missingMinutes)>0){
																if(parseInt(missingMinutes)>1){
																	ETAString += parseInt(missingMinutes)+" minuti e ";
																}
																else{
																	ETAString += parseInt(missingMinutes)+" minuto e ";
																}
															}
															if(parseInt(missingSeconds)>0){
																if(parseInt(missingSeconds)>1){
																	ETAString += parseInt(missingSeconds)+" secondi";
																}
																else{
																	ETAString += parseInt(missingSeconds)+" secondo";
																}
															}
															ETAString +=" al termine";

															$(".progress-ETA-itm_{{$id}}").html(ETAString);
														}
													}
												}

												var perc= parseInt((e.loaded / e.total) * 100);
												$(".ag-progress_{{$id}}").css("width",perc+"%");
												$(".ag-progress_{{$id}}").text(perc+"%");

												// if current upload field is linked to doc_sheet object,
												// updates the doc_sheet upload_progress_bar
												if(sheetID){
													$(".doc-tpl-link[data-docid="+sheetID+"] .ag-sheetlist-itm-upl").css("width",perc+"%");
												}
											}
										},false);
									}
									return currHXR;
								},
								success: function(data){




									console.log('File upload success', data);
									let myfile = data.files['file_'+{{$id|json_encode}}];

									if( myfile && myfile.success ) {
										$('#{{$id}}_hid').val(myfile.id);
									} else {
										let message = myfile.message;
										//TODO
									}

									setTimeout(function(){
									// show feedback response row
										var res=data["files"]["file_{{$id}}"];
										if(typeof(res["success"])!="undefined"){
											if(res["success"]==true){
												$(".ag-upl-feedback.itm_{{$id}}.ag-upl-success").css("display","block");
											}
											else if(res["success"]==false){
												showAjaxError(res["message"]);
											}
										}
									},700);
								},
								error: function(jqXHR, textStatus, errorThrown){
									setTimeout(function(){
										let httpCode = jqXHR.status;
										showAjaxError('Codice di errore HTTP ' + httpCode);
									},700);
								},
								complete: function(jqXHR, textStatus){
									// enable save button after file upload
									$(currForm)
										.find(".save-button")
										.prop("disabled",false);

									// hide the progress bar and ETA text
									$(".progress-ETA").css("display","none");
									$(".ag-progress-line_{{$id}}").css("display","none");
								}
							});

						});

						let showAjaxError = function(errorMessage) {
							$(".ag-upl-feedback.itm_{{$id}}.ag-upl-error").css("display","block");
							if(errorMessage){
								$(".ag-upl-feedback.itm_{{$id}}.ag-upl-error .ag-upl-feedback-txt").text(errorMessage);
							}
						}

						//$("#ag-fileupl-s-act-{{$id}}").click(function(){
						//});
					</script>
				{{elseif $type=='select'}}
					<script>

						{{if $agSelectParent_}}
							var agTotalSelect2Elem = $("#{{$agSelectParent_}}_div select").length;
							var agLastAddedSelect = $("#{{$agSelectParent_}}_div select")[agTotalSelect2Elem-1]
						{{else}}
							var agLastAddedSelect= $("#{{$id}}")[0];
						{{/if}}

						$(agLastAddedSelect).select2({
							theme: 'bootstrap',
							containerCssClass: {{$reqCls|json_encode}},
							dropdownCssClass: {{$reqCls|json_encode}},
							width: 'style',
							{{if $field->isAjax()}}
								ajax: {
									url: {{$field->getForm()->action()->asString()|json_encode}},
									delay: 100,
									dataType: 'json',
									data: function (params) {
										params.ajaxParams = $(agLastAddedSelect).data('ajaxParams');
										params.ajaxField = {{$field->getName()|json_encode}};
										{{if $field->getForm()}}
											params.ajaxForm = {{$field->getForm()->getId()|json_encode}};
										{{/if}}
										return params;
									},
								},
							{{/if}}
						});
						$('#select2-{{$id}}-container').parent().on("keydown", function(e) {
							var key = e.keyCode;
							if (key != 9 && key != 13 && key != 16) {
								$(this).closest(".select2-container").siblings('select:enabled').select2('open');
								$(".select2-search__field").focus();
							}
						});
					</script>
				{{elseif $type=='currency'}}
					</div><!-- Input group end -->
				{{elseif $type=='checkbox'}}
					<span class="custom-control-indicator"></span>
					</label>
				{{elseif $prepend}}
					</div><!-- Input group end -->
				{{/if}}
				<script>
					$("#{{$id}}").formhandle();
				</script>
				<div id="{{$id}}_feedback"
					class="form-control-feedback {{$fvclass}}"
					style="display: {{if $vfeedback}}inline{{else}}none{{/if}};">{{strip}}
						{{if $vfeedback}}{{$vfeedback|htmlentities}}{{/if}}
				{{/strip}}</div>
			{{/block}}
		</div>
	{{if ! (isset($norow) && $norow)}}
		</div>
	{{/if}}


	{{if $type=='asyncFile'}}
		<div class="form-group row ag-progress-line_{{$id}}">

			<div class="offset-sm-2 col-sm-10">
				<div class="progress ag-progress ag-prog-itm_{{$id}}">
					<div class="progress-bar ag-progress_{{$id}}" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
				</div>
			</div>
		</div>
		<div class="form-group row">
			<div class="offset-sm-2 col-sm-10">
				<div class="progress-ETA progress-ETA-itm_{{$id}}"></div>
				<div class="ag-upl-feedback ag-upl-success itm_{{$id}}">
					<span class="ag-upl-feedback-icn fa fa-thumbs-o-up"></span> File caricato correttamente
				</div>
				<div class="ag-upl-feedback ag-upl-error itm_{{$id}}">
					<span class="ag-upl-feedback-icn fa fa-exclamation-circle"></span> Errore:
					<div class="ag-upl-feedback-txt"></div>
				</div>
			</div>
		</div>

	{{/if}}

{{/if}}
