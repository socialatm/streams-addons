<div class="widget">
	<h3>{{$my_calendars_label}}</h3>
	{{foreach $my_calendars as $calendar}}
	<div {{if !$calendar.sharees}}class="form-group"{{/if}}>
		<i class="fa fa-calendar"></i>&nbsp;<strong>{{$calendar.displayname}}</strong>
		<div class="pull-right">
			<i id="share-icon" class="fa fa-share-alt fakelink" onclick="openClose('share-calendar-{{$calendar.calendarid}}')"></i>
			<a href="/cdav/calendars/{{$calendar.nick}}/{{$calendar.uri}}/?export"><i class="fa fa-cloud-download"></i></a>
			<a href="/cdav/calendar/drop/{{$calendar.calendarid}}"><i class="fa fa-trash-o drop-icons"></i></a>
		</div>
	</div>
	{{if $calendar.sharees}}
	<div class="form-group">
		{{foreach $calendar.sharees as $sharee}}
		<div>{{$sharee}}</div>
		{{/foreach}}
	</div>
	{{/if}}
	<form id="share-calendar-{{$calendar.calendarid}}" method="post" action="" style="display: none;">
		<input name="calendarid" type="hidden" value="{{$calendar.calendarid}}">
		<input name="instanceid" type="hidden" value="{{$calendar.instanceid}}">
		<input name="{DAV:}displayname" type="hidden" value="{{$calendar.displayname}}">
		<div class="form-group">
			<select name="sharee" class="form-control">
				{{$sharee_options}}
			</select>
		</div>
		<div class="form-group">
			<select name="access" class="form-control">
				{{$access_options}}
			</select>
		</div>
		<div class="form-group">
			<button type="submit" name="share" value="share" class="btn btn-success btn-sm btn-block"><i class="fa fa-share-alt"></i> Submit</button>
		</div>
	</form>
	{{/foreach}}
	<form id="create-calendar" method="post" action="">
		<label for="create">{{$create_label}}</label>
		<div class="input-group">
			<input id="create" name="{DAV:}displayname" type="text" placeholder="{{$create_placeholder}}" class="widget-input">
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
	<div class="form-group">
		<i class="fa {{if $calendar.access == 'read-write'}}fa-calendar-check-o{{else}} fa-calendar-times-o{{/if}}"></i>&nbsp;<strong>{{$calendar.share_displayname}}</strong>
		<div class="pull-right">
			<a href="/cdav/calendars/{{$calendar.nick}}/{{$calendar.uri}}/?export"><i class="fa fa-cloud-download"></i></a>
			<a href="/cdav/calendar/drop/{{$calendar.calendarid}}"><i class="fa fa-trash-o drop-icons"></i></a>
		</div>
	</div>
	{{/foreach}}
</div>
{{/if}}
