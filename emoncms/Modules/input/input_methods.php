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

class InputMethods
{
    private $redis;

    public function __construct($redis)
    {
        $this->redis = $redis;
    }
    
    public function post($userid)
    {
        $this->redis->incr("fiveseconds:inputhits");
        
        // Nodeid
        global $route,$param;

        // Default nodeid is zero
        $nodeid = 0;
        
        if ($param->exists('node')) {
            $nodeid = $param->val('node');
        } else if ($route->subaction) {
            $nodeid = $route->subaction;
        }
        
        if (preg_replace('/[^\p{N}\p{L}_\s-.]/u','',$nodeid)!=$nodeid) return "Error: invalid node name";
        if (strlen($nodeid)>16) return "Error: node name must be 16 characters or less";
        if ($nodeid=="") $nodeid = 0;

        // Time
        if ($param->exists('time')) $time = (int) $param->val('time'); else $time = time();

        // Data
        $datain = false;
        /* The code below processes the data regardless of its type,
         * unless fulljson is used in which case the data is decoded
         * from JSON.  The previous 'json' type is retained for
         * backwards compatibility, since some strings would be parsed
         * differently in the two cases. */
        if ($param->exists('json')) $datain = $param->val('json');
        else if ($param->exists('fulljson')) $datain = $param->val('fulljson');
        else if ($param->exists('csv')) $datain = $param->val('csv');
        else if ($param->exists('data')) $datain = $param->val('data');
        
        if ($datain=="" || $datain==false) return "Error: Request contains no data via csv, json or data tag";

       if ($param->exists('fulljson')) {
            $inputs = json_decode($datain, true, 2);
            if (is_null($inputs)) {
                return "Error decoding JSON string (invalid or too deeply nested)";
            } else if (!is_array($inputs)) {
                return "Input must be a JSON object";
            }
        } else {
            $json = preg_replace('/[^\w\s-.:,]/','',$datain);
            $datapairs = explode(',', $json);

            $inputs = array();
            $csvi = 0;
            for ($i=0; $i<count($datapairs); $i++)
            {
                $keyvalue = explode(':', $datapairs[$i]);

                if (isset($keyvalue[1])) {
                    $key = $keyvalue[0];
                    if ($key=='') return "Error: Format error, json key missing or invalid character";
                    if (strlen($key)>64) return "Error: input name must be 64 characters or less";
                    $value = (float) $keyvalue[1];
                } else {
                    $key = $csvi+1;
                    $value = $keyvalue[0];
                    $csvi ++;
                }
                
                $post_time = $time;
                
                // Start of rate limiter
                $lasttime = $this->redis->get("postlimiter:$userid:$nodeid:$key");
                if ($lasttime==null) $lasttime = 0;
                                
                if (($post_time-$lasttime)>=5) {
                    $this->redis->set("postlimiter:$userid:$nodeid:$key",$post_time);
                    $inputs[$key] = $value;
                }
            }
        }
        
        $packet = array(
            'userid' => $userid,
            'time' => $time,
            'nodeid' => $nodeid,
            'data'=>$inputs
        );
        
        if (count($inputs)>0) {
            $str = json_encode($packet);
            $uid = intval($userid);

            global $IQL;
            
            if ($uid<$IQL["L1"]) {
                $this->redis->rpush('inputbuffer1',$str);
            } elseif ($uid>=$IQL["L1"] && $uid<$IQL["L2"]) {
                $this->redis->rpush('inputbuffer2',$str);
            } elseif ($uid>=$IQL["L2"] && $uid<$IQL["L3"]) {
                $this->redis->rpush('inputbuffer3',$str);
            } elseif ($uid>=$IQL["L3"] && $uid<$IQL["L4"]) {
                $this->redis->rpush('inputbuffer4',$str);
            } elseif ($uid>=$IQL["L4"] && $uid<$IQL["L5"]) {
                $this->redis->rpush('inputbuffer5',$str);
            } else {
                $this->redis->rpush('inputbuffer6',$str);
            }
        }
        
        return "ok";
    }

