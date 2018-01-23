<?php

    /*

    All Emoncms code is released under the GNU Affero General Public License.
    See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org

    */
    
    
       
    $emoncms_version = "";

    $ltime = microtime(true);

    define('EMONCMS_EXEC', 1);

    require "process_settings.php";
    require "core.php";
    require "route.php";
    require "param.php";

    $apikey = false;
    if (isset($_GET['apikey'])) {
        $apikey = $_GET['apikey'];
    } else if (isset($_POST['apikey'])) {
        $apikey = $_POST['apikey'];
    } else if (isset($_SERVER["HTTP_AUTHORIZATION"])) {
        // Support passing apikey on Authorization header per rfc6750, like example:
        //      GET /resource HTTP/1.1
        //      Host: server.example.com
        //      Authorization: Bearer THE_API_KEY_HERE
        
        if (isset($_SERVER["CONTENT_TYPE"]) && $_SERVER["CONTENT_TYPE"]=="aes128cbc") {
            // If content_type is AES128CBC
        } else {
            $apikey = str_replace('Bearer ', '', $_SERVER["HTTP_AUTHORIZATION"]);
        }
    }
    
    /*
    if ($apikey) {
        // Only allow alphanumeric characters
        if (preg_replace('/[^\w]/','',$apikey)!=$apikey) $apikey = false;
        // Only allow 32 character length
        if (strlen($apikey)!=32) $apikey = false;
    }*/

    // UNCOMMENT TO PUT THE SITE OFFLINE!!!!!!!!!
