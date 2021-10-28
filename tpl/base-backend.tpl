{{* Base backend template file *}}
<!DOCTYPE html>
<html lang="it">
<head>
	{{block name='meta'}}
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta name="generator" content="DoPhp Framework">
	{{/block}}

	{{block name='favicon'}}
		<link rel="manifest" href="{{$med}}/manifest.json">
		<link rel="shortcut icon" href="{{$med}}/img/favicon.ico" type="image/x-icon"/>
	{{/block}}

	{{block name='title'}}
		<title>{{$config['site']['name']}}{{if isset($pageTitle)}} - {{$pageTitle}}{{/if}}</title>
	{{/block}}

	{{block name='headincludes'}}
		<!-- jQuery -->
		<script src="{{$config['dophp']['url']}}/webcontent/js/jquery.min.js"></script>

		<!-- jQuery TimeAgo plugin -->
		<script src="{{$config['dophp']['url']}}/webcontent/js/jquery.timeago.js" type="text/javascript"></script>
		<script src="{{$config['dophp']['url']}}/webcontent/js/jquery.timeago.it.js" type="text/javascript"></script>

		<!-- jQuery Cookie Plugin -->
		<script src="{{$config['dophp']['url']}}/webcontent/js/jquery.cookie.js" type="text/javascript"></script>

		<!-- Tether -->
		<link rel="stylesheet" href="{{$config['dophp']['url']}}/webcontent/css/tether.min.css">
		<script src="{{$config['dophp']['url']}}/webcontent/js/tether.min.js"></script>

		<!-- Popper (required by Bootstrap Menu) -->
		<script src="{{$config['dophp']['url']}}/webcontent/js/popper.min.js"></script>
		<script src="{{$config['dophp']['url']}}/webcontent/js/popper-utils.min.js"></script>

		<!-- Select2 -->
		<link rel="stylesheet" href="{{$config['dophp']['url']}}/webcontent/select2/css/select2.min.css">
		<link rel="stylesheet" href="{{$config['dophp']['url']}}/webcontent/css/select2-bootstrap.css">
		<script src="{{$config['dophp']['url']}}/webcontent/select2/js/select2.full.min.js"></script>
		<script src="{{$config['dophp']['url']}}/webcontent/select2/js/i18n/it.js"></script>

		<!-- Bootstrap -->
		<link rel="stylesheet" href="{{$config['dophp']['url']}}/webcontent/css/bootstrap.min.css">
		<script src="{{$config['dophp']['url']}}/webcontent/js/bootstrap.min.js"></script>

		<!-- Bootstratp date picker -->
		<link rel="stylesheet" href="{{$config['dophp']['url']}}/webcontent/datepicker/css/bootstrap-datepicker.standalone.min.css">
		<script src="{{$config['dophp']['url']}}/webcontent/datepicker/js/bootstrap-datepicker.min.js"></script>
		<script src="{{$config['dophp']['url']}}/webcontent/datepicker/locales/bootstrap-datepicker.it.min.js"></script>

		<!-- Font-awesome icons -->
		<link rel="stylesheet" href="{{$config['dophp']['url']}}/webcontent/css/font-awesome.min.css">

		<!-- DataTables -->
		<link rel="stylesheet" type="text/css" href="{{$config['dophp']['url']}}/webcontent/DataTables/DataTables-1.10.15/css/jquery.dataTables.min.css"/>
		<link rel="stylesheet" type="text/css" href="{{$config['dophp']['url']}}/webcontent/DataTables/Buttons-1.3.1/css/buttons.dataTables.min.css"/>
		<link rel="stylesheet" type="text/css" href="{{$config['dophp']['url']}}/webcontent/DataTables/ColReorder-1.3.3/css/colReorder.dataTables.min.css"/>
		<link rel="stylesheet" type="text/css" href="{{$config['dophp']['url']}}/webcontent/DataTables/FixedColumns-3.2.2/css/fixedColumns.dataTables.min.css"/>
		<link rel="stylesheet" type="text/css" href="{{$config['dophp']['url']}}/webcontent/DataTables/FixedHeader-3.1.2/css/fixedHeader.dataTables.min.css"/>
		<link rel="stylesheet" type="text/css" href="{{$config['dophp']['url']}}/webcontent/DataTables/Responsive-2.1.1/css/responsive.dataTables.min.css"/>
		<link rel="stylesheet" type="text/css" href="{{$config['dophp']['url']}}/webcontent/DataTables/RowGroup-1.0.0/css/rowGroup.dataTables.min.css"/>
		<link rel="stylesheet" type="text/css" href="{{$config['dophp']['url']}}/webcontent/DataTables/RowReorder-1.2.0/css/rowReorder.dataTables.min.css"/>
		<link rel="stylesheet" type="text/css" href="{{$config['dophp']['url']}}/webcontent/DataTables/Scroller-1.4.2/css/scroller.dataTables.min.css"/>
		<link rel="stylesheet" type="text/css" href="{{$config['dophp']['url']}}/webcontent/DataTables/Select-1.2.2/css/select.dataTables.min.css"/>

		<script type="text/javascript" src="{{$config['dophp']['url']}}/webcontent/DataTables/JSZip-3.1.3/jszip.min.js"></script>
		<script type="text/javascript" src="{{$config['dophp']['url']}}/webcontent/DataTables/pdfmake-0.1.27/build/pdfmake.min.js"></script>
		<script type="text/javascript" src="{{$config['dophp']['url']}}/webcontent/DataTables/pdfmake-0.1.27/build/vfs_fonts.js"></script>
		<script type="text/javascript" src="{{$config['dophp']['url']}}/webcontent/DataTables/DataTables-1.10.15/js/jquery.dataTables.min.js"></script>
		<script type="text/javascript" src="{{$config['dophp']['url']}}/webcontent/DataTables/Buttons-1.3.1/js/dataTables.buttons.min.js"></script>
		<script type="text/javascript" src="{{$config['dophp']['url']}}/webcontent/DataTables/Buttons-1.3.1/js/buttons.colVis.min.js"></script>
		<script type="text/javascript" src="{{$config['dophp']['url']}}/webcontent/DataTables/Buttons-1.3.1/js/buttons.html5.min.js"></script>
		<script type="text/javascript" src="{{$config['dophp']['url']}}/webcontent/DataTables/Buttons-1.3.1/js/buttons.print.min.js"></script>
		<script type="text/javascript" src="{{$config['dophp']['url']}}/webcontent/DataTables/ColReorder-1.3.3/js/dataTables.colReorder.min.js"></script>
		<script type="text/javascript" src="{{$config['dophp']['url']}}/webcontent/DataTables/FixedColumns-3.2.2/js/dataTables.fixedColumns.min.js"></script>
		<script type="text/javascript" src="{{$config['dophp']['url']}}/webcontent/DataTables/FixedHeader-3.1.2/js/dataTables.fixedHeader.min.js"></script>
		<script type="text/javascript" src="{{$config['dophp']['url']}}/webcontent/DataTables/Responsive-2.1.1/js/dataTables.responsive.min.js"></script>
		<script type="text/javascript" src="{{$config['dophp']['url']}}/webcontent/DataTables/RowGroup-1.0.0/js/dataTables.rowGroup.min.js"></script>
		<script type="text/javascript" src="{{$config['dophp']['url']}}/webcontent/DataTables/RowReorder-1.2.0/js/dataTables.rowReorder.min.js"></script>
		<script type="text/javascript" src="{{$config['dophp']['url']}}/webcontent/DataTables/Scroller-1.4.2/js/dataTables.scroller.min.js"></script>
		<script type="text/javascript" src="{{$config['dophp']['url']}}/webcontent/DataTables/Select-1.2.2/js/dataTables.select.min.js"></script>

		<!-- DoPhp utils -->
		<link rel="stylesheet" type="text/css" href="{{$config['dophp']['url']}}/webcontent/css/base-backend.css"/>
		<script src="{{$config['dophp']['url']}}/webcontent/js/form.js"></script>
		<script src="{{$config['dophp']['url']}}/webcontent/js/buttons.js"></script>

		<!-- DoPhp font -->
		<link rel="stylesheet" type="text/css" href="{{$config['dophp']['url']}}/webcontent/css/mgmt-glyph.css"/>
	{{/block}}

	<!-- Dophp default styles and scripts -->
	<link rel="stylesheet" href="{{$config['dophp']['url']}}/med/css/bootstrap.css">
	<link rel="stylesheet" href="{{$config['dophp']['url']}}/med/css/general.css">
	<link rel="stylesheet" href="{{$config['dophp']['url']}}/med/css/header.css">
	<link rel="stylesheet" href="{{$config['dophp']['url']}}/med/css/footer.css">
	<link rel="stylesheet" href="{{$config['dophp']['url']}}/med/css/datatable.css">
	<link rel="stylesheet" href="{{$config['dophp']['url']}}/med/css/alerts.css">
	<link rel="stylesheet" href="{{$config['dophp']['url']}}/med/css/form.css">
	<link rel="stylesheet" href="{{$config['dophp']['url']}}/med/css/sheet.css">
	<link rel="stylesheet" href="{{$config['dophp']['url']}}/med/css/upload.css">
	<link rel="stylesheet" href="{{$config['dophp']['url']}}/med/css/collapsable.css">
	<link rel="stylesheet" href="{{$config['dophp']['url']}}/med/css/base.css">

	{{block name='head'}}{{/block}}
