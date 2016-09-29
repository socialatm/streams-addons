// Declare the rendezvous namespace
var rv = rv || {};

/**
 * Elements that make up the popup.
 */
rv.container = document.getElementById('popup');
rv.content = document.getElementById('popup-content');
rv.closer = document.getElementById('popup-closer');

$('#add-marker').click(function(evt) { rv.createMarkerDialog(evt); });
$('#recenter-control').click(function(evt) { rv.zoomToMyLocation(); });
/**
 * Create an overlay to anchor the popup to the map.
 */
rv.overlay = new ol.Overlay(/** @type {olx.OverlayOptions} */ ({
	element: rv.container,
	autoPan: true,
	autoPanAnimation: {
		duration: 250
	}
}));
/**
 * Add a click handler to hide the popup.
 * @return {boolean} Don't follow the href.
 */
rv.closer.onclick = function () {
	rv.overlay.setPosition(undefined);
	rv.closer.blur();
	return false;
};


/*
 // Marker feature
 rv.sharedMarker = new ol.Feature({
 geometry: new ol.geom.Point(ol.proj.fromLonLat([0,0]))
 });
 rv.sharedMarker.setStyle(new ol.style.Style({
 image: new ol.style.Circle({
 radius: 6,
 fill: new ol.style.Fill({
 color: '#FF0000'
 }),
 stroke: new ol.style.Stroke({
 color: '#fff',
 width: 2
 })
 })
 }));
 rv.vectorSource = new ol.source.Vector({
 features: [rv.sharedMarker]
 });
 
 rv.vectorLayer = new ol.layer.Vector({
 source: rv.vectorSource
 });
 */
rv.view = new ol.View({
	center: ol.proj.transform([0, 0], 'EPSG:4326', 'EPSG:3857'),
	zoom: 5
});

// Create the map
rv.map = new ol.Map({
	overlays: [rv.overlay],
	layers: [
		new ol.layer.Tile({
			source: new ol.source.TileWMS({
				url: 'https://wms.jpl.nasa.gov/wms.cgi',
				layers: 'modis'
				
			})
		})
	],
	target: 'map',
	view: rv.view
});
rv.map.addControl(new ol.control.ZoomSlider());

// Geolocation tracking

rv.geolocation = new ol.Geolocation(/** @type {olx.GeolocationOptions} */ ({
	projection: rv.view.getProjection(),
	trackingOptions: {
		maximumAge: 10000,
		enableHighAccuracy: true,
		timeout: 600000
	}
}));

// Listen to position changes
rv.geolocation.on('change', function (evt) {
	//rv.view.setCenter(rv.geolocation.getPosition());
	//rv.view.setZoom(16);
});

rv.geolocation.on('error', function () {
	alert('geolocation error');
	// FIXME we should remove the coordinates in positions
});



rv.accuracyFeature = new ol.Feature();
rv.accuracyFeature.bindTo('geometry', rv.geolocation, 'accuracyGeometry');

rv.positionFeature = new ol.Feature({
	type: 'mylocation',
	name: 'Me'
});

rv.positionFeature.setStyle(new ol.style.Style({
	image: new ol.style.Circle({
		radius: 6,
		fill: new ol.style.Fill({
			color: '#3399CC'
		}),
		stroke: new ol.style.Stroke({
			color: '#fff',
			width: 2
		})
	})
}));

rv.positionFeature.bindTo('geometry', rv.geolocation, 'position')
		.transform(function () {
		}, function (coordinates) {
			return coordinates ? new ol.geom.Point(coordinates) : null;
		});
// Create the user location layer
rv.userLocationFeatures = new ol.source.Vector({
	features: [rv.positionFeature, rv.accuracyFeature]
});
rv.userLocationLayer = new ol.layer.Vector({
	source: rv.userLocationFeatures
});
rv.map.addLayer(rv.userLocationLayer);


rv.markerLayerSource = new ol.source.Vector();
rv.markerLayer = new ol.layer.Vector({
	source: rv.markerLayerSource,
	name: 'markers'
});
rv.map.addLayer(rv.markerLayer);

// Map click handler
rv.map.on('singleclick', function (evt) {
	var coordinate = evt.coordinate;
	//check for features
	var feature = false;
	rv.map.forEachFeatureAtPixel(rv.map.getPixelFromCoordinate(coordinate), function (f, layer) {
		if (f.get('type') === 'mylocation') {
			var lat = parseFloat(ol.proj.transform(f.getGeometry().getExtent(), 'EPSG:3857', 'EPSG:4326')[1]).toFixed(3);
			var lon = parseFloat(ol.proj.transform(f.getGeometry().getExtent(), 'EPSG:3857', 'EPSG:4326')[0]).toFixed(3);
			rv.content.innerHTML = f.get('name') + '<br>lat: ' + lat + '<br>lon: ' + lon;
			rv.overlay.setPosition(coordinate);
			feature = true;
		} 
		if (f.get('type') === 'marker') {
			var lat = parseFloat(ol.proj.transform(f.getGeometry().getExtent(), 'EPSG:3857', 'EPSG:4326')[1]).toFixed(3);
			var lon = parseFloat(ol.proj.transform(f.getGeometry().getExtent(), 'EPSG:3857', 'EPSG:4326')[0]).toFixed(3);
			rv.content.innerHTML = f.get('name') + '<br>'+ f.get('description') +'<br>lat: ' + lat + '<br>lon: ' + lon;
			rv.overlay.setPosition(coordinate);
			feature = true;
		} 
	});
	if (!feature) {
		var lat = parseFloat(ol.proj.transform(coordinate, 'EPSG:3857', 'EPSG:4326')[1]).toFixed(3);
		var lon = parseFloat(ol.proj.transform(coordinate, 'EPSG:3857', 'EPSG:4326')[0]).toFixed(3);
		rv.overlay.setPosition(coordinate);
	}
});




rv.createMarker = function (coords, name, description) {
	var marker = new ol.Feature({
		name: name || '',
		description: description || '',
		type: 'marker'
	});
	marker.setGeometry(new ol.geom.Point(coords));
	marker.setStyle(new ol.style.Style({
		image: new ol.style.Icon(/** @type {olx.style.IconOptions} */ ({
			anchor: [0.5, 30],
			anchorXUnits: 'fraction',
			anchorYUnits: 'pixels',
			opacity: 0.75,
			src: '/addon/rendezvous/view/img/marker_red_30px.png'
		}))
	}));
	rv.markerLayerSource.addFeature(marker);
};

rv.createMarkerDialog = function (evt) {
	$("#add-marker-dialog").dialog({
		modal: true,
		buttons: {
			Ok: function () {
				$(this).dialog("close");
			},
			Cancel: function () {
				$(this).dialog("close");
			}
		}
	});
	
};

rv.zoomToMyLocation = function () {
	rv.view.setCenter(rv.geolocation.getPosition());
	rv.view.setZoom(16);
};


$(window).load(function () {

	rv.geolocation.setTracking(true); // Start position tracking

});
