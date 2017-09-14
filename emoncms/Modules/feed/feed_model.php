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

class Feed
{
    private $mysqli;
    private $redis;
    public $engine;
    private $csvdownloadlimit_mb = 10;
    private $log;
    
    private $max_npoints_returned = 1825;

    public function __construct($mysqli,$redis,$settings)
    {        
        $this->mysqli = $mysqli;
        $this->redis = $redis;
        $this->log = new EmonLogger(__FILE__);
        
        // Load different storage engines
        require "Modules/feed/engine/PHPTimeSeries.php";
        require "Modules/feed/engine/PHPFina.php";
        require "Modules/feed/engine/service/PHPFina.php";
           
        // Backwards compatibility 
        if (!isset($settings)) $settings= array();
        if (!isset($settings['phpfina'])) $settings['phpfina'] = array();
        if (!isset($settings['phptimeseries'])) $settings['phptimeseries'] = array();
              
        // Load engine instances to engine array to make selection below easier
        $this->server = array();
        $this->server[0] = array();
        $this->server[0][Engine::PHPTIMESERIES] = new PHPTimeSeries($settings['phptimeseries']);
        $this->server[0][Engine::PHPFINA] = new PHPFina($settings['phpfina']);
        
        $this->server[1] = array();
        $this->server[1][Engine::PHPFINA] = new RemotePHPFina("http://localhost:8080/phpfina/");

        $this->server[2] = array();
        $this->server[2][Engine::PHPFINA] = new RemotePHPFina("http://localhost:8082/phpfina/");
                       
        
        if (isset($settings['csvdownloadlimit_mb'])) {
            $this->csvdownloadlimit_mb = $settings['csvdownloadlimit_mb']; 
        }
        
        if (isset($settings['max_npoints_returned'])) {
            $this->max_npoints_returned = $settings['max_npoints_returned'];
        }
    }

    public function create($userid,$name,$datatype,$engine,$options_in,$server)
    {   
        $userid = (int) $userid;
        $name = preg_replace('/[^\w\s-:]/','',$name);
        $datatype = (int) $datatype;
        $engine = (int) $engine;
        
        $server = (int) $server;
        if ($server<0 || $server>2) return array('success'=>false, 'message'=>'server must be 0, 1 or 2');
        
        if ($datatype!=1 && $datatype!=2) return array('success'=>false, 'message'=>'missing datatype: 1: realtime, 2: daily');
        if ($datatype==3) return array('success'=>false, 'message'=>'histogram feed type is not available');
        
        if ($engine==0) return array('success'=>false, 'message'=>'mysql feed engine is not available');
        if ($engine==1) return array('success'=>false, 'message'=>'timestore feed engine is not available');
        if ($engine==3) return array('success'=>false, 'message'=>'graphite feed engine is not available');
        if ($engine==4) return array('success'=>false, 'message'=>'phptimestore feed engine is not available');
        if ($engine==6) return array('success'=>false, 'message'=>'PHPFiwa feed engine is not available');
        
        // If feed of given name by the user already exists
        $feedid = $this->get_id($userid,$name);
        if ($feedid!=0) return array('success'=>false, 'message'=>'feed already exists');
        
        $result = $this->mysqli->query("INSERT INTO feeds (userid,name,datatype,public,engine,server) VALUES ('$userid','$name','$datatype',false,'$engine','$server')");
        $feedid = $this->mysqli->insert_id;

        if ($feedid>0)
        {
            $this->redis->sAdd("user:feeds:$userid", $feedid);
            $this->redis->hMSet("feed:$feedid",array(
                'id'=>$feedid,
                'userid'=>$userid,
                'name'=>$name,
                'datatype'=>$datatype,
                'tag'=>'',
                'public'=>false,
                'size'=>0,
                'engine'=>$engine,
                'server'=>$server
            ));
            
            $options = array();
            if ($engine==Engine::PHPFINA) $options['interval'] = (int) $options_in->interval;
            if ($engine==Engine::PHPFIWA) $options['interval'] = (int) $options_in->interval;
            
            $engineresult = false;
            if ($datatype==DataType::HISTOGRAM) {
                $engineresult = $this->histogram->create($feedid,$options);
            } else {
                $engineresult = $this->server[$server][$engine]->create($feedid,$options);
            }

            if ($engineresult == false)
            {
                $this->log->warn("Feed model: failed to create feed model feedid=$feedid");
                // Feed engine creation failed so we need to delete the meta entry for the feed
                
                $this->mysqli->query("DELETE FROM feeds WHERE `id` = '$feedid'");

                $userid = $this->redis->hget("feed:$feedid",'userid');
                $this->redis->del("feed:$feedid");
                $this->redis->srem("user:feeds:$userid",$feedid);

                return array('success'=>false, 'message'=>"");
            }

            return array('success'=>true, 'feedid'=>$feedid, 'result'=>$engineresult);
        } else return array('success'=>false);
    }

