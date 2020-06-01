{{$pythoncheckmsg}}
{{include file="field_checkbox.tpl" field=$finder1}}
{{$finder1msg}}
{{include file="field_input.tpl" field=$finder1config}}
{{include file="field_checkbox.tpl" field=$finder2}}
{{$finder2msg}}
{{include file="field_input.tpl" field=$finder2config}}
{{include file="field_checkbox.tpl" field=$exiftool}}
{{$exiftoolmsg}}
{{include file="field_input.tpl" field=$limit}}
{{include file="field_checkbox.tpl" field=$deletetables}}
<div class="submit"><input type="submit" name="page_faces" value="{{$submit}}"></div>
