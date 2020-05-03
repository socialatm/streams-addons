function loadAbc(id) {
	// autoload the rendering script to save resources when not required.
	if (typeof ABCJS == 'undefined') { 
		var abcScript = document.createElement('script');
		abcScript.onload = function () {
			renderAbcSingle(id);
		};
		abcScript.src = '/addon/abc/abcjs_basic_5.9.1-min.js';
		document.head.appendChild(abcScript);
	}
	else {
		renderAbcSingle(id);
	}
}

function doAbc() {
	
	$('code.music-abc').each(
		function ( index ) {
		        var id = '_' + Math.random().toString(36).substr(2, 9);
			var abctext = $(this).text();
			$(this).replaceWith("<div class='abc-wrapper'><code class='newmusic' id='abcmusic"+id+"'><a href='javascript:renderAbcSingle(\""+id+"\");'>Show music</a>" + abctext + "</code><div id='notation"+id+"'></div><div id='audio"+id+"'></div></div>");
		}
	);
}

function renderAbcSingle(id) {
    var abctext = $('code#abcmusic'+id).html().replace(/<br>/g,'\n').replace(/&gt;/g,'>').replace(/&lt;/g,'<');
	$('code#abcmusic'+id).hide();
	ABCJS.renderAbc('notation'+id,abctext);
    $('code#abcmusic'+id).parent().parent().css('height',''+$('div#notation'+id+' > svg').height()+'px');
	//ABCJS.renderMidi('audio'+id,abctext);
}


function unrenderAbcSingle(id) {
	$('code#abcmusic'+id).show();
	$('code#abcmusic'+id).prev().attr('href','javascript:renderAbcSingle(\''+id+'\');').text('Show music');;
	$('div#notation'+id+' > svg').remove();
	$('code#abcmusic'+id).parent().parent().css('height','400px').next().show();
}

function enrichMidi() {
	$("a[href^='data:audio/midi,']").each(
		function ( index ) {
			alert( $(this).text());
		}
	);
}
