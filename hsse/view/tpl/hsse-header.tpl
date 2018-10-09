<script language="javascript" type="text/javascript">

var editor = false;
var plaintext = '{{$editselect}}';
var pretext = '{{$pretext}}';
var instance = null;
function initEditor(cb){
	if(editor == false){
		$("#profile-jot-text-loading").show();
		$("#profile-jot-tools").removeClass('d-none');
		{{$geotag}}
		if(plaintext == 'none') {
			$("#profile-jot-text-loading").hide();
			$(".jothidden").show();

                	var texteditor = document.getElementById('profile-jot-text');
                	sceditor.create(texteditor, {
                		format: 'bbcode',
                		style: 'addon/hsse/sceditor/minified/themes/content/default.min.css',
                		id: 'sceditor-001',
                		height: 300,
                		toolbar: 'bold,italic,underline,strike,superscript,subscript|code,quote|horizontalrule|cut,copy,paste|hzsize,color|bulletlist,orderedlist|removeformat|maximize,source'
                	});
                	instance = sceditor.instance(texteditor);
			instance.val($('#profile-jot-text').val());
                	instance.blur(function () {
                        	instance.updateOriginal(true);
                	});

			$("#profile-jot-text").addClass('jot-expanded');
			{{if $bbco_autocomplete}}
			$("#sceditor-001 textarea").bbco_autocomplete('{{$bbco_autocomplete}}'); // autocomplete bbcode
			{{/if}}
			{{if $editor_autocomplete}}
			if(typeof channelId === 'undefined')
				$("#sceditor-001 textarea").editor_autocomplete(baseurl+"/acl");
			else
				$("#sceditor-001 textarea").editor_autocomplete(baseurl+"/acl",[channelId]); // Also gives suggestions from current channel's connections
			{{/if}}
			editor = true;
			if (typeof cb!="undefined") cb();
			if(pretext.length)
				addeditortext(pretext);
			return;
		}
			editor = true;
	} else {
		if (typeof cb!="undefined") cb();
	}
}



function enableOnUser(){
	if(editor)
		return;

	initEditor();
}
</script>

<script src="library/blueimp_upload/js/vendor/jquery.ui.widget.js"></script>
<script src="library/blueimp_upload/js/jquery.iframe-transport.js"></script>
<script src="library/blueimp_upload/js/jquery.fileupload.js"></script>