    public function exist($id)
    {
        $feedexist = false;

        if (!$this->redis->exists("feed:$id")) {
            if ($this->load_feed_to_redis($id))
            {
                $feedexist = true;
            }
        } else {
            $feedexist = true;
        }
        return $feedexist;
    }

    public function get_id($userid,$name)
    {
        $userid = intval($userid);
        $name = preg_replace('/[^\w\s-:]/','',$name);
        $result = $this->mysqli->query("SELECT id FROM feeds WHERE userid = '$userid' AND name = '$name'");
        if ($result->num_rows>0) { $row = $result->fetch_array(); return $row['id']; } else return false;
    }
    /*

    User Feed lists

    Returns a specified user's feedlist in different forms:
    get_user_feeds: 	all the feeds table data
    get_user_feed_ids: 	only the id's
    get_user_feed_names: 	id's and names

    */

    public function get_user_feeds($userid)
    {
        $userid = (int) $userid;
        $feeds = $this->redis_get_user_feeds($userid);
        return $feeds;
    }
    
    public function get_user_public_feeds($userid)
    {
        $feeds = $this->get_user_feeds($userid);
        $publicfeeds = array();
        foreach ($feeds as $feed) { if ($feed['public']) $publicfeeds[] = $feed; }
        return $publicfeeds;
    }
    
    public function redis_get_user_feeds($userid)
    {
        $userid = (int) $userid;
        if (!$this->redis->exists("user:feeds:$userid")) $this->load_to_redis($userid);
      
        $feeds = array();
        $feedids = $this->redis->sMembers("user:feeds:$userid");
        
        $pipe = $this->redis->multi(Redis::PIPELINE);
        foreach ($feedids as $id)
        {
            $this->redis->hGetAll("feed:$id");
            $this->redis->hmget("feed:lastvalue:$id",array('time','value'));
        }
        $result = $pipe->exec();
        
        for ($i=0; $i<count($result); $i+=2) {
            $row = $result[$i];
            $lastvalue = $result[$i+1];
            $row['time'] = strtotime($lastvalue['time']);
            $row['value'] = $lastvalue['value'];
            $feeds[] = $row;
        }
        
        return $feeds;
    }
    
    public function get_user_feeds_with_meta($userid)
    {
        $userid = (int) $userid;
        $feeds = $this->get_user_feeds($userid);
        for ($i=0; $i<count($feeds); $i++) {
            $id = $feeds[$i]["id"];
            if ($meta = $this->get_meta($id)) {
                foreach ($meta as $meta_key=>$meta_val) {
                    $feeds[$i][$meta_key] = $meta_val;
                }
            }
        }
        return $feeds;
    }
    
    public function mysql_get_user_feeds($userid)
    {
        $userid = (int) $userid;
        $feeds = array();
        $result = $this->mysqli->query("SELECT * FROM feeds WHERE `userid` = '$userid'");
        while ($row = (array)$result->fetch_object())
        {
            $row['time'] = strtotime($row['time']);
            $feeds[] = $row;
        }
        return $feeds;
    }
    
    public function get_user_feed_ids($userid)
    {
        $userid = (int) $userid;
        if (!$this->redis->exists("user:feeds:$userid")) $this->load_to_redis($userid);
        $feedids = $this->redis->sMembers("user:feeds:$userid");
        return $feedids;
    }

    /*

    Feeds table GET public functions

    */

