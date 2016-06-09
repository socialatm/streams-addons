<div class="widget">
	<h3>{{$addressbooks_label}}</h3>
	{{foreach $addressbooks as $addressbook}}
	<div id="addressbook-{{$addressbook.id}}">
		<div class="form-group">
			<i class="fa fa-user generic-icons"></i>{{$addressbook.displayname}}
			<div class="pull-right">
				<a href="#" onclick="dropItem('/cdav/addressbook/drop/{{$addressbook.id}}', '#addressbook-{{$addressbook.id}}'); return false;"><i class="fa fa-trash-o drop-icons"></i></a>
			</div>
		</div>
	</div>
	{{/foreach}}
	<form id="create-addressbook" method="post" action="">
		<label for="create">{{$create_label}}</label>
		<div class="input-group">
			<input id="create" name="{DAV:}displayname" type="text" placeholder="{{$create_placeholder}}" class="widget-input">
			<div class="input-group-btn">
				<button type="submit" name="create" value="create" class="btn btn-default btn-sm"><i class="fa fa-user-plus"></i></button>
			</div>
		</div>
	</form>
</div>
