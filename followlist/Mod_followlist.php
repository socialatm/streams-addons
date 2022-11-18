<?php
namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Lib\ASCollection;
use Code\Lib\Connect;
use Code\Lib\Apps;
use Code\Lib\ServiceClass;
use Code\Render\Theme;


class Followlist extends Controller
{

    public function post()
    {
        $actors = [];

        if (!local_channel()) {
            return;
        }

        if (!Apps::addon_app_installed(local_channel(), 'followlist')) {
            return;
        }

        check_form_security_token_redirectOnErr('/stream', 'followlist');

        $max_records = get_config('system', 'max_imported_channels', 1000);
        $importer = App::get_channel();
        if (! $importer) {
            notice('Unable to locate channel');
            return;
        }

        $url = $_REQUEST['url'];

        $src = null;

        $type = '';
        $collection = false;

        if (($_FILES) && array_key_exists('userfile', $_FILES) && intval($_FILES['userfile']['size'])) {
            $src = $_FILES['userfile']['tmp_name'];
            $type = $_FILES['userfile']['type'];
        }

        if ($src && $type === 'text/csv') {
            $actors = $this->fetchFromFile($src, $max_records);
        }
        elseif ($src) {
            $collection = $this->fetchFromURL($src);
        }
        else {
            $collection = $url;
        }

        if ($src) {
            unlink($src);
        }

        if ($collection) {
            $obj = new ASCollection($collection, $importer, 0, $max_records);
            $actors = $obj->get();
        }

        if ($actors) {
            notice(t('Adding connections') . EOL);

            foreach ($actors as $actor) {
                if (get_config('system', 'followlist_test', false)) {
                    logger('followlist: ' . $actor);
                    continue;
                }
                $result = Connect::connect($importer, is_array($actor) ? $actor['id'] : $actor);
                if (!$result['success']) {
                    notice(t('Connect failed: ') . $result['message']);
                }
            }
            notice(t('Finished adding connections') . EOL);
        }
        else {
            notice (t('No entries found.'));
        }
    }

    public function get()
    {

        $desc = t('This app allows you to connect to everybody in a pre-defined ActivityPub collection or CSV file, such as follower/following lists. Install the app and revisit this page to input the source URL.');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        if (!local_channel()) {
            return login();
        }


        if (!Apps::addon_app_installed(local_channel(), 'followlist')) {
            return $text;
        }

        $max_records = get_config('system', 'max_imported_channels', 1000);

        // check service class limits
        $total_channels = 0;
        $r = q("select count(*) as total from abook where abook_channel = %d and abook_self = 0 ",
            intval(local_channel())
        );
        if ($r) {
            $total_channels = $r[0]['total'];
            $sc = ServiceClass::fetch(local_channel(), 'total_channels');
            if ($sc !== false) {
                $allowed = intval($sc) - $total_channels;
                if ($allowed < $max_records) {
                    $max_records = $allowed;
                }
            }
        }

        return replace_macros(Theme::get_template('followlist.tpl', 'addon/followlist'), [
            '$page_title' => t('Followlist'),
            '$limits' => sprintf(t('You may import up to %d records'), $max_records),
            '$form_security_token' => get_form_security_token("followlist"),
            '$disabled' => (($total_channels > $max_records) ? ' disabled="disabled" ' : EMPTY_STR),
            '$notes' => t('Enter the URL of an ActivityPub followers/following collection to import'),
            '$upload' => t('Or upload an ActivityPub or CSV followers file from your device'),
            '$url' => ['url', t('URL of followers/following list'), '', ''],
            '$submit' => t('Submit')
        ]);

    }

    protected function fetchFromFile($src, $maxRecords)
    {
        $actors = [];
        $lines = file($src);
        if ($lines) {
            array_shift($lines);
        }
        if ($lines) {
            foreach ($lines as $line) {
                $csv = str_getcsv($line);
                if ($csv) {
                    $actors[] = $csv[0];
                }
            }
        }
        return array_slice($actors, 0, $maxRecords);
    }

    protected function fetchFromURL($src)
    {
        return json_decode(file_get_contents($src), true);
    }


}