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

class Input
{
    private $mysqli;
    private $feed;
    private $redis;

    public function __construct($mysqli,$redis,$feed)
    {
        $this->mysqli = $mysqli;
        $this->feed = $feed;
        $this->redis = $redis;
    }
    
    public function create_input($userid, $nodeid, $name)
    {
        $userid = (int) $userid;
        
        $nodeid = preg_replace('/[^\p{N}\p{L}_\s-.]/u','',$nodeid);
        if (strlen($nodeid)>16) return false; // restriction placed on emoncms.org
        $name = preg_replace('/[^\p{N}\p{L}_\s-.]/u','',$name);
        if (strlen($name)>64) return false; // restriction placed on emoncms.org
        
        $stmt = $this->mysqli->prepare("INSERT INTO input (userid,name,nodeid,description,processList) VALUES (?,?,?,'','')");
        $stmt->bind_param("iss",$userid,$name,$nodeid);
        $stmt->execute();
        $stmt->close();
        
        $id = $this->mysqli->insert_id;
        
        if ($id>0) {
            $this->redis->sAdd("user:inputs:$userid", $id);
            $this->redis->hMSet("input:$id",array('id'=>$id,'nodeid'=>$nodeid,'name'=>$name,'description'=>"", 'processList'=>""));
        }
        return $id;
    }

    public function exists($inputid)
    {
        $inputid = (int) $inputid;
        $result = $this->mysqli->query("SELECT id FROM input WHERE `id` = '$inputid'");
        if ($result->num_rows == 1) return true; else return false;
    }
    
    public function access($userid,$inputid)
    {
        $userid = (int) $userid;
        $inputid = (int) $inputid;
        
        $stmt = $this->mysqli->prepare("SELECT id FROM input WHERE userid=? AND id=?");
        $stmt->bind_param("ii",$userid,$inputid);
        $stmt->execute();
        $stmt->bind_result($id);
        $result = $stmt->fetch();
        $stmt->close();
        
        if ($result && $id>0) return true; else return false;
    }
    
    public function exists_nodeid_name($userid,$nodeid,$name)
    {
        $userid = (int) $userid;
        $nodeid = preg_replace('/[^\p{N}\p{L}_\s-.]/u','',$nodeid);
        $name = preg_replace('/[^\p{N}\p{L}_\s-.]/u','',$name);
        
        $stmt = $this->mysqli->prepare("SELECT id FROM input WHERE userid=? AND nodeid=? AND name=?");
        $stmt->bind_param("iss",$userid,$nodeid,$name);
        $stmt->execute();
        $stmt->bind_result($id);
        $result = $stmt->fetch();
        $stmt->close();
        
        if ($result && $id>0) return $id; else return false;
    } 
    
    public function check_node_id_valid($nodeid)
    {
        global $max_node_id_limit;
        if (!is_numeric ($nodeid)) return false;
        $nodeid = (int) $nodeid;

        if (!isset($max_node_id_limit)) $max_node_id_limit = 32;

        if ($nodeid<$max_node_id_limit) {
            return true;
        } else {
            return false;
        }
    }

    public function validate_access($dbinputs, $nodeid)
    {
        global $max_node_id_limit;
        if (!isset($dbinputs[$nodeid]) && (count($dbinputs) >= $max_node_id_limit )) return false;
        return true;
    }

    public function set_timevalue($id, $time, $value)
    {
        $id = (int) $id;
        $time = (int) $time;
        $value = (float) $value;
        $this->redis->hMset("input:lastvalue:$id", array('value' => $value, 'time' => $time));
    }

    // used in conjunction with controller before calling another method
    public function belongs_to_user($userid, $inputid)
    {
        $userid = (int) $userid;
        $inputid = (int) $inputid;

        $result = $this->mysqli->query("SELECT id FROM input WHERE userid = '$userid' AND id = '$inputid'");
        if ($result->fetch_array()) return true; else return false;
    }

