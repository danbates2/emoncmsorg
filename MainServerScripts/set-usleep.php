<?php

    $redis = new Redis();
    $connected = $redis->connect("127.0.0.1");

    $redis->set('usleep:1',6000);
    $redis->set('usleep:2',6000);
    $redis->set('usleep:3',6000);
    $redis->set('usleep:4',6000);
    $redis->set('usleep:5',5000);
    $redis->set('usleep:6',6000);

    $redis->set('SS0:usleep',5000);
    $redis->set('SS1:usleep',20000);
    $redis->set('SS2:usleep',6000);