<script>
var activeCommentID = 0;
var activeCommentText = '';

	$(document).ready(function() {
	
		$("#profile-jot-text").focus(enableOnUser);
		$("#profile-jot-text").click(enableOnUser);

		$('#id_mimetype').on('load', jotSetMime);
		$('#id_mimetype').on('change', jotSetMime);

		function jotSetMime() { 
			var mtype = $('#id_mimetype').val(); 
			if(mtype == 'text/bbcode')
				$('#profile-jot-submit-left').show();
			else
				$('#profile-jot-submit-left').hide();
		}

		$('#invisible-wall-file-upload').fileupload({
			url: 'wall_attach/{{$nickname}}',
			dataType: 'json',
			dropZone: $('#profile-jot-text'),
			maxChunkSize: 4 * 1024 * 1024,
			add: function(e,data) {
				$('#profile-rotator').show();
				data.submit();
			},
			done: function(e,data) {
				addeditortext(data.result.message);
				$('#jot-media').val($('#jot-media').val() + data.result.message);
			},
			stop: function(e,data) {
				preview_post();
				$('#profile-rotator').hide();
			},
		});

		$('#wall-file-upload').click(function(event) { event.preventDefault(); $('#invisible-wall-file-upload').trigger('click'); return false;});
		$('#wall-file-upload-sub').click(function(event) { event.preventDefault(); $('#invisible-wall-file-upload').trigger('click'); return false;});

        // call initialization file
        if (window.File && window.FileList && window.FileReader) {
          DragDropUploadInit();
        }


		$('#invisible-comment-upload').fileupload({
			url: 'wall_attach/{{$nickname}}',
			dataType: 'json',
			dropZone: $(),
			maxChunkSize: 4 * 1024 * 1024,
			add: function(e,data) {

				var tmpStr = $("#comment-edit-text-" + activeCommentID).val();
				if(tmpStr == activeCommentText) {
					tmpStr = "";
					$("#comment-edit-text-" + activeCommentID).addClass("comment-edit-text-full");
					$("#comment-edit-text-" + activeCommentID).removeClass("comment-edit-text-empty");
					openMenu("comment-tools-" + activeCommentID);
					$("#comment-edit-text-" + activeCommentID).val(tmpStr);
				}
				data.submit();
			},

			done: function(e,data) {
				textarea = document.getElementById("comment-edit-text-" + activeCommentID);
				textarea.value = textarea.value + data.result.message;
			},
			stop: function(e,data) {
				$('body').css('cursor', 'auto');
				preview_comment(activeCommentID);
				activeCommentID = 0;
			},
		});
	});

	function deleteCheckedItems() {
		var checkedstr = '';

		$('.item-select').each( function() {
			if($(this).is(':checked')) {
				if(checkedstr.length != 0) {
					checkedstr = checkedstr + ',' + $(this).val();
				}
				else {
					checkedstr = $(this).val();
				}
			}
		});
		$.post('item', { dropitems: checkedstr }, function(data) {
			window.location.reload();
		});
	}

	function hsseGetLink() {
                        var reply = prompt("{{$linkurl}}");
                        if(reply && reply.length) {
                            reply = bin2hex(reply);
                            $('#profile-rotator').show();
                            $.get('{{$baseurl}}/linkinfo?f=&binurl=' + reply, function(data) {
                                    instance.insert(data,true);
				    instance.updateOriginal();
                                    $('#profile-rotator').hide();
                            });
                        }
	}

	function jotGetLocation() {
		reply = prompt("{{$whereareu}}", $('#jot-location').val());
		if(reply && reply.length) {
			$('#jot-location').val(reply);
		}
	}

	function jotGetExpiry() {
		//reply = prompt("{{$expirewhen}}", $('#jot-expire').val());
		$('#expiryModal').modal();
		$('#expiry-modal-OKButton').on('click', function() {
			reply=$('#expiration-date').val();
			if(reply && reply.length) {
				$('#jot-expire').val(reply);
				$('#expiryModal').modal('hide');
			}
		})
	}

	function jotGetPubDate() {
		//reply = prompt("{{$expirewhen}}", $('#jot-expire').val());
		$('#createdModal').modal();
		$('#created-modal-OKButton').on('click', function() {
			reply=$('#created-date').val();
			if(reply && reply.length) {
				$('#jot-created').val(reply);
				$('#createdModal').modal('hide');
			}
		})
	}


	function jotShare(id,post_type) {
		if(post_type == 6) {
			window.location.href = 'rpost?f=&post_id='+id;
		}
		else {
			if ($('#jot-popup').length != 0) $('#jot-popup').show();

			$('#like-rotator-' + id).show();
			$.get('{{$baseurl}}/share/' + id, function(data) {
				if (!editor) $("#profile-jot-text").val("");
				initEditor(function(){
					addeditortext(data);
					$('#like-rotator-' + id).hide();
					$(window).scrollTop(0);
				});
			});
		}
	}

	function linkdropper(event) {
		var linkFound = event.dataTransfer.types.contains("text/uri-list");
		if(linkFound) {
			event.preventDefault();
			var editwin = '#' + event.target.id;
			var commentwin = false;
			if(editwin) {
				commentwin = ((editwin.indexOf('comment') >= 0) ? true : false);
				if(commentwin) {
					var commentid = editwin.substring(editwin.lastIndexOf('-') + 1);
					$('#comment-edit-text-' + commentid).addClass('hover');
				}
			}
		}
	}

	function linkdropexit(event) {
		var editwin = '#' + event.target.id;
		var commentwin = false;
		if(editwin) {
			commentwin = ((editwin.indexOf('comment') >= 0) ? true : false);
			if(commentwin) {
				var commentid = editwin.substring(editwin.lastIndexOf('-') + 1);
				$('#comment-edit-text-' + commentid).removeClass('hover');
			}
		}
	}

	function linkdrop(event) {
		var reply = event.dataTransfer.getData("text/uri-list");
		if(reply) {
			event.preventDefault();
			var editwin = '#' + event.target.id;
			var commentwin = false;
			if(editwin) {
				commentwin = ((editwin.indexOf('comment') >= 0) ? true : false);
				if(commentwin) {
					var commentid = editwin.substring(editwin.lastIndexOf('-') + 1);
					commentOpen(document.getElementById(event.target.id),commentid);

				}
			}
		}

		if(reply && reply.length) {
			reply = bin2hex(reply);
			$('#profile-rotator').show();
			$.get('{{$baseurl}}/linkinfo?f=&binurl=' + reply, function(data) {
				if(commentwin) {
					$(editwin).val( $(editwin).val() + data );
					$('#profile-rotator').hide();
				}
				else {
					if (!editor) $("#profile-jot-text").val("");
					initEditor(function(){
					instance.insert(data);
					instance.updateOriginal();
					//addeditortext(data);
					$('#profile-rotator').hide();
					});
				}
			});
		}
	}

	function itemTag(id) {
		reply = prompt("{{$term}}");
		if(reply && reply.length) {
			reply = reply.replace('#','');
			if(reply.length) {

				commentBusy = true;
				$('body').css('cursor', 'wait');

				$.get('{{$baseurl}}/tagger/' + id + '?term=' + reply);
				if(timer) clearTimeout(timer);
				timer = setTimeout(updateInit,3000);
				liking = 1;
			}
		}
	}

	function itemCancel() {
		$("#jot-title").val('');
		$("#profile-jot-text").val('');
		$("#jot-category").tagsinput('removeAll');
		if (instance !== null) {
			instance.destroy();
			instance.val('');
			instance.updateOriginal();
		}

		postSaveChanges('clean');

		{{if $reset}}
		$('.jothidden').hide();
		$("#profile-jot-text").removeClass('jot-expanded');
		$("#profile-jot-tools").addClass('d-none');
		$("#jot-preview-content").html('').hide();
		editor = false;
		{{else}}
		window.history.back();
		{{/if}}
	}

	function itemFiler(id) {
		if($('#item-filer-dialog').length)
			$('#item-filer-dialog').remove();

		$.get('filer/', function(data){
			$('body').append(data);
			$('#item-filer-dialog').modal('show');
			$("#filer_save").click(function(e){
				e.preventDefault();
				reply = $("#id_term").val();
				if(reply && reply.length) {
					commentBusy = true;
					$('body').css('cursor', 'wait');
					$.get('{{$baseurl}}/filer/' + id + '?term=' + reply, updateInit);
					liking = 1;
					$('#item-filer-dialog').modal('hide');
				}
				return false;
			});
		});
		
	}

	function itemBookmark(id) {
		$.get('{{$baseurl}}/bookmarks?f=&item=' + id);
		if(timer) clearTimeout(timer);
		timer = setTimeout(updateInit,1000);
	}

	function itemAddToCal(id) {
		$.get('{{$baseurl}}/events/add/' + id);
		if(timer) clearTimeout(timer);
		timer = setTimeout(updateInit,1000);
	}

	function toggleVoting() {
		if($('#jot-consensus').val() > 0) {
			$('#jot-consensus').val(0);
			$('#profile-voting, #profile-voting-sub').removeClass('fa-check-square-o').addClass('fa-square-o');
		}
		else {
			$('#jot-consensus').val(1);
			$('#profile-voting, #profile-voting-sub').removeClass('fa-square-o').addClass('fa-check-square-o');
		}
	}

	function toggleNoComment() {
		if($('#jot-nocomment').val() > 0) {
			$('#jot-nocomment').val(0);
			$('#profile-nocomment, #profile-nocomment-sub').removeClass('fa-comments-o').addClass('fa-comments');
			$('#profile-nocomment-wrapper').attr('title', '{{$nocomment_enabled|escape:'javascript'}}');
		}
		else {
			$('#jot-nocomment').val(1);
			$('#profile-nocomment, #profile-nocomment-sub').removeClass('fa-comments').addClass('fa-comments-o');
			$('#profile-nocomment-wrapper').attr('title', '{{$nocomment_disabled|escape:'javascript'}}');
		}
	}

	function jotReact(id,icon) {
		if(id && icon) {
			$.get('{{$baseurl}}/react?f=&postid=' + id + '&emoji=' + icon);
			if(timer) clearTimeout(timer);
			timer = setTimeout(updateInit,1000);
		}
	}

	function jotClearLocation() {
		$('#jot-coord').val('');
		$('#profile-nolocation-wrapper').attr('disabled', true);
	}


    var initializeEmbedPhotoDialog = function () {
        $('.embed-photo-selected-photo').each(function (index) {
            $(this).removeClass('embed-photo-selected-photo');
        });
        getPhotoAlbumList();
        $('#embedPhotoModalBodyAlbumDialog').off('click');
        $('#embedPhotoModal').modal('show');
    };

    var choosePhotoFromAlbum = function (album) {
        $.post("embedphotos/album", {name: album},
            function(data) {
                if (data['status']) {
                    $('#embedPhotoModalLabel').html("{{$modalchooseimages}}");
                    $('#embedPhotoModalBodyAlbumDialog').html('\
                            <div><div class="nav nav-pills flex-column">\n\
                                <li class="nav-item"><a class="nav-link" href="#" onclick="initializeEmbedPhotoDialog();return false;">\n\
                                    <i class="fa fa-chevron-left"></i>&nbsp\n\
                                    {{$modaldiffalbum}}\n\
                                    </a>\n\
                                </li>\n\
                            </div><br></div>')
                    $('#embedPhotoModalBodyAlbumDialog').append(data['content']);
                    $('#embedPhotoModalBodyAlbumDialog').click(function (evt) {
                        evt.preventDefault();
                        var image = document.getElementById(evt.target.id);
                        if (typeof($(image).parent()[0]) !== 'undefined') {
                            var imageparent = document.getElementById($(image).parent()[0].id);
                            $(imageparent).toggleClass('embed-photo-selected-photo');
                            var href = $(imageparent).attr('href');
                            $.post("embedphotos/photolink", {href: href},
                                function(ddata) {
                                    if (ddata['status']) {
                                        //addeditortext(ddata['photolink']);
					instance.insert(ddata['photolink'],null,true);
					instance.updateOriginal();
					preview_post();
                                    } else {
                                        window.console.log("{{$modalerrorlink}}" + ':' + ddata['errormsg']);
                                    }
                                    return false;
                                },
         	                   'json');
	                        $('#embedPhotoModalBodyAlbumDialog').html('');
    	                    $('#embedPhotoModalBodyAlbumDialog').off('click');
        	                $('#embedPhotoModal').modal('hide');
                        }
                    });
                    $('#embedPhotoModalBodyAlbumListDialog').addClass('d-none');
                    $('#embedPhotoModalBodyAlbumDialog').removeClass('d-none');
                } else {
                    window.console.log("{{$modalerroralbum}} " + JSON.stringify(album) + ':' + data['errormsg']);
                }
                return false;
            },
        'json');
    };

    var getPhotoAlbumList = function () {
        $.post("embedphotos/albumlist", {},
            function(data) {
                if (data['status']) {
                    var albums = data['albumlist']; //JSON.parse(data['albumlist']);
                    $('#embedPhotoModalLabel').html("{{$modalchoosealbum}}");
                    $('#embedPhotoModalBodyAlbumList').html('<ul class="nav nav-pills flex-column"></ul>');
                    for(var i=0; i<albums.length; i++) {
                        var albumName = albums[i].text;
			var jsAlbumName = albums[i].jstext;
			var albumLink = '<li class="nav-item">';
			albumLink += '<a class="nav-link" href="#" onclick="choosePhotoFromAlbum(\'' + jsAlbumName + '\'); return false;">' + albumName + '</a>';
                        albumLink += '</li>';
                        $('#embedPhotoModalBodyAlbumList').find('ul').append(albumLink);
                    }
                    $('#embedPhotoModalBodyAlbumDialog').addClass('d-none');
                    $('#embedPhotoModalBodyAlbumListDialog').removeClass('d-none');
                } else {
                    window.console.log("{{$modalerrorlist}}" + ':' + data['errormsg']);
                }
                return false;
            },
        'json');
    };

    //
    // initialize drag-drop
    function DragDropUploadInit() {

      var filedrag = $("#profile-jot-text");

	  // file drop
        filedrag.on("dragover", DragDropUploadFileHover);
        filedrag.on("dragleave", DragDropUploadFileHover);
        filedrag.on("drop", DragDropUploadFileSelectHandler);

    }

	// file drag hover
	function DragDropUploadFileHover(e) {
		if(e.type == 'dragover')
			$(e.target).addClass('hover');
		else
			$(e.target).removeClass('hover');
	}

    // file selection
    function DragDropUploadFileSelectHandler(e) {

      // cancel event and hover styling
      DragDropUploadFileHover(e);
      // open editor if it isn't yet initialised
	  if (!editor) {
			enableOnUser();
	  }
	  linkdrop(e);

    }

