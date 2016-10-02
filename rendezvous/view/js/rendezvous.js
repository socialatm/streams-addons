// Declare the rendezvous namespace
var rv = rv || {};

rv.selectedLatLon = {};
rv.markers = [];
rv.members = [];
rv.currentMemberID = null;
rv.memberUpdateID = null;
rv.memberUpdateInterval = 20000;
// Data object for local GPS tracking
rv.gps = {
	lat: null,
	lng: null,
	updated: null,
	secondsSinceUpdated: 0, // Track time since last server update
	options: {
		updateInterval: 5	// Minimum number of seconds between location updates sent to server
	},
	sendLocationUpdate: function () {
		var lat = this.lat;
		var lng = this.lng;
		var updated = this.updated;
		if (lat === null || lng === null || updated === null) {
			return false;
		}
		//window.console.log('lat: ' + lat + ', lng: ' + lng + ', updated: ' + updated.toISOString());
		$.post("/rendezvous/v1/update/location", {
			lat: lat,
			lng: lng,
			updated: updated.toISOString(),
			id: rv.identity.id,
			secret: rv.identity.secret
		},
		function (data) {
			if (data['success']) {
			} else {
				window.console.log(data['message']);
			}
			return false;
		},
				'json');
	}
};

rv.identity = {
	id: null,
	name: '',
	secret: null
}

rv.map = L.map('map').setView([0, 0], 13);


L.tileLayer('https://api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token=pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpandmbXliNDBjZWd2M2x6bDk3c2ZtOTkifQ._QA7i5Mpkd_m30IGElHziw', {
	maxZoom: 18,
	attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, ' +
			'<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
			'Imagery © <a href="http://mapbox.com">Mapbox</a>',
	id: 'mapbox.streets'
}).addTo(rv.map);

/*
 L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', {
 attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
 }).addTo(rv.map);
 */
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

//rv.myLocationMarker = new L.Marker([0,0]).bindPopup("<b>Me</b><br />Current position.");
rv.myLocationMarker = new L.CircleMarker([0, 0], {
	stroke: true,
	radius: 10,
	weight: 5,
	color: '#fff',
	opacity: 1,
	fillColor: '#f00',
	fillOpacity: 1
});
rv.gpsControl = new L.Control.Gps({
	marker: rv.myLocationMarker,
	autoActive: true
})
rv.gpsControl.on('gpslocated', function (latlng, marker) {
	if (rv.gps.updated !== null) {
		rv.gps.secondsSinceUpdated = rv.gps.secondsSinceUpdated + Math.ceil(((new Date()).getTime() - rv.gps.updated.getTime()) / 1000);
		//window.console.log('since updated: ' + Math.ceil(((new Date()).getTime()-rv.gps.updated.getTime())/1000) + ' sec');
		if (rv.gps.secondsSinceUpdated >= rv.gps.options.updateInterval) {
			// TODO: send updated location to server
			//window.console.log('sending location: ' + rv.gps.secondsSinceUpdated + ' sec');
			rv.gps.secondsSinceUpdated = 0;
			rv.gps.sendLocationUpdate();
		}
	}
	rv.gps.lat = latlng.latlng.lat;
	rv.gps.lng = latlng.latlng.lng;
	rv.gps.updated = new Date();
	var date = rv.gps.updated.toISOString().substring(0, 10)
	var time = rv.gps.updated.toISOString().substring(11, 16)
	rv.myLocationMarker.bindPopup('<center><b>You</b><br>' + date + ' ' + time + '<center>');
	//window.console.log('Location updated: (' + rv.gps.lat + ', ' + rv.gps.lng + ') at ' + rv.gps.updated.toString());
});
rv.map.addControl(rv.gpsControl);//inizialize control



rv.createMarker = function (e) {
	var marker = L.marker([rv.selectedLatLon.lat, rv.selectedLatLon.lng]);
	var id = rv.guid('marker');

	marker.addTo(rv.map)
			.bindPopup(rv.editMarkerHTML)
			.openPopup();
	marker.on('click', function () {
		window.console.log('you clicked marker: ' + id);
		rv.currentMarkerID = id; // global tracker of currently selected marker ID
	});
	rv.markers.push({
		marker: marker,
		id: id
	});

};

