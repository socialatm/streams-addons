<?php
// With rare exception, controllers use the 'Code\Module' namespace and extend the Code\Web\Controller class
namespace Code\Module;

use Code\Web\Controller;

/* Autoload the Apps code */
use Code\Lib\Apps;

class Randplace extends Controller {

    function get() {

        if (! local_channel()) {
            return;
        }

        /* We are also going to create an 'app'. If it has not yet been installed, visiting https://{yoursite}/randplace should return a description
         * of the app. t is a translation function. It is passed English text and returns text in the browser language (if available).  
         */

        $desc = t('This app (if installed) provides a random post location on your submitted posts, taken from a list of world cities');

        if (! Apps::addon_app_installed(local_channel(), 'randplace')) {
            return $desc . '<br>' . t('This app is not currently installed');
		}
        return $desc . '<br>' . t('This app has been installed.');
    }
}