    public function get($id)
    {
        $id = (int) $id;
        if (!$this->exist($id)) return array('success'=>false, 'message'=>'Feed does not exist');

        // Get from redis cache
        $row = $this->redis->hGetAll("feed:$id");
        $lastvalue = $this->redis->hmget("feed:lastvalue:$id",array('time','value'));
        $row['time'] = $lastvalue['time'];
        $row['value'] = $lastvalue['value'];

        return $row;
    }

    public function get_field($id,$field)
    {
        $id = (int) $id;
        if (!$this->exist($id)) return array('success'=>false, 'message'=>'Feed does not exist');

        if ($field!=NULL) // if the feed exists
        {
            $field = preg_replace('/[^\w\s-]/','',$field);
            $val = $this->redis->hget("feed:$id",$field);
            if ($val) return $val; else return 0;
        }
        else return array('success'=>false, 'message'=>'Missing field parameter');
    }

    public function get_timevalue($id)
    {
        $id = (int) $id;
        
        if ($this->redis->exists("feed:lastvalue:$id")) {
            $lastvalue = $this->redis->hmget("feed:lastvalue:$id",array('time','value'));
            
        } else {
            $lastvalue = $this->get_timevalue_from_data($id);
            
            if (!isset($lastvalue['time']) || !isset($lastvalue['value'])) {
                $this->log->warn("ERROR: Feed Model, No time or value for feed $id");
            }
            
            $this->redis->hMset("feed:lastvalue:$id", array(
                'value' => $lastvalue['value'], 
                'time' => $lastvalue['time']
            ));
        }

        return $lastvalue;
    }

    public function get_timevalue_seconds($id)
    {
        $lastvalue = $this->get_timevalue($id);
        if ($lastvalue['time']!=0) $lastvalue['time'] = strtotime($lastvalue['time']);
        return $lastvalue;
    }

    public function get_value($id)
    {
        $lastvalue = $this->get_timevalue($id);
        return $lastvalue['value'];
    }

    public function get_timevalue_from_data($feedid)
    {
        $feedid = (int) $feedid;
        if (!$this->exist($feedid)) return array('success'=>false, 'message'=>'Feed does not exist');

        $engine = $this->get_engine($feedid);
        $server = $this->get_server($feedid);
        
        if (isset($this->server[$server][$engine])) { 
            // Call to engine lastvalue method
            return $this->server[$server][$engine]->lastvalue($feedid);
        } else {
            return false;
        }
    }

    /*

    Feeds table SET public functions

    */

    public function set_feed_fields($id,$fields)
    {
        $id = (int) $id;
        if (!$this->exist($id)) return array('success'=>false, 'message'=>'Feed does not exist');

        $fields = json_decode(stripslashes($fields));

        $array = array();

        // Repeat this line changing the field name to add fields that can be updated:
        if (isset($fields->name)) $array[] = "`name` = '".preg_replace('/[^\w\s-:]/','',$fields->name)."'";
        if (isset($fields->tag)) $array[] = "`tag` = '".preg_replace('/[^\w\s-:]/','',$fields->tag)."'";
        if (isset($fields->public)) $array[] = "`public` = '".intval($fields->public)."'";

        // Convert to a comma seperated string for the mysql query
        $fieldstr = implode(",",$array);
        $this->mysqli->query("UPDATE feeds SET ".$fieldstr." WHERE `id` = '$id'");

        // Update redis
        if (isset($fields->name)) $this->redis->hset("feed:$id",'name',$fields->name);
        if (isset($fields->tag)) $this->redis->hset("feed:$id",'tag',$fields->tag);
        if (isset($fields->public)) $this->redis->hset("feed:$id",'public',$fields->public);

        if ($this->mysqli->affected_rows>0){
            return array('success'=>true, 'message'=>'Field updated');
        } else {
            return array('success'=>false, 'message'=>'Field could not be updated');
        }
    }

    /*

    Feed data public functions

    insert, update, get and specialist histogram public functions

    */

    public function insert_data($feedid,$updatetime,$feedtime,$value)
    {
        $feedid = (int) $feedid;
        if (!$this->exist($feedid)) return array('success'=>false, 'message'=>'Feed does not exist');

        if ($feedtime == null) $feedtime = time();
        $updatetime = intval($updatetime);
        $feedtime = intval($feedtime);
        $value = floatval($value);
        
        $res = $this->get_engine_and_server($feedid);
        $server = (int) $res['server']; $engine = $res['engine'];
        
        $this->set_timevalue($feedid, $value, $updatetime);
        
        // Call to engine post method
        $this->redis->rpush("feedpostqueue:$server","$feedid,$feedtime,$value,$engine,0");

        return $value;
    }
    
