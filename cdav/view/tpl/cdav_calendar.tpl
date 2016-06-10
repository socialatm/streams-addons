<script language="javascript" type="text/javascript" src="/library/moment/moment.min.js"></script>
<script language="javascript" type="text/javascript" src="/library/fullcalendar/fullcalendar.min.js"></script>
<script language="javascript" type="text/javascript" src="/library/fullcalendar/lang-all.js"></script>'

<script>
$(document).ready(function() {
	$('#calendar').fullCalendar({
		eventSources: [ {{$calendar_sources}} ]
	})
});
</script>

<div id="calendar"></div>
