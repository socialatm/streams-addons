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
				<script src="/addon/rendezvous/view/js/js.cookie.js"></script>

		</head>
		<body>
				<div id="gps-discovery" style="display: none; color:white; font-weight:bolder; position:absolute; top: 80px; left: 50px; z-index: 1000;">Searching for location...</div>
				<div id="map" class="map"></div>
<!--				<div id="spinner" style="position: relative; width: 20px; z-index: 10000;"></div>-->
				<div id="add-marker-button-wrapper" style="display: none;">
						<div><button class="add-marker btn btn-default"><span><i class="fa fa-plus">&nbsp;Add marker</i></span></button></div>
				</div>
				
				<div id="edit-marker-button-wrapper" style="display: none;">
						<div>
								<button class="edit-marker btn btn-default btn-sm" title="Edit marker"><span><i class="fa fa-pencil"></i></span></button>
								<button class="delete-marker btn btn-danger btn-sm" title="Delete marker"><span><i class="fa fa-trash-o"></i></span></button>
						</div>
				</div>

				 
				<div id="new-member-form" title="Welcome to Rendezvous!">
						<p>
								Enter your name to join this rendezvous. To begin sharing your location with the other
								members, tap the GPS control. When your location is discovered, a red dot will appear
								and others will be able to see you on the map.
						</p>
						<form>
							<fieldset style='width: 100px;'>
								<label for="new-member-name">Name</label>
								<input type="text" name="new-member-name" id="new-member-name" placeholder="" value="{{$name}}" class="text ui-widget-content ui-corner-all">

								<!-- Allow form submission with keyboard without duplicating the dialog button -->
								<input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
							</fieldset>
						</form>
				</div>

				<div id="new-marker-form" title="New marker">
						
						<form>
							<fieldset style='width: 100px;'>
								<label for="new-marker-name">Name</label>
								<input type="text" name="new-marker-name" id="new-marker-name" placeholder="My marker" value="" class="text ui-widget-content ui-corner-all">
								<br>
								<label for="new-marker-description">Description</label>
								<br>
								<textarea rows="5" cols="30" name="new-marker-description" id="new-marker-description" placeholder="Let's meet here" class="text ui-widget-content ui-corner-all"></textarea>

								<!-- Allow form submission with keyboard without duplicating the dialog button -->
								<input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
							</fieldset>
						</form>
				</div>
				<div id="edit-marker-form" title="Edit marker">
						
						<form>
							<fieldset style='width: 100px;'>
								<label for="edit-marker-name">Name</label>
								<input type="text" name="edit-marker-name" id="edit-marker-name" placeholder="My marker" value="" class="text ui-widget-content ui-corner-all">
								<br>
								<label for="edit-marker-description">Description</label>
								<br>
								<textarea rows="5" cols="30" name="edit-marker-description" id="edit-marker-description" placeholder="Let's meet here" class="text ui-widget-content ui-corner-all"></textarea>

								<!-- Allow form submission with keyboard without duplicating the dialog button -->
								<input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
							</fieldset>
						</form>
				</div>
				
				<script src="/addon/rendezvous/view/js/leaflet.js"></script>
				<script src="/addon/rendezvous/view/js/leaflet-gps.js"></script>
				<!--<script src="/addon/rendezvous/view/js/spin.js"></script>-->
				<script src="/addon/rendezvous/view/js/rendezvous.js"></script>
				<script>
						rv.group = {
								id: '{{$group}}'
						};
						rv.identity.name = null;
						rv.zroot = '{{$zroot}}';
				</script>
		</body>
</html>