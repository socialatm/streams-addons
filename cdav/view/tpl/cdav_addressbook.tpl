<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<h2>{{$displayname}}</h2>
	</div>
	<div class="section-content-wrapper">
		{{foreach $cards as $card}}
			{{$card@index}}. {{$card.fn}}, {{$card.tel}}, <a href="mailto:{{$card.email}}">{{$card.email}}</a><br>
		{{/foreach}}
	</div>
</div>
