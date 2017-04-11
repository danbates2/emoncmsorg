<?php
    /*

    All Emoncms code is released under the GNU Affero General Public License.
    See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org

    */
    
    // =======
    $IQid = 3;
    // =======

    define('EMONCMS_EXEC', 1);

    // Load common settings
    require "/home/username/scripts/script-settings.php";
    
    // Set error log location
    ini_set("error_log", "$log_location/inputqueue-error-$IQid.log");

    // Script lock, only one instance of the input queue can run at one time
    $fp = fopen("inputqueue$IQid-lock", "w");
    if (! flock($fp, LOCK_EX | LOCK_NB)) { echo "Already running\n"; die; }

    // Set document root
    chdir($emoncms_root);

    require "process_settings.php";
    require "Lib/EmonLogger.php";
   
    error_log("Start of error log file");
    
    $mysqli = new mysqli($server,$username,$password,$database);

    $redis = new Redis();
    $connected = $redis->connect($redis_server);

    require("Modules/user/user_model.php");
    $user = new User($mysqli,$redis,null);

    include "Modules/feed/feed_model.php";
    $feed = new Feed($mysqli,$redis, $feed_settings);

    require "Modules/input/input_model.php"; // 295
    $input = new Input($mysqli,$redis, $feed);

    require "Modules/input/process_model.php"; // 886
    $process = new Process($mysqli,$input,$feed);

    $rn = 0;
    $ltime = time();
    $usleep = 1000;
    
    $inputlimiter = array();
    
    while(true)
    {
        if ((time()-$ltime)>=1)
        {
            $ltime = time();
            $buflength = $redis->llen("inputbuffer$IQid");
            $usleep = (int) $redis->get("usleep:$IQid");
            if ($usleep<0) $usleep = 0;
            echo "Buffer length: ".$buflength." ".$usleep." ".$rn."\n";
            $redis->incrby("queue$IQid:rn",$rn); $rn = 0;

            if ($redis->get("stopinputqueue$IQid")==1) {
              $redis->set("stopinputqueue$IQid",0);
              die;
            }
        }

        // check if there is an item in the queue to process
        $line_str = false;
        
        if ($redis->llen("inputbuffer$IQid")>0)
        {
            // check if there is an item in the queue to process
            $line_str = $redis->lpop("inputbuffer$IQid");
        }

        if ($line_str)
        {
            $wrkstart = microtime(true);
            $rn++;

            $packet = json_decode($line_str);
            $userid = $packet->userid;
            $time = $packet->time;
            $nodeid = $packet->nodeid;
            $data = $packet->data;
            
            // Load current user input meta data
            // It would be good to avoid repeated calls to this
            $dbinputs = $input->get_inputs($userid);

            $tmp = array();

            foreach ($data as $name => $value)
            {            
                if (!isset($dbinputs[$nodeid][$name])) {
                    $inputid = $input->create_input($userid, $nodeid, $name);
                    if ($inputid>0) {
                        $dbinputs[$nodeid][$name] = array('id'=>$inputid);
                        $input->set_timevalue($dbinputs[$nodeid][$name]['id'],$time,$value);
                    }
                } else {
                    $inputid = $dbinputs[$nodeid][$name]['id'];
                    // Start of rate limiter
                    $lasttime = 0; if (isset($inputlimiter[$inputid])) $lasttime = $inputlimiter[$inputid];
                    if (($time-$lasttime)>=4)
                    {
                        $inputlimiter[$inputid] = $time;
                        $input->set_timevalue($dbinputs[$nodeid][$name]['id'],$time,$value);
                        if ($dbinputs[$nodeid][$name]['processList']) {
                            $tmp[] = array('value'=>$value,'processList'=>$dbinputs[$nodeid][$name]['processList']);
                        }
                    } else { 
                        //error_log("Error: input $userid $inputid $time - $lasttime posting too fast, dropped"); 
                    }
                }
            }
            
            foreach ($tmp as $i) $process->input($time,$i['value'],$i['processList']);
            
            $wrktime = (int)((microtime(true) - $wrkstart)*1000000);
            if ($wrktime>100000) error_log("$userid, $nodeid, $wrktime");
            
        }
        usleep($usleep);
    }
