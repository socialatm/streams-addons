<?php

namespace Code\Module;

use App;
use Code\Lib\Apps;
use Code\Web\HTTPSig;
use Code\Web\Controller;
use Code\Lib\Channel;
use Code\Lib\Url;
use Code\Render\Theme;

class Content_import extends Controller {


    function post() {

        if(! local_channel())
            return;

        $channel = App::get_channel();

        $hz_server = $_REQUEST['hz_server'];
        $force = intval($_REQUEST['force']);
    
        if(strpos($hz_server,'http') !== 0) {
            $hz_server = 'https://' . $hz_server;
        }

        if(! $hz_server) {
            notice(t('No server specified') . EOL);
            return;
        }

        $m = parse_url($hz_server);

        // The API will convert these to UTC.

        $since = datetime_convert(date_default_timezone_get(),date_default_timezone_get(),$_REQUEST['since']);
        $until = datetime_convert(date_default_timezone_get(),date_default_timezone_get(),$_REQUEST['until']);

        $poll_interval = get_config('system','poll_interval',3);

        if(intval($_REQUEST['items'])) {
            $page = 0;

            while(1) {
                $headers = [ 
                    'X-API-Token'      => random_string(),
                    'X-API-Request'    => $hz_server . '/api/z/1.0/item/export_page?f=&zap_compat=1&since=' . urlencode($since) . '&until=' . urlencode($until) . '&page=' . $page ,
                    'Host'             => $m['host'],
                    '(request-target)' => 'get /api/z/1.0/item/export_page?f=&zap_compat=1&since=' . urlencode($since) . '&until=' . urlencode($until) . '&page=' . $page ,
                ];

                $headers = HTTPSig::create_sig($headers,$channel['channel_prvkey'], Channel::url($channel),true,'sha512');

                $x = Url::get($hz_server . '/api/z/1.0/item/export_page?f=&zap_compat=1&since=' . urlencode($since)
                    . '&until=' . urlencode($until) . '&page=' . $page, [ 'headers' => $headers ]);

                logger('page: ' . $page);
                if(! $x['success']) {
                    logger('no API response');
                    break;
                }

                $j = json_decode($x['body'],true);

                if(! (isset($j['item']) && is_array($j['item']) && count($j['item'])))
                    break;

                proc_run('php','addon/content_import/item_import_helper.php', sprintf('%d',$page), $since, $until, $channel['channel_address'], urlencode($hz_server));
                sleep($poll_interval);

                $page ++;
            }
            logger('post_import: complete');
            notice(t('Posts imported') . EOL);

        }

        if(intval($_REQUEST['files'])) {

            $headers = [ 
                'X-API-Token'      => random_string(),
                'X-API-Request'    => $hz_server . '/api/z/1.0/files?f=&zap_compat=1&since=' . urlencode($since) . '&until=' . urlencode($until),
                'Host'             => $m['host'],
                '(request-target)' => 'get /api/z/1.0/files?f=&zap_compat=1&since=' . urlencode($since) . '&until=' . urlencode($until),
            ];

            $headers = HTTPSig::create_sig($headers,$channel['channel_prvkey'], Channel::url($channel),true,'sha512');

            $x = Url::get($hz_server . '/api/z/1.0/files?f=&zap_compat=1&since=' . urlencode($since) . '&until=' . urlencode($until), [ 'headers' => $headers ]);

            if(! $x['success']) {
                logger('no API response');
                return;
            }

            $poll_interval = get_config('system','poll_interval',3);

            $j = json_decode($x['body'],true);
  
            if(! $j['success']) 
                return;

            if(isset($j) && is_array($j['results']) && count($j['results'])) {
                foreach($j['results'] as $jj) {
                    if (! $force) {
                        $r = q("select hash from attach where hash = '%s' and uid = %d",
                            dbesc($jj['hash']),
                            intval($channel['channel_id'])
                        );
                        if ($r) {
                            continue;
                        }
                    }
                    proc_run('php','addon/content_import/file_import_helper.php',$jj['hash'], $channel['channel_address'], urlencode($hz_server));
                    sleep($poll_interval);
                }
            }

            notice(t('Files imported') . EOL);
        }

    }


    function get() {


        $desc = t('This addon app copies existing content and file storage to a cloned/copied channel. Once the app is installed, visit the newly installed app. This will allow you to set the location of your original channel and an optional date range of files/conversations to copy.'); 

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        if(! ( local_channel() && Apps::addon_app_installed(local_channel(),'content_import'))) { 
            return $text;
        }

        if(! local_channel()) {
            return login();
        }

        $o = replace_macros(Theme::get_template('content_import.tpl','addon/content_import'), [
            '$header' => t('Content Import'),
            '$desc' => t('This will import all your conversations and cloud files from a cloned channel on another server. This may take a while if you have lots of posts and or files.'),
            '$items' => [ 'items', t('Include posts'), true, t('Conversations, Articles, Cards, and other posted content'), [ t('No'),t('Yes') ]],
            '$files' => [ 'files', t('Include files'), true, t('Files, Photos and other cloud storage'), [ t('No'),t('Yes') ]],
            '$force' => [ 'force', t('Force file updates'), true, t('Otherwise ignore files that have already been imported'), [ t('No'),t('Yes') ]],
            '$fr_server' => ['hz_server', t('Original Server base URL'),'',''],
            '$since' => ['since', t('Since modified date yyyy-mm-dd'),'0001-01-01 00:00:00',''],
            '$until' => ['until', t('Until modified date yyyy-mm-dd'),datetime_convert('UTC',date_default_timezone_get(),'now'),''],
            '$submit' => t('Submit'),
        ]);
        return $o;
    }
}
