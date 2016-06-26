<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<h2>{{$displayname}}</h2>
	</div>
	<div class="section-content-wrapper">
		{{foreach $cards as $card}}
			{{$card@index}}. {{$card.fn}},
			{{foreach $card.tels as $tel}}{{$tel.type}}: {{$tel.nr}}{{if !$tel@last}}, {{/if}}{{/foreach}}
			{{foreach $card.emails as $email}}{{$email.type}}: <a href="mailto:{{$email.address}}">{{$email.address}}</a>{{if !$email@last}}, {{/if}}{{/foreach}}<br>
		{{/foreach}}
	</div>
</div>
