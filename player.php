<?
session_start();
$id = session_id();
include "src/include.php";


if(file_exists(__DIR__ . "/tmp/{$id}.ts")){

  $_SESSION['file_end'] = @filectime(__DIR__ . "/tmp/{$id}.ts");
  echo _header();

?>

<div id="vidcontainer" style="left: 0px; top: 0px; width: 1280px; height: 720px;"><object id="video" type="video/mpeg" style="position: absolute; left: 0px; top: 0px; width: 100%; height: 100%;"></object></div>

<object id="appmgr" type="application/oipfApplicationManager" style="position: absolute; left: 0px; top: 0px; width: 0px; height: 0px;"></object><object id="oipfcfg" type="application/oipfConfiguration" style="position: absolute; left: 0px; top: 0px; width: 0px; height: 0px;"></object>

<div style='top:0;right:0;color:#000;background-color:#fff;padding:0 10px; display:none;' id='plinfo'></div>
<img style='position:absolute;' id='loader' width='100' src="/src/preloader.gif" alt="" />
<div style='background-color:#000;bottom:0px;right:0px;' id='timeshift'>...</div>

<script type="text/javascript">
//<![CDATA[

var pause=false;
var start_time=<?=@$_SESSION['file_start']?>;
var real_time=0;
var last_time=<?=$_SESSION['file_end']-$_SESSION['file_start']?>*1000;
var flag = true;
var utc = 10800000;
var pl = 2; // not state
var url = "http://<?=$_SERVER['SERVER_NAME']?>/tmp/<?=$id?>.ts";

$(function(){
  registerKeyEventListener();
  initApp();
  playvideo(0);
  
  setInterval(function(){
    var videlem = document.getElementById('video');
    if(flag==true){
      real_time = videlem.playPosition;
    }else{
      if(pl==1){
        videlem.seek(real_time);
        flag = true;
      }
      pl++;
    }
    timeshift_update();
  },1000);
  
  setKeyset(0x1+0x2+0x4+0x8+0x10+0x20+0x100);
});


function timeshift_update(){
  $("#timeshift").text(show_time(real_time-utc)+" / "+show_time(last_time-utc));
}


function playvideo(){
    var videlem = document.getElementById('video');
    videlem.onPlayStateChange = function() {
      if(videlem.playState == 5){
        document.location.href = '/<?=$_SESSION['uri']?>';
      }else if(videlem.playState == 1){
        $("#loader").hide();
      }else{
        if(pause==false)$("#loader").show();
      }
    };
 
    videlem.data = url;
    videlem.play(1);
}


function handleKeyCode(kc) {
  var videlem = document.getElementById('video');
  if(kc==VK_PLAY && pause == true){
    videlem.play(1);
    $("#plinfo").hide();
    pause = false;
    return true;
  }else if (kc==VK_PAUSE) {
    if(pause==false){
      videlem.play(0);
      $("#plinfo").text("PAUSE").show();
      pause = true;
    }else{
      videlem.play(1);
      $("#plinfo").hide();
      pause = false;
    }
    return true;
  }else if (kc==VK_STOP) {
    document.location.href='/<?=$_SESSION['uri']?>';
    return true;
  }else if(kc==VK_FAST_FWD){
    flag = false;pl=0;
    real_time += 15000; // +15 sec
    if(real_time>last_time){
      real_time=last_time;
      document.location.href='/<?=$_SESSION['uri']?>';
    }
    timeshift_update();
    return false;
  }else if(kc==VK_REWIND){
    flag = false;pl=0;
    real_time -= 15000; // -15 sec
    if(real_time<0)real_time=0;
    timeshift_update();
    return false;
  }
  return false;
};



function show_time(t){
    var date = new Date(t);
    var hours = date.getHours();
    var minutes = date.getMinutes();
    var seconds = date.getSeconds();
    if (hours < 10) 
      hours = '0' + hours;
    if (minutes < 10) 
      minutes = '0' + minutes;
    if (seconds < 10) 
      seconds = '0' + seconds;
    var str = hours + ':' + minutes + ':' + seconds;
    return str;
}

//]]>
</script>
<?
}else{

echo "<div style='color:red;top:300px;background-color:#000;padding:20px;left:520px;'>Файл не найден!</div>";

?>

<script type="text/javascript">
//<![CDATA[

$(function(){
  registerKeyEventListener();
  initApp();
});

//]]>
</script>

<?
}
?>


<?=_footer();?>