    public function insert_data_padding_mode($feedid,$updatetime,$feedtime,$value,$padding_mode)
    {
        $feedid = (int) $feedid;
        if (!$this->exist($feedid)) return array('success'=>false, 'message'=>'Feed does not exist');

        if ($feedtime == null) $feedtime = time();
        $updatetime = intval($updatetime);
        $feedtime = intval($feedtime);
        $value = floatval($value);

        $res = $this->get_engine_and_server($feedid);
        $server = (int) $res['server']; $engine = $res['engine'];
        
        $this->set_timevalue($feedid, $value, $updatetime);
        
        $pm = 0;
        if ($padding_mode=="join") $pm = 1;
        $this->redis->rpush("feedpostqueue:$server","$feedid,$feedtime,$value,$engine,$pm");
        
        return $value;
    }

    public function update_data($feedid,$updatetime,$feedtime,$value)
    {
        $feedid = (int) $feedid;
        if (!$this->exist($feedid)) return array('success'=>false, 'message'=>'Feed does not exist');

        if ($feedtime == null) $feedtime = time();
        $updatetime = intval($updatetime);
        $feedtime = intval($feedtime);
        $value = floatval($value);

        $res = $this->get_engine_and_server($feedid);
        $server = (int) $res['server']; $engine = $res['engine'];
        
        // Call to engine update method
        $this->redis->rpush("feedpostqueue:$server","$feedid,$feedtime,$value,$engine,0");
       
        // need to find a way to not update if value being updated is older than the last value
        // in the database, redis lastvalue is last update time rather than last datapoint time.
        // So maybe we need to store both in redis.

        if ($updatetime!=false) $this->set_timevalue($feedid, $value, $updatetime);
        return $value;
    }

    public function get_data($feedid,$start,$end,$outinterval,$skipmissing,$limitinterval,$backup=false)
    {
        $feedid = (int) $feedid;      
        if (!$this->exist($feedid)) return array('success'=>false, 'message'=>'Feed does not exist');
        $engine = $this->get_engine($feedid);
        $server = $this->get_server($feedid);
        return $this->server[$server][$engine]->get_data_new($feedid,$start,$end,$outinterval,$skipmissing,$limitinterval,$backup);
    }
    
    public function get_data_DMY($feedid,$start,$end,$mode)
    {
        $feedid = (int) $feedid;
        if ($end<=$start) return array('success'=>false, 'message'=>"Request end time before start time");
        if (!$this->exist($feedid)) return array('success'=>false, 'message'=>'Feed does not exist');
        $engine = $this->get_engine($feedid);
        $server = $this->get_server($feedid);
        if ($engine != Engine::PHPFINA && $engine != Engine::PHPTIMESERIES) return array('success'=>false, 'message'=>"This request is only supported by PHPFina AND PHPTimeseries");

        global $session;
        $userid = $this->get_field($feedid,"userid");
        $timezone = $this->get_user_timezone($userid);
        $data = $this->server[$server][$engine]->get_data_DMY($feedid,$start,$end,$mode,$timezone);
        return $data;
    }
    
    public function get_data_DMY_time_of_day($feedid,$start,$end,$mode,$split)
    {
        $feedid = (int) $feedid;
        if ($end<=$start) return array('success'=>false, 'message'=>"Request end time before start time");
        if (!$this->exist($feedid)) return array('success'=>false, 'message'=>'Feed does not exist');
        $engine = $this->get_engine($feedid);
        $server = $this->get_server($feedid);
        
        if ($engine != Engine::PHPFINA) return array('success'=>false, 'message'=>"This request is only supported by PHPFina");
        
        // Call to engine get_data
        global $session;
        $userid = $this->get_field($feedid,"userid");
        $timezone = $this->get_user_timezone($userid);
            
        $data = $this->server[$server][$engine]->get_data_DMY_time_of_day($feedid,$start,$end,$mode,$timezone,$split);
        return $data;
    }
    
