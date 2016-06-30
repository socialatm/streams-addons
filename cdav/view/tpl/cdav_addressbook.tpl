<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<h2>{{$displayname}}</h2>
	</div>
	<div class="section-content-wrapper">
		{{foreach $cards as $card}}
			<strong>{{$card@index + 1}}. {{$card.fn}}</strong><br>
			{{foreach $card.tels as $tel}}{{$tel.type}}: {{$tel.nr}}<br>{{/foreach}}
			{{foreach $card.emails as $email}}{{$email.type}}: <a href="mailto:{{$email.address}}">{{$email.address}}</a><br>{{/foreach}}
			<br>
		{{/foreach}}
	</div>
</div>
