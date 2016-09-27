
/**
* Create the map.
*/

var sharedMarker = new ol.Feature({
		geometry: new ol.geom.Point(ol.proj.fromLonLat([0,0]))
});
sharedMarker.setStyle(new ol.style.Style({
		image: new ol.style.Icon(/** @type {olx.style.IconOptions} */ ({
		anchor: [0.5, 30],
				anchorXUnits: 'fraction',
				anchorYUnits: 'pixels',
				opacity: 0.75,
				src: '/addon/map/view/img/marker_red_30px.png'
		}))
}));
var vectorSource = new ol.source.Vector({
		features: [sharedMarker]
});
var vectorLayer = new ol.layer.Vector({
		source: vectorSource
});
var map = new ol.Map({
		layers: [
				new ol.layer.Tile({
						source: new ol.source.OSM()
				}),
				vectorLayer
		],
		overlays: [overlay],
		target: 'map',
		view: new ol.View({
		center: ol.proj.fromLonLat([0,0]),
				zoom: 5
		})
});
map.addControl(new ol.control.ZoomSlider());