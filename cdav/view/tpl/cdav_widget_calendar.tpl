<div class="widget">
	<h3>{{$my_calendars_label}}</h3>
	{{foreach $my_calendars as $calendar}}
	<div id="calendar-{{$calendar.calendarid}}">
		<div class="form-group">
			<i id="calendar-btn-{{$calendar.calendarid}}" class="fa {{if $calendar.switch}}fa-calendar-check-o{{else}}fa-calendar-o{{/if}} generic-icons fakelink" onclick="add_remove_json_source('{{$calendar.json_source}}')" style="color: {{$calendar.color}};"></i>{{$calendar.displayname}}
			<div class="pull-right">
				<i id="share-icon" class="fa fa-share-alt fakelink generic-icons" onclick="openClose('share-calendar-{{$calendar.calendarid}}')"></i>
				<a href="#" onclick="dropItem('/cdav/calendar/drop/{{$calendar.calendarid}}', '#calendar-{{$calendar.calendarid}}'); return false;"><i class="fa fa-trash-o drop-icons"></i></a>
			</div>
		</div>
		<div id="share-calendar-{{$calendar.calendarid}}" style="display: none;">
			{{if $calendar.sharees}}
			<div class="form-group">
				{{foreach $calendar.sharees as $sharee}}
				<div id="sharee-{{$calendar.calendarid}}">
					<i class="fa fa-share generic-icons"></i>{{$sharee.name}}&nbsp;{{$sharee.access}}
					<div class="pull-right">
						<i class="fa fa-trash-o drop-icons" onclick="drop_sharee('{{$calendar.calendarid}}', '{{$calendar.instanceid}}', '{{$sharee.hash}}');"></i>
					</div>
				</div>
				{{/foreach}}
			</div>
			{{/if}}
			<form method="post" action="">
				<label for="create">{{$share_label}}</label>
				<input name="calendarid" type="hidden" value="{{$calendar.calendarid}}">
				<input name="instanceid" type="hidden" value="{{$calendar.instanceid}}">
				<input name="{DAV:}displayname" type="hidden" value="{{$calendar.displayname}}">
				<div class="form-group">
					<select id="create" name="sharee" class="form-control">
						{{$sharee_options}}
					</select>
				</div>
				<div class="form-group">
					<select name="access" class="form-control">
						{{$access_options}}
					</select>
				</div>
				<div class="form-group">
					<button type="submit" name="share" value="share" class="btn btn-success btn-sm btn-block"><i class="fa fa-share-alt"></i> Share</button>
				</div>
			</form>
		</div>
	</div>
	{{/foreach}}
	<form id="create-calendar" method="post" action="">
		<label for="create">{{$create_label}}</label>
		<div id="create-form" class="input-group colorpicker-component">
			<input id="color" name="color" type="hidden" value="#3a87ad">
			<input id="create" name="{DAV:}displayname" type="text" placeholder="{{$create_placeholder}}" class="widget-input">
			<span class="input-group-addon"><i></i></span>
			<div class="input-group-btn">
				<button type="submit" name="create" value="create" class="btn btn-default btn-sm"><i class="fa fa-calendar-plus-o"></i></button>
			</div>
		</div>
	</form>
</div>

{{if $shared_calendars}}
<div class="widget">
	<h3>{{$shared_calendars_label}}</h3>
	{{foreach $shared_calendars as $calendar}}
	<div id="shared-calendar-{{$calendar.calendarid}}" class="form-group">
		<i id="calendar-btn-{{$calendar.calendarid}}" class="fa {{if $calendar.switch}}{{if $calendar.access == 'read-write'}}fa-calendar-check-o{{else}}fa-calendar-times-o{{/if}}{{else}}fa-calendar-o{{/if}} generic-icons fakelink" onclick="add_remove_json_source('{{$calendar.json_source}}', {{if $calendar.access == 'read-write'}}'fa-calendar-check-o'{{else}}'fa-calendar-times-o'{{/if}})"  style="color: {{$calendar.color}};"></i>{{$calendar.share_displayname}}
		<div class="pull-right">
			<a href="#" onclick="dropItem('/cdav/calendar/drop/{{$calendar.calendarid}}', '#shared-calendar-{{$calendar.calendarid}}'); return false;"><i class="fa fa-trash-o drop-icons"></i></a>
		</div>
	</div>
	{{/foreach}}
</div>
{{/if}}
