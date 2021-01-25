
<h3>{{$header}}</h3>

{{include file="field_input.tpl" field=$qrtext}}

<center><div id="qr-output"></div></center>
		
<script>
	function makeqr() {
		var txt = $('#id_qrtext').val();
		$('#qr-output').html('<img src="/photo/qr/?f=&qr=' + txt + '" /></img>');
	}
</script>
