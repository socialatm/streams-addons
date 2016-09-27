// Declare the rendezvous namespace
var rv = rv || {};

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
// Create the map
rv.map = new ol.Map({
		layers: [
				new ol.layer.Tile({
						source: new ol.source.OSM()
				}),
				rv.vectorLayer
		],
		target: 'map',
		view: new ol.View({
				center: ol.proj.fromLonLat([0,0]),
				zoom: 5
		})
});
rv.map.addControl(new ol.control.ZoomSlider());

// Map click handler
rv.map.on('singleclick', function (evt) {
	var coordinate = evt.coordinate;
	window.console.log('coordinate: ' + coordinate);
});

rv.showDialog = function () {
	$( "#dialog-message" ).dialog({
      modal: true,
      buttons: {
        Ok: function() {
          $( this ).dialog( "close" );
        }
      }
    });
}

$(window).load(function (){   
    rv.showDialog();
});
