/*

  table.js is released under the GNU Affero General Public License.
  See COPYRIGHT.txt and LICENSE.txt.

  Part of the OpenEnergyMonitor project:
  http://openenergymonitor.org
 
*/

var customtablefields = {

    'icon':
    {
        'draw': function(row,field)
        {
            if (table.data[row][field] == true) return "<i class='"+table.fields[field].trueicon+"' type='input' style='cursor:pointer'></i>";
            if (table.data[row][field] == false) return "<i class='"+table.fields[field].falseicon+"' type='input' style='cursor:pointer'></i>";
        },

        'event': function()
        {
            // Event code for clickable switch state icon's
            $(table.element).on('click', 'i[type=input]', function() {
                var row = $(this).parent().attr('row');
                var field = $(this).parent().attr('field');
                if (!table.data[row]['#READ_ONLY#']) {
                    table.data[row][field] = !table.data[row][field];

                    var fields = {};
                    fields[field] = table.data[row][field];

                    $(table.element).trigger("onSave",[table.data[row]['id'],fields]);
                    if (table.data[row][field]) $(this).attr('class', table.fields[field].trueicon); else $(this).attr('class', table.fields[field].falseicon);
                    table.draw();
                }
            });
        }
    },

    'updated':
    {
        'draw': function (row,field) { return list_format_updated(table.data[row][field]) }
    },

    'value':
    {
        'draw': function (row,field) { return list_format_value(table.data[row][field]) }
    },

    'processlist':
    {
        'draw': function (row,field) { 

          var processlist = table.data[row][field];
          if (!processlist) return "";
          
          var processPairs = processlist.split(",");

          var out = "";

          for (z in processPairs)
          {
            var keyvalue = processPairs[z].split(":");

            var key = parseInt(keyvalue[0]);
            var type = "";
            var color = "";

            switch(key)
            {
              case 1:
                key = 'log'; type = 2; break;
              case 2:  
                key = 'x'; type = 0; break;
              case 3:  
                key = '+'; type = 0; break;
              case 4:    
                key = 'kwh'; type = 2; break;
              case 5:  
                key = 'kwhd'; type = 2; break;
              case 6:
                key = 'x inp'; type = 1; break;
              case 7:
                key = 'ontime'; type = 2; break;
              case 8:
                key = 'kwhinckwhd'; type = 2; break;
              case 9:
                key = 'kwhkwhd'; type = 2; break;
              case 10:  
                key = 'update'; type = 2; break;
              case 11: 
                key = '+ inp'; type = 1; break;
              case 12:
                key = '/ inp'; type = 1; break;
              case 13:
                key = 'phaseshift'; type =2; break;
              case 14:
                key = 'accumulate'; type = 2; break;
              case 15:
                key = 'rate'; type = 2; break;
              case 16:
                key = 'hist'; type = 2; break;
              case 17:  
                key = 'average'; type = 2; break;
              case 18:
                key = 'flux'; type = 2; break;
              case 19:
                key = 'pwrgain'; type = 2; break;
              case 20:
                key = 'pulsdiff'; type = 2; break;
              case 21:
                key = 'kwhpwr'; type = 2; break;
              case 22:
                key = '- inp'; type = 1; break;
              case 23:
                key = 'kwhkwhd'; type = 2; break;
              case 24:
                key = '> 0'; type = 3; break;
              case 25:
                key = '< 0'; type = 3; break;
              case 26:
                key = 'unsign'; type = 3; break;
              case 27:
                key = 'max'; type = 2; break;
              case 28:
                key = 'min'; type = 2; break;
              case 29:
                key = '+ feed'; type = 4; break;
              case 30:
                key = '- feed'; type = 4; break;
              case 31:
                key = 'x feed'; type = 4; break;
              case 32:
                key = '/ feed'; type = 4; break;
              case 33:
                key = '= 0'; type = 3; break;
              case 34:
                key = 'whacc'; type = 2; break;
              case 35:
                key = 'MQTT'; type = 5; break;
              case 36:
                key = 'null'; type = 3; break;
              case 37:
                key = 'ori'; type = 3; break;
              case 38:
                key = '!sched 0'; type = 6; break;
              case 39:
                key = '!sched N'; type = 6; break;
              case 40:
                key = 'sched 0'; type = 6; break;
              case 41:
                key = 'sched N'; type = 6; break;
              case 42:
                key = '0? skip'; type = 3; break;
              case 43:
                key = '!0? skip'; type = 3; break;
              case 44:
                key = 'N? skip'; type = 3; break;
              case 45:
                key = '!N? skip'; type = 3; break;
              case 46:
                key = '>? skip'; type = 0; break;
              case 47:
                key = '>=? skip'; type = 0; break;
              case 48:
                key = '<? skip'; type = 0; break;
              case 49:
                key = '<=? skip'; type = 0; break;
              case 50:
                key = '=? skip'; type = 0; break;
              case 51:
                key = '!=? skip'; type = 0; break;
              case 52:
                key = 'GOTO'; type = 0; break;
            }  

            value = keyvalue[1];
            
            switch(type)
            {
              case 0:
                type = 'user value: '; color = 'important';
                break;
              case 1:
                type = 'input: '; color = 'warning';
                break;
              case 2:
                type = 'feed: '; color = 'info';
                break;
              case 3:
                type = ''; color = 'important';
                value = ''; // Argument type is NONE, we don't mind the value
                break;
              case 4:
                type = 'feed: '; color = 'warning';
                break;
              case 5:
                type = 'topic: '; color = 'info';
                break;
              case 6:
                type = 'schedule: '; color = 'warning';
                break;
            }

            if (type == 'feed: ') { 
              out += "<a target='_blank' href='"+path+"graph/"+value+"'<span class='label label-"+color+"' title='"+type+value+"' style='cursor:pointer'>"+key+"</span></a> "; 
            } else {
              out += "<span class='label label-"+color+"' title='"+type+value+"' style='cursor:default'>"+key+"</span> ";
            }
          }
          
          return out;
        }
    },

    'iconlink':
    {
        'draw': function (row,field) { 
          var icon = 'icon-eye-open'; if (table.fields[field].icon) icon = table.fields[field].icon;
          return "<a href='"+table.fields[field].link+table.data[row]['id']+"' ><i class='"+icon+"' ></i></a>" 
        }
    },

    'iconbasic':
    {
        'draw': function(row,field)
        {
            return "<i class='"+table.fields[field].icon+"' type='icon' row='"+row+"' style='cursor:pointer'></i>";
        }
    },
    
    'hinteditable':
    {
        'draw': function (row,field) { return "…";},
        'edit': function (row,field) { return "<input type='text' value='"+table.data[row][field]+"' / >" },
        'save': function (row,field) { return $("[row="+row+"][field="+field+"] input").val() }
    },

    'iconconfig':
    {
        'draw': function(row,field)
        {
            return table.data[row]['#NO_CONFIG#'] ? "" : "<i class='"+table.fields[field].icon+"' type='icon' row='"+row+"' style='cursor:pointer'></i>";
        }
    },

    'date': {
        'draw': function (t,row,child_row,field) {
            var date = new Date();
            date.setTime(1000 * t.data[row][field]); //from seconds to miliseconds
            return (date.getDate() + '/' + (date.getMonth() + 1) + '/' + date.getFullYear() + ' ' + date.getHours() + ':' + date.getMinutes());
        },
        'edit':function (t,row,child_row,field) {
            var date= new Date();
            date.setTime(1000 * t.data[row][field]); //from seconds to miliseconds
            var day = date.getDate();
            var month = date.getMonth() +1; // getMonth() returns 0-11
            var year = date.getFullYear();
            var hours= date.getHours();
            var minutes = date.getMinutes();
            return '<div class="input-append date" id="'+field +'-'+row+'-'+t.data[row][field]+'" data-format="dd/MM/yyyy hh:mm" data-date="'+day+'/'+month+'/'+year+' '+hours+':'+minutes+'"><input data-format="dd/MM/yyyy hh:mm" value="'+day+'/'+month+'/'+year+' '+hours+':'+minutes+'" type="text" /><span class="add-on"> <i data-time-icon="icon-time" data-date-icon="icon-calendar"></i></span></div>';
        },
        'save': function (t,row,child_row,field) { 
            return parse_timepicker_time($("[row='"+row+"'][child_row='"+child_row+"'][field='"+field+"'] input").val());
        }    
    },
  
    'fixeddate': {
        'draw': function (t,row,child_row,field) {
            var date = new Date();
            date.setTime(1000 * t.data[row][field]); //from seconds to miliseconds
            return (date.getDate() + '/' + (date.getMonth() + 1) + '/' + date.getFullYear() + ' ' + date.getHours() + ':' + date.getMinutes());
        }
    }
}



