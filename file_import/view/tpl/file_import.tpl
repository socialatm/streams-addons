<h3>{{$header}}</h3>

<p class="descriptive-text">{{$desc}}</p>

<form action="file_import" method="post" >

{{include file="field_input.tpl" field=$fr_server}}
{{include file="field_input.tpl" field=$since}}
{{include file="field_input.tpl" field=$until}}

<input type="submit" name="submit" value="{{$submit}}" />
</form>

