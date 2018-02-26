<?php

/*

All Emoncms code is released under the GNU Affero General Public License.
See COPYRIGHT.txt and LICENSE.txt.

---------------------------------------------------------------------
Emoncms - open source energy visualisation
Part of the OpenEnergyMonitor project:
http://openenergymonitor.org

*/

define('EMONCMS_EXEC', 1);

$redis = new Redis();
$redis_server = '/var/run/redis/redis.sock';
$connected = $redis->connect($redis_server);
if (!$connected) die;

error_reporting(E_ALL);
ini_set('display_errors', 'on');

$fp = fopen("storageserver0lock", "w");
if (! flock($fp, LOCK_EX | LOCK_NB)) { echo "Already running\n"; die; }

require "/etc/emoncms/script-settings.php";
chdir($emoncms_root);

require "process_settings.php";
$logger = new EmonLogger();

require "Modules/feed/engine/PHPFina.php";
require "Modules/feed/engine/PHPTimeSeries.php";
$phpfina = new PHPFina($feed_settings['phpfina']);
$phptimeseries = new PHPTimeSeries($feed_settings['phpfina']);

$usleep = 0;
$ltime = time();

$count = 0;

while(true)
{

    if ((time()-$ltime)>1) {
        $ltime = time();
        
        $usleep = (int) $redis->get('SS0:usleep');
        if ($usleep<0) $usleep = 0;
        
        if ($redis->get('storageserver0-stop')==1) {
            $redis->set('storageserver0-stop',0);
            die;
        }  
        $redis->incr("storageserver0-count",$count);
        $redis->incr("SS0:count",$count);
        $count = 0;
    }

    if ($redis->llen('feedpostqueue:0')>10)
    {
        for ($i=0; $i<10; $i++)
        {
            $line = $redis->lpop("feedpostqueue:0");
            $count ++;
            
            $d = explode(",",$line);

            if (count($d)==5) {
                $id = (int) $d[0];
                $time = (int) $d[1];
                $value = (float) $d[2];
                $engine = (int) $d[3][0];
                $padding_mode = (int) $d[4][0];
                
                // print "$id,$time,$value,$engine,$padding_mode\n";
                
                if ($engine==5) {
                    if ($padding_mode==1) $phpfina->padding_mode = "join";
                    $phpfina->post($id,$time,$value);
                    if ($padding_mode==1) $phpfina->padding_mode = "nan";
                }
                
                if ($engine==2) {
                    $phptimeseries->post($id,$time,$value);
                }
            }
        }
    }
    
    usleep($usleep);
}


class EmonLogger
{
    public function __construct()
    {
    
    }

    public function info ($message){
        // print $message."\n";
    }
    
    public function warn ($message){
        print $message."\n";
    }
}
