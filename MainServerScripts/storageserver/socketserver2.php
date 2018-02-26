<?php

$fp = fopen("socketserver2lock", "w");
if (! flock($fp, LOCK_EX | LOCK_NB)) { echo "Already running\n"; die; }

$redis = new Redis();
$redis_server = '/var/run/redis/redis.sock';
$connected = $redis->connect($redis_server);
if (!$connected) die;

$server = stream_socket_server("tcp://0.0.0.0:PORT", $errno, $errorMessage);

if ($server === false) {
    throw new UnexpectedValueException("Could not bind to socket: $errorMessage");
}

$usleep = 0;
$ltime = time();

$count = 0;

while (true)
{
    $client = @stream_socket_accept($server);
    if(is_resource($client)) {
        $name = stream_socket_get_name($client,true);
        print "client connected: $name\n";

        while($client)
        {
            if ((time()-$ltime)>1) {
                $ltime = time();
                
                $usleep = (int) $redis->get('SS2:usleep');
                if ($usleep<0) $usleep = 0;
                
                if ($redis->get('socketserver2-stop')==1) {
                    $redis->set('socketserver2-stop',0);
                    die;
                }
                
                $redis->incr("socketserver2-count",$count);
                $redis->incr("SS2:count",$count);
                $count = 0;
            }
            
            if ($redis->llen('feedpostqueue:2')>10)
            {
                $lines = "";
                for ($i=0; $i<10; $i++) {
                    $lines .= $redis->lpop("feedpostqueue:2")."\n";
                    $count ++;
                }

                $result = fwrite($client,$lines);
                if (!$result) {
	    	            $client = false;
                    print "client disconnected\n";
	              }
            }
            
	          usleep($usleep);
        }
    }

    usleep(10000);

    if ($redis->get('socketserver2-stop')==1) {
        print "socketserver2-stop received in waiting loop\n";
        $redis->set('socketserver2-stop',0);
        die;
    }
}