    public function set_fields($id,$fields)
    {
        $id = (int) $id;
        if (!$this->exists($id)) return array('success'=>false, 'message'=>'Input does not exist');
        $fields = json_decode(stripslashes($fields));
        
        $success = false;

        if (isset($fields->name)) {
            if (preg_replace('/[^\p{N}\p{L}_\s-]/u','',$fields->name)!=$fields->name) return array('success'=>false, 'message'=>'invalid characters in input name');
            $stmt = $this->mysqli->prepare("UPDATE input SET name = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->name,$id);
            if ($stmt->execute()) $success = true;
            $stmt->close();
            
            if ($this->redis) $this->redis->hset("input:$id",'name',$fields->name);
        }
        
        if (isset($fields->description)) {
            if (preg_replace('/[^\p{N}\p{L}_\s-.]/u','',$fields->description)!=$fields->description) return array('success'=>false, 'message'=>'invalid characters in input description');
            $stmt = $this->mysqli->prepare("UPDATE input SET description = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->description,$id);
            if ($stmt->execute()) $success = true;
            $stmt->close();
            
            if ($this->redis) $this->redis->hset("input:$id",'description',$fields->description);
        }

        if ($success){
            return array('success'=>true, 'message'=>'Field updated');
        } else {
            return array('success'=>false, 'message'=>'Field could not be updated');
        }
    }

    // -----------------------------------------------------------------------------------------
    // get_inputs, returns user inputs by node name and input name
    // - last time and value not included
    // - used by input/post, input/bulk input methods
    // -----------------------------------------------------------------------------------------
    public function get_inputs($userid)
    {
        $userid = (int) $userid;
        if (!$this->redis->exists("user:inputs:$userid")) $this->load_to_redis($userid);

        $dbinputs = array();
        $inputids = $this->redis->sMembers("user:inputs:$userid");

        $pipe = $this->redis->multi(Redis::PIPELINE);
        foreach ($inputids as $id) $row = $this->redis->hGetAll("input:$id");
        $result = $pipe->exec();
        
        foreach ($result as $row) {
            if ($row['nodeid']==null) $row['nodeid'] = 0;
            if (!isset($dbinputs[$row['nodeid']])) $dbinputs[$row['nodeid']] = array();
            $dbinputs[$row['nodeid']][$row['name']] = array('id'=>$row['id'], 'processList'=>$row['processList']);
        }

        return $dbinputs;
    }

    // -----------------------------------------------------------------------------------------
    // get_inputs_v2, returns user inputs by node name and input name
    // - last time and value is included in the response
    // - input id is not included in the response
    //
    // {"emontx":{
    //   "1":{"time":TIME,"value":100,"processList":""},
    //   "2":{"time":TIME,"value":200,"processList":""},
    //   "3":{"time":TIME,"value":300,"processList":""}
    // }}
    // -----------------------------------------------------------------------------------------
    public function get_inputs_v2($userid)
    {
        $userid = (int) $userid;
        if (!$this->redis->exists("user:inputs:$userid")) $this->load_to_redis($userid);

        $dbinputs = array();
        $inputids = $this->redis->sMembers("user:inputs:$userid");

        foreach ($inputids as $id)
        {
            $row = $this->redis->hGetAll("input:$id");
            if ($row['nodeid']==null) $row['nodeid'] = 0;
            
            $lastvalue = $this->redis->hmget("input:lastvalue:$id",array('time','value'));
            if (!isset($lastvalue['time']) || !is_numeric($lastvalue['time']) || is_nan($lastvalue['time'])) {
                $row['time'] = null;
            } else {
                $row['time'] = (int) $lastvalue['time'];
            }
            if (!isset($lastvalue['value']) || !is_numeric($lastvalue['value']) || is_nan($lastvalue['value'])) {
                $row['value'] = null;
            } else {
                $row['value'] = (float) $lastvalue['value'];
            }
            
            if (!isset($dbinputs[$row['nodeid']])) $dbinputs[$row['nodeid']] = array();
            $dbinputs[$row['nodeid']][$row['name']] = array('time'=>$row['time'], 'value'=>$row['value'], 'processList'=>$row['processList']);
        }

        return $dbinputs;
    }
    
    // -----------------------------------------------------------------------------------------
    // getlist: returns a list of user inputs (no grouping)
    // -----------------------------------------------------------------------------------------
    public function getlist($userid)
    {
        $userid = (int) $userid;
        if (!$this->redis->exists("user:inputs:$userid")) $this->load_to_redis($userid);

        $inputs = array();
        $inputids = $this->redis->sMembers("user:inputs:$userid");
        
        $pipe = $this->redis->multi(Redis::PIPELINE);
        foreach ($inputids as $id)
        {
            $this->redis->hGetAll("input:$id");
            $this->redis->hmget("input:lastvalue:$id",array('time','value'));
        }
        $result = $pipe->exec();
        
        for ($i=0; $i<count($result); $i+=2) {
            $row = $result[$i];
            $lastvalue = $result[$i+1];
            if (!isset($lastvalue['time']) || !is_numeric($lastvalue['time']) || is_nan($lastvalue['time'])) {
                $row['time'] = null;
            } else {
                $row['time'] = (int) $lastvalue['time'];
            }
            if (!isset($lastvalue['value']) || !is_numeric($lastvalue['value']) || is_nan($lastvalue['value'])) {
                $row['value'] = null;
            } else {
                $row['value'] = (float) $lastvalue['value'];
            }
            $inputs[] = $row;
        }
        return $inputs;
    }
    
    public function get_name($id)
    {
        $id = (int) $id;
        if (!$this->redis->exists("input:$id")) $this->load_input_to_redis($id);
        return $this->redis->hget("input:$id",'name');
    }

    public function get_processlist($id)
    {
        $id = (int) $id;
        if (!$this->redis->exists("input:$id")) $this->load_input_to_redis($id);
        return $this->redis->hget("input:$id",'processList');
    }

    public function get_last_value($id)
    {
        $id = (int) $id;
        return $this->redis->hget("input:lastvalue:$id",'value');
    }
    
    public function delete($userid, $inputid)
    {
        $userid = (int) $userid;
        $inputid = (int) $inputid;
        // Inputs are deleted permanentely straight away rather than a soft delete
        // as in feeds - as no actual feed data will be lost
        $this->mysqli->query("DELETE FROM input WHERE userid = '$userid' AND id = '$inputid'");
        
        $this->redis->del("input:$inputid");
        $this->redis->srem("user:inputs:$userid",$inputid);
        
        return "input deleted";
    }
    
    // userid and inputids are checked in belongs_to_user and delete
    public function delete_multiple($userid, $inputids) {
        foreach ($inputids as $inputid) {
            if ($this->belongs_to_user($userid, $inputid)) $this->delete($userid, $inputid);
        }
        return "inputs deleted";
    }

    public function clean($userid)
    {
        $userid = (int) $userid;
        $n = 0;
        $qresult = $this->mysqli->query("SELECT * FROM input WHERE `userid` = '$userid'");
        while ($row = $qresult->fetch_array())
        {
            $inputid = $row['id'];
            if ($row['processList']==NULL || $row['processList']=='')
            {
                $result = $this->mysqli->query("DELETE FROM input WHERE userid = '$userid' AND id = '$inputid'");
                
                $this->redis->del("input:$inputid");
                $this->redis->srem("user:inputs:$userid",$inputid);
                $n++;
            }
        }
        return "Deleted $n inputs";
    }

    // -----------------------------------------------------------------------------------------
    // Processlist functions
    // -----------------------------------------------------------------------------------------
    public function add_process($process_class,$userid,$inputid,$processid,$arg)
    {
        $userid = (int) $userid;
        $inputid = (int) $inputid;
        $processid = (int) $processid;                                    // get process type (ProcessArg::)
        
        $process = $process_class->get_process($processid);
        $processtype = $process[1];                                       // Array position 1 is the processtype: VALUE, INPUT, FEED
        $datatype = $process[4];                                          // Array position 4 is the datatype
        
        if (!$process) {
            return array('success'=>false, 'message'=>'This process does not exist');
        }
        
        if (isset($process[5]) && $process[5]=="Deleted") {
            return array('success'=>false, 'message'=>'This process is not supported on this server');
        }
        
        switch ($processtype) {
            case ProcessArg::VALUE:                                       // If arg type value
                if ($arg == '') return array('success'=>false, 'message'=>'Argument must be a valid number greater or less than 0.');
                
                $arg = (float)$arg;
                $arg = str_replace(',','.',$arg); // hack to fix locale issue that converts . to ,
                    
                break;
            case ProcessArg::INPUTID:                                     // If arg type input
                $arg = (int) $arg;
                if (!$this->exists($arg)) return array('success'=>false, 'message'=>'Input does not exist!');
                if (!$this->access($userid,$arg)) return array('success'=>false, 'message'=>'You do not have permission to access input');
                break;
            case ProcessArg::FEEDID:                                      // If arg type feed
                $arg = (int) $arg;
                if (!$this->feed->exist($arg)) return array('success'=>false, 'message'=>'Feed does not exist!');
                if (!$this->feed->access($userid,$arg)) return array('success'=>false, 'message'=>'You do not have permission to access feed');
                break;
            case ProcessArg::NONE:                                        // If arg type none
                $arg = 0;
                break;
        }

        $list = $this->get_processlist($inputid);
        
        // check to see if feed is already being written too
        $listarray = explode(",",$list);
        foreach ($listarray as $item) {
            $keyval = explode(":",$item);
            $tmp = $process_class->get_process((int)$keyval[0]);
            if ($tmp[1]==ProcessArg::FEEDID && $keyval[1]==$arg) {
                return array('success'=>false, 'message'=>'Feed is already being written to, select create new');
            }
        }
        
        if ($list) $list .= ',';
        $list .= $processid . ':' . $arg;
        $this->set_processlist($inputid, $list);

        return array('success'=>true, 'message'=>'Process added');
    }

    /******
    * delete input process by index
    ******/
    public function delete_process($inputid, $index)
    {
        $inputid = (int) $inputid;
        $index = (int) $index;

        $success = false;
        $index--; // Array is 0-based. Index from process page is 1-based.

        // Load process list
        $array = explode(",", $this->get_processlist($inputid));

        // Delete process
        if (count($array)>$index && $array[$index]) {unset($array[$index]); $success = true;}

        // Save new process list
        $this->set_processlist($inputid, implode(",", $array));

        return $success;
    }

    /******
    * move_input_process - move process up/down list of processes by $moveby (eg. -1, +1)
    ******/
    public function move_process($id, $index, $moveby)
    {
        $id = (int) $id;
        $index = (int) $index;
        $moveby = (int) $moveby;

        if (($moveby > 1) || ($moveby < -1)) return false;  // Only support +/-1 (logic is easier)

        $process_list = $this->get_processlist($id);
        $array = explode(",", $process_list);
        $index = $index - 1; // Array is 0-based. Index from process page is 1-based.

        $newindex = $index + $moveby; // Calc new index in array
        // Check if $newindex is greater than size of list
        if ($newindex > (count($array)-1)) $newindex = (count($array)-1);
        // Check if $newindex is less than 0
        if ($newindex < 0) $newindex = 0;

        $replace = $array[$newindex]; // Save entry that will be replaced
        $array[$newindex] = $array[$index];
        $array[$index] = $replace;

        // Save new process list
        $this->set_processlist($id, implode(",", $array));
        return true;
    }

    // USES: redis input
    public function reset_process($id)
    {
        $id = (int) $id;
        $this->set_processlist($id, "");
    }
    
    private function set_processlist($id, $processlist)
    {
        $this->redis->hset("input:$id",'processList',$processlist);
        $this->mysqli->query("UPDATE input SET processList = '$processlist' WHERE id='$id'");
    }
    
    // -----------------------------------------------------------------------------------------
    // Redis cache loaders
    // -----------------------------------------------------------------------------------------
    private function load_input_to_redis($inputid)
    {
        $result = $this->mysqli->query("SELECT id,nodeid,name,description,processList FROM input WHERE `id` = '$inputid'");
        $row = $result->fetch_object();

        $this->redis->sAdd("user:inputs:$userid", $row->id);
        $this->redis->hMSet("input:$row->id",array(
            'id'=>$row->id,
            'nodeid'=>$row->nodeid,
            'name'=>$row->name,
            'description'=>$row->description,
            'processList'=>$row->processList
        ));
    }

    private function load_to_redis($userid)
    {
        $result = $this->mysqli->query("SELECT id,nodeid,name,description,processList FROM input WHERE `userid` = '$userid'");
        while ($row = $result->fetch_object())
        {
            $this->redis->sAdd("user:inputs:$userid", $row->id);
            $this->redis->hMSet("input:$row->id",array(
                'id'=>$row->id,
                'nodeid'=>$row->nodeid,
                'name'=>$row->name,
                'description'=>$row->description,
                'processList'=>$row->processList
            ));
        }
    }

}
