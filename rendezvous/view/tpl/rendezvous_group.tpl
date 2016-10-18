<!DOCTYPE html>
<html>
		<head>
				<title>{{$pagetitle}}</title>
				<link href="/addon/rendezvous/view/css/jquery-ui.css" rel='stylesheet' type='text/css'>
				<link href="/addon/rendezvous/view/css/rendezvous.css?v=0.1.0" rel='stylesheet' type='text/css'>
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
				<div class="zoom-fit" style="position:absolute; top: 130px; left: 10px; z-index: 1000;"><button class="btn btn-primary btn-md" title="Auto fit"><span><i class="fa fa-arrows-alt"></i></span></button></div>
<!--				<div id="member-list-btn" style="position:absolute; top: 10px; left: 60px; z-index: 1000;"><button class="btn btn-default btn-md" title="Members"><span><i class="fa fa-users">&nbsp;<span id="number-members">1</span></i></span></button></div>
				<div id="member-list" style="display: none; position:absolute; top: 40px; left: 60px; z-index: 1000;">This is the list</div>-->
				<div id="member-list-container" style="position:absolute; top: 10px; left: 60px; z-index: 1000;"> 
						<div id="member-list-btn" ><button class="btn btn-default btn-md" title="Members"><span><i class="fa fa-users">&nbsp;<span class="badge badge-success" id="number-members">1</span></i></span></button></div>
						<div id="member-list" style="display: none;">
								<ul class="list-group">
										<li class="list-group-item">Andrew</li>
										<li class="list-group-item">Emily</li>
								</ul>
						</div>
				</div>
				<div id="map" class="map"></div>
<!--				<div id="spinner" style="position: relative; width: 20px; z-index: 10000;"></div>-->
				<div id="add-marker-button-wrapper" style="display: none;">
<!--						<div><button class="add-marker btn btn-default" title="Add marker"><span><i class="fa fa-plus">&nbsp;Add marker</i></span></button></div>
						<div><button class="zoom-fit btn btn-default" title="Auto fit"><span><i class="fa fa-arrows-alt">&nbsp;Auto fit</i></span></button></div>-->
						<div><button class="add-marker btn btn-success btn-sm" title="{{$newMarker}}"><span><i class="fa fa-plus">&nbsp;Add marker</i></span></button></div>
						
				</div>
				
				<div id="edit-marker-button-wrapper" style="display: none;">
						<div>
								<button class="edit-marker btn btn-default btn-sm" title="{{$editMarker}}"><span><i class="fa fa-pencil"></i></span></button>
								<button class="delete-marker btn btn-danger btn-sm" title="{{$deleteMarker}}"><span><i class="fa fa-trash-o"></i></span></button>
						</div>
				</div>

				<div id="delete-member-button-wrapper" style="display: none;">
						<div>
								<button class="delete-member btn btn-danger btn-sm" title="{{$deleteMarker}}"><span><i class="fa fa-trash-o"></i></span></button>
						</div>
				</div>
				 
				<div id="new-member-form" title="{{$welcomeMessageTitle}}">
						<p>{{$welcomeMessage}}</p>
						<form>
							<fieldset style='width: 100px;'>
								<label for="new-member-name">{{$nameText}}</label>
								<input type="text" name="new-member-name" id="new-member-name" placeholder="" value="{{$name}}" class="text ui-widget-content ui-corner-all">

								<!-- Allow form submission with keyboard without duplicating the dialog button -->
								<input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
							</fieldset>
						</form>
				</div>

				<div id="new-marker-form" title="{{$newMarker}}">
						
						<form>
							<fieldset style='width: 100px;'>
								<label for="new-marker-name">{{$nameText}}</label>
								<input type="text" name="new-marker-name" id="new-marker-name" placeholder="{{$myMarkerPlaceholder}}" value="" class="text ui-widget-content ui-corner-all">
								<br>
								<label for="new-marker-description">{{$descriptionText}}</label>
								<br>
								<textarea rows="5" cols="30" name="new-marker-description" id="new-marker-description" placeholder="{{$myMarkerDescriptionPlaceholder}}" class="text ui-widget-content ui-corner-all"></textarea>

								<!-- Allow form submission with keyboard without duplicating the dialog button -->
								<input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
							</fieldset>
						</form>
				</div>
				<div id="edit-marker-form" title="{{$editMarker}}">
						
						<form>
							<fieldset style='width: 100px;'>
								<label for="edit-marker-name">{{$nameText}}</label>
								<input type="text" name="edit-marker-name" id="edit-marker-name" placeholder="{{$myMarkerPlaceholder}}" value="" class="text ui-widget-content ui-corner-all">
								<br>
								<label for="edit-marker-description">{{$descriptionText}}</label>
								<br>
								<textarea rows="5" cols="30" name="edit-marker-description" id="edit-marker-description" placeholder="{{$myMarkerDescriptionPlaceholder}}" class="text ui-widget-content ui-corner-all"></textarea>

								<!-- Allow form submission with keyboard without duplicating the dialog button -->
								<input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
							</fieldset>
						</form>
				</div>
				<div id="identity-deleted-message" title="{{$newIdentity}}">
						<p>{{$identityDeletedMessage}}</p>
				</div>
				<script src="/addon/rendezvous/view/js/leaflet.js"></script>
				<script src="/addon/rendezvous/view/js/leaflet-gps.js"></script>
				<script>
						var mapboxAccessToken = '{{$mapboxAccessToken}}';
				</script>
				<script src="{{$version}}"></script>
				<script>
						rv.group = {
								id: '{{$group}}'
						};
						rv.identity.name = null;
						rv.zroot = '{{$zroot}}';
				</script>
		</body>
</html>