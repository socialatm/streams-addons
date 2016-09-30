// Declare the rendezvous namespace
var rv = rv || {};

rv.selectedLatLon = {};
rv.markers = [];

rv.map = L.map('map').setView([51.505, -0.09], 13);

L.tileLayer('https://api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token=pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpandmbXliNDBjZWd2M2x6bDk3c2ZtOTkifQ._QA7i5Mpkd_m30IGElHziw', {
	maxZoom: 18,
	attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, ' +
			'<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
			'Imagery © <a href="http://mapbox.com">Mapbox</a>',
	id: 'mapbox.streets'
}).addTo(rv.map);

L.marker([51.5, -0.09]).addTo(rv.map)
		.bindPopup("<b>Hello world!</b><br />I am a popup.").openPopup();

rv.popup = L.popup();

rv.onMapClick = function (e) {
	rv.selectedLatLon = e.latlng;
	window.console.log(e.latlng.toString());
	rv.popup
			.setLatLng(e.latlng)
			//.setContent("You clicked the map at " + e.latlng.toString())
			.setContent(
				$('#add-marker-button-wrapper').html()
			)
			.openOn(rv.map);

	
$('.add-marker').on('click', rv.createMarker);
	
}

rv.map.on('click', rv.onMapClick);

rv.map.addControl( new L.Control.Gps({
	marker: new L.Marker([0,0]).bindPopup("<b>Me</b><br />Current position."),
	autoActive:false
}) );//inizialize control



rv.createMarker = function (e) {
	var marker = L.marker([rv.selectedLatLon.lat, rv.selectedLatLon.lng]);
	var id = rv.guid('marker');
	
	marker.addTo(rv.map)
		.bindPopup(rv.editMarkerHTML)
		.openPopup();
	marker.on('click', function(){
		window.console.log('you clicked marker: ' + id);
		rv.currentMarkerID = id; // global tracker of currently selected marker ID
	});
	rv.markers.push({
		marker: marker,
		id: id
	});
			
}
rv.guid = function (prefix) {
	return prefix+'-xxxxxxxx'.replace(/[xy]/g, function(c) {
		var r = Math.random()*16|0, v = c == 'x' ? r : (r&0x3|0x8);
		return v.toString(16);
	});
}
rv.editMarkerHTML = function (id) {
	setTimeout(function() {
		$('.edit-marker').on('click', rv.editMarker);
		$('.delete-marker').on('click', rv.deleteMarker);
	}, 300);
		return $('#edit-marker-button-wrapper').html();
}

rv.editMarker = function (e) {
	window.console.log('edit marker: ' + rv.currentMarkerID);
	$( "#edit-marker-form" ).dialog({
      modal: true,
      buttons: {
        Ok: function() {
          $( this ).dialog( "close" );
        }
      }
    });
}

rv.deleteMarker = function (e) {
	window.console.log('delete marker');
	rv.markers.forEach(function(f) {
		window.console.log(f.marker._latlng.toString());
		window.console.log(f.id);
		if(f.id === rv.currentMarkerID) {
			window.console.log('Deleting marker!');
			rv.map.removeLayer(f.marker);
		}
	});
	
}


$(window).load(function () {

});