</script>

<script>
$( document ).on( "click", ".wall-item-delete-link,.page-delete-link,.layout-delete-link,.block-delete-link", function(e) {
	var link = $(this).attr("href"); // "get" the intended link in a var

	if (typeof(eval($.fn.modal)) === 'function'){
		e.preventDefault();
		bootbox.confirm("<h4>{{$confirmdelete}}</h4>",function(result) {
			if (result) {
				document.location.href = link;
			}
		});
	} else {
		return confirm("{{$confirmdelete}}");
	}
});
</script>


<script>
	var postSaveTimer = null;

	function postSaveChanges(action) {
		if({{$auto_save_draft}}) {

			var doctype = $('#jot-webpage').val();
			var postid = '-' + doctype + '-' + $('#jot-postid').val();

			if(action != 'clean') {
				localStorage.setItem("post_title" + postid, $("#jot-title").val());
				localStorage.setItem("post_body" + postid, $("#profile-jot-text").val());
				if($("#jot-category").length)
					localStorage.setItem("post_category" + postid, $("#jot-category").val());
			}

			if(action == 'start') {
				postSaveTimer = setTimeout(function () {
					postSaveChanges('start');
				},10000);
			}

			if(action == 'stop') {
				clearTimeout(postSaveTimer);
				postSaveTimer = null;
			}

			if(action == 'clean') {
				clearTimeout(postSaveTimer);
				postSaveTimer = null;
				localStorage.removeItem("post_title" + postid);
				localStorage.removeItem("post_body" + postid);
				localStorage.removeItem("post_category" + postid);
			}
		} 

	}

	$(document).ready(function() {

		var cleaned = false;

		if({{$auto_save_draft}}) {
			var doctype = $('#jot-webpage').val();
			var postid = '-' + doctype + '-' + $('#jot-postid').val();
			var postTitle = localStorage.getItem("post_title" + postid);
			var postBody = localStorage.getItem("post_body" + postid);
			var postCategory = (($("#jot-category").length) ? localStorage.getItem("post_category" + postid) : '');
			var openEditor = false;

			if(postTitle) {
				$('#jot-title').val(postTitle);
				openEditor = true;
			}
			if(postBody) {
				$('#profile-jot-text').val(postBody);
				openEditor = true;
			}
			if(postCategory) {
				var categories = postCategory.split(',');
				categories.forEach(function(cat) {
					$('#jot-category').tagsinput('add', cat);
				});
				openEditor = true;
			}
			if(openEditor) {
				enableOnUser();
			}
		} else {
			postSaveChanges('clean');
		}

		$(document).on('submit', '#profile-jot-form', function() {
			postSaveChanges('clean');
			cleaned = true;
		});

		$(document).on('focusout',"#profile-jot-wrapper",function(e){
			if(! cleaned)
				postSaveChanges('stop');
		});

		$(document).on('focusin',"#profile-jot-wrapper",function(e){
			postSaveTimer = setTimeout(function () {
				postSaveChanges('start');
			},10000);
		});


	});

	sceditor.formats.bbcode.set(
		'zrl',
		{
    			isSelfClosing: false,
    			isInline: true,
			allowsempty: true,
			
    			format: function(element,content) {
				if (!element.attr('href'))
					return content;
				return '[zrl=' + element.attr('href') + ']' + content + '[/zrl]';
			},
    			html: function(token,attrs,content) {
				return '<a href="' + attrs.defaultattr + '">' + content + '</a>';
			},
			
    			quoteType: sceditor.BBCodeParser.QuoteType.auto
		}
	);

	sceditor.formats.bbcode.set (
		'zmg',
		{
    			isSelfClosing: false,
    			isInline: true,
			allowsempty: true,
			
    			format: function(element,content) {
				if (!element.attr('src'))
					return '';

				size = 0;
				if (element.attr('width') && element.attr('height'))
					size = element.attr('width') + 'x' + element.attr('height');
				if (size) 
					return '[zmg=' + size + ']' + element.attr('src') + '[/zmg]';
				else
					return '[zmg]' + element.attr('src') + '[/zmg]';
			},
    			html: function(token,attrs,content) {
				sizearray = attrs.defaultattr.split('x');
				sizeinfo = '';
				if (sizearray[0] && sizearray[1])
					sizeinfo = 'width = "' + sizearray[0] + '" height = "' + sizearray[1] + '"';
				return '<img src="' + content + '" ' + sizeinfo + '/>';
			},
			
    			quoteType: sceditor.BBCodeParser.QuoteType.auto
		}

	);

</script>