    /*

    input/bulk.json?data=[[0,16,1137],[2,17,1437,3164],[4,19,1412,3077]]

    The first number of each node is the time offset (see below).

    The second number is the node id, this is the unique identifer for the wireless node.

    All the numbers after the first two are data values. The first node here (node 16) has only one data value: 1137.

    Optional offset and time parameters allow the sender to set the time
    reference for the packets.
    If none is specified, it is assumed that the last packet just arrived.
    The time for the other packets is then calculated accordingly.

    offset=-10 means the time of each packet is relative to [now -10 s].
    time=1387730127 means the time of each packet is relative to 1387730127
    (number of seconds since 1970-01-01 00:00:00 UTC)

    Examples:

    // legacy mode: 4 is 0, 2 is -2 and 0 is -4 seconds to now.
      input/bulk.json?data=[[0,16,1137],[2,17,1437,3164],[4,19,1412,3077]]
    // offset mode: -6 is -16 seconds to now.
      input/bulk.json?data=[[-10,16,1137],[-8,17,1437,3164],[-6,19,1412,3077]]&offset=-10
    // time mode: -6 is 1387730121
      input/bulk.json?data=[[-10,16,1137],[-8,17,1437,3164],[-6,19,1412,3077]]&time=1387730127
    // sentat (sent at) mode:
      input/bulk.json?data=[[520,16,1137],[530,17,1437,3164],[535,19,1412,3077]]&offset=543

    See pull request for full discussion:
    https://github.com/emoncms/emoncms/pull/118
    */
    public function bulk($userid)
    {
        $this->redis->incr("fiveseconds:inputhits");
        
        global $param;
        
        $data = json_decode($param->val('data'));

        $dropped = 0; $droppednegative = 0;
        
        $len = count($data);
        if ($len==0) return "ok"; // empty data return ok
        
        if (!isset($data[$len-1][0])) return "Error: Format error, last item in bulk data does not contain any data";

        // Sent at mode: input/bulk.json?data=[[45,16,1137],[50,17,1437,3164],[55,19,1412,3077]]&sentat=60
        if ($param->exists('sentat')) {
            $time_ref = time() - (int) $param->val('sentat');
        }
        // Offset mode: input/bulk.json?data=[[-10,16,1137],[-8,17,1437,3164],[-6,19,1412,3077]]&offset=-10
        elseif ($param->exists('offset')) {
            $time_ref = time() - (int) $param->val('offset');
        }
        // Time mode: input/bulk.json?data=[[-10,16,1137],[-8,17,1437,3164],[-6,19,1412,3077]]&time=1387729425
        elseif ($param->exists('time')) {
            $time_ref = (int) $param->val('time');
        }
        // Legacy mode: input/bulk.json?data=[[0,16,1137],[2,17,1437,3164],[4,19,1412,3077]]
        else {
            $time_ref = time() - (int) $data[$len-1][0];
        }
        
        foreach ($data as $item)
        {
            if (count($item)>2)
            {
                // check for correct time format
                $itemtime = (int) $item[0];

                $time = $time_ref + (int) $itemtime;
                if (!is_object($item[1])) {
                    $nodeid = $item[1]; 
                } else {
                    return "Format error, node must not be an object";
                }

                $inputs = array();
                $name = 1;
                for ($i=2; $i<count($item); $i++)
                {
                    if (is_object($item[$i]))
                    {
                        $value = (float) current($item[$i]);
                        $inputs[key($item[$i])] = $value;
                        continue;
                    }
                    if (strlen($item[$i]))
                    {
                        $value = (float) $item[$i];
                        $inputs[$name] = $value;
                    }
                    $name ++;
                }
                
                if ($nodeid=="") $nodeid = 0;

                $array = array(
                    'userid'=>$userid,
                    'time'=>$time,
                    'nodeid'=>$nodeid,
                    'data'=>$inputs
                );

                // Start of rate limiter
                $lasttime = $this->redis->get("limiter:$userid:$nodeid");
                if ($lasttime==null) $lasttime = 0;
                
                $post_time = $time;
                if (($post_time-$lasttime)>=1)
                {
                    $this->redis->set("limiter:$userid:$nodeid",$post_time);
                    $str = json_encode($array);
                    $uid = intval($userid);
                    
                    global $IQL;
                    
                    if ($uid<$IQL["L1"]) {
                        $this->redis->rpush('inputbuffer1',$str);
                    } elseif ($uid>=$IQL["L1"] && $uid<$IQL["L2"]) {
                        $this->redis->rpush('inputbuffer2',$str);
                    } elseif ($uid>=$IQL["L2"] && $uid<$IQL["L3"]) {
                        $this->redis->rpush('inputbuffer3',$str);
                    } elseif ($uid>=$IQL["L3"] && $uid<$IQL["L4"]) {
                        $this->redis->rpush('inputbuffer4',$str);
                    } elseif ($uid>=$IQL["L4"] && $uid<$IQL["L5"]) {
                        $this->redis->rpush('inputbuffer5',$str);
                    } else {
                        $this->redis->rpush('inputbuffer6',$str);
                    }
                } else { 
                    if (($post_time-$lasttime)<0) $droppednegative ++;
                    $dropped ++;
                }
            }
        }
        return "ok";
    }
}
