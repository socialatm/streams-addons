<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<h2>{{$displayname}}</h2>
	</div>
	<div class="section-content-wrapper-np">
		{{foreach $cards as $card}}
			<div id="vcard-header-{{$card.id}}" class="vcard-header" onclick="openClose('vcard-info-{{$card.id}}')">
				{{if $card.photo}}<img class="vcard-photo" src="{{$card.photo}}" width="32px" height="32px">{{else}}<div class="vcard-nophoto"><i class="fa fa-user"></i></div>{{/if}}<h3 class="vcard-fn">{{$card.fn}}</h3>
			</div>
			<div id="vcard-info-{{$card.id}}" class="vcard-info">
				{{if $card.org}}
				<div class="vcard-org">
					<strong>Organisation:</strong> {{$card.org}}
				</div>
				{{/if}}
				{{if $card.title}}
				<div class="vcard-title">
					<strong>Title:</strong> {{$card.title}}
				</div>
				{{/if}}
				{{if $card.tels}}
				<div class="vcard-tel">
					<strong>Phone:</strong><br>{{foreach $card.tels as $tel}}{{if $tel.type}}{{$tel.type}}: {{/if}}{{$tel.nr}}<br>{{/foreach}}
				</div>
				{{/if}}
				{{if $card.emails}}
				<div class="vcard-email">
					<strong>Email:</strong><br>{{foreach $card.emails as $email}}{{if $email.type}}{{$email.type}}: {{/if}}<a href="mailto:{{$email.address}}">{{$email.address}}</a><br>{{/foreach}}
				</div>
				{{/if}}
				{{if $card.impps}}
				<div class="vcard-impp">
					<strong>Instant message:</strong><br>{{foreach $card.impps as $impp}}{{if $impp.type}}{{$impp.type}}: {{/if}}{{$impp.address}}<br>{{/foreach}}
				</div>
				{{/if}}
				{{if $card.urls}}
				<div class="vcard-url">
					<strong>Website:</strong><br>{{foreach $card.urls as $url}}{{if $url.type}}{{$url.type}}: {{/if}}{{$url.address}}<br>{{/foreach}}
				</div>
				{{/if}}
				{{if $card.adrs}}
				<div class="vcard-adr">
					{{foreach $card.adrs as $adr}}<strong>Address{{if $adr.type}} ({{$adr.type}}){{/if}}:</strong><br>{{foreach $adr.address as $adr_part}}{{if $adr_part}}{{$adr_part}}<br>{{/if}}{{/foreach}}{{/foreach}}
				</div>
				{{/if}}
				{{if $card.note}}
				<div class="vcard-note">
					<strong>Note:</strong> {{$card.note}}
				</div>
				{{/if}}
			</div>
		{{/foreach}}
	</div>
</div>
