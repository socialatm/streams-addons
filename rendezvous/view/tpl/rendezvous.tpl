<!DOCTYPE html>
<html>
		<head>
				<title>{{$pagetitle}}</title>
				<link href="/addon/rendezvous/view/css/ol.css" rel='stylesheet' type='text/css'>
				<link rel="stylesheet" href="/addon/rendezvous/view/css/jquery-ui.css">
				<link href="/addon/rendezvous/view/css/rendezvous.css" rel='stylesheet' type='text/css'>
				
				<script src="/addon/rendezvous/view/js/jquery-1.12.4.js"></script>
				<script src="/addon/rendezvous/view/js/jquery-ui.js"></script>
				<script src="/addon/rendezvous/view/js/ol.js?v=0.1" ></script>
		</head>
		<body>
				<div id="map" class="map">
						<div id="recenter-control" class="ol-control"><img src='addon/map/view/img/center-control.png' width="20px"></div>
				</div>
				
				<div id="popup" class="ol-popup">
						<a href="#" id="popup-closer" class="ol-popup-closer"></a>
						<div id="marker-popup-content">
								<div id="add-marker" class="btn btn-default"><a href="">Create marker</a></div>
						</div>
						<div id="popup-content"></div>
				</div>
				 
				<div id="add-marker-dialog" title="Add marker">
					<p>
						<span class="ui-icon ui-icon-circle-check" style="float:left; margin:0 7px 50px 0;"></span>
						Create a new marker!
					</p>
				</div>
				
				<script src="/addon/rendezvous/view/js/rendezvous.js?v=0.1" ></script>
		</body>
</html>