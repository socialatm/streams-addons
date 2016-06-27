{{if $my_calendars}}
<div class="widget">
	<h3>{{$my_calendars_label}}</h3>
	{{foreach $my_calendars as $calendar}}
	<div id="calendar-{{$calendar.calendarid}}">
		<div{{if !$calendar@last}} class="form-group"{{/if}}>
			<i id="calendar-btn-{{$calendar.calendarid}}" class="fa {{if $calendar.switch}}fa-calendar-check-o{{else}}fa-calendar-o{{/if}} generic-icons fakelink" onclick="add_remove_json_source('{{$calendar.json_source}}', '{{$calendar.color}}')" style="color: {{$calendar.color}};"></i>{{$calendar.displayname}}
			<div class="pull-right">
				<a href="/cdav/calendars/{{$calendar.ownernick}}/{{$calendar.uri}}/?export"><i id="download-icon" class="fa fa-cloud-download fakelink generic-icons"></i></a>
				<i id="share-icon" class="fa fa-share-alt fakelink generic-icons" onclick="openClose('share-calendar-{{$calendar.calendarid}}')"></i>
				<a href="#" onclick="var drop = dropItem('/cdav/calendar/drop/{{$calendar.calendarid}}', '#calendar-{{$calendar.calendarid}}'); if(drop) { add_remove_json_source('{{$calendar.json_source}}', '{{$calendar.color}}', 'drop'); } return false;"><i class="fa fa-trash-o drop-icons"></i></a>
			</div>
		</div>
		<div id="share-calendar-{{$calendar.calendarid}}" class="sub-menu" style="display: none; border-color: {{$calendar.color}};">
			{{if $calendar.sharees}}
			{{foreach $calendar.sharees as $sharee}}
			<div id="sharee-{{$calendar.calendarid}}" class="form-group">
				<i class="fa fa-share generic-icons"></i>{{$sharee.name}}&nbsp;{{$sharee.access}}
				<div class="pull-right">
					<a href="#" onclick="dropItem('/cdav/calendar/dropsharee/{{$calendar.calendarid}}/{{$calendar.instanceid}}/{{$sharee.hash}}', '#sharee-{{$calendar.calendarid}}'); return false;"><i class="fa fa-trash-o drop-icons"></i></a>
				</div>
			</div>
			{{/foreach}}
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
					<button type="submit" name="share" value="share" class="btn btn-primary btn-sm"><i class="fa fa-share-alt"></i> Share</button>
				</div>
			</form>
		</div>
	</div>
	{{/foreach}}
</div>
{{/if}}

{{if $shared_calendars}}
<div class="widget">
	<h3>{{$shared_calendars_label}}</h3>
	{{foreach $shared_calendars as $calendar}}
	<div id="shared-calendar-{{$calendar.calendarid}}"{{if !$calendar@last}} class="form-group"{{/if}}>
		<i id="calendar-btn-{{$calendar.calendarid}}" class="fa {{if $calendar.switch}}{{if $calendar.access == 'read-write'}}fa-calendar-check-o{{else}}fa-calendar-times-o{{/if}}{{else}}fa-calendar-o{{/if}} generic-icons fakelink" onclick="add_remove_json_source('{{$calendar.json_source}}', '{{$calendar.color}}', {{if $calendar.access == 'read-write'}}'fa-calendar-check-o'{{else}}'fa-calendar-times-o'{{/if}})"  style="color: {{$calendar.color}};"></i>{{$calendar.share_displayname}}
		<div class="pull-right">
			<a href="/cdav/calendars/{{$calendar.ownernick}}/{{$calendar.uri}}/?export"><i id="download-icon" class="fa fa-cloud-download fakelink generic-icons"></i></a>
			<a href="#" onclick="var drop = dropItem('/cdav/calendar/drop/{{$calendar.calendarid}}', '#shared-calendar-{{$calendar.calendarid}}'); if(drop) { add_remove_json_source('{{$calendar.json_source}}', '{{$calendar.color}}', 'drop'); } return false;"><i class="fa fa-trash-o drop-icons"></i></a>
		</div>
	</div>
	{{/foreach}}
</div>
{{/if}}

<div class="widget">
	<h3>{{$tools_label}}</h3>
	<ul class="nav nav-pills nav-stacked">
		<li>
			<a href="#" onclick="openClose('create-calendar'); return false;"><i class="fa fa-calendar-plus-o generic-icons"></i> {{$create_label}}</a>
		</li>
		<form id="create-calendar" method="post" action="" style="display: none;" class="sub-menu">
			<div id="create-form" class="input-group form-group colorpicker-component">
				<input id="color" name="color" type="hidden" value="#3a87ad">
				<input id="create" name="{DAV:}displayname" type="text" placeholder="{{$create_placeholder}}" class="widget-input">
				<span class="input-group-addon"><i></i></span>
			</div>
			<div class="form-group">
				<button type="submit" name="create" value="create" class="btn btn-primary btn-sm">Create</button>
			</div>
		</form>
		<li>
			<a href="#" onclick="openClose('upload-form'); return false;"><i class="fa fa-cloud-upload generic-icons"></i> {{$import_label}}</a>
		</li>
		<form id="upload-form" enctype="multipart/form-data" method="post" action="" style="display: none;" class="sub-menu">
			<div class="form-group">
				<select id="import" name="target" class="form-control">
					<option value="">{{$import_placeholder}}</option>
					{{foreach $writable_calendars as $writable_calendar}}
					<option value="{{$writable_calendar.id.0}}:{{$writable_calendar.id.1}}">{{$writable_calendar.displayname}}</option>
					{{/foreach}}
				</select>
			</div>
			<div class="form-group">
				<input id="event-upload-choose" type="file" name="userfile" />
			</div>
			<button class="btn btn-primary btn-sm" type="submit" name="c_upload" value="c_upload">Upload</button>
		</form>
	</ul>
</div>
