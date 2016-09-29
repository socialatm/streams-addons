<!DOCTYPE html>
<html>
		<head>
				<title>Rendezvous</title>
				<link rel="stylesheet" href="/addon/rendezvous/view/css/jquery-ui.css">
				<link href="/addon/rendezvous/view/css/rendezvous.css" rel='stylesheet' type='text/css'>
				<meta name="viewport" content="width=device-width, initial-scale=1.0">
				<link href="/addon/rendezvous/view/css/leaflet.css" rel='stylesheet' type='text/css'>
<!--				<script src="/addon/rendezvous/view/js/jquery-1.12.4.js"></script>
				<script src="/addon/rendezvous/view/js/jquery-ui.js"></script>-->

		</head>
		<body>
				<div id="mapid" class="map"></div>

				<script src="/addon/rendezvous/view/js/leaflet.js"></script>-->
				<script>

						var mymap = L.map('mapid').setView([51.505, -0.09], 13);

						L.tileLayer('https://api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token=pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpandmbXliNDBjZWd2M2x6bDk3c2ZtOTkifQ._QA7i5Mpkd_m30IGElHziw', {
							maxZoom: 18,
							attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, ' +
									'<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
									'Imagery © <a href="http://mapbox.com">Mapbox</a>',
							id: 'mapbox.streets'
						}).addTo(mymap);


						L.marker([51.5, -0.09]).addTo(mymap)
								.bindPopup("<b>Hello world!</b><br />I am a popup.").openPopup();

						L.circle([51.508, -0.11], 500, {
							color: 'red',
							fillColor: '#f03',
							fillOpacity: 0.5
						}).addTo(mymap).bindPopup("I am a circle.");

						L.polygon([
							[51.509, -0.08],
							[51.503, -0.06],
							[51.51, -0.047]
						]).addTo(mymap).bindPopup("I am a polygon.");


						var popup = L.popup();

						function onMapClick(e) {
							popup
									.setLatLng(e.latlng)
									.setContent("You clicked the map at " + e.latlng.toString())
									.openOn(mymap);
						}

						mymap.on('click', onMapClick);

				</script>
		</body>
</html>