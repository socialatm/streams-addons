<div class="generic-content-wrapper">
	<div class="section-title-wrapper clearfix">
		{{if $owner}}
		<button type="button" class="btn btn-success btn-sm pull-right acl-form-trigger" onclick="openClose('new-wiki-form-wrapper');" data-form_id="new-wiki-form"><i class="fa fa-plus-circle"></i>&nbsp;{{$create}}</button>
		{{/if}}
		<h2>{{$header}}</h2>
	</div>
	{{if $owner}}
	<div id="new-wiki-form-wrapper" class="section-content-tools-wrapper">
		<form id="new-wiki-form" action="wiki/{{$channel}}/create/wiki" method="post" class="acl-form" data-form_id="new-wiki-form" data-allow_cid='{{$allow_cid}}' data-allow_gid='{{$allow_gid}}' data-deny_cid='{{$deny_cid}}' data-deny_gid='{{$deny_gid}}'>
			{{include file="field_input.tpl" field=$wikiName}}
			{{include file="field_select.tpl" field=$mimeType}}
			{{include file="field_checkbox.tpl" field=$typelock}}
			{{include file="field_checkbox.tpl" field=$notify}}
			<div>
				<div class="btn-group pull-right">
					<button id="dbtn-acl" class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#aclModal" title="Permission settings" onclick="return false;">
						<i id="jot-perms-icon" class="fa fa-{{$lockstate}} jot-icons"></i>
					</button>
					<button id="new-wiki-submit" class="btn btn-primary btn-sm" type="submit" name="submit" >{{$submit}}</button>
				</div>
			</div>
		</form>
		<div class="clear"></div>
	</div>
	{{/if}}
	<div class="section-content-wrapper-np">
		<table id="wikis-index">
			<tr>
				<th width="96%">{{$name}}</th>
				<th width="1%">{{$type}}</th>
				<th width="1%"></th>
				{{if $owner}}
				<th width="1%"></th>
				{{/if}}
				<th width="1%"></th>
				{{if $owner}}
				<th width="1%"></th>
				{{/if}}
			</tr>
			{{foreach $wikis as $wiki}}
			<tr class="wikis-index-row">
				<td><a href="/wiki/{{$channel}}/{{$wiki.urlName}}/Home" title="{{$view}}"{{if $wiki.active}} class="active"{{/if}}>{{$wiki.title}}</a></td>
				<td>{{if $wiki.typelock}}{{$wiki.mimeType}}{{else}}{{$unlocked}}{{/if}}</td>
				{{if $owner}}
				<td><i class="fa fa-pencil" onclick="openCloseTR('wikis-index-edit-{{$wiki.id}}')"></i></td>
				{{/if}}
				<td class="dropdown">
					{{if $wiki.lockstate == 'lock'}}
					<i class="fa fa-lock lockview" data-toggle="dropdown" onclick="lockview('item',{{$wiki.id}});"></i>
					<ul id="panel-{{$wiki.id}}" class="lockview-panel dropdown-menu dropdown-menu-right"></ul>
					{{/if}}
				</td>
				<td><i class="fa fa-download" onclick="wiki_download_wiki('{{$wiki.resource_id}}'); return false;"></i></td>
				{{if $owner}}
				<td><i class="fa fa-trash-o drop-icons" onclick="wiki_delete_wiki('{{$wiki.title}}', '{{$wiki.resource_id}}'); return false;"></i></td>
				{{/if}}
			</tr>
			{{if $owner}}
			<tr id="wikis-index-edit-{{$wiki.id}}" style="display:none">
				<td colspan="6">
					<form id="edit-wiki-form-{{$wiki.id}}" method="post" action="wiki/{{$channel}}/update/wiki" class="acl-form" data-form_id="edit-wiki-form-{{$wiki.id}}" data-allow_cid='{{$wiki.json_allow_cid}}' data-allow_gid='{{$wiki.json_allow_gid}}' data-deny_cid='{{$wiki.json_deny_cid}}' data-deny_gid='{{$wiki.json_deny_gid}}'>
						<input type="hidden" name="origRawName" value="{{$wiki.title}}">
						{{include file="field_input.tpl" field=['updateRawName', $edit_wiki_name, $wiki.title]}}
						<div class="btn-group float-right">
							<button class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#aclModal" type="button">
								<i class="jot-perms-icon fa fa-{{$wiki.lockstate}}"></i>
							</button>
							<button class="btn btn-primary btn-sm" type="submit" value="edit">Submit</button>
						</div>						
					</form>
				</td>
			</tr>
			{{/if}}
			{{/foreach}}
		</table>
	</div>
</div>
{{$acl}}
<script>
	{{if $owner}}
	function wiki_delete_wiki(wikiHtmlName, resource_id) {
		if(!confirm('Are you sure you want to delete the entire wiki: ' + JSON.stringify(wikiHtmlName))) {
			return;
		}
		$.post("wiki/{{$channel}}/delete/wiki", {resource_id: resource_id}, function (data) {
			if (data.success) {
				window.console.log('Wiki deleted');
				// Refresh list and redirect page as necessary
				window.location = 'wiki/{{$channel}}';
			} else {
				alert('Error deleting wiki!');
				window.console.log('Error deleting wiki.');
			}
		}, 'json');
	}
	{{/if}}
	function wiki_download_wiki(resource_id) {
			window.location = "wiki/{{$channel}}/download/wiki/" + resource_id;
	}
</script>
