<?php

function widget_cdav() {
	if(!local_channel())
		return;

	return '<h3>Sample Widget</h3>';
}