</head>
{{$testServer=isset($config['testserver']) && $config['testserver']}}
<body {{if $testServer}}class="testserver"{{/if}}>
{{if $testServer}}
	<div class="testserver-top">
		Attenzione! Questo è un server di test. Tutti i dati inseriti potrebbero essere cancellati senza preavviso.
	</div>
{{/if}}
<div id="bodyDiv" {{if $testServer}}class="testserver"{{/if}}>
{{block name='body'}}
	{{block name='navbar'}}
		{{if isset($user) && $user->getUid()}}

			<nav class="navbar navbar-expand-lg navbar-dark bg-dark ag-navbar">
				<button class="navbar-toggler navbar-toggler-right" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
					<span class="navbar-toggler-icon"></span>
				</button>
				{{block name='navbarlogo'}}
					<a class="navbar-brand ag-logged-logo" href="?do=home">
						<img class="logo" src="{{$med}}/img/logo.png" alt="{{$config['site']['name']}}">
						<!-- {{$config['site']['name']|htmlentities}} -->
					</a>
				{{/block}}

				{{block name='navbarmenu'}}
					<div class="collapse navbar-collapse ag-main-menu-cont" id="navbarSupportedContent">
						<ul class="navbar-nav mr-auto">
							{{if isset($menu) && $user->getUid()}}
								{{foreach $menu->getChilds() as $m}}
									{{$childs=$m->getChilds()}}
									<li class="nav-item {{if $childs}}dropdown{{/if}} {{if $m->isActive()}}active{{/if}} ag-mmexp-targ nowrap">
										<a
										class="nav-link {{if $childs}}dropdown-toggle{{else}}menu-link{{/if}}"
											href="{{$m->getUrl()}}"
										data-label={{$m->getLabel()|json_encode}}
										id="{{$m->getId()|htmlentities}}_a"
										{{if $childs}}
											data-toggle="dropdown"
											aria-haspopup="true"
											aria-expanded="false"
										{{/if}}
										>
										{{if $m->getIcon()}}<span class="fa {{$m->getIcon()}}"></span>{{/if}}
										{{$m->getLabel()|htmlentities}}
										</a>
										{{if $childs}}
											<div class="dropdown-menu {{if $childs|@count > 10}}multi-column columns-{{if $childs|@count > 20}}3{{else}}2{{/if}}{{/if}}" style="{{if $childs|@count > 10}}left:-100px{{/if}}" aria-labelledby="{{$m->getId()|htmlentities}}_a">
												{{if $childs|@count > 10}}<div class="row">{{/if}}
													{{assign var="contatore_item" value=0}}
													{{foreach $childs as $c}}
														{{assign var="contatore_item" value=$contatore_item+1}}
														{{if $childs|@count > 10 && $childs|@count < 21 && ($contatore_item == 1 || $contatore_item == 11)}}<div class="col-sm-6">{{elseif $childs|@count > 20 && $contatore_item % 10 == 1 }}<div class="col-sm-4">{{/if}}
														{{if $c->getUrl() || $c->getLabel()}}
															<a class="dropdown-item menu-link" href="{{$c->getUrl()}}"
																data-label={{$m->getLabel()|json_encode}}
															>{{$c->getLabel()|htmlentities}}</a>
														{{else}}
															<div class="dropdown-divider"></div>
														{{/if}}
														{{if $childs|@count > 10 && ($contatore_item == 10 || $contatore_item == 20 || $contatore_item == 30)}}</div>{{/if}}
													{{/foreach}}
												{{if $childs|@count > 10}}</div>{{/if}}
											</div>
										{{/if}}
									</li>
								{{/foreach}}
							{{/if}}
						</ul>
					</div>
				{{/block}}

				{{block name='navbarinner'}}{{/block}}

				{{block name='navbarlogout'}}
					<form method="GET" action="" class="d-none d-lg-block">
						<input type="hidden" name="do" value="logout">
						<button type="submit" class="btn btn-sm btn-outline-secondary ag-foreBtn ag-logged-btn"><span class="fa fa-sign-out"></span>
							{{_('Logout')}}
						</button>
					</form>
				{{/block}}
			</nav>

		{{/if}}
	{{/block}}

	<div class="container-fluid">
		<div class="row ag-breads-row">
			<div>
				{{block name='buttonbar'}}
				{{/block}}
			</div>
			<div class="col sub-header-cont">
				{{block name='breadcrumb'}}
					<!-- Breadcrumb -->
					{{if $pageTitle}}
						<ol class="breadcrumb">
							<li class="breadcrumb-item">
								<div class="bc-text"><a href="?do=home">Home</a></div>
								<div class="bc-arrow mgmt-icon mgmt-arrow"></div>
							</li>
							{{if isset($breadcrumb)}}
								{{foreach $breadcrumb as $url => $descr}}
									<li class="breadcrumb-item">
										<div class="bc-text"><a href="{{$url|htmlentities}}">{{$descr|htmlentities}}</a></div>
										<div class="bc-arrow mgmt-icon mgmt-arrow"></div>
									</li>
								{{/foreach}}
							{{/if}}
							<div class="bc-text active">
								<li class="breadcrumb-item active">{{$pageTitle|htmlentities}}{{block name='breadbadges'}}{{/block}}</li>
							</div>
						</ol>
					{{/if}}
				{{/block}}
			</div>
		</div>
	</div>

	<div class="container">
		{{if isset($alerts)}}
			{{foreach $alerts as $alert}}
				<div class="alert alert-{{$alert->getType()}}" role="alert">
					{{$alert->getMessage()|htmlentities}}
				</div>
			{{/foreach}}
		{{/if}}

		{{block name='content'}}{{/block}}
	</div>

	<footer class="footer">
		<div class="container text-muted navbar-dark">
			{{$config['site']['name']}} ver. {{$config['site']['version']}}
			{{block name='footerinnerend'}}{{/block}}
		</div>
	</footer>
{{/block}}

{{block name='foot'}}{{/block}}

{{block name='piwik'}}
	{{if isset($config['piwik']) && $config['piwik']}}
		<!-- Piwik -->
		<script type="text/javascript">
			var _paq = _paq || [];
			/* tracker methods like "setCustomDimension" should be called before "trackPageView" */
			_paq.push(['trackPageView']);
			_paq.push(['enableLinkTracking']);
			(function() {
				var u="{{$config['piwik']['url']}}";
				_paq.push(['setTrackerUrl', u+'piwik.php']);
				_paq.push(['setSiteId', "{{$config['piwik']['id']}}"]);
				var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
				g.type='text/javascript'; g.async=true; g.defer=true; g.src=u+'piwik.js'; s.parentNode.insertBefore(g,s);
			})();
		</script>
		<noscript><p><img src="{{$config['piwik']['url']}}piwik.php?idsite={{$config['piwik']['id']}}&rec=1" style="border:0;" alt="" /></p></noscript>
		<!-- End Piwik Code -->
	{{/if}}
{{/block}}
</div>
</body>
</html>
