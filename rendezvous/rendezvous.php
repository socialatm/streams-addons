<?php

/**
 *
 * Name: Rendezvous
 * Description: Group sharing of real-time location on a dynamic map
 * Version: 0.1.0
 * Author: Andrew Manning <andrew@reticu.li>
 * MinVersion: 1.12
 *
 */

function rendezvous_module() {}

/**
 * @brief Return the current plugin version
 *
 * @return string Current plugin version
 */
function rendezvous_get_version() {
    return '0.1.0';
}

function rendezvous_load() {
    register_hook('load_pdl', 'addon/rendezvous/rendezvous.php', 'rendezvous_load_pdl');
    logger("Load Rendezvous", LOGGER_DEBUG);
}

function rendezvous_unload() {
    unregister_hook('load_pdl', 'addon/rendezvous/rendezvous.php', 'rendezvous_load_pdl');    
    logger("Unload Rendezvous", LOGGER_DEBUG);
}

function rendezvous_install() {
    set_config('rendezvous', 'dropTablesOnUninstall', 0);
    $errors = rendezvous_create_database_tables();
    
    if ($errors) {
        notice('Error creating the database tables');
        logger('Error creating the database tables: ' . $errors);
    } else {
        info('Installation successful');
        logger('Database tables installed successfully', LOGGER_DEBUG);
    }
    return;
}

function rendezvous_uninstall() {
    $errors = false;
    $dropTablesOnUninstall = intval(get_config('rendezvous', 'dropTablesOnUninstall'));
    logger('Rendezvous uninstall drop tables admin setting: ' . $dropTablesOnUninstall, LOGGER_DEBUG);
    if ($dropTablesOnUninstall === 1) {
				foreach(array('rendezvous_groups', 'rendezvous_members') as $table) {
						$r = q("DROP TABLE IF EXISTS %s;", dbesc($table));
						if (!$r) {
								$errors .= t('Errors encountered deleting database table '.$table.'.') . EOL;
						}
				}
        if ($errors) {
            notice('Errors encountered deleting Rendezvous database tables.');
            logger('Errors encountered deleting Rendezvous database tables: ' . $errors);
        } else {
            info('Rendezvous uninstalled successfully. Database tables deleted.');
            logger('Rendezvous uninstalled successfully. Database tables deleted.');
        }
    } else {
        info('Rendezvous uninstalled successfully.');
        logger('Rendezvous uninstalled successfully.');
    }
    del_config('rendezvous', 'dropTablesOnUninstall');
    return;
}

function rendezvous_plugin_admin_post(&$a) {
    $dropTablesOnUninstall = ((x($_POST, 'dropTablesOnUninstall')) ? intval($_POST['dropTablesOnUninstall']) : 0);
    logger('Rendezvous drop tables admin setting: ' . $dropTablesOnUninstall, LOGGER_DEBUG);
    set_config('rendezvous', 'dropTablesOnUninstall', $dropTablesOnUninstall);
    info(t('Settings updated.') . EOL);
}

function rendezvous_plugin_admin(&$a, &$o) {
    $t = get_markup_template("admin.tpl", "addon/rendezvous/");

    $dropTablesOnUninstall = get_config('rendezvous', 'dropTablesOnUninstall');
    if (!$dropTablesOnUninstall)
        $dropTablesOnUninstall = 0;
    $o = replace_macros($t, array(
        '$submit' => t('Submit Settings'),
        '$dropTablesOnUninstall' => array('dropTablesOnUninstall', t('Drop tables when uninstalling?'), $dropTablesOnUninstall, t('If checked, the Map database tables will be deleted When the Map plugin is uninstalled.')),
    ));
}

function rendezvous_init($a) {}

function rendezvous_load_pdl($a, &$b) {
    if ($b['module'] === 'rendezvous') {
				if (argc() > 1) {
        $b['layout'] = '
						[template]none[/template]
        ';
				} 
    }
}

function rendezvous_content($a) {
		
		if (argc() > 1) {
				$group = argv(1);
				if(rendezvous_valid_group($group)) {
						$o .= replace_macros(get_markup_template('rendezvous_group.tpl', 'addon/rendezvous'), array(
								'$pagetitle' => t('Rendezvous'),
								'$group' => $group,
								'$name' => autoname(6),
								'$zroot' => z_root()
								//'$isowner' => $isowner,
						));
						return $o;
				} else {
						notice('Invalid rendezvous');
						goaway('/');
				}
		}
		//$isowner = ((local_channel()) ? true : false);
		if(local_channel()) {
				$o .= replace_macros(get_markup_template('rendezvous.tpl', 'addon/rendezvous'), array(
				));
				return $o;
		} else {
				notice('Permission denied');
				goaway('/');
		}
}