/*
    if ($apikey) {
        echo "ok"; die;
    } else if(isset($_SESSION['admin'])) {

    } else {
        //echo file_get_contents("offline.html");
        //die;
    }
*/
    $redis = new Redis();
    $connected = $redis->connect($redis_server);
    if (!$connected) {
        echo "Can't connect to redis database"; die;
    }
    $redis->incr('fiveseconds:totalhits');


    // API corrections
    if (isset($_GET['q'])) {
        if ($_GET['q']=="api/post.json") $_GET['q'] = "input/post";
        else if ($_GET['q']=="api/post") $_GET['q'] = "input/post";
        else if ($_GET['q']=="emoncms/input/post.json") $_GET['q'] = "input/post";
        else if ($_GET['q']=="emoncms/input/bulk.json") $_GET['q'] = "input/bulk";
    }
    // 5) Get route and load controller
    $route = new Route(get('q'), server('DOCUMENT_ROOT'), server('REQUEST_METHOD'));
    
    // Load get/post/encrypted parameters - only used by input/post and input/bulk API's
    $session = false;
    $param = new Param($route,$redis);
    
    $iplog = false;
    $timelog = false;

    if (isset($_GET['q']) && $apikey!==false) {
    
        if ($_GET['q']=="input/post.json") $_GET['q'] = "input/post";
        else if ($_GET['q']=="input/bulk.json") $_GET['q'] = "input/bulk";

        if ($_GET['q']=="input/post") {
            //echo "ok"; die;
            $userid = $redis->get("writeapikey:$apikey");
            if ($userid!==false) {
                if ($iplog) $redis->incr("iplog:u:$userid:p");
                require "Modules/input/input_methods.php";
                $inputMethods = new InputMethods($redis);
                $result = $inputMethods->post($userid);
                // if ($result!="ok") apierrorlog("post: ".$userid." ".$result." ".json_encode($_GET));
                header('Content-Type: application/json');
                print $result;
                if ($timelog) logrequest();
                die;
            }
        }

        else if ($_GET['q']=="input/bulk") {
            //echo "ok"; die;
            $userid = $redis->get("writeapikey:$apikey");
            if ($userid!==false) {
                if ($iplog) $redis->incr("iplog:u:$userid:b");
                require "Modules/input/input_methods.php";
                $inputMethods = new InputMethods($redis);
                $result = $inputMethods->bulk($userid);
                // if ($result!="ok") apierrorlog("post: ".$userid." ".$result." ".json_encode($_GET));
                header('Content-Type: application/json');
                print $result;
                if ($timelog) logrequest();
                die;
            }
        }

        else if ($_GET['q']=="feed/list.json") {
            //echo "ok"; die;
            $userid = 0;
            if ($redis->exists("writeapikey:$apikey")) { $userid = $redis->get("writeapikey:$apikey"); }
            else if ($redis->exists("readapikey:$apikey")) { $userid = $redis->get("readapikey:$apikey"); }

            if ($userid>0) {

                $feeds = array();
                $feedids = $redis->sMembers("user:feeds:$userid");
                foreach ($feedids as $id)
                {
                    $row = $redis->hGetAll("feed:$id");
                    $lastvalue = $redis->hmget("feed:lastvalue:$id",array('time','value'));
                    $row['time'] = strtotime($lastvalue['time']);
                    $row['value'] = $lastvalue['value'];
                    $feeds[] = $row;
                }
                header('Content-Type: application/json');
                print json_encode($feeds);
                if ($iplog) $redis->incr("iplog:u:$userid:r");
                if ($timelog) logrequest();
                die;
            }
        }
        else if ($_GET['q']=="feed/fetch.json") {
            //echo "ok"; die;
            $userid = 0;
            if ($redis->exists("writeapikey:$apikey")) { $userid = $redis->get("writeapikey:$apikey"); }
            else if ($redis->exists("readapikey:$apikey")) { $userid = $redis->get("readapikey:$apikey"); }

            if ($userid>0) {
                if (isset($_GET['ids'])) {
                    $feedids = (array) (explode(",",($_GET['ids'])));
                    $feeds = array();
                    for ($i=0; $i<count($feedids); $i++) {
                        $feedid = (int) $feedids[$i];
                        $feeds[$i] = false;
                        if ($redis->exists("feed:$feedid")) {
                            $fuid = (int) $redis->hget("feed:$feedid","userid");
                            if ($userid==$fuid) {
                                $feeds[$i] = 1 * $redis->hget("feed:lastvalue:$feedid",'value');
                            }
                        }
                    }
                    header('Content-Type: application/json');
                    print json_encode($feeds);
                    if ($iplog) $redis->incr("iplog:u:$userid:f");
                    if ($timelog) logrequest();
                }
                die;
            }
        }
        // -------------------
    }

    if ($https_enable == true){
        if (!isset($_GET['q']) || $_GET['q']=="" || $_GET['q']=="user/login") {
            if(!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == "") {
                // $redis->incr("httpsredirects");
                $redirect = "https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
                // header("HTTP/1.1 301 Moved Permanently");
                header("Location: $redirect");
                die;
            }
        }
    }

    $path = get_application_path();
    
    require "Lib/EmonLogger.php";

    // 2) Database
    $mysqli = @new mysqli($server,$username,$password,$database);

    if ( $mysqli->connect_error ) {
        echo "Can't connect to database, please verify credentials/configuration in settings.php<br />";
        if ( $display_errors ) {
            echo "Error message: <b>" . $mysqli->connect_error . "</b>";
        }
        die();
    }

    if (!$mysqli->connect_error && $dbtest==true) {
        require "Lib/dbschemasetup.php";
        if (!db_check($mysqli,$database)) db_schema_setup($mysqli,load_db_schema(),true);
    }

    // 3) User sessions
    require "Modules/user/rememberme_model.php";
    $rememberme = new Rememberme($mysqli);

    require("Modules/user/user_model.php");
    $user = new User($mysqli,$redis,$rememberme);

    if ($apikey) {
        $session = $user->apikey_session($apikey);
        if (empty($session)) {
              header($_SERVER["SERVER_PROTOCOL"]." 401 Unauthorized");
              header('WWW-Authenticate: Bearer realm="API KEY", error="invalid_apikey", error_description="Invalid API key"');
              print "Invalid API key";
              // $log = new EmonLogger(__FILE__);
              // $log->error("Invalid API key '" . $apikey. "'");
              if ($iplog) $redis->incr("iplog:ip:".getenv("REMOTE_ADDR"));
              exit();
        }
    } else {
        if ($session===false) $session = $user->emon_session_start();
    }

    if (get('embed')==1) $embed = 1; else $embed = 0;

    // If no route specified use defaults
    if (!$route->controller && !$route->action)
    {
        // Non authenticated defaults
        if (!isset($session['read']) || (isset($session['read']) && !$session['read']))
        {
            $route->controller = $default_controller;
            $route->action = $default_action;
            $route->subaction = "";
        }
        else // Authenticated defaults
        {
            if (isset($session["startingpage"]) && $session["startingpage"]!="") {
                header('Location: '.$session["startingpage"]);
                die;
            } else {
                // Authenticated defaults
                $route->controller = $default_controller_auth;
                $route->action = $default_action_auth;
                $route->subaction = "";
            }
        }
    }

    // 6) Load the main page controller
    $output = controller($route->controller);

    // If no controller of this name - then try username
    // need to actually test if there isnt a controller rather than if no content
    // is returned from the controller.
    if ($output['content'] == "#UNDEFINED#" && $public_profile_enabled && $route->controller!='admin')
    {
        $userid = $user->get_id($route->controller);
        if ($userid) {
            $route->subaction = $route->action;
            $session['userid'] = $userid;
            $session['username'] = $route->controller;
            $session['read'] = 1;
            $session['profile'] = 1;
            $route->controller = $public_profile_controller;
            $route->action = $public_profile_action;
            
            $output = controller($route->controller);
            
            if ($output["content"]=="" && $route->subaction=="graph") {
                $route->controller = "graph";
                $route->action = "";
                $_GET['userid'] = $userid;
                $output = controller($route->controller);
            }
        }
    }

    // If no controller found or nothing is returned, give friendly error
    if ($output['content'] === "#UNDEFINED#") {
        header($_SERVER["SERVER_PROTOCOL"]." 406 Not Acceptable");
        $output['content'] = "URI not acceptable. No controller '" . $route->controller . "'. (" . $route->action . "/" . $route->subaction .")";
    }

    // If not authenticated and no ouput, asks for login
    if ($output['content'] == "" && (!isset($session['read']) || (isset($session['read']) && !$session['read']))) {

        if(!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == ""){
            // $redis->incr("httpsredirects");
            $redirect = $path."/user/login";
            header("Location: $redirect");
            die;
        } else {
            // $redis->incr("httpsredirects:here");
            $route->controller = "user";
            $route->action = "login";
            $route->subaction = "";
            $output = controller($route->controller);
        }
    }

    if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != ""){
        $redis->incr("httpshits");
    }

    if (!$session || $session["userid"]==0) {
        if ($iplog) $redis->incr("iplog:ip:".getenv("REMOTE_ADDR"));
    } else {
        if ($iplog) $redis->incr("iplog:u:".$session["userid"]);
    }
    // $mysqli->close();
    $redis->close();

    $output['route'] = $route;
    $output['session'] = $session;

    $theme = "basic";

    // 7) Output
    if ($route->format == 'json')
    {
        header('Content-Type: application/json');
        if ($route->controller=='time') {
            print $output['content'];
        } elseif ($route->controller=='input' && $route->action=='post') {
            print $output['content'];
        } elseif ($route->controller=='input' && $route->action=='bulk') {
            print $output['content'];
        } else {
            print json_encode($output['content']);
        }
    }
    else if ($route->format == 'html')
    {
        // Select the theme
        $themeDir = "Theme/" . $theme . "/";
        if ($embed == 1) {
            print view($themeDir . "embed.php", $output);
        } else {
            $menu = load_menu();
            $output['mainmenu'] = view($themeDir . "menu_view.php", array());
            print view($themeDir . "theme.php", $output);
        }
    }
    else if ($route->format == 'text')
    {
        header('Content-Type: text');
        print $output['content'];
    }
    else if ($route->format == 'text/plain')
    {
        header('Content-Type: text/plain');
        print $output['content'];
    }
    else {
        header($_SERVER["SERVER_PROTOCOL"]." 406 Not Acceptable");
        print "URI not acceptable. Unknown format '".$route->format."'.";
    }
    
    if ($timelog) logrequest();
    
    function logrequest() {
        global $ltime;
        $fh = fopen("/home/trystan/emoncms.log","a");
        $t = round((microtime(true)-$ltime)*1000000);
        
        $seconds = floor($t / 1000000);
        if ($seconds==0) $seconds = "";
        
        
        fwrite($fh,time()."\t$seconds\t$t\t".$_GET['q']."\n");
        fclose($fh);
    }
    
    function apierrorlog($error) {
        //$fh = fopen("/home/username/apierror.log","a");
        //fwrite($fh,$error."\n");
        //fclose($fh);
    }
    
    //if (isset($session['userid'])) {
    //    $redis->incr("user:postrate:".$session['userid']);
    //}
