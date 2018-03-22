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

function feed_controller()
{
    global $mysqli, $redis, $session, $route, $feed_settings;
    $result = false;

    include "Modules/feed/feed_model.php";
    $feed = new Feed($mysqli,$redis,$feed_settings);

    if ($route->format == 'html')
    {
        if ($route->action == "" && $session['write']) $result = view("Modules/feed/Views/feedlist_view.php",array());
        if ($route->action == "list" && $session['write']) $result = view("Modules/feed/Views/feedlist_view.php",array());
        if ($route->action == "api" && $session['write']) $result = view("Modules/feed/Views/feedapi_view.php",array());
    }

    if ($route->format == 'json')
    {
        // Public actions available on public feeds.
        if ($route->action == "list")
        {
            $redis->incr("fiveseconds:getdatahits");
            if (!isset($_GET['userid']) && $session['read']) $result = $feed->get_user_feeds($session['userid']);
            if (isset($_GET['userid']) && $session['read'] && $_GET['userid'] == $session['userid']) $result = $feed->get_user_feeds($session['userid']);
            if (isset($_GET['userid']) && $session['read'] && $_GET['userid'] != $session['userid']) $result = $feed->get_user_public_feeds(get('userid'));
            if (isset($_GET['userid']) && !$session['read']) $result = $feed->get_user_public_feeds(get('userid'));
        } elseif ($route->action == "listwithmeta" && $session['read']) {
            $result = $feed->get_user_feeds_with_meta($session['userid']);
        } elseif ($route->action == "getid" && $session['read']) {
            $redis->incr("fiveseconds:getdatahits");
            $result = $feed->get_id($session['userid'],get('name'));
        } elseif ($route->action == "create" && $session['write']) {
            $result = $feed->create($session['userid'],get('tag'),get('name'),get('datatype'),get('engine'),json_decode(get('options')),0);
        } elseif ($route->action == "updatesize" && $session['write']) {
            $result = $feed->update_user_feeds_size($session['userid']);
        } elseif ($route->action == "fetch") {
            $feedids = (array) (explode(",",(get('ids'))));
            for ($i=0; $i<count($feedids); $i++) {
                $feedid = (int) $feedids[$i];
                if ($feed->exist($feedid)) {  // if the feed exists
                   $f = $feed->get($feedid);
                   if ($f['public'] || ($session['userid']>0 && $f['userid']==$session['userid'] && $session['read'])) {
                       $result[$i] = 1*$feed->get_value($feedid); // null is a valid response
                   } else { $result[$i] = false; }
                } else { $result[$i] = false; } // false means feed not found
            }
        } else {
            $feedid = (int) get('id');
            // Actions that operate on a single existing feed that all use the feedid to select:
            // First we load the meta data for the feed that we want

            if ($feed->exist($feedid)) // if the feed exists
            {
                $f = $feed->get($feedid);
                // if public or belongs to user
                if ($f['public'] || ($session['userid']>0 && $f['userid']==$session['userid'] && $session['read']))
                {
                    $redis->incr("fiveseconds:getdatahits");
                    
                    if ($route->action == "value") $result = $feed->get_value($feedid);
                    else if ($route->action == "timevalue") $result = $feed->get_timevalue_seconds($feedid);
                    else if ($route->action == "get") $result = $feed->get_field($feedid,get('field')); // '/[^\w\s-]/'
                    else if ($route->action == "aget") $result = $feed->get($feedid);
                    else if ($route->action == "getmeta") $result = $feed->get_meta($feedid);
                    else if ($route->action == 'datanew' || $route->action == 'data') {
                        $skipmissing = 1;
                        $limitinterval = 1;
                        if (isset($_GET['skipmissing']) && $_GET['skipmissing']==0) $skipmissing = 0;
                        if (isset($_GET['limitinterval']) && $_GET['limitinterval']==0) $limitinterval = 0;
                        $interval = get('interval');
                        if (isset($_GET['dp'])) $interval = (int)(((get('end') - get('start')) / ((int)$_GET['dp']))*0.001);
                        
                        if (isset($_GET['interval'])) {
                        
                            $backup = false;
                            if (isset($_GET['backup']) && $_GET['backup']=="true") $backup = true;
                        
                            $result = $feed->get_data($feedid,get('start'),get('end'),get('interval'),$skipmissing,$limitinterval,$backup);
                        } else if (isset($_GET['mode'])) {
                            if (isset($_GET['split'])) {
                                $result = $feed->get_data_DMY_time_of_day($feedid,get('start'),get('end'),get('mode'),get('split'));
                            } else {
                                $result = $feed->get_data_DMY($feedid,get('start'),get('end'),get('mode'));
                            }
                        }
                    }
                    else if ($route->action == 'average') {
                        if (isset($_GET['interval'])) {
                            $result = $feed->get_average($feedid,get('start'),get('end'),get('interval'));
                        } else if (isset($_GET['mode'])) {
                            $result = $feed->get_average_DMY($feedid,get('start'),get('end'),get('mode'));
                        }
                    }
                    
                    else if ($route->action == "csvexport") $feed->csv_export($feedid,get('start'),get('end'),get('interval'),get('timeformat'));
                    
                }

                // write session required
                if (isset($session['write']) && $session['write'] && $session['userid']>0 && $f['userid']==$session['userid'])
                {
                    // Storage engine agnostic
                    if ($route->action == 'set') $result = $feed->set_feed_fields($feedid,get('fields'));
                    
                    else if ($route->action == "insert") {
                        $redis->incr("fiveseconds:directfeedinsert");
                        
                        if (isset($_GET["join"]) && $_GET["join"]==1) {
                            $result = $feed->insert_data_padding_mode($feedid,time(),get("time"),get("value"),"join");
                        } else {
                            $result = $feed->insert_data($feedid,time(),get("time"),get("value"));
                        }
                    }
                    
                    else if ($route->action == "update") {
                        if (isset($_GET['updatetime'])) $updatetime = get("updatetime"); else $updatetime = time();
                        $result = $feed->update_data($feedid,$updatetime,get("time"),get('value'));
                    }
                    else if ($route->action == "delete") $result = $feed->delete($feedid);
                    
                    else if ($route->action == "export") {
                        if ($f['engine']==Engine::PHPTIMESERIES) $result = $feed->phptimeseries_export($feedid,get('start'));
                        elseif ($f['engine']==Engine::PHPFINA) $result = $feed->phpfina_export($feedid,get('start'));
                    }
                }
            }
            else
            {
                $result = array('success'=>false, 'message'=>'Feed does not exist');
            }
        }
    }

    return array('content'=>$result);
}