function rendezvous_post($a) {
		$channel = App::get_channel();
		if (argc() === 4 && argv(1) === 'v1' && argv(2) === 'new' && argv(3) === 'group') {
				$r = rendezvous_create_group($channel);
				if ($r['success']) {
						rendezvous_api_return(array('id' => $r['guid']));
				} else {
						rendezvous_api_return(array(), false, $r['message']);
				}
		}
		if (argc() === 4 && argv(1) === 'v1' && argv(2) === 'get' && argv(3) === 'groups') {
				$x = rendezvous_get_groups($channel);
				if ($x['success']) {
						$html = '';
						foreach ($x['groups'] as $group) {
								$html .= replace_macros(get_markup_template('rendezvous_groups_list.tpl', 'addon/rendezvous'), array(
										'$shareLink' => z_root() . '/rendezvous/' . $group['guid'] . '/',
										'$group' => $group['guid']
								));
						}
						if($html === '') {
								$html = '
										<div class="descriptive-text">
										Press the button above to create a rendezvous!
										</div>
								';
						}
						rendezvous_api_return(array('groups' => $x['groups'], 'html' => $html));
				} else {
						rendezvous_api_return(array(), false, 'Error fetching groups');
				}
		}
		if (argc() === 4 && argv(1) === 'v1' && argv(2) === 'get' && argv(3) === 'identity') {
				if (isset($_POST['group'])) {
						$group = $_POST['group'];
				} else {
						rendezvous_api_return(array(), false, 'Valid rendezvous ID is required');
				}
				if (isset($_POST['name'])) {
						$name = $_POST['name'];
				} else {
						$name = '';
				}
				if (isset($_POST['currentTime'])) {
						$currentTime = $_POST['currentTime'];
						logger('$currentTime: ' . $currentTime, LOGGER_DEBUG);
						$date1 = new DateTime($currentTime);
						logger('date1: ' . $date1->format('Y-m-d H:i:sP'), LOGGER_DEBUG);
						$date2 = new DateTime();
						logger('date2: ' . $date2->format('Y-m-d H:i:sP'), LOGGER_DEBUG);
						$interval = $date1->diff($date2);				
						logger('seconds different: ' . $interval->s, LOGGER_DEBUG);
						$timeOffset = round(floatval($interval->s)/60);
						logger('hours different: ' . $timeOffset, LOGGER_DEBUG);
				} else {
						$timeOffset = 0;
				}
				$x = rendezvous_new_identity($group, $name);
				if ($x['success']) {
						rendezvous_api_return(array('id' => $x['id'], 'secret' => $x['secret'], 'name' => $name, 'timeOffset' => $timeOffset));
				} else {
						rendezvous_api_return(array(), false, 'Error adding new member');
				}
		}
		if (argc() === 4 && argv(1) === 'v1' && argv(2) === 'update' && argv(3) === 'location') {
				if (isset($_POST['lat']) && isset($_POST['lng']) && isset($_POST['updated']) && isset($_POST['id']) && isset($_POST['secret'])) {
						$x = rendezvous_update_location($_POST['lat'], $_POST['lng'], $_POST['updated'], $_POST['id'], $_POST['secret']);
						if($x['success']) {
								rendezvous_api_return(array());
						} else {
								rendezvous_api_return(array(), false, $x['message']);
						}
				} else {
						rendezvous_api_return(array(), false, 'lat, lng, and updated time are required');
				}
		}
		if (argc() === 4 && argv(1) === 'v1' && argv(2) === 'get' && argv(3) === 'members') {
				if (isset($_POST['secret']) && isset($_POST['id'])) {
						$secret = $_POST['secret'];
						$id = $_POST['id'];
				} else {
						rendezvous_api_return(array(), false, 'Valid member secret is required');
				}
				$x = rendezvous_get_members($id, $secret);
				if ($x['success']) {
						rendezvous_api_return(array('members' => $x['members']));
				} else {
						rendezvous_api_return(array(), false, $x['message']);
				}
		}
		if (argc() === 4 && argv(1) === 'v1' && argv(2) === 'delete' && argv(3) === 'group') {
				if (isset($_POST['group'])) {
						$group = $_POST['group'];
				} else {
						rendezvous_api_return(array(), false, 'Valid rendezvous ID is required');
				}
				$x = rendezvous_delete_group($group, $channel);
				if ($x['success']) {
						rendezvous_api_return(array());
				} else {
						rendezvous_api_return(array(), false, $x['message']);
				}
		}
		if (argc() === 4 && argv(1) === 'v1' && argv(2) === 'create' && argv(3) === 'marker') {
				if (isset($_POST['name']) && isset($_POST['secret']) && isset($_POST['mid']) && isset($_POST['group']) && isset($_POST['lat']) && isset($_POST['lng'])) {
						$name = $_POST['name'];
						$group = $_POST['group'];
						$secret = $_POST['secret'];
						$mid = $_POST['mid'];
						$lat = $_POST['lat'];
						$lng = $_POST['lng'];
				} else {
						rendezvous_api_return(array(), false, 'Marker name, member ID and secret are required');
				}
				$description = ((isset($_POST['description'])) ? $_POST['description'] : '');
				$created = ((isset($_POST['created'])) ? date("Y-m-d H:i:s", strtotime($_POST['created'])) : date("Y-m-d H:i:s", 'now'));
				$x = rendezvous_create_marker($name, $description, $group, $mid, $secret, $created, $lat, $lng);
				if ($x['success']) {
						rendezvous_api_return(array('id' => $x['id']));
				} else {
						rendezvous_api_return(array(), false, $x['message']);
				}
		}
		if (argc() === 4 && argv(1) === 'v1' && argv(2) === 'get' && argv(3) === 'markers') {
				if (isset($_POST['mid']) && isset($_POST['group'])) {
						$group = $_POST['group'];
						$mid = $_POST['mid'];
				} else {
						rendezvous_api_return(array(), false, 'Group name and member ID are required');
				}
				if(!rendezvous_valid_member($mid, $group)) {
						rendezvous_api_return(array(), false, 'Invalid group name or member ID');
				}
				$x = rendezvous_get_markers($group, $mid);
				if ($x['success']) {
						rendezvous_api_return(array('markers' => $x['markers']));
				} else {
						rendezvous_api_return(array(), false, $x['message']);
				}
				
		}
}

