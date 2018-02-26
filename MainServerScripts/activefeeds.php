<?php

  $start = microtime(true);

  define('EMONCMS_EXEC', 1);
  
  chdir("/var/www/emoncms");

  require "process_settings.php";
  require "Modules/log/EmonLogger.php";
  $mysqli = @new mysqli($server,$username,$password,$database);

  $redis = new Redis();
  $redis->connect("127.0.0.1");

  include "Modules/feed/feed_model.php";
  $feed = new Feed($mysqli,$redis, $feed_settings);
        
  $keys = $redis->keys("feed:lastvalue:*");
  
  $now = time();
  
  $min5 = 0;
  $min10 = 0;
  $min30 = 0;
  $hour = 0;
  $h24 = 0;
  $h48 = 0;
  
  foreach ($keys as $key)
  {
    $parts = explode(":",$key);
    $feedid = $parts[2];
    
    $time = strtotime($redis->hget("feed:lastvalue:$feedid","time"));
    $diff = $now-$time;
    if ($diff<300) $min5++;
    if ($diff<600) $min10++;
    if ($diff<1800) $min30++;
    if ($diff<3600) $hour++;
    if ($diff<86400) $h24++;    
    if ($diff<172800) $h48++;
  }
  
  echo "Active feeds in: \n";
  echo "5 min\t$min5\n";
  echo "10 min\t$min10\n";
  echo "30 min\t$min30\n";
  echo "hour\t$hour\n";
  echo "24 h\t$h24\n";
  echo "48 h\t$h48\n";
  
  /*
  $feed->insert_data(FEEDID,time(),time(),$min5);
  $feed->insert_data(FEEDID,time(),time(),$min10);
  $feed->insert_data(FEEDID,time(),time(),$min30);
  $feed->insert_data(FEEDID,time(),time(),$hour);
  $feed->insert_data(FEEDID,time(),time(),$h24);
  $feed->insert_data(FEEDID,time(),time(),$h48);
  */
