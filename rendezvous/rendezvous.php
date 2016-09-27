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
						$r = q("DROP TABLE IF EXISTS '%s';", dbesc($table));
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
        $b['layout'] = '
						[template]none[/template]
        ';
    }
}

function rendezvous_content($a) {
    $o .= replace_macros(get_markup_template('rendezvous.tpl', 'addon/rendezvous'), array(
        '$pagetitle' => t('Rendezvous')
    ));
    return $o;
}

function rendezvous_post($a) {}

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