function rendezvous_create_database_tables() {
    $str = file_get_contents('addon/rendezvous/rendezvous_schema_mysql.sql');
    $arr = explode(';', $str);
    $errors = false;
    foreach ($arr as $a) {
        if (strlen(trim($a))) {
            $r = q(trim($a));
            if (!$r) {
                $errors .= t('Errors encountered creating database tables.') . $a . EOL;
            }
        }
    }
    return $errors;
}

/**
 * Return the JSON encoded $ret array with the $success state and error message
 * $errmsg if $success is false. Error message can be translated.
 * @param array $ret
 * @param boolean $success
 * @param string $errmsg
 */
function rendezvous_api_return($ret = array(), $success = true, $errmsg = '') {
		$ret = array_merge($ret, array('success' => $success));
		if ($success) {
				$ret = array_merge($ret, array('message' => ''));
		} else {
				$ret = array_merge($ret, array('message' => t($errmsg)));
		}
		json_return_and_die($ret);
}

function rendezvous_valid_group($group) {
		$r = q("SELECT guid from rendezvous_groups where guid = '%s' and deleted = 0", 
						dbesc($group)
		);
		if ($r) {
				return true;
		} else {
				return false;
		}
}

function rendezvous_valid_member($mid, $group, $secret = '') {
		if($secret !== '') {
				$secretsql = " and secret = '" . dbesc($secret) . "' ";
		} else {
				$secretsql = '';
		}
		$r = q("SELECT name from rendezvous_members where mid = '%s' and rid = '%s' and deleted = 0 " . $secretsql . " LIMIT 1", 
						dbesc($mid),
						dbesc($group)
		);
		if ($r) {
				return true;
		} else {
				return false;
		}
}

function rendezvous_create_group($channel) {
		if (!local_channel())
				return array('success' => false, 'message' => 'Must be local authenticated channel');
		
		$guid = autoname(12);
		$r = q("INSERT INTO rendezvous_groups ( uid, guid, created ) VALUES ( %d, '%s', '%s' ) ", 
						dbesc($channel['channel_id']),
						dbesc($guid),
						dbesc(datetime_convert('UTC', date_default_timezone_get()))
		);
		if ($r) {
				return array('success' => true, 'message' => '', 'guid' => $guid);
		} else {
				return array('success' => false, 'message' => 'Error creating group');
		}
}

function rendezvous_get_groups($channel) {
		if (!local_channel())
				return array('success' => false, 'message' => 'Must be local authenticated channel');
		$r = q("SELECT guid from rendezvous_groups where uid = %d and deleted = 0", 
						dbesc($channel['channel_id'])
						//dbesc(datetime_convert('UTC', date_default_timezone_get()))
		);
		if ($r) {
				return array('success' => true, 'message' => '', 'groups' => $r);
		} elseif (count($r) === 0) {
				return array('success' => true, 'message' => '', 'groups' => array());
		} else {
				return array('success' => false, 'message' => 'Error fetching groups');
		}
}

