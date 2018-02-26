<?php
    global $path;
    $version = 1;
?>
<script type="text/javascript" src="<?php echo $path; ?>Modules/user/user.js?ver=<?php echo $version; ?>"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/feed/feed.js?ver=<?php echo $version; ?>"></script>
<script type="text/javascript" src="<?php echo $path; ?>Lib/tablejs/table.1.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Lib/tablejs/custom-table-fields.js"></script>
<link href="<?php echo $path; ?>Lib/bootstrap-datetimepicker-0.0.11/css/bootstrap-datetimepicker.min.css" rel="stylesheet">
<script type="text/javascript" src="<?php echo $path; ?>Lib/bootstrap-datetimepicker-0.0.11/js/bootstrap-datetimepicker.min.js"></script>

<style>
input[type="text"] {
    width: 88%;
}

.icon-circle-arrow-down {
    cursor:pointer
}
</style>

<br>

<div id="apihelphead"><div style="float:right;"><a href="<?php echo $path; ?>site/api#feed"><?php echo _('Feed API Help'); ?></a></div></div>
<div class="container">
        <div id="localheading"><h2><?php echo _('Feeds'); ?></h2></div>

        <div id="table"></div>

        <div id="nofeeds" class="alert alert-block hide">
                <h4 class="alert-heading"><?php echo _('No feeds created'); ?></h4>
                <p><?php echo _('Feeds are where your monitoring data is stored. The recommended route for creating feeds is to start by creating inputs (see the inputs tab). Once you have inputs you can either log them straight to feeds or if you want you can add various levels of input processing to your inputs to create things like daily average data or to calibrate inputs before storage: '); ?><a href="<?php echo $path; ?>site/api#feed"><?php echo _('Feed API help.'); ?></a></p>
        </div>

        <hr>
        <button id="refreshfeedsize" class="btn btn-small" >Refresh feed size <i class="icon-refresh" ></i></button>
        
        <!--<p style="font-size:12px; color:#aaa; padding-top:15px">Window width: <span id="window_width"></span></p>-->
</div>

<div id="myModal" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" data-backdrop="false">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="myModalLabel"><?php echo _('WARNING deleting a feed is permanent'); ?></h3>
    </div>
    <div class="modal-body">
        <p><?php echo _('Are you sure you want to delete this feed?'); ?></p>
    </div>
    <div class="modal-footer">
        <button class="btn" data-dismiss="modal" aria-hidden="true"><?php echo _('Cancel'); ?></button>
        <button id="confirmdelete" class="btn btn-primary"><?php echo _('Delete permanently'); ?></button>
    </div>
</div>

<div id="ExportModal" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="ExportModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="ExportModalLabel"><b><span id="SelectedExport"></span></b> CSV export</h3>
    </div>
    <div class="modal-body">
    <p>Select the time range and interval that you wish to export: </p>
        <table class="table">
        <tr>
            <td>
                <p><b>Start date & time</b></p>
                <div id="datetimepicker1" class="input-append date">
                    <input id="export-start" data-format="dd/MM/yyyy hh:mm:ss" type="text" />
                    <span class="add-on"> <i data-time-icon="icon-time" data-date-icon="icon-calendar"></i></span>
                </div>
            </td>
            <td>
                <p><b>End date & time</b></p>
                <div id="datetimepicker2" class="input-append date">
                    <input id="export-end" data-format="dd/MM/yyyy hh:mm:ss" type="text" />
                    <span class="add-on"> <i data-time-icon="icon-time" data-date-icon="icon-calendar"></i></span>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <p><b>Interval</b></p>
                <select id="export-interval" >
                    <option value="">Select interval</option>
                    <option value=10>10s</option>
                    <option value=30>30s</option>
                    <option value=60>1 min</option>
                    <option value=300>5 mins</option>
                    <option value=600>10 mins</option>
                    <option value=900>15 mins</option>
                    <option value=1800>30 mins</option>
                    <option value=3600>1 hour</option>
                    <option value=21600>6 hour</option>
                    <option value=43200>12 hour</option>
                    <option value=86400>Daily</option>
                    <option value=604800>Weekly</option>
                    <option value=2678400>Monthly</option>
                    <option value=31536000>Annual</option>
                </select>
            </td>
            <td>
                <p><b>Date time format</b></p>
                <div class="checkbox">
                  <label><input type="checkbox" id="export-timeformat" value="" checked>Excel (d/m/Y H:i:s)</label>
                </div>
                <label>Offset secs (for daily)&nbsp;<input id="export-timezone-offset" type="text" class="input-mini" disabled=""></label>
            </td>
        </tr>
        </table>
            <div class="alert alert-info">
                <p>Selecting an interval shorter than the feed interval will use the feed interval instead.</p>
                <p>Date time in excel format is in user timezone. Offset can be set if exporting in Unix epoch time format.</p>
            </div>
    </div>
    <div class="modal-footer">
        <div id="downloadsizeplaceholder" style="float: left">Estimated download size: <span id="downloadsize">0</span>MB</div>
        <button class="btn" data-dismiss="modal" aria-hidden="true"><?php echo _('Close'); ?></button>
        <button class="btn" id="export">Export</button>
    </div>
