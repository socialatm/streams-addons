<div class="map-setting-block">
		<div class="descriptive-text">
				Create a new map that people can join to share their locations and
				markers.
		</div>
		<h3>Rendezvous
				<span class="pull-right">
						<button id="add-new-group" class="btn btn-default btn-xs" title="Add new rendezvous">
								<i class="fa fa-plus"></i><span>&nbsp;Add new rendezvous</span>
						</button>
				</span>
		</h3>
		<div class="clear" ></div>
		<div id="group-list" class="list-group" style="margin-top: 20px;margin-bottom: 20px;"></div>
</div>

<script>
	$(document).ready(function () {
		
		$(document).on('click', '#add-new-group', function (event) {
			rv.createGroup(event);
			return false;
		});
		
		rv.getGroups();
	});

	var rv = rv || {};
	
	rv.groups = [];

	rv.createGroup = function(e) {

		$.post("rendezvous/v1/new/group", {},
				function (data) {
					if (data['success']) {
//						rv.groups.push({
//								id: data['id']
//						});
					rv.getGroups();
					} else {
						window.console.log(data['message']);
					}
					return false;
				},
				'json');

	};

	rv.getGroups = function() {

		$.post("rendezvous/v1/get/groups", {},
				function (data) {
					if (data['success']) {
							$('#group-list').html(data['html']);
							var groups = data['groups'];
							rv.groups = [];							
							for(var i = 0; i < groups.length; i++) {	
								rv.groups.push({
										id: groups[i].guid
								});
							}
					} else {
						window.console.log(data['message']);
					}
					return false;
				},
				'json');

	};
</script>