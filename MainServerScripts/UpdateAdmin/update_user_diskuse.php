<?php

  chdir("/var/www/emoncms/");

  define('EMONCMS_EXEC', 1);

  // 1) Load settings and core scripts
  require "process_settings.php";
  require "Lib/EmonLogger.php";
  // 2) Database
  $mysqli = @new mysqli($server,$username,$password,$database);

  $redis = new Redis();
  $redis->connect("127.0.0.1");

  require("Modules/user/user_model.php");
  $user = new User($mysqli,$redis,null);

  require "Modules/feed/feed_model.php";
  $feed = new Feed($mysqli,$redis,$feed_settings);
  
  $result = $mysqli->query("SELECT * FROM users");
  while ($row = $result->fetch_object()) {
      $userid = $row->id;
      $diskuse = $feed->update_user_feeds_size($userid);
      // $mysqli->query("UPDATE users SET `diskuse` = '$diskuse' WHERE `id`= '$userid'");
      
      print $userid." ".$diskuse."\n";
  }
  
  