    public function get_average($feedid,$start,$end,$outinterval)
    {
        $feedid = (int) $feedid;
        if ($end<=$start) return array('success'=>false, 'message'=>"Request end time before start time");
        if (!$this->exist($feedid)) return array('success'=>false, 'message'=>'Feed does not exist');
        $engine = $this->get_engine($feedid);
        $server = $this->get_server($feedid);
        
        if ($engine != Engine::PHPFINA) return array('success'=>false, 'message'=>"This request is only supported by PHPFina");
        
        $data = $this->server[$server][$engine]->get_average($feedid,$start,$end,$outinterval);
        return $data;
    }
    
    public function get_average_DMY($feedid,$start,$end,$mode)
    {
        $feedid = (int) $feedid;
        if ($end<=$start) return array('success'=>false, 'message'=>"Request end time before start time");
        if (!$this->exist($feedid)) return array('success'=>false, 'message'=>'Feed does not exist');
        $engine = $this->get_engine($feedid);
        $server = $this->get_server($feedid);
        if ($engine!=Engine::PHPFINA) return array('success'=>false, 'message'=>"This request is only supported by PHPFina");

        // Call to engine get_data
        global $session;
        $userid = $this->get_field($feedid,"userid");
        $timezone = $this->get_user_timezone($userid);
        
        $data = $this->server[$server][$engine]->get_average_DMY($feedid,$start,$end,$mode,$timezone);
        return $data;
    }
    
    public function csv_export($feedid,$start,$end,$outinterval,$datetimeformat)
    {
        $this->redis->incr("fiveseconds:exporthits");
        $feedid = (int) $feedid;
        $start = (int) $start;
        $end = (int) $end;
        $outinterval = (int) $outinterval;
        $datetimeformat = (int) $datetimeformat;
        
        if ($end<=$start) return array('success'=>false, 'message'=>"Request end time before start time");
        if (!$this->exist($feedid)) return array('success'=>false, 'message'=>'Feed does not exist');
        $engine = $this->get_engine($feedid);
        $server = $this->get_server($feedid);
        
        // Download limit
        $downloadsize = (($end - $start) / $outinterval) * 17; // 17 bytes per dp
        if ($downloadsize>(25*1048576)) {
            $this->log->warn("csv_export() CSV download limit exeeded downloadsize=$downloadsize feedid=$feedid");
            return array('success'=>false, 'message'=>"CSV download limit exeeded downloadsize=$downloadsize");
        }
        
        if ($datetimeformat == 1) {
            global $session;
            $userid = $this->get_field($feedid,"userid");
            $usertimezone = $this->get_user_timezone($userid);
        } else {
            $usertimezone = false;
        }

        // Call to engine get_average method
        return $this->server[$server][$engine]->csv_export($feedid,$start,$end,$outinterval,$usertimezone);
    }


    public function delete($feedid)
    {
        $feedid = (int) $feedid;
        if (!$this->exist($feedid)) return array('success'=>false, 'message'=>'Feed does not exist');

        $engine = $this->get_engine($feedid);
        $server = $this->get_server($feedid);
        
        // Call to engine delete method
        $this->server[$server][$engine]->delete($feedid);

        $this->mysqli->query("DELETE FROM feeds WHERE `id` = '$feedid'");

        $userid = $this->redis->hget("feed:$feedid",'userid');
        $this->redis->del("feed:$feedid");
        $this->redis->srem("user:feeds:$userid",$feedid);
    }

    public function update_user_feeds_size($userid)
    {
        $userid = (int) $userid;
        $total = 0;
        $result = $this->mysqli->query("SELECT id,engine FROM feeds WHERE `userid` = '$userid'");
        while ($row = $result->fetch_array())
        {
            $size = 0;
            $feedid = $row['id'];
            $engine = $row['engine'];
            
            // Call to engine get_feed_size method
            $server = $this->get_server($feedid);
            
            if (isset($this->server[$server][$engine])) {
            $size = $this->server[$server][$engine]->get_feed_size($feedid);
            }
            
            $this->mysqli->query("UPDATE feeds SET `size` = '$size' WHERE `id`= '$feedid'");
            $this->redis->hset("feed:$feedid",'size',$size);
            $total += $size;
        }
        
        $this->mysqli->query("UPDATE users SET `diskuse` = '$total' WHERE `id`= '$userid'");
        return $total;
    }
    
