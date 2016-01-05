<h3>{{$title}}</h3>

<form action="hubwall" method="post">

{{include file="field_input.tpl" field=$subject}}

<textarea name="text"></textarea>
<br />
<input type="submit" name="submit" value="{{$submit}}" />
</form>

