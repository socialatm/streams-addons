<!DOCTYPE html>
<html>
		<head>
				<title>{{$pagetitle}}</title>
				<link href="/addon/rendezvous/view/css/jquery-ui.css" rel='stylesheet' type='text/css'>
				<link href="/addon/rendezvous/view/css/rendezvous.css" rel='stylesheet' type='text/css'>
				<link href="/library/bootstrap/css/bootstrap.min.css?v=1.13.3" rel='stylesheet' type='text/css'>
				<link href="/library/font_awesome/css/font-awesome.min.css?v=1.13.3" rel='stylesheet' type='text/css'>
				
				<meta name="viewport" content="width=device-width, initial-scale=1.0">
				<link href="/addon/rendezvous/view/css/leaflet.css" rel='stylesheet' type='text/css'>
				<link href="/addon/rendezvous/view/css/leaflet-gps.css" rel='stylesheet' type='text/css'>
				<script src="/addon/rendezvous/view/js/jquery-1.12.4.js"></script>
				<script src="/addon/rendezvous/view/js/jquery-ui.js"></script>

		</head>
		<body>
				<div id="map" class="map"></div>
				
				<div id="add-marker-button-wrapper" style="display: none;">
						<div><button class="add-marker btn btn-default"><span><i class="fa fa-plus">&nbsp;Add marker</i></span></button></div>
				</div>
				
				<div id="edit-marker-button-wrapper" style="display: none;">
						<div>
								<button class="edit-marker btn btn-default btn-sm" title="Edit marker"><span><i class="fa fa-pencil"></i></span></button>
								<button class="delete-marker btn btn-danger btn-sm" title="Delete marker"><span><i class="fa fa-trash-o"></i></span></button>
						</div>
				</div>

				 
<div id="edit-marker-form" title="Edit Marker">
  <p>
    Edit marker info here.
  </p>
</div>
				
				<script src="/addon/rendezvous/view/js/leaflet.js"></script>
				<script src="/addon/rendezvous/view/js/leaflet-gps.js"></script>
				<script src="/addon/rendezvous/view/js/rendezvous.js"></script>
		</body>
</html>