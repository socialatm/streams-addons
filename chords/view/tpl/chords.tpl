<h3>{{$header}}</h3>

<div>{{$desc}}</div>

<br /><br />
<form action="chords" method="post">
{{include file="field_input.tpl" field=$chord}}
{{include file="field_select.tpl" field=$tuning}}
{{include file="field_checkbox.tpl" field=$lefty}}
<input type="submit" name="submit" value={{$submit}} />
</form>
<br /><br />
{{$chords}}
<br />