function rendezvous_new_identity($rid, $name) {
		if (!$name) {
				$name = '';
		}
		if (!rendezvous_valid_group($rid)) {
				return array('success' => false, 'message' => 'Not a valid group');
		}
		$secret = random_string(12);
		$mid = random_string(5);
		$r = q("INSERT INTO rendezvous_members ( rid, mid, secret, name ) VALUES ( '%s', '%s', '%s', '%s' ) ", 
						dbesc($rid),
						dbesc($mid),
						dbesc($secret),
						dbesc($name)
						
		);
		if ($r) {
				return array('success' => true, 'message' => '', 'id' => $mid, 'secret' => $secret);
		} else {
				return array('success' => false, 'message' => 'Error adding new member');
		}
}

function rendezvous_update_location($lat, $lng, $updated, $mid, $secret) {
		//logger(date("Y-m-d H:i:s"), LOGGER_DEBUG);
		$updateTime = date("Y-m-d H:i:s", strtotime($updated)); 
		//logger($updateTime, LOGGER_DEBUG);
		$r = q("UPDATE rendezvous_members SET lat = %f, lng = %f, updated = '%s' where mid = '%s' and secret = '%s' and deleted = 0", 
						floatval($lat),
						floatval($lng),
						dbesc($updateTime),
						dbesc($mid),
						dbesc($secret)
		);
		if ($r) {
				return array('success' => true, 'message' => '');
		} else {
				return array('success' => false, 'message' => 'Error updating location');
		}
		
}

function rendezvous_get_members($mid, $secret) {
		$m = q("SELECT rid from rendezvous_members where mid = '%s' and secret = '%s' and deleted = 0 LIMIT 1", 
						dbesc($mid),
						dbesc($secret)
		);
		if(!$m) {
				return array('success' => false, 'message' => 'Invalid member ID and secret');
		}
		$rid = $m[0]['rid'];
		$r = q("SELECT lat,lng,updated,mid,name from rendezvous_members where rid = '%s' and mid != '%s' and deleted = 0", 
						dbesc($rid),
						dbesc($mid)
		);
		if ($r) {
				return array('success' => true, 'message' => '', 'members' => $r);
		} elseif (count($r) === 0) {
				return array('success' => true, 'message' => '', 'members' => array());
		} else {
				return array('success' => false, 'message' => 'Error getting group member data');
		}
}

function rendezvous_delete_group($group, $channel) {
		//logger($group,LOGGER_DEBUG);
		$g = q("UPDATE rendezvous_groups set deleted = 1 where guid = '%s' and uid = %d and deleted = 0", 
						dbesc($group),
						dbesc($channel['channel_id'])
		);
		if($g){
				
				$m = q("UPDATE rendezvous_members set deleted = 1 where rid = '%s' and deleted = 0", 
								dbesc($group),
								dbesc($channel['channel_id'])
				);
				if($m){
						return array('success' => true, 'message' => '');
				} else {
						return array('success' => false, 'message' => 'Error deleting group members');
				}
		} else {
				return array('success' => false, 'message' => 'Error deleting group and members');
		}		
		
}

function rendezvous_get_marker($id, $rid) {
		$r = q("SELECT * from rendezvous_markers where id = %d and rid = '%s' and deleted = 0 LIMIT 1", 
						intval($id),
						dbesc($rid)			
		);
		if($r[0]) {
				return $r[0];
		} else {
				return null;
		}
}

function rendezvous_create_marker($name, $description, $rid, $mid, $secret, $created, $lat, $lng) {
		if(!rendezvous_valid_member($mid, $rid, $secret)) {
				return array('success' => false, 'message' => 'Invalid group member');
		}
		if(!$created || $created === '') {
				$created = date("Y-m-d H:i:s", 'now');
		}
		$r = q("INSERT INTO rendezvous_markers ( rid, mid, description, name, created, lat, lng ) VALUES ( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) ", 
						dbesc($rid),
						dbesc($mid),
						dbesc($description),
						dbesc($name),
						dbesc($created),
						dbesc($lat),
						dbesc($lng)
						
		);
		if($r) {
				return array('success' => true, 'message' => '', 'marker' => $r);
		} else {
				return array('success' => false, 'message' => 'Error creating marker');
		}
}

function rendezvous_get_markers($group, $mid) {
		$r = q("SELECT * from rendezvous_markers where rid = '%s' and deleted = 0", 
						dbesc($group)			
		);
		if ($r) {
				return array('success' => true, 'message' => '', 'markers' => $r);
		} elseif (count($r) === 0) {
				return array('success' => true, 'message' => '', 'markers' => array());
		} else {
				return array('success' => false, 'message' => 'Error fetching markers');
		}
}