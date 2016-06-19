<script language="javascript" type="text/javascript" src="/library/moment/moment.min.js"></script>
<script language="javascript" type="text/javascript" src="/library/fullcalendar/fullcalendar.min.js"></script>
<script language="javascript" type="text/javascript" src="/library/fullcalendar/lang-all.js"></script>

<script>
$(document).ready(function() {
	$('#calendar').fullCalendar({
		eventSources: [ {{$sources}} ],

		header: false,

		lang: '{{$lang}}',
		firstDay: {{$first_day}},

		monthNames: aStr['monthNames'],
		monthNamesShort: aStr['monthNamesShort'],
		dayNames: aStr['dayNames'],
		dayNamesShort: aStr['dayNamesShort'],
		allDayText: aStr['allday'],

		timeFormat: 'HH:mm',

		loading: function(isLoading, view) {
			$('#events-spinner').spin('tiny');
			$('#events-spinner > i').css('color', 'transparent');
			if(!isLoading) {
				$('#events-spinner').spin(false);
				$('#events-spinner > i').css('color', '');
			}
		}
	});

	// echo the title
	var view = $('#calendar').fullCalendar('getView');
	$('#title').text(view.title);

	$('#create-form').colorpicker({ input: '#color' });

	$(document).on('click','#fullscreen-btn', on_fullscreen);
	$(document).on('click','#inline-btn', on_inline);
});

function changeView(action, viewName) {
	$('#calendar').fullCalendar(action, viewName);
	var view = $('#calendar').fullCalendar('getView');
	if(view.type === 'agendaDay' || view.type === 'agendaWeek') {
		$('#calendar').fullCalendar('option', 'height', 'auto');
	}
	else {
		$('#calendar').fullCalendar('option', 'height', '');
	}
	$('#title').text(view.title);
}

function add_remove_json_source(source, color, status) {

	if(status === undefined)
		status = 'fa-calendar-check-o';

	if(status === 'drop') {
		$('#calendar').fullCalendar( 'removeEventSource', source );
		return;
	}

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

function on_fullscreen() {
	var view = $('#calendar').fullCalendar('getView');
	if(view.type === 'month') {
		$('#calendar').fullCalendar('option', 'height', $(window).height() - $('.section-title-wrapper').outerHeight(true) - 2); // -2 is for border width (.generic-content-wrapper top and bottom) of .generic-content-wrapper
	}
}

function on_inline() {
	var view = $('#calendar').fullCalendar('getView');
	if(view.type === 'month') {
		$('#calendar').fullCalendar('option', 'height', '');
	}
}
</script>

<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<div class="pull-right">
			<button id="fullscreen-btn" type="button" class="btn btn-default btn-xs" onclick="makeFullScreen();"><i class="fa fa-expand"></i></button>
			<button id="inline-btn" type="button" class="btn btn-default btn-xs" onclick="makeFullScreen(false);"><i class="fa fa-compress"></i></button>
			<div class="btn-group">
				<button class="btn btn-default btn-xs" onclick="changeView('prev', false);" title="{{$prev}}"><i class="fa fa-backward"></i></button>
				<button id="events-spinner" class="btn btn-default btn-xs" onclick="changeView('today', false);" title="{{$today}}"><i class="fa fa-bullseye"></i></button>
				<button class="btn btn-default btn-xs" onclick="changeView('next', false);" title="{{$next}}"><i class="fa fa-forward"></i></button>
			</div>
		</div>
		<h2 id="title"></h2>
		<div class="clear"></div>
	</div>
	<div class="clear"></div>
	<div class="section-content-wrapper-np">
		<div id="calendar"></div>
	</div>
</div>