// Calculate and color updated time
function list_format_updated(time)
{
  time = time * 1000;
  var now = (new Date()).getTime() - table.timeServerLocalOffset;
  var update = (new Date(time)).getTime();

  var secs = (now-update)/1000;
  var mins = secs/60;
  var hour = secs/3600;
  var day = hour/24;

  var updated = secs.toFixed(0)+"s ago";
  if (secs< 0) updated = secs.toFixed(0)+"s ahead";
  else if (secs.toFixed(0) == 0) updated = "now";
  else if (day>7) updated = "inactive";
  else if (day>2) updated = day.toFixed(1)+" days ago";
  else if (hour>2) updated = hour.toFixed(0)+" hrs ago";
  else if (secs>180) updated = mins.toFixed(0)+" mins ago";

  secs = Math.abs(secs);
  var color = "rgb(255,0,0)";
  if (secs<25) color = "rgb(50,200,50)"
  else if (secs<60) color = "rgb(240,180,20)"; 
  else if (secs<(3600*2)) color = "rgb(255,125,20)"

  return "<span style='color:"+color+";'>"+updated+"</span>";
}

// Format value dynamically 
function list_format_value(value) {
  if (value == null) return 'NULL';
  value = parseFloat(value);
  if (value>=1000) value = parseFloat((value).toFixed(0));
  else if (value>=100) value = parseFloat((value).toFixed(1));
  else if (value>=10) value = parseFloat((value).toFixed(2));
  else if (value<=-1000) value = parseFloat((value).toFixed(0));
  else if (value<=-100) value = parseFloat((value).toFixed(1));
  else if (value<10) value = parseFloat((value).toFixed(2));
  return value;
}

function list_format_size(bytes) {
  if (!$.isNumeric(bytes)) {
    return "n/a";
  } else if (bytes<1024) {
    return bytes+"B";
  } else if (bytes<1024*100) {
    return (bytes/1024).toFixed(1)+"KB";
  } else if (bytes<1024*1024) {
    return Math.round(bytes/1024)+"KB";
  } else if (bytes<=1024*1024*1024) {
    return Math.round(bytes/(1024*1024))+"MB";
  } else {
    return (bytes/(1024*1024*1024)).toFixed(1)+"GB";
  }
}

  function parse_timepicker_time(timestr){
    var tmp = timestr.split(" ");
    if (tmp.length!=2) return false;

    var date = tmp[0].split("/");
    if (date.length!=3) return false;

    var time = tmp[1].split(":");
    if (time.length!=2) return false;

return new Date(date[2],date[1]-1,date[0],time[0],time[1],0).getTime() / 1000;
}
