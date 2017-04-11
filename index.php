<?
session_start();
$id = session_id();
include "src/include.php";

$max_timeshift = 60; // максимум минут для таймшифта одной записи
$hour_save = 6; // сколько часов хранить записи
$now_time = time();

$uri = explode("/", $_SERVER['REQUEST_URI']);
$uri = explode("?", $uri[1]);
$_SESSION['uri'] = $uri[0];


if(isset($_GET['pause'])){

  // удаляем мусор
  $tmp_dir = __DIR__ . "/tmp";
  foreach(scandir($tmp_dir) as $v){
    if($v != "." and $v != ".." and $v != "index.html" and (filectime("{$tmp_dir}/{$v}")<$now_time-($hour_save*3600))){
      @unlink("{$tmp_dir}/{$v}");
    }
  }

  $channels = fileread(__DIR__ . "/src/channels.db" );
  $end_time = $now_time+$max_timeshift*60;

  exec("pidof astra_{$id}", $pid);
  if(count($pid)) exec("kill -9 " . implode("", $pid));
  if(!file_exists(__DIR__ . "/tmp/astra_{$id}")){
    $exe = __DIR__ . "/tmp/astra_{$id}";
    copy(__DIR__ . "/src/astra", $exe);
    chmod($exe, 0777);
  }
  @unlink(__DIR__ . "/tmp/{$id}.lua");
  @unlink(__DIR__ . "/tmp/{$id}.ts");
  $_SESSION['file_start'] = time();
  
  $conf = "#!/usr/bin/env " . __DIR__ . "/tmp/astra_{$id}\n\n";
  $conf .= "--" . date("d.m.Y H:i", $now_time) . "..." . date("H:i", $end_time) . "\n";
  $conf .= "--{$_SESSION['uri']}\n";
  $conf .= "pidfile('/var/run/astra_{$id}.pid')\n\n";
  $conf .= "timer({

    interval = 1,
    callback = function(self)
            local current_date_time = os.date('*t')
            local current_time = current_date_time.hour * 100 + current_date_time.min
            if (current_time == " . intval(date("Hi", $end_time)) . ") then
                os.exit()
            end
    end

})

make_channel({
    name = 'astra_{$id}',
    input = {
        '" . @$channels[$_SESSION['uri']][1] . "',
    },
    output = {
        'file://" . __DIR__ . "/tmp/{$id}.ts',
    }
})\n";

  $file = __DIR__ . "/tmp/{$id}.lua";
  file_put_contents($file, $conf);
  chmod($file, 0777);
  exec("sudo /usr/bin/screen -dmS timeshift_{$id} " . $file);
  die("ok");
}else if(isset($_GET['getinfo'])){
  $start = @$_SESSION['file_start'];
  $getinfo = array('size' => round(@filesize(__DIR__ . "/tmp/{$id}.ts")/1048576,2),
                   'start' => date("H:i:s", $start),
                   'now' => date("H:i:s", $now_time),
                   'total' => date("H:i:s", ($now_time-$start)-3600*3) // коррекция UTC -3часа
  );
  echo json_encode($getinfo,1);
  exit;
}


echo _header();

?><object id="video" type="video/broadcast"   style="position: absolute; left: 0px; top: 0px; width: 1280px; height: 720px"  ></object>
<object id="appmgr" type="application/oipfApplicationManager"   style="position: absolute; left: 0px; top: 0px; width: 0px; height: 0px"  ></object><object id="oipfcfg" type="application/oipfConfiguration"   style="position: absolute; left: 0px; top: 0px; width: 0px; height: 0px"  ></object>

<img style='position:absolute;display:none;' id='loader' width='100' src="/src/preloader.gif" alt="" />

<?
$channels = fileread(__DIR__ . "/src/channels.db");
if(!isset($channels[$_SESSION['uri']])){
  echo "<div style='color:red;top:300px;background-color:#000;padding:20px;left:435px;'>Отредактируйте файл `/channels.db`<br />Канал `{$_SESSION['uri']}` не найден!</div>";
}else{
  echo '<div id="appendix" style="bottom:2px;right:3px;font:15px/1em Tahoma;color:white;background-color:#333;border-radius:3px;padding:3px 5px;opacity:0.8;">Канал ' . mb_strtoupper($_SESSION['uri']) . ' можно поставить на ПАУЗУ (не более ' . $max_timeshift . ' мин.)</div>';
}
?>

<div id='message' style='opacity:0.8;background-color:#333;width:450px;height:250px;bottom:0px;left:420px;color:#eee;padding:10px;display:none;'>
<div id='flash' style='width:100%;text-align:center;color:#fff;top:18px;' >ВКЛЮЧЁН TIMESHIFT!</div>
<div style='width:90%;top:55px;left:22px;border-top:1px solid #ccc;'></div>
<div style='top:70px;color:#eee;height:110px;left:20px;'>Время старта: <span id='start'>-</span><br />Время сейчас: <span id='now'>-</span><br />Время записи: <span id='total'>-</span> (max <?=$max_timeshift?> мин)<br />Объём файла: <span id='size'>-</span></div>
<div style='width:90%;top:188px;left:22px;border-top:1px solid #ccc;'></div>
<div style='left:20px;bottom:30px;'><span style='padding:5px 15px;background-color:#eee;color:#111;'>▶</span> старт с таймшифтом</div>
<div style='left:20px;bottom:0px;'><span style='padding:5px 15px;background-color:#eee;color:#111;'>■</span> выход</div>

</div>

<script type="text/javascript">
//<![CDATA[

var c;

$(function(){
  initVideo();
  registerKeyEventListener();
  initApp();
  
  setTimeout(function(){
    $('#appendix').fadeOut();
  }, 15000); //15 sec
  
  setKeyset(0x1+0x2+0x4+0x8+0x10+0x20+0x100);
});



function handleKeyCode(kc) {
  if (kc==VK_PLAY){
    $('#loader').show();
    setTimeout(function(){
      document.location.href='/player.php';
    }, 1000);
    return true;
  }else if (kc==VK_STOP){
    document.location.href='/<?=$_SESSION['uri']?>';
    return true;
  }else if (kc==VK_PAUSE || kc == VK_1){
    $.get("/<?=$_SESSION['uri']?>?pause", function(r){
      if(r=="ok"){
        $("#appendix").hide();
        $("#message").show();
        clearInterval(c);
        c = setInterval(function(){
          $("#flash").fadeToggle();
          $.get('/<?=$_SESSION['uri']?>?getinfo', function(r){
            $("#size").text(r.size+" МБайт");
            $("#start").text(r.start);
            $("#now").text(r.now);
            $("#total").text(r.total);
          }, 'json');
        },1000);
      }
    });
    return true;
  }
  return false;
};

//]]>
</script>
<?=_footer()?>