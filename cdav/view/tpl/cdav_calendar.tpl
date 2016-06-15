<script language="javascript" type="text/javascript" src="/library/moment/moment.min.js"></script>
<script language="javascript" type="text/javascript" src="/library/fullcalendar/fullcalendar.min.js"></script>
<script language="javascript" type="text/javascript" src="/library/fullcalendar/lang-all.js"></script>

<script>
$(document).ready(function() {
	$('#calendar').fullCalendar({
		eventSources: [ {{$sources}} ]
	});

	$('#create-form').colorpicker({ input: '#color' });

});

function add_remove_json_source(source, color, status) {

	if(status === undefined)
		status = 'fa-calendar-check-o';

	var parts = source.split('/');
	var id = parts[4];

	var selector = '#calendar-btn-' + id;

	if($(selector).hasClass('fa-calendar-o')) {
		$('#calendar').fullCalendar( 'addEventSource', { url: source, color: color });
		$(selector).removeClass('fa-calendar-o');
		$(selector).addClass(status);
		$.get('/cdav/calendar/switch/' + id + '/1');
	}
	else {
		$('#calendar').fullCalendar( 'removeEventSource', source );
		$(selector).removeClass(status);
		$(selector).addClass('fa-calendar-o');
		$.get('/cdav/calendar/switch/' + id + '/0');
	}
}

function drop_sharee(id, iid, sharee) {
	var selector = '#sharee-' + id;
	var confirm = confirmDelete();

	if(confirm) {
		$('body').css('cursor', 'wait');
		$(selector).fadeTo('fast', 0.33);

		var posting = $.post( '/cdav/calendar', { calendarid: id, instanceid: iid, sharee: sharee, access: 4, share: 'drop' } );

		posting.done(function() {
			$(selector).remove();
			$('body').css('cursor', 'auto');
		});
	}
}
</script>

<div id="calendar"></div>
