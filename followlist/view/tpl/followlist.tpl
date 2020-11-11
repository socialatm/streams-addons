<h3>{{$page_title}}</h3>
<div class="descriptive-text">{{$notes}}</div>	
<div class="descriptive-text">{{$limits}}</div>	
<form action="followlist" method="post" enctype="multipart/form-data" >
  {{if ! $disabled}}
  <input type='hidden' name='form_security_token' value='{{$form_security_token}}' />
  {{/if}}
  {{include file="field_input.tpl" field=$url}}

  <strong>{{$upload}}</strong>
  <input id="followlist-upload-choose" class="form-control-file w-100" type="file" name="userfile" />

	<br>
	<br>
  <input type="submit" name="submit" value="{{$submit}}" {{$disabled}}>
</form>
<br>
<br>