    public function get_feed_size($feedid) {
        $server = $this->get_server($feedid);
        $engine = $this->get_engine($feedid);
        return $this->server[$server][$engine]->get_feed_size($feedid);
    }

    public function get_meta($feedid) {
        $feedid = (int) $feedid;
        $engine = $this->get_engine($feedid);
        $server = $this->get_server($feedid);
        return $this->server[$server][$engine]->get_meta($feedid);
    }

    // PHPTimeSeries specific functions that we need to make available to the controller

    public function phptimeseries_export($feedid,$start) {
        $this->redis->incr("fiveseconds:exporthits");
        $server = $this->get_server($feedid);
        return $this->server[$server][Engine::PHPTIMESERIES]->export($feedid,$start);
    }
    
    public function phpfiwa_export($feedid,$start,$layer) {
        $server = $this->get_server($feedid);
        return $this->server[$server][Engine::PHPFIWA]->export($feedid,$start,$layer);
    }
    
    public function phpfina_export($feedid,$start) {
        $server = $this->get_server($feedid);
        return $this->server[$server][Engine::PHPFINA]->export($feedid,$start);
    }

    public function set_timevalue($feedid, $value, $time)
    {
        $updatetime = date("Y-n-j H:i:s", $time);
        $this->redis->hMset("feed:lastvalue:$feedid", array('value' => $value, 'time' => $updatetime));
    }
    
    private function get_engine($feedid)
    {
        return $this->redis->hget("feed:$feedid",'engine');
    }
    
    private function get_server($feedid)
    {
        return (int) $this->redis->hget("feed:$feedid",'server');
    }
    
    private function get_engine_and_server($feedid)
    {
        return $this->redis->hmget("feed:$feedid",array('engine','server'));
    }

    public function load_to_redis($userid)
    {
        $this->redis->incr("loadtoredis");
        $result = $this->mysqli->query("SELECT id,userid,name,datatype,tag,public,size,engine,server FROM feeds WHERE `userid` = '$userid'");
        while ($row = $result->fetch_object())
        {
            $this->redis->sAdd("user:feeds:$userid", $row->id);
            $this->redis->hMSet("feed:$row->id",array(
            'id'=>$row->id,
            'userid'=>$row->userid,
            'name'=>$row->name,
            'datatype'=>$row->datatype,
            'tag'=>$row->tag,
            'public'=>$row->public,
            'size'=>$row->size,
            'engine'=>$row->engine,
            'server'=>$row->server
            ));
            
            // Last time and value
            $id = $row->id;
            $lastvalue = $this->get_timevalue_from_data($id);
            
            if (!isset($lastvalue['time']) || !isset($lastvalue['value'])) {
                $this->log->warn("ERROR: Feed Model, No time or value for feed $id");
            }
            
            $this->redis->hMset("feed:lastvalue:$id", array(
                'value' => $lastvalue['value'], 
                'time' => $lastvalue['time']
            ));
        }
    }

    public function load_feed_to_redis($id)
    {
        $this->redis->incr("loadtoredis");
        $result = $this->mysqli->query("SELECT id,userid,name,datatype,tag,public,size,engine,server FROM feeds WHERE `id` = '$id'");
        $row = $result->fetch_object();

        if (!$row) {
            // $this->log->warn("Feed model: Requested feed does not exist feedid=$id");
            return false;
        }        

        $this->redis->hMSet("feed:$row->id",array(
            'id'=>$row->id,
            'userid'=>$row->userid,
            'name'=>$row->name,
            'datatype'=>$row->datatype,
            'tag'=>$row->tag,
            'public'=>$row->public,
            'size'=>$row->size,
            'engine'=>$row->engine,
            'server'=>$row->server
        ));

        return true;
    }
    
    public function get_user_timezone($userid) 
    {
        $result = $this->mysqli->query("SELECT timezone FROM users WHERE id = '$userid';");
        $row = $result->fetch_object();

        $now = new DateTime();
        try {
            $now->setTimezone(new DateTimeZone($row->timezone));
            $timezone = $row->timezone;
        } catch (Exception $e) {
            $timezone = "UTC";
        }
        return $timezone;
    }
}

