<script>
var chess_game_id = '{{$game_id}}';
var chess_timer = null;
var chess_board = null;
var chess_viewing_history = false;
var chess_original_pos = '{{$position}}';
var chess_new_pos = [];
var chess_viewing_position = '';
var chess_viewing_mid = '';
var chess_game_ended = {{$ended}};
var chess_game = null;
var chess_game_move = null;
window.console.log("3");
var chess_init = function () {
	$("#chess-verify-move").hide();
	if($("#chess-game-" + chess_game_id).length) {
		$("#chess-game-" + chess_game_id).css("font-weight","bold");
		$("#chess-game-" + chess_game_id + " a").css("color", "blue");
	}
	$("#chess-revert").hide();
	$("#expand-aside").on('click', function () {
		setTimeout(chess_fit_board(),500)
	});
	if (chess_game_ended === 1) {
		$("#chess-turn-indicator").html('Game Over');
		$("#chess-resume-game").show();
	} else {
		$("#chess-resume-game").hide();
	}
	var cfg = {
		//sparePieces: true,
		position: '{{$position}}',
		orientation: 'white',
		//dropOffBoard: 'snapback',
		onDragStart: false
	};
	chess_board = new ChessBoard('chessboard', cfg);
	chess_game = new Chess(chess_original_pos);
	
	$(window).resize(chess_fit_board);
	setTimeout(chess_fit_board,300);
	chess_timer = setTimeout(chess_update_game,15000);
};

var chess_fit_board = function () {
	var viewportHeight = $(window).height() - $("#chessboard").offset().top;
	var leftOffset = 0;
	if($(window).width() > 767) {
		leftOffset = 1.1*$('#region_1').width() + $('#region_1').offset().left;
	} else if ($("main").hasClass('region_1-on') ) {
		$('#chessboard').css('zIndex',-100);
		$('#region_1').css('background-color', 'rgba(255,255,255,0.8)');
		//leftOffset = $('#region_1').width();
	} else {
		$('#chessboard').css('zIndex','auto');
	}
	$("#chessboard").offset({ left: leftOffset});
	var centerRegionWidth = $('#region_2').width();
	if (viewportHeight < centerRegionWidth * 1.25) {
		$("#chessboard").css('width', viewportHeight / 1.25);
		$("#white-player-name").css('margin-top', viewportHeight / 1.2);
		$("#white-player-name").css('margin-left', centerRegionWidth * 0.2);
		$("#black-player-name").css('margin-left', centerRegionWidth * 0.2);
	} else {
		$("#chessboard").css('width', centerRegionWidth * 1.0);
		$("#white-player-name").css('margin-top', centerRegionWidth * 1.05);
		$("#white-player-name").css('margin-left', centerRegionWidth * 0.2);
		$("#black-player-name").css('margin-left', centerRegionWidth * 0.2);
	}
	chess_board.resize();
};
// only allow pieces to be dragged when the board is oriented
// in their direction
var chess_onDragStart = function(source, piece, position, orientation) {
	return false;
};


var chess_update_game = function () {
	$.post("chess/update", {game_id: chess_game_id} ,
	function(data) {
		window.console.log('update received: '+JSON.stringify(data));
		if (data['status']) {
			chess_game = new Chess(data['position']);
			chess_board.position(data['position']);
			chess_game_ended = data['ended'];
			if (chess_game_ended) {
				$('#chess-turn-indicator').html("Game Over");
				return false;
			}
		} else {
			window.console.log('Error updating: ' + data['errormsg']);
		}
		return false;
	},
	'json');
	chess_timer = setTimeout(chess_update_game,15000);
};


$(document).ready(chess_init);
</script>
<h2 id='chess-turn-indicator'>
	{{if $ended}}
	Game Over
	{{/if}}
</h2>
<div id="chess-enforce-legal-moves"></div>
<div id="black-player-name">
	<h2>{{$blackplayer}}</h2>
</div>
<div id="chessboard" style="width: 400px; position: fixed;"></div>
<div id="white-player-name" style="margin-top: 400px;">
<h2>{{$whiteplayer}}</h2>
</div>