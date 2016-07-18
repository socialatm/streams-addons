<script>
$(document).ready(function() {

	$(document).on('click', '.vcard-header', setActive);

	function setActive() {
		var id = $(this).data('id');
		var header = $('#vcard-header-' + id);
		var info = $('#vcard-info-' + id);

		if(header.hasClass('active')) {
			$(header).removeClass('active');
			$(info).hide();
		}
		else {
			$(header).addClass('active');
			$(info).show();
		}
	}

});
</script>

<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<h2>{{$displayname}}</h2>
	</div>
	<div class="section-content-wrapper-np">
		{{foreach $cards as $card}}
			<div id="vcard-header-{{$card.id}}" class="vcard-header" data-id="{{$card.id}}">
				{{if $card.photo}}<img class="vcard-photo" src="{{$card.photo}}" width="32px" height="32px">{{else}}<div class="vcard-nophoto"><i class="fa fa-user"></i></div>{{/if}}<h3 class="vcard-fn">{{$card.fn}}</h3>
				{{if $card.emails.0.address}}<span class="hidden-xs">{{$card.emails.0.address}}</span>{{/if}}
			</div>
			<div id="vcard-info-{{$card.id}}" class="vcard-info">
				{{if $card.org}}
				<div class="vcard-org">
					<strong>{{$org_label}}:</strong> {{$card.org}}
				</div>
				{{/if}}
				{{if $card.title}}
				<div class="vcard-title">
					<strong>{{$title_label}}:</strong> {{$card.title}}
				</div>
				{{/if}}
				{{if $card.tels}}
				<div class="vcard-tel">
					<strong>{{$tel_label}}:</strong><br>{{foreach $card.tels as $tel}}{{if $tel.type}}{{$tel.type}}: {{/if}}{{$tel.nr}}<br>{{/foreach}}
				</div>
				{{/if}}
				{{if $card.emails}}
				<div class="vcard-email">
					<strong>{{$email_label}}:</strong><br>{{foreach $card.emails as $email}}{{if $email.type}}{{$email.type}}: {{/if}}<a href="mailto:{{$email.address}}">{{$email.address}}</a><br>{{/foreach}}
				</div>
				{{/if}}
				{{if $card.impps}}
				<div class="vcard-impp">
					<strong>{{$impp_label}}:</strong><br>{{foreach $card.impps as $impp}}{{if $impp.type}}{{$impp.type}}: {{/if}}{{$impp.address}}<br>{{/foreach}}
				</div>
				{{/if}}
				{{if $card.urls}}
				<div class="vcard-url">
					<strong>{{$url_label}}:</strong><br>{{foreach $card.urls as $url}}{{if $url.type}}{{$url.type}}: {{/if}}{{$url.address}}<br>{{/foreach}}
				</div>
				{{/if}}
				{{if $card.adrs}}
				<div class="vcard-adr">
					{{foreach $card.adrs as $adr}}<strong>{{$adr_label}}{{if $adr.type}} ({{$adr.type}}){{/if}}:</strong><br>{{foreach $adr.address as $adr_part}}{{if $adr_part}}{{$adr_part}}<br>{{/if}}{{/foreach}}{{/foreach}}
				</div>
				{{/if}}
				{{if $card.note}}
				<div class="vcard-note">
					<strong>{{$note_label}}:</strong> {{$card.note}}
				</div>
				{{/if}}
			</div>
		{{/foreach}}
	</div>
</div>