</div>

<script>

    var path = "<?php echo $path; ?>";

    // Extemd table library field types
    for (z in customtablefields) table.fieldtypes[z] = customtablefields[z];

    table.element = "#table";

    table.fields = {
        'id':{'title':"<?php echo _('Id'); ?>", 'type':"fixedlink", 'link':path+"graph/"},
        'name':{'title':"<?php echo _('Name'); ?>", 'type':"text"},
        'tag':{'title':"<?php echo _('Tag'); ?>", 'type':"text"},
        'datatype':{'title':"<?php echo _('Datatype'); ?>", 'type':"fixedselect", 'options':['','REALTIME','DAILY','HISTOGRAM']},
        'engine':{'title':"<?php echo _('Engine'); ?>", 'type':"fixedselect", 'options':['MYSQL','TIMESTORE','PHPTIMESERIES','GRAPHITE','PHPTIMESTORE','PHPFINA','PHPFIWA']},
        'public':{'title':"<?php echo _('Public'); ?>", 'type':"icon", 'trueicon':"icon-globe", 'falseicon':"icon-lock"},
        'size':{'title':"<?php echo _('Size'); ?>", 'type':"fixed"},

        'time':{'title':"<?php echo _('Updated'); ?>", 'type':"updated"},
        'value':{'title':"<?php echo _('Value'); ?>",'type':"value"},

        // Actions
        'edit-action':{'title':'', 'type':"edit"},
        'delete-action':{'title':'', 'type':"delete"},
        'view-action':{'title':'', 'type':"iconlink", 'link':path+"graph/"},
        'icon-basic':{'title':'', 'type':"iconbasic", 'icon':'icon-circle-arrow-down'}

    }
    

    table.groupby = 'tag';
    table.deletedata = false;
    resize();
    
    // table.draw();

    update();

    function update()
    {
        var apikeystr = ""; if (feed.apikey!="") apikeystr = "?apikey="+feed.apikey;
        
        $.ajax({ url: path+"feed/list.json"+apikeystr, dataType: 'json', async: true, success: function(data) {
        
            table.data = data;
        
            for (z in table.data)
            {
                if (table.data[z].size<1024*100) {
                    table.data[z].size = (table.data[z].size/1024).toFixed(1)+"kb";
                } else if (table.data[z].size<1024*1024) {
                    table.data[z].size = Math.round(table.data[z].size/1024)+"kb";
                } else if (table.data[z].size>=1024*1024) {
                    table.data[z].size = Math.round(table.data[z].size/(1024*1024))+"Mb";
                }
            }
            table.draw();
            if (table.data.length != 0) {
                $("#nofeeds").hide();
                $("#apihelphead").show();
                $("#localheading").show();
            } else {
                $("#nofeeds").show();
                $("#localheading").hide();
                $("#apihelphead").hide();
            }
        } });
    }

    var updater = setInterval(update, 10000);

    $("#table").bind("onEdit", function(e){
        clearInterval(updater);
    });

    $("#table").bind("onSave", function(e,id,fields_to_update){
        feed.set(id,fields_to_update);
        updater = setInterval(update, 10000);
    });

    $("#table").bind("onDelete", function(e,id,row){
        clearInterval(updater);
        $('#myModal').modal('show');
        $('#myModal').attr('feedid',id);
        $('#myModal').attr('feedrow',row);
    });

    $("#confirmdelete").click(function()
    {
        var id = $('#myModal').attr('feedid');
        var row = $('#myModal').attr('feedrow');
        feed.remove(id);
        table.remove(row);
        update();

        $('#myModal').modal('hide');
        updater = setInterval(update, 10000);
    });

    $("#refreshfeedsize").click(function(){
        $.ajax({ url: path+"feed/updatesize.json", success: function(data){update();} });
    });
    
  // Export feature
  $("#table").on("click",".icon-circle-arrow-down,.icon-download", function(){
    var row = $(this).attr('row');
    if (row == undefined) {
      // is tag group
      $("#export").attr('export-type',"group");
      var group = $(this).attr('group');
      $("#export").attr('group',group);
      var rows = $(this).attr('rows').split(",");
      var feedids = [];
      for (i in rows) { feedids.push(table.data[rows[i]].id); } // get feedids from rowids
      $("#export").attr('feedids',feedids);
      $("#export").attr('feedcount',rows.length);
      $("#SelectedExport").html(group + " tag ("+rows.length+" feeds)");
      calculate_download_size(rows.length);
    } else {
      // is feed
      $("#export").attr('export-type',"feed");
      $("#export").attr('feedid',table.data[row].id);
      var name = table.data[row].tag+": "+table.data[row].name;
      $("#export").attr('name',name);
      $("#SelectedExport").html(name);
      calculate_download_size(1);
    }
    if ($("#export-timezone-offset").val()=="") {
      var timezoneoffset = user.timezoneoffset();
      if (timezoneoffset==null) timezoneoffset = 0;
      $("#export-timezone-offset").val(parseInt(timezoneoffset));
    }
    $('#ExportModal').modal('show');
  });

  $('#datetimepicker1').datetimepicker({
    language: 'en-EN'
  });

  $('#datetimepicker2').datetimepicker({
    language: 'en-EN',
    useCurrent: false //Important! See issue #1075
  });

  $('#datetimepicker1').on("changeDate", function (e) {
    $('#datetimepicker2').data("datetimepicker").setStartDate(e.date);
  });

  $('#datetimepicker2').on("changeDate", function (e) {
    $('#datetimepicker1').data("datetimepicker").setEndDate(e.date);
  });

  now = new Date();
  today = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 00, 00);
  var picker1 = $('#datetimepicker1').data('datetimepicker');
  var picker2 = $('#datetimepicker2').data('datetimepicker');
  picker1.setLocalDate(today);
  picker2.setLocalDate(today);
  picker1.setEndDate(today);
  picker2.setStartDate(today);

  $('#export-interval, #export-timeformat').on('change', function(e) 
  {
    $("#export-timezone-offset").prop("disabled", $("#export-timeformat").prop('checked'));
    if ($("#export").attr('export-type') == 'group') {
      var downloadsize = calculate_download_size($("#export").attr('feedcount')); 
    } else {
      calculate_download_size(1); 
    }
  });

  $('#datetimepicker1, #datetimepicker2').on('changeDate', function(e) 
  {
    if ($("#export").attr('export-type') == 'group') {
      var downloadsize = calculate_download_size($("#export").attr('feedcount')); 
    } else {
      calculate_download_size(1); 
    }
  });
  
  $("#export").click(function()
  {
    var export_start = parse_timepicker_time($("#export-start").val());
    var export_end = parse_timepicker_time($("#export-end").val());
    var export_interval = $("#export-interval").val();
    var export_timezone_offset = parseInt($("#export-timezone-offset").val());
    var export_timeformat = ($("#export-timeformat").prop('checked') ? 1 : 0);
    if (export_timeformat) { export_timezone_offset = 0; }
    if (!export_start) {alert("Please enter a valid start date."); return false; }
    if (!export_end) {alert("Please enter a valid end date."); return false; }
    if (export_start>=export_end) {alert("Start date must be further back in time than end date."); return false; }
    if (export_interval=="") {alert("Please select interval to download."); return false; }
    var downloadlimit = 25;

    if ($(this).attr('export-type') == 'group') {
      var feedids = $(this).attr('feedids');
      var downloadsize = calculate_download_size($(this).attr('feedcount')); 
      url = path+"feed/csvexport.json?ids="+feedids+"&start="+(export_start+(export_timezone_offset))+"&end="+(export_end+(export_timezone_offset))+"&interval="+export_interval+"&timeformat="+export_timeformat+"&name="+$(this).attr('group');
    } else {
      var feedid = $(this).attr('feedid');
      var downloadsize = calculate_download_size(1); 
      url = path+"feed/csvexport.json?id="+feedid+"&start="+(export_start+(export_timezone_offset))+"&end="+(export_end+(export_timezone_offset))+"&interval="+export_interval+"&timeformat="+export_timeformat+"&name="+$(this).attr('name');
    }
    console.log(url);
    if (downloadsize>(downloadlimit*1048576)) {
      var r = confirm("Estimated download file size is large.\nServer could take a long time or abort depending on stored data size.\Limit is "+downloadlimit+"MB.\n\nTry exporting anyway?");
      if (!r) return false;
    }
    window.open(url);
  });

  function calculate_download_size(feedcount){
    var export_start = parse_timepicker_time($("#export-start").val());
    var export_end = parse_timepicker_time($("#export-end").val());
    var export_interval = $("#export-interval").val();
    var export_timeformat_size = ($("#export-timeformat").prop('checked') ? 20 : 11);// bytes per timestamp
    var downloadsize = 0;
    if (!(!$.isNumeric(export_start) || !$.isNumeric(export_end) || !$.isNumeric(export_interval) || export_start > export_end )) { 
      downloadsize=((export_end - export_start) / export_interval) * (export_timeformat_size + (feedcount*7)); // avg bytes per data
    }
    $("#downloadsize").html((downloadsize/1024/1024).toFixed(2));
    var downloadlimit = 25;
    $("#downloadsizeplaceholder").css('color', (downloadsize == 0 || downloadsize > (downloadlimit*1048576) ? 'red' : ''));
    return downloadsize;
  }

  function parse_timepicker_time(timestr){
    var tmp = timestr.split(" ");
    if (tmp.length!=2) return false;

    var date = tmp[0].split("/");
    if (date.length!=3) return false;

    var time = tmp[1].split(":");
    if (time.length!=3) return false;

    return new Date(date[2],date[1]-1,date[0],time[0],time[1],time[2],0).getTime() / 1000;
  }
    
  function parseISOLocal(s) {
      var b = s.split(/\D/);
      return new Date(b[0], b[1]-1, b[2], b[3], b[4], b[5]);
  }
    

$(window).resize(function(){
    resize();
});

function resize() {
    var width = $(window).width();
    $("#window_width").html(width);

    if (width<800) {
        // $("td[field=datatype]").hide();
        // $("th[field=datatype]").hide();
        table.fields["datatype"].display = false;
        table.fields["engine"].display = false;
        table.fields["public"].display = false;
        table.fields["size"].display = false;
        table.fields["tag"].display = false;
    } else {
        table.fields["datatype"].display = true;
        table.fields["engine"].display = true;
        table.fields["public"].display = true;
        table.fields["size"].display = true;
        table.fields["tag"].display = true;
    }
    
    table.draw();
}


</script>
