<div class="widget">
	<h3>{{$addressbooks_label}}</h3>
	{{foreach $addressbooks as $addressbook}}
	<div id="addressbook-{{$addressbook.id}}">
		<div class="form-group">
			<i class="fa fa-user generic-icons"></i><a href="/cdav/addressbook/{{$addressbook.id}}">{{$addressbook.displayname}}</a>
			<div class="pull-right">
				<a href="/cdav/addressbooks/{{$addressbook.ownernick}}/{{$addressbook.uri}}/?export"><i id="download-icon" class="fa fa-cloud-download fakelink generic-icons"></i></a>
				<a href="#" onclick="dropItem('/cdav/addressbook/drop/{{$addressbook.id}}', '#addressbook-{{$addressbook.id}}'); return false;"><i class="fa fa-trash-o drop-icons"></i></a>
			</div>
		</div>
	</div>
	{{/foreach}}
</div>

<div class="widget">
	<h3>{{$tools_label}}</h3>
	<ul class="nav nav-pills nav-stacked">
		<li>
			<a href="#" onclick="openClose('create-addressbook'); return false;"><i class="fa fa-user-plus generic-icons"></i> {{$create_label}}</a>
		</li>
		<form id="create-addressbook" method="post" action="" style="display: none;" class="sub-menu">
			<div class="form-group">
				<input id="create" name="{DAV:}displayname" type="text" placeholder="{{$create_placeholder}}" class="form-control form-group">
				<button type="submit" name="create" value="create" class="btn btn-primary btn-sm">Create</button>
			</div>
		</form>
		<li>
			<a href="#" onclick="openClose('upload-form'); return false;"><i class="fa fa-cloud-upload generic-icons"></i> {{$import_label}}</a>
		</li>
		<form id="upload-form" enctype="multipart/form-data" method="post" action="" style="display: none;" class="sub-menu">
			<div class="form-group">
				<select id="import" name="target" class="form-control">
					<option value="">{{$import_placeholder}}</option>
					{{foreach $addressbooks as $addressbook}}
					<option value="{{$addressbook.id}}">{{$addressbook.displayname}}</option>
					{{/foreach}}
				</select>
			</div>
			<div class="form-group">
				<input id="addressbook-upload-choose" type="file" name="userfile" />
			</div>
			<button class="btn btn-primary btn-sm" type="submit" name="a_upload" value="a_upload">Upload</button>
		</form>
	</ul>
</div>
