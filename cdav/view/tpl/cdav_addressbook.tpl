<script>
$(document).ready(function() {

	$(document).on('click', '.vcard-header, .vcard-cancel', updateView);

	function updateView() {
		var id = $(this).data('id');
		var action = $(this).data('action');
		var header = $('#vcard-header-' + id);
		var cancel = $('#vcard-cancel-' + id);
		var info = $('#vcard-info-' + id);
		var vcardPreview = $('#vcard-preview-' + id);
		var fn = $('#vcard-fn-' + id);

		if(action === 'open') {
			$(header).addClass('active');
			$(cancel).show();
			$(info).show();
			$(fn).show();
			$(vcardPreview).hide();
		}
		else {
			$(header).removeClass('active');
			$(cancel).hide();
			$(info).hide();
			$(fn).hide();
			$(vcardPreview).show();
		}
	}

});
</script>

<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<button type="button" class="btn btn-success btn-xs pull-right" onclick="openClose('create_form')"><i class="fa fa-plus-circle"></i> Add contact</button>
		<h2>{{$displayname}}</h2>
	</div>
	<div id="create_form" class="section-content-tools-wrapper">
		<form id="new_card_form" method="post" action="">
			<input type="hidden" name="target" value="{{$id}}">

			<div class="vcard-fn-create form-group">
				<label>Name:</label>
				<input type="text" name="fn" value="">
			</div>

			<div class="vcard-org form-group">
				<label>{{$org_label}}:</label>
				<input type="text" name="org" value="">
			</div>

			<div class="vcard-title form-group">
				<label>{{$title_label}}:</label>
				<input type="text" name="title" value="">
			</div>

			<div class="vcard-tel form-group">
				<label>{{$tel_label}}:</label><br>
				<div class="form-group">
					<select name="tel_type[]">
						<option value="">Type</option>
						<option value="CELL">Mobile</option>
						<option value="HOME">Home</option>
						<option value="WORK">Work</option>
						<option value="OTHER">Other</option>
					</select>
					<input type="text" name="tel[]" value="">
				</div>
			</div>


			<div class="vcard-email form-group">
				<label>{{$email_label}}:</label><br>
				<div class="form-group">
					<select name="email_type[]">
						<option value="">Type</option>
						<option value="HOME">Home</option>
						<option value="WORK">Work</option>
						<option value="OTHER">Other</option>
					</select>
					<input type="text" name="email[]" value="">
				</div>
			</div>

			<div class="vcard-impp form-group">
				<label>{{$impp_label}}:</label><br>
				<div class="form-group">
					<select name="impp_type[]">
						<option value="">Type</option>
						<option value="HOME">Home</option>
						<option value="WORK">Work</option>
						<option value="OTHER">Other</option>
					</select>
					<input type="text" name="impp[]" value="">
				</div>
			</div>

			<div class="vcard-url form-group">
				<label>{{$url_label}}:</label><br>
				<div class="form-group">
					<select name="url_type[]">
						<option value="">Type</option>
						<option value="HOME">Home</option>
						<option value="WORK">Work</option>
						<option value="OTHER">Other</option>
					</select>
					<input type="text" name="url[]" value="">
				</div>
			</div>

			<div class="vcard-url form-group">
				<div class="form-group">
					<label>{{$adr_label}}:</label>
					<select name="adr_type[]">
						<option value="">Type</option>
						<option value="HOME">Home</option>
						<option value="WORK">Work</option>
						<option value="OTHER">Other</option>
					</select>
				</div>
				<div class="form-group">
					<label>P.O. Box:</label>
					<input type="text" name="adr[0][]" value="">
				</div>
				<div class="form-group">
					<label>Additional:</label>
					<input type="text" name="adr[0][]" value="">
				</div>
				<div class="form-group">
					<label>Street:</label>
					<input type="text" name="adr[0][]" value="">
				</div>
				<div class="form-group">
					<label>Locality:</label>
					<input type="text" name="adr[0][]" value="">
				</div>
				<div class="form-group">
					<label>Region:</label>
					<input type="text" name="adr[0][]" value="">
				</div>
				<div class="form-group">
					<label>ZIP Code:</label>
					<input type="text" name="adr[0][]" value="">
				</div>
				<div class="form-group">
					<label>Country:</label>
					<input type="text" name="adr[0][]" value="">
				</div>
			</div>

			<div class="vcard-note form-group">
				<label>{{$note_label}}:</label>
				<textarea name="note" class="form-control"></textarea>
			</div>

			<button type="submit" name="create" value="create_card" class="btn btn-primary btn-sm pull-right">Create</button>
			<div class="clear"></div>
		</form>
	</div>

	{{foreach $cards as $card}}
	<form id="card_form" method="post" action="">
		<input type="hidden" name="target" value="{{$id}}">
		<input type="hidden" name="uri" value="{{$card.uri}}">
		<div class="section-content-wrapper-np">
			<div id="vcard-cancel-{{$card.id}}" class="vcard-cancel" data-id="{{$card.id}}" data-action="cancel"><i class="fa fa-close"></i></div>
			<div id="vcard-header-{{$card.id}}" class="vcard-header" data-id="{{$card.id}}" data-action="open">
				{{if $card.photo}}<img class="vcard-photo" src="{{$card.photo}}" width="32px" height="32px">{{else}}<div class="vcard-nophoto"><i class="fa fa-user"></i></div>{{/if}}
				<span id="vcard-preview-{{$card.id}}" class="vcard-preview">
					{{if $card.fn}}<span class="vcard-fn-preview">{{$card.fn}}</span>{{/if}}
					{{if $card.emails.0.address}}<span class="vcard-email-preview hidden-xs">{{$card.emails.0.address}}</span>{{/if}}
					{{if $card.tels.0}}<span class="vcard-tel-preview hidden-xs">{{$card.tels.0.nr}}</span>{{/if}}
				</span>
				<input id="vcard-fn-{{$card.id}}" class="vcard-fn" type="text" name="fn" value="{{$card.fn}}" size="{{$card.fn|count_characters:true}}">
			</div>
		</div>
		<div id="vcard-info-{{$card.id}}" class="vcard-info section-content-wrapper">
			{{if $card.org}}
			<div class="vcard-org form-group">
				<label>{{$org_label}}:</label>
				<input type="text" name="org" value="{{$card.org}}" size="{{$card.org|count_characters:true}}">
			</div>
			{{/if}}
			{{if $card.title}}
			<div class="vcard-title form-group">
				<label>{{$title_label}}:</label>
				<input type="text" name="title" value="{{$card.title}}" size="{{$card.title|count_characters:true}}">
			</div>
			{{/if}}
			{{if $card.tels}}
			<div class="vcard-tel form-group">
				<label>{{$tel_label}}:</label><br>
				{{foreach $card.tels as $tel}}
				<div class="form-group">
					<select name="tel_type[]">
						<option value=""{{if $tel.type.0 != 'CELL' && $tel.type.0 != 'HOME' && $tel.type.0 != 'WORK' && $tel.type.0 != 'OTHER'}} selected="selected"{{/if}}>{{$tel.type.1}}</option>
						<option value="CELL"{{if $tel.type.0 == 'CELL'}} selected="selected"{{/if}}>Mobile</option>
						<option value="HOME"{{if $tel.type.0 == 'HOME'}} selected="selected"{{/if}}>Home</option>
						<option value="WORK"{{if $tel.type.0 == 'WORK'}} selected="selected"{{/if}}>Work</option>
						<option value="OTHER"{{if $tel.type.0 == 'OTHER'}} selected="selected"{{/if}}>Other</option>
					</select>
					<input type="text" name="tel[]" value="{{$tel.nr}}" size="{{$tel.nr|count_characters:true}}">
				</div>
				{{/foreach}}
			</div>
			{{/if}}
			{{if $card.emails}}
			<div class="vcard-email form-group">
				<label>{{$email_label}}:</label><br>
				{{foreach $card.emails as $email}}
				<div class="form-group">
					<select name="email_type[]">
						<option value=""{{if $email.type.0 != 'HOME' && $email.type.0 != 'WORK' && $email.type.0 != 'OTHER'}} selected="selected"{{/if}}>{{$email.type.1}}</option>
						<option value="HOME"{{if $email.type.0 == 'HOME'}} selected="selected"{{/if}}>Home</option>
						<option value="WORK"{{if $email.type.0 == 'WORK'}} selected="selected"{{/if}}>Work</option>
						<option value="OTHER"{{if $email.type.0 == 'OTHER'}} selected="selected"{{/if}}>Other</option>
					</select>
					<input type="text" name="email[]" value="{{$email.address}}" size="{{$email.address|count_characters:true}}">
				</div>
				{{/foreach}}
			</div>
			{{/if}}
			{{if $card.impps}}
			<div class="vcard-impp form-group">
				<label>{{$impp_label}}:</label><br>
				{{foreach $card.impps as $impp}}
				<div class="form-group">
					<select name="impp_type[]">
						<option value=""{{if $impp.type.0 != 'HOME' && $impp.type.0 != 'WORK' && $impp.type.0 != 'OTHER'}} selected="selected"{{/if}}>{{$impp.type.1}}</option>
						<option value="HOME"{{if $impp.type.0 == 'HOME'}} selected="selected"{{/if}}>Home</option>
						<option value="WORK"{{if $impp.type.0 == 'WORK'}} selected="selected"{{/if}}>Work</option>
						<option value="OTHER"{{if $impp.type.0 == 'OTHER'}} selected="selected"{{/if}}>Other</option>
					</select>
					<input type="text" name="impp[]" value="{{$impp.address}}" size="{{$impp.address|count_characters:true}}">
				</div>
				{{/foreach}}
			</div>
			{{/if}}
			{{if $card.urls}}
			<div class="vcard-url form-group">
				<label>{{$url_label}}:</label><br>
				{{foreach $card.urls as $url}}
				<div class="form-group">
					<select name="url_type[]">
						<option value=""{{if $url.type.0 != 'HOME' && $url.type.0 != 'WORK' && $url.type.0 != 'OTHER'}} selected="selected"{{/if}}>{{$url.type.1}}</option>
						<option value="HOME"{{if $url.type.0 == 'HOME'}} selected="selected"{{/if}}>Home</option>
						<option value="WORK"{{if $url.type.0 == 'WORK'}} selected="selected"{{/if}}>Work</option>
						<option value="OTHER"{{if $url.type.0 == 'OTHER'}} selected="selected"{{/if}}>Other</option>
					</select>
					<input type="text" name="url[]" value="{{$url.address}}" size="{{$url.address|count_characters:true}}">
				</div>
				{{/foreach}}
			</div>
			{{/if}}
			{{if $card.adrs}}
			<div class="vcard-url form-group">
				{{foreach $card.adrs as $adr}}
				<div class="form-group">
					<label>{{$adr_label}}:</label>
					<select name="adr_type[]">
						<option value=""{{if $adr.type.0 != 'HOME' && $adr.type.0 != 'WORK' && $adr.type.0 != 'OTHER'}} selected="selected"{{/if}}>{{$adr.type.1}}</option>
						<option value="HOME"{{if $adr.type.0 == 'HOME'}} selected="selected"{{/if}}>Home</option>
						<option value="WORK"{{if $adr.type.0 == 'WORK'}} selected="selected"{{/if}}>Work</option>
						<option value="OTHER"{{if $adr.type.0 == 'OTHER'}} selected="selected"{{/if}}>Other</option>
					</select>
				</div>
				<div class="form-group">
					<label>P.O. Box:</label>
					<input type="text" name="adr[{{$adr@index}}][]" value="{{$adr.address.0}}" size="{{$adr.address.0|count_characters:true}}">
				</div>
				<div class="form-group">
					<label>Additional:</label>
					<input type="text" name="adr[{{$adr@index}}][]" value="{{$adr.address.1}}" size="{{$adr.address.1|count_characters:true}}">
				</div>
				<div class="form-group">
					<label>Street:</label>
					<input type="text" name="adr[{{$adr@index}}][]" value="{{$adr.address.2}}" size="{{$adr.address.2|count_characters:true}}">
				</div>
				<div class="form-group">
					<label>Locality:</label>
					<input type="text" name="adr[{{$adr@index}}][]" value="{{$adr.address.3}}" size="{{$adr.address.3|count_characters:true}}">
				</div>
				<div class="form-group">
					<label>Region:</label>
					<input type="text" name="adr[{{$adr@index}}][]" value="{{$adr.address.4}}" size="{{$adr.address.4|count_characters:true}}">
				</div>
				<div class="form-group">
					<label>ZIP Code:</label>
					<input type="text" name="adr[{{$adr@index}}][]" value="{{$adr.address.5}}" size="{{$adr.address.5|count_characters:true}}">
				</div>
				<div class="form-group">
					<label>Country:</label>
					<input type="text" name="adr[{{$adr@index}}][]" value="{{$adr.address.6}}" size="{{$adr.address.6|count_characters:true}}">
				</div>
				{{/foreach}}
			</div>
			{{/if}}

			{{if $card.note}}
			<div class="vcard-note form-group">
				<label>{{$note_label}}:</label>
				<textarea name="note" class="form-control">{{$card.note}}</textarea>
			</div>
			{{/if}}
			<button type="submit" name="delete" value="delete_card" class="btn btn-danger btn-sm pull-left">Delete</button>
			<button type="submit" name="update" value="update_card" class="btn btn-primary btn-sm pull-right">Update</button>
			<div class="clear"></div>
		</div>
	</form>
	{{/foreach}}
</div>
