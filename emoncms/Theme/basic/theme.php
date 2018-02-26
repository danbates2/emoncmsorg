<!doctype html>
<?php
  /*
  All Emoncms code is released under the GNU Affero General Public License.
  See COPYRIGHT.txt and LICENSE.txt.

  ---------------------------------------------------------------------
  Emoncms - open source energy visualisation
  Part of the OpenEnergyMonitor project:
  http://openenergymonitor.org
  */
  global $ltime,$path,$fullwidth,$menucollapses,$emoncms_version,$theme,$verify;
  
  $themecolor = "standard";
?>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Emoncms - <?php echo $route->controller.' '.$route->action.' '.$route->subaction; ?></title>
        <link rel="shortcut icon" href="<?php echo $path; ?>Theme/<?php echo $theme; ?>/favicon.png" />
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black">
        <link rel="apple-touch-startup-image" href="<?php echo $path; ?>Theme/<?php echo $theme; ?>/ios_load.png">
        <link rel="apple-touch-icon" href="<?php echo $path; ?>Theme/<?php echo $theme; ?>/logo_normal.png">
        <!--<link href="<?php echo $path; ?>Lib/bootstrap/css/bootstrap.min.css" rel="stylesheet">
        <link href="<?php echo $path; ?>Lib/bootstrap/css/bootstrap-responsive.min.css" rel="stylesheet">
        <link href="<?php echo $path; ?>Lib/bootstrap-datetimepicker-0.0.11/css/bootstrap-datetimepicker.min.css" rel="stylesheet">
        -->
        <link href="<?php echo $path; ?>Lib/bootstrap/css/bootstrap-combined.min.css" rel="stylesheet">
        
        <?php if ($themecolor=="blue") { ?>
            <link href="<?php echo $path; ?>Theme/<?php echo $theme; ?>/emon-blue.css" rel="stylesheet">
        <?php } else { ?>
            <link href="<?php echo $path; ?>Theme/<?php echo $theme; ?>/emon-standard.css" rel="stylesheet">
        <?php } ?>
        
        <script type="text/javascript" src="<?php echo $path; ?>Lib/jquery-1.11.3.min.js"></script>
    </head>
    <body>
        <div id="wrap">
        
        <div id="emoncms-navbar" class="navbar navbar-inverse navbar-fixed-top">
            <div class="navbar-inner">
                    <?php  if ($menucollapses) { ?>
                    <style>
                        /* this is menu colapsed */
                        @media (max-width: 979px){
                          .menu-description {
                            display: inherit !important ;
                          }
                        }
                        @media (min-width: 980px) and (max-width: 1200px){
                          .menu-text {
                            display: none !important;
                          }
                        }
                    </style>
                    <button type="button" class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
                        <img src="<?php echo $path; ?>Theme/<?php echo $theme; ?>/favicon.png" style="width:28px;"/>
                    </button>

                    <div class="nav-collapse collapse">
                    <?php } else { ?>
                        <style>
                            @media (max-width: 1200px){
                              .menu-text {
                                display: none !important;
                              }
                            }
                            @media (max-width: 480px){
                              .menu-dashboard {
                                display: none !important;
                              }
                            }

                            @media (max-width: 320px){
                              .menu-extra {
                                display: none !important;
                              }
                            }
                        </style>
                    <?php } ?>
                    <?php
                        echo $mainmenu;
                    ?>
                    <?php
                        if ($menucollapses) {
                    ?>
                    </div>
                    <?php
                        }
                    ?>
            </div>
        </div>

        <div id="topspacer"></div>
        
        <!--<div style="background-color:#f3a48b; color:#fff; padding:10px;">Emoncms.org will be offline between 9:45am and 10:45am today, see <a href="https://community.openenergymonitor.org/t/emoncms-org-downtime-tomorrow-17th-october/5371">forum thread</a></div>-->

        <?php if (isset($session['emailverified']) && !$session['emailverified']) { ?>
        <div id="verifymessage" style="background-color:#209ed3; color:#fff; padding:10px;"><i class="icon-exclamation-sign icon-white"></i> Your account email address is not verified. <span style="color:rgba(255,255,255,0.8)">You can amend your email address on the account page.</span><button id="send-verification-email" class="btn btn-small" style="float:right; margin-top:-2px">Request or re-send verification email</button></div>
        <?php } else if (isset($verify)) { ?>
        <div id="verifymessage" style="background-color:#209ed3; color:#fff; padding:10px;"><?php echo $verify['message']; ?></div>
        <?php } ?>

        <?php if (isset($submenu) && ($submenu)) { ?>
          <div id="submenu">
              <div class="container">
                  <?php echo $submenu; ?>
              </div>
          </div><br>
        <?php } ?>

        <?php
          if ($fullwidth && $route->controller=="dashboard") {
        ?>
        <div>
            <?php echo $content; ?>
        </div>
        <?php } else if ($fullwidth) { ?>
        <div class = "container-fluid"><div class="row-fluid"><div class="span12">
            <?php echo $content; ?>
        </div></div></div>
        <?php } else { ?>
        <div class="container">
            <?php echo $content; ?>
        </div>
        <?php } ?>

        <div style="clear:both; height:60px;"></div>
        </div>

        <div id="footer">
            <?php echo _('Powered by '); ?>
            <a href="http://openenergymonitor.org">openenergymonitor.org</a>
            <span><?php echo $emoncms_version; ?></span>
        </div>
        <script type="text/javascript" src="<?php echo $path; ?>Lib/bootstrap/js/bootstrap.js"></script>
    </body>
    
    <?php if (isset($session['emailverified']) && !$session['emailverified']) { ?>
    <script>
    $("#send-verification-email").click(function(){
        
        $.ajax({
          url: "<?php echo $path; ?>user/resend-verify.json",
          data: "&username="+encodeURIComponent("<?php echo $session['username']; ?>"),
          dataType: "json",
          success: function(result) {
              $("#verifymessage").html(result.message);
          } 
        });
    });
    </script>
    <?php } ?>
</html>
