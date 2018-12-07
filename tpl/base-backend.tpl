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
	{{/block}}

	{{block name='head'}}{{/block}}
</head>
<body>
{{block name='body'}}

	{{block name='navbar'}}
		{{if $user->getUid()}}

			<nav class="navbar navbar-expand-lg navbar-dark ag-navbar">
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
							{{if $user->getUid()}}
								{{foreach $menu->getChilds() as $m}}
									{{$childs=$m->getChilds()}}
									<li class="nav-item {{if $childs}}dropdown{{/if}} {{if $m->isActive()}}active{{/if}} ag-mmexp-targ">
										<a
										class="nav-link {{if $childs}}dropdown-toggle{{else}}menu-link{{/if}}"
											href="{{$m->getUrl()}}"
										data-label={{$m->getLabel()|json_encode}}
										{{if $childs}}
											id="navbarDropdownMenuLink{{$m@iteration}}"
											data-toggle="dropdown"
											aria-haspopup="true"
											aria-expanded="false"
										{{/if}}
									>
										{{if $m->getIcon()}}<span class="fa {{$m->getIcon()}}"></span>{{/if}}
										{{$m->getLabel()|htmlentities}}
									</a>
									{{if $childs}}
										<div class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink{{$m@iteration}}">
											{{foreach $childs as $c}}
												<a class="dropdown-item menu-link" href="{{$c->getUrl()}}"
													data-label={{$m->getLabel()|json_encode}}
												>{{$c->getLabel()|htmlentities}}</a>
												{{/foreach}}
											</div>
										{{/if}}
									</li>
								{{/foreach}}

								{{block name='navbarmenulogout'}}
									<form method="GET" action="" class="d-lg-none">
										<input type="hidden" name="do" value="logout">
										<span class="fa fa-sign-out ag-nav-img"></span><input type="submit" class="dropdown-item ag-nav-btn ag-img-btn" value="Esci">
									</form>
								{{/block}}

							{{/if}}
						</ul>
					</div>
				{{/block}}

				{{block name='navbarinnerend'}}{{/block}}
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
							<li class="breadcrumb-item"><a href="?do=home">Home</a></li>
							{{if isset($breadcrumb)}}
								{{foreach $breadcrumb as $url => $descr}}
									<li class="breadcrumb-item"><a href="{{$url|htmlentities}}">{{$descr|htmlentities}}</a></li>
								{{/foreach}}
							{{/if}}
							<li class="breadcrumb-item active">{{$pageTitle|htmlentities}}{{block name='breadbadges'}}{{/block}}</li>
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
			{{block name='foterinnerend'}}{{/block}}
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
</body>
</html>