rv.guid = function (prefix) {
	return prefix + '-xxxxxxxx'.replace(/[xy]/g, function (c) {
		var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
		return v.toString(16);
	});
}
rv.editMarkerHTML = function (id) {
	setTimeout(function () {
		$('.edit-marker').on('click', rv.editMarker);
		$('.delete-marker').on('click', rv.deleteMarker);
	}, 300);
	return $('#edit-marker-button-wrapper').html();
};

rv.editMarker = function (e) {
	window.console.log('edit marker: ' + rv.currentMarkerID);
	$("#edit-marker-form").dialog({
		modal: true,
		buttons: {
			Ok: function () {
				$(this).dialog("close");
			}
		}
	});
};

rv.deleteMarker = function (e) {
	window.console.log('delete marker');
	rv.markers.forEach(function (f) {
		window.console.log(f.marker._latlng.toString());
		window.console.log(f.id);
		if (f.id === rv.currentMarkerID) {
			window.console.log('Deleting marker!');
			rv.map.removeLayer(f.marker);
		}
	});

};

rv.getIdentity = function () {
	var identity = Cookies.getJSON('identity');
	var group = Cookies.getJSON('group');
	if (typeof (group) !== 'undefined' && group === rv.group.id && typeof (identity) !== 'undefined' && typeof (identity.id) !== 'undefined' && identity.id !== null) {
		rv.identity = identity;
		if (rv.memberUpdateID === null) {
			rv.memberUpdateID = window.setInterval(rv.getMembers, rv.memberUpdateInterval);
		}
		return true;
	} else {
		var name = window.prompt("Please enter your name", rv.identity.name);
		if (name === null) {
			name = rv.identity.name;
		}
		$.post("/rendezvous/v1/get/identity", {group: rv.group.id, name: name},
		function (data) {
			if (data['success']) {
				rv.identity.secret = data['secret'];
				rv.identity.id = data['id'];
				rv.identity.name = data['name'];

				Cookies.set('identity', rv.identity, {expires: 365, path: ''});
				Cookies.set('group', rv.group.id, {expires: 365, path: ''});

				if (rv.memberUpdateID === null) {
					rv.memberUpdateID = window.setInterval(rv.getMembers, rv.memberUpdateInterval);
				}
			} else {
				window.console.log(data['message']);
			}
			return false;
		},
				'json');
	}
};

rv.getMembers = function () {
	if (rv.identity.id === null || rv.identity.secret === null) {
		return false;
	}
	$.post("/rendezvous/v1/get/members", {id: rv.identity.id, secret: rv.identity.secret},
	function (data) {
		if (data['success']) {
			var members = data['members'];
			rv.members = [];
			for (var i = 0; i < rv.members.length; i++) {
				rv.map.removeLayer(rv.members[i].marker);
			}
			for (var i = 0; i < members.length; i++) {

				var marker = L.marker([members[i].lat, members[i].lng]);
				var mid = members[i].mid;

				marker.addTo(rv.map)
						.bindPopup('<b>' + members[i].name + '</b><br>' + members[i].updated);
				marker.on('click', function () {
					rv.currentMemberID = mid; // global tracker of currently selected member
				});

				rv.members.push({
					name: members[i].name,
					id: members[i].mid,
					lat: members[i].lat,
					lng: members[i].lng,
					updated: members[i].updated,
					marker: marker
				});
			}
			// TODO: Create markers for each member on the map.
			//window.console.log('members: ' + JSON.stringify(rv.members));
			rv.zoomToFitMembers();
		} else {
			window.console.log(data['message']);
		}
		return false;
	},
			'json');
};

rv.zoomToFitMembers = function () {
	var markers = [];
	for (var i = 0; i < rv.members.length; i++) {
		markers.push(rv.members[i].marker);
	}

	var group = new L.featureGroup(markers);

	rv.map.fitBounds(group.getBounds());
};

$(window).load(function () {

	rv.getIdentity();

});
