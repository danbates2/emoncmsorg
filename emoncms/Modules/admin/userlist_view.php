<?php
  defined('EMONCMS_EXEC') or die('Restricted access');
  global $path;
  global $fullwidth;
  $fullwidth = true;
?>
<script type="text/javascript" src="<?php echo $path; ?>Lib/tablejs/table.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Lib/tablejs/custom-table-fields.js"></script>

<h2>Users</h2>

<p>Number of users: <span id="numberofusers"></span></p>

<style>

.afeed {
    color:#00aa00;
    font-weight:bold;
}

</style>

<div class="pagination">
  <ul>
  </ul>
</div>

<div class="input-prepend">
  <span class="add-on">Order by</span>
  <select id="orderby" style="width:120px">
    <option value="id">Id</option>
    <option value="username">Username</option>
    <option value="email">Email</option>
    <option value="lastactive">Last active</option>
    <option value="inputs">Inputs</option>
    <option value="activefeeds">Active Feeds</option>
    <option value="feeds">Feeds</option>
    <option value="server">Server</option>
    <option value="diskuse" selected>Diskuse</option>
    <option value="email_verified">Email Verified</option>
  </select>
  
  <select id="order" style="width:120px">
    <option value="ascending">Ascending</option>
    <option value="decending" selected>Decending</option>
  </select>
</div>

<div class="input-prepend input-append" style="padding-left:20px">
  <span class="add-on">User search</span>
  <input id="user-search-key" type="text" />
  <button class="btn" id="user-search">Search</button>
</div>

<table class="table">
  <tr>
    <th>Id</th>
    <th>Username</th>
    <th>Email</th>
    <th>Last active</th>
    <th>Inputs</th>
    <th>Feeds</th>
    <th>Fina/Ts</th>
    <th>Server (0,1,2)</th>
    <th>Diskuse</th>
  </tr>
  <tbody id="users"></tbody>
</table>


<div class="pagination">
  <ul>
  </ul>
</div>

<script>

var path = "<?php echo $path; ?>";
var users = {};

var admin = {
   
    'numberofusers':function()
    {
        var result = 0;
        $.ajax({ url: path+"admin/numberofusers", dataType: 'text', async: false, success: function(data) {result = data;} });
        return result;
    },
    
    'userlist':function(page,perpage,orderby,order,searchq)
    {
        console.log("userlist: "+page+" "+perpage+" "+orderby+" "+searchq);
        var searchstr = "";
        if (searchq!=false) searchstr = "&search="+searchq;
        var result = {};
        $.ajax({ url: path+"admin/userlist.json?page="+(page-1)+"&perpage="+perpage+"&orderby="+orderby+"&order="+order+searchstr, dataType: 'json', async: false, success: function(data) 
        {
            for (var z in data)
            {
                if (data[z].diskuse<1024*100) {
                    data[z].diskuse = (data[z].diskuse/1024).toFixed(1)+" kb";
                } else if (data[z].diskuse<1024*1024) {
                    data[z].diskuse = Math.round(data[z].diskuse/1024)+" kb";
                } else if (data[z].diskuse>=1024*1024) {
                    data[z].diskuse = Math.round(data[z].diskuse/(1024*1024))+" Mb";
                }
                
            }
            result = data;
        }
        });
        return result;
    }
}
  
// -------------------------------------------------------------------------------------------

var number_of_users = admin.numberofusers();
var users_per_page = 250;
var number_of_pages = Math.ceil(number_of_users / users_per_page);
var orderby = "diskuse";
var page = 1;
var order = "decending";
var searchq = false;
  
var out = "";
for (var z=0; z<number_of_pages; z++) {
    out += '<li><a class="pageselect" href="#">'+(z+1)+'</a></li>';
}
$(".pagination").find("ul").html(out);
$("#numberofusers").html(number_of_users);

users = admin.userlist(page,250,orderby,order,searchq);
table_draw();

$(".pagination").on("click",".pageselect",function(){
    page = $(this).html();
    users = admin.userlist(page,250,orderby,order,searchq);
    table_draw();
});

$("#orderby").change(function(){
   orderby = $(this).val();
   users = admin.userlist(page,250,orderby,order,searchq);
   table_draw();
});

$("#order").change(function(){
   order = $(this).val();
   users = admin.userlist(page,250,orderby,order,searchq);
   table_draw();
});

$("#user-search").click(function(){
    searchq = $("#user-search-key").val();
    users = admin.userlist(page,250,orderby,order,searchq);
    table_draw();
});

function table_draw() {
  var out = "";
  for (var z in users) {
  
      var email_verified = users[z].email_verified*1;
  
      if (email_verified) {
          
          out += "<tr style='background-color:rgba(0,255,0,0.2)'>";
      } else {
          out += "<tr>";
      }
      out += "<td><a href='../admin/setuser.json?id="+users[z].id+"'>"+users[z].id+"</a></td>";
      out += "<td>"+users[z].username+"</td>";
      out += "<td>"+users[z].email+"</td>";
      out += "<td>"+printdate(users[z].lastactive*1000)+"</td>";
      out += "<td>"+users[z].inputs+"</td>";
      out += "<td><span class='afeed'>"+users[z].activefeeds+"</span>/<b>"+users[z].feeds+"</b></td>";
      out += "<td>";
      out += "<span class='label label-success'>"+users[z].phpfina+"</span>"
      out += "<span class='label label-info'>"+users[z].phptimeseries+"</span>"
      out += "</td>";
      
      out += "<td>";
      out += "<span class='label label-info'>"+users[z].server0+"</span>"
      out += "<span class='label label-warning'>"+users[z].server1+"</span>"
      out += "<span class='label label-important'>"+users[z].server2+"</span>"
      out += "</td>";
      out += "<td>"+users[z].diskuse+"</td>";
      
      out += "</tr>";
  }
  $("#users").html(out);
}

function printdate(timestamp)
{
    var date = new Date();
    
    var date = new Date(timestamp);
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var year = date.getFullYear();
    var month = months[date.getMonth()];
    var day = date.getDate();
    
    var minutes = date.getMinutes();
    if (minutes<10) minutes = "0"+minutes;
    
    var datestr = date.getHours()+":"+minutes+" "+day+" "+month+" "+year;
    if (timestamp==0) datestr = "";
    return datestr;
};
</script>
