// Declare the rendezvous namespace
var rv = rv || {};


rv.mymap = L.map('map').setView([51.505, -0.09], 13);

L.tileLayer('https://api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token=pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpandmbXliNDBjZWd2M2x6bDk3c2ZtOTkifQ._QA7i5Mpkd_m30IGElHziw', {
	maxZoom: 18,
	attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, ' +
			'<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
			'Imagery © <a href="http://mapbox.com">Mapbox</a>',
	id: 'mapbox.streets'
}).addTo(rv.mymap);

L.marker([51.5, -0.09]).addTo(rv.mymap)
		.bindPopup("<b>Hello world!</b><br />I am a popup.").openPopup();

rv.popup = L.popup();

function onMapClick(e) {
	rv.popup
			.setLatLng(e.latlng)
			.setContent("You clicked the map at " + e.latlng.toString())
			.openOn(rv.mymap);
}

rv.mymap.on('click', onMapClick);

rv.mymap.addControl( new L.Control.Gps({marker: new L.Marker([0,0])}) );//inizialize control

$(window).load(function () {


});
