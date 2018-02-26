<?php

/*
    All Emoncms code is released under the GNU Affero General Public License.
    See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org

*/

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function user_controller()
{
    global $mysqli, $user, $path, $session, $route ,$allowusersregister, $email_verification;

    $result = false;

    // Load html,css,js pages to the client
    if ($route->format == 'html')
    {
        if ($route->action == 'login' && !$session['read']) $result = view("Modules/user/login_block.php", array('verify'=>array()));
        
        if ($route->action == 'view' && $session['write']) {
            $result = view("Modules/user/profile/profile.php", array());
        }
        
        if ($route->action == 'logout' && $session['read']) {$user->logout(); header('Location: '.$path);}

        if ($route->action == 'verify' && $email_verification && isset($_GET['email']) && isset($_GET['key'])) {
            if (!$session['read']) {
                $verify = $user->verify_email($_GET['email'], $_GET['key']);
                $result = view("Modules/user/login_block.php", array('allowusersregister'=>$allowusersregister,'verify'=>$verify));
            } else if ($session['write']) {
                global $verify;
                $verify = $user->verify_email($_GET['email'], $_GET['key']);
                
                if ($verify['success']) {
                    $session['emailverified'] = 1;
                    $_SESSION['emailverified'] = 1;
                }
                
                $result = view("Modules/user/profile/profile.php", array());
            }
        }
    }

    // JSON API
    if ($route->format == 'json')
    {
        // Core session
        if ($route->action == 'login' && !$session['read']) $result = $user->login(post('username'),post('password'),post('rememberme'));
        if ($route->action == 'register' && $allowusersregister) $result = $user->register(post('username'),post('password'),post('email'));
        if ($route->action == 'logout' && $session['read']) $user->logout();
        
        if ($route->action == 'resend-verify' && $email_verification) $result = $user->send_verification_email(get('username'));

        if ($route->action == 'changeusername' && $session['write']) $result = $user->change_username($session['userid'],get('username'));
        if ($route->action == 'changeemail' && $session['write']) $result = $user->change_email($session['userid'],get('email'));
        if ($route->action == 'changepassword' && $session['write']) $result = $user->change_password($session['userid'],get('old'),get('new'));
        
        if ($route->action == 'passwordreset') $result = $user->passwordreset(get('username'),get('email'));
        // Apikey
        if ($route->action == 'newapikeyread' && $session['write']) $result = $user->new_apikey_read($session['userid']);
        if ($route->action == 'newapikeywrite' && $session['write']) $result = $user->new_apikey_write($session['userid']);

        if ($route->action == 'auth' && !$session['read']) $result = $user->get_apikeys_from_login(post('username'),post('password'));

        // Get and set - user by profile client
        if ($route->action == 'get' && $session['write']) $result = $user->get($session['userid']);
        if ($route->action == 'set' && $session['write']) $result = $user->set($session['userid'],json_decode(post('data')));

        if ($route->action == 'timezone' && $session['read']) $result = $user->get_timezone($session['userid']);
        if ($route->action == 'gettimezones' && $session['read']) $result = $user->get_timezones();
    }

    return array('content'=>$result);
}
