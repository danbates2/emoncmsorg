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

function admin_controller()
{
    global $mysqli,$session,$route,$redis,$feed_settings;

    $result = false;
    $sessionadmin = false;
    // Allow for special admin session if updatelogin property is set to true in settings.php
    // Its important to use this with care and set updatelogin to false or remove from settings
    // after the update is complete.
    if (isset($session['admin'])) {
        if ($session['admin']) $sessionadmin = true;
    }
        
    if ($sessionadmin)
    {
        if ($route->action == 'view') $result = view("Modules/admin/admin_main_view.php", array());

        if ($route->action == 'db')
        {
            $applychanges = get('apply');
            if (!$applychanges) $applychanges = false;
            else $applychanges = true;

            require_once "Lib/dbschemasetup.php";
            $updates = array();
            $updates[] = array(
                'title'=>"Database schema",
                'description'=>"",
                'operations'=>db_schema_setup($mysqli,load_db_schema(),$applychanges)
            );

            $result = view("Modules/admin/update_view.php", array('applychanges'=>$applychanges, 'updates'=>$updates));
        }

        if ($route->action == 'users' && $session['write'] && $session['admin'])
        {
            $result = view("Modules/admin/userlist_view.php", array());
        }
        



        if ($route->action == 'numberofusers' && $session['write'] && $session['admin'])
        {
            $route->format = "text";
            $result = $mysqli->query("SELECT COUNT(*) FROM users");
            $row = $result->fetch_array();
            $result = (int) $row[0];
        }

        if ($route->action == 'userlist' && $session['write'] && $session['admin'])
        {

            $limit = "";
            if (isset($_GET['page']) && isset($_GET['perpage'])) {
                $page = (int) $_GET['page'];
                $perpage = (int) $_GET['perpage'];
                $offset = $page * $perpage;
                $limit = "LIMIT $perpage OFFSET $offset";
            }
            
            $orderby = "diskuse";
            if (isset($_GET['orderby'])) {
                if ($_GET['orderby']=="id") $orderby = "id";
                if ($_GET['orderby']=="username") $orderby = "username";
                if ($_GET['orderby']=="email") $orderby = "email";
                if ($_GET['orderby']=="diskuse") $orderby = "diskuse";
                if ($_GET['orderby']=="inputs") $orderby = "inputs";
                if ($_GET['orderby']=="lastactive") $orderby = "lastactive";
                if ($_GET['orderby']=="activefeeds") $orderby = "activefeeds";
                if ($_GET['orderby']=="feeds") $orderby = "feeds";
                if ($_GET['orderby']=="email_verified") $orderby = "email_verified";
            }
            
            $order = "DESC";
            if (isset($_GET['order'])) {
                if ($_GET['order']=="decending") $order = "DESC";
                if ($_GET['order']=="ascending") $order = "ASC";
            }
            
            $search = false;
            $searchstr = "";
            if (isset($_GET['search'])) {
                $search = $_GET['search'];
                $search_out = preg_replace('/[^\p{N}\p{L}_\s-]/u','',$search);
                if ($search_out!=$search || $search=="") { 
                    $search = false; 
                }
                if ($search!==false) $searchstr = "WHERE username LIKE '%$search%' OR email LIKE '%$search%'";
            }
        
            $data = array();
            $result = $mysqli->query("SELECT id,username,email,diskuse,inputs,activefeeds,feeds,phptimeseries,phpfina,server0,email_verified,lastactive FROM users $searchstr ORDER BY $orderby $order ".$limit);
            
            while ($row = $result->fetch_object()) {
                $userid = $row->id;
                $data[] = $row;
            }
            $result = $data;
        }

        if ($route->action == 'setuser' && $session['write'] && $session['admin'])
        {
            $_SESSION['userid'] = intval(get('id'));
            header("Location: ../user/view");
        }
        
        if ($route->action == 'setuserfeed' && $session['write'] && $session['admin'])
        {
            $feedid = (int) get("id");
            $result = $mysqli->query("SELECT userid FROM feeds WHERE id=$feedid");
            $row = $result->fetch_object();
            $userid = $row->userid;
            $_SESSION['userid'] = $userid;
            header("Location: ../user/view");
        }

    }

    return array('content'=>$result);
}
