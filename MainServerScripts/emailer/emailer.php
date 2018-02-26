<?php

$fp = fopen("runlock", "w");
if (! flock($fp, LOCK_EX | LOCK_NB)) { echo "Already running\n"; die; }

// include SwiftMailer. One is the path from a PEAR install,
// the other from libphp-swiftmailer.
$have_swift = @include_once ("Swift/swift_required.php"); 
if (!$have_swift) {
   $have_swift = @include_once ("swift_required.php");
}

if (!$have_swift){
    print "Could not find SwiftMailer - cannot proceed";
    exit;
};

$smtp_email_settings = array(
  'host'=>"",
  'username'=>"",
  'password'=>"",
  'from'=>array('email' => 'visiblename'),
  'port'=>465
);

$redis = new Redis();
$redis->connect("127.0.0.1");

while(true) 
{
    if ($redis->llen("emailqueue")>0) {
    
        $email = json_decode($redis->lpop("emailqueue"));        

        $transport = Swift_SmtpTransport::newInstance($smtp_email_settings['host'], $smtp_email_settings['port'], 'ssl')
        ->setUsername($smtp_email_settings['username'])->setPassword($smtp_email_settings['password']);

        $emailsto = explode(",",$email->emailto);

        $mailer = Swift_Mailer::newInstance($transport);
        $message = Swift_Message::newInstance()
          ->setSubject($email->subject)
          ->setFrom($smtp_email_settings['from'])
          ->setTo($emailsto)
          ->setBody($email->message, 'text/html');
        $result = $mailer->send($message);
        
        print "Sent ".$email->type." email to: ".$email->emailto."\n";
    }

    sleep(1);
}
