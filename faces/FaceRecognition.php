<?php

namespace Zotlabs\Module;

class FaceRecognition {

	private $defaultProcId = 0;
	private $localDebug = 0;

	function test() {
		$cmd = escapeshellcmd("python3 " . getcwd() . "/addon/faces/py/faces.py -t OK");
		exec($cmd, $o);
		if ($o[0] === 'OK') {
			return true;
		} else {
			return false;
		}
	}

	function detect() {
		if ($this->localDebug) {
			if (!$this->test()) {
				return array('status' => false, 'message' => 'python script failed to start');
			}
		}
		$ret = $this->isScriptRunning();
		if ($ret['status']) {
			return array('status' => $ret['status'], 'message' => $ret['message']);
		}

		$logfile = get_config('logrot', 'logrotpath') . '/faces.log';
		$logfileparam = " --logfile " . $logfile;
		if (!$logfile)
			$logfileparam = "";
		else {
			if (!is_writable($logfile)) {
				logger("PLEASE CHECK PATH OR PERMISSIONS! Can not write log file " . $logfile, LOGGER_DEBUG);
				$logfileparam = "";
			}
		}
		$logEnabled = get_config('system', 'debugging');
		if (!$logEnabled) {
			$logfile = '';
			$this->localDebug = 0;
		}
		$loglevel = (get_config('system', 'loglevel') ? get_config('system', 'loglevel') : LOGGER_NORMAL);
		$limit = get_config('faces', 'limit');

		$channel_id = "0";

		$finderConfig = "";
		if (get_config('faces', 'finder1') == "1") {
			$finderConfig .= " --finder1 " . (get_config('faces', 'finder1config') ? get_config('faces', 'finder1config') : "confidence=0.5;minsize=20");
		}
		if (get_config('faces', 'finder2') == "1") {
			$finderConfig .= " --finder2 " . (get_config('faces', 'finder2config') ? get_config('faces', 'finder2config') : "tolerance=0.6;model=hog");
		}

		@include('.htconfig.php');
		$r = q("update faces_proc set created = '%s', updated = '%s', running = %d where proc_id = %d ", dbesc(datetime_convert()), dbesc(datetime_convert()), intval(1), intval(0));
		$cmd = escapeshellcmd("python3 " . getcwd() . "/addon/faces/py/faces.py"
				. " --host " . $db_host . " --user " . $db_user . " --pass " . $db_pass . " --db " . $db_data
				. " --imagespath " . getcwd() . " --channelid " . $channel_id
				. " --limit " . $limit
				. " --procid " . $this->defaultProcId
				. " --loglevel " . $loglevel . $logfileparam . " --logconsole " . $this->localDebug
				. $finderConfig);

		logger('The pyhton script will be executed using the following command ...', LOGGER_DEBUG);
		logger($cmd, LOGGER_DEBUG);

		if ($this->localDebug) {
			exec($cmd, $o);
			logger('The pyhton script finished. The messages are...', LOGGER_DEBUG);
			$ret['message'] = $ret['message'] . '<br>debug messages from pyhthon script... ';
			foreach ($o as $line) {
				$ret['message'] = $ret['message'] . '<br>' . $line;
				logger($line, LOGGER_DEBUG);
			}
		} else {
			exec($cmd . ' > /dev/null 2>/dev/null &'); // production
		}

		return array('status' => true, 'message' => $ret['message']);
	}

	function isScriptRunning() {
		$r = q("SELECT * FROM faces_proc WHERE proc_id = %d", intval($this->defaultProcId));
		if (!$r) {
			$msg = 'First time the python script is started (empty table for processes)';
			logger($msg, LOGGER_DEBUG);
			q("insert into faces_proc ( proc_id, running ) 
					values ( %d, %d ) ", intval($this->defaultProcId), intval(1)
			);
			return array('status' => false, 'message' => $msg);
		}
		$updated = $r[0]["updated"];
		if ($r[0]["running"] == 1) {
			$elapsed = strtotime(datetime_convert()) - strtotime($updated); // both UTC
			if ($elapsed > 60 * 10) {
				// Th Python script writes a timestamp "updated" every 10 seconds to indicate it is still running.
				// Now we are 10 minutes away from the last "updated" written by the script.
				// It might be that the python script hangs or was stopped.
				$msg = 'The script was started and did not finish yet. Please watch this condition. Why? It might be that the python script hangs, run into errors or was stopped externally. The last update by the script was at ' . $updated . '. This is more then 10 minutes ago. This is unusual because the script writes a time stamp every 10 seconds to indicate that it is still running.';
				logger($msg, LOGGER_DEBUG);
				return array('status' => false, 'message' => $msg);
			}
		} else if ($r[0]["running"] == 0) {
			// The python script is not running.
			$msg = 'The python script was not running.';
			logger($msg, LOGGER_DEBUG);
			return array('status' => false, 'message' => $msg);
		}
		$msg = 'The python script is still running. Last update: ' . $updated;
		logger($msg, LOGGER_DEBUG);
		return array('status' => true, 'message' => $msg);
	}

}
