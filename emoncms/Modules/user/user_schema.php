<?php

$schema['users'] = array(
    'id' => array('type' => 'int(11)', 'Null'=>'NO', 'Key'=>'PRI', 'Extra'=>'auto_increment'),
    'username' => array('type' => 'varchar(30)'),
    'email' => array('type' => 'varchar(40)'),
    'password' => array('type' => 'varchar(64)'),
    'salt' => array('type' => 'varchar(32)'),
    'apikey_write' => array('type' => 'varchar(64)'),
    'apikey_read' => array('type' => 'varchar(64)'),
    'lastlogin' => array('type' => 'datetime'),
    'admin' => array('type' => 'int(11)', 'Null'=>'NO'),

    // User profile fields
    'gravatar' => array('type' => 'varchar(30)', 'default'=>''),
    'name'=>array('type'=>'varchar(30)', 'default'=>''),
    'location'=>array('type'=>'varchar(30)', 'default'=>''),
    'timezone' => array('type'=>'varchar(64)', 'default'=>'UTC'),
    'language' => array('type' => 'varchar(5)', 'default'=>'en_EN'),
    'bio' => array('type' => 'text', 'default'=>''),
    'tags' => array('type' => 'text'),
    'startingpage' => array('type'=>'varchar(64)', 'default'=>'feed/list'),
    
    // Usage 
    'lastactive'=> array('type' => 'int(11)'),
    
    'phptimeseries'=> array('type' => 'int(11)'),
    'phpfina'=> array('type' => 'int(11)'),
    
    'server0'=> array('type' => 'int(11)'),
    //'server1'=> array('type' => 'int(11)'),
    //'server2'=> array('type' => 'int(11)'),
    
    'inputs'=> array('type' => 'int(11)'),
    'activeinputs'=> array('type' => 'int(11)'),
    'feeds'=> array('type' => 'int(11)'),
    'activefeeds'=> array('type' => 'int(11)'),
    'diskuse' => array('type' => 'bigint(20)')
);

$schema['rememberme'] = array(
    'userid' => array('type' => 'int(11)'),
    'token' => array('type' => 'varchar(40)'),
    'persistentToken' => array('type' => 'varchar(40)'),
    'expire' => array('type' => 'datetime')
);
