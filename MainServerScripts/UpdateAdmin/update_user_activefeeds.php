<?php

  $start = microtime(true);

  define('EMONCMS_EXEC', 1);
  
  chdir("/var/www/emoncms");

  require "process_settings.php";
  require "Lib/EmonLogger.php";
  $mysqli = @new mysqli($server,$username,$password,$database);

  $redis = new Redis();
  $redis->connect("127.0.0.1");

  include "Modules/feed/feed_model.php";
  $feed = new Feed($mysqli,$redis, $feed_settings);
        
  // $keys = $redis->keys("feed:lastvalue:*");
  
  $now = time();
  
  $min5 = 0;
  $min10 = 0;
  $min30 = 0;
  $hour = 0;
  $h24 = 0;
  $h48 = 0;
  
  $feeds = array();
  $activefeeds = array();
  
  $result = $mysqli->query("SELECT id,userid FROM feeds");
  while ($row = $result->fetch_object())
  {
    $feedid = $row->id;
    $userid = $row->userid;
    $strtime = $redis->hget("feed:lastvalue:$feedid","time");
    if ($strtime!=null) {
      $time = strtotime($strtime);
      $diff = $now-$time;
      if ($diff<300) $min5++;
      if ($diff<600) $min10++;
      if ($diff<1800) $min30++;
      if ($diff<3600) $hour++;
      if ($diff<86400) $h24++;    
      if ($diff<172800) {
          $h48++;
          if (!isset($activefeeds[$userid])) $activefeeds[$userid] = 0;
          $activefeeds[$userid]++;
      }
    }
  }
  
  echo "Active feeds in: \n";
  echo "5 min\t$min5\n";
  echo "10 min\t$min10\n";
  echo "30 min\t$min30\n";
  echo "hour\t$hour\n";
  echo "24 h\t$h24\n";
  echo "48 h\t$h48\n";
  
  /*
  $feed->insert_data(ID,time(),time(),$min5);
  $feed->insert_data(ID,time(),time(),$min10);
  $feed->insert_data(ID,time(),time(),$min30);
  $feed->insert_data(ID,time(),time(),$hour);
  $feed->insert_data(ID,time(),time(),$h24);
  $feed->insert_data(ID,time(),time(),$h48);
  */
  
  print (microtime(true)-$start)."\n";
  
  foreach ($activefeeds as $userid=>$useractivefeeds)  {
  $mysqli->query("UPDATE users SET `activefeeds` = '$useractivefeeds' WHERE `id`= '$userid'");
  }
