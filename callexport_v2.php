<?php
set_include_path('.:/usr/share/php:/root/bin');
include 'asterisk/config.php';
require 'asterisk/utils.php';
const DEBUG = TRUE;

date_default_timezone_set('Asia/Yekaterinburg');

function normPhone($phone) {
  $resPhone = preg_replace("/[^0-9]/", "", $phone);

  if (strlen($resPhone) === 5) {
    $resPhone = '8343'.$resPhone;
  }
  if (strlen($resPhone) === 10) {
    $resPhone = '8'.$resPhone;
  }
  if (strlen($resPhone) === 11) {
    $resPhone = preg_replace("/^7/", "8", $resPhone);
  }
  return $resPhone;
}

function lock($name) {
    $lock = sys_get_temp_dir()."/$name.lock";
    $aborted = file_exists($lock) ? filemtime($lock) : null;
    $fp = fopen($lock, 'w');

    if (!flock($fp, LOCK_EX|LOCK_NB)) {
        // заблокировать файл не удалось, значит запущена копия скрипта
        return false;
    }
    // получили блокировку файла

    // если файл уже существовал значит предыдущий запуск кто-то прибил извне
    if ($aborted) {
        error_log(sprintf("Запуск скрипта %s был завершен аварийно %s", $name, date('c', $aborted)));
    }

    // снятие блокировки по окончанию работы
    // если этот callback, не будет выполнен, то блокировка
    // все равно будет снята ядром, но файл останется
    register_shutdown_function(function() use ($fp, $lock) {
        flock($fp, LOCK_UN);
        fclose($fp);
        unlink($lock);
    });

    return true;
}

   if (!lock('callexport')) { exit; }

   $host = $ast_db_host;
   $db   = $ast_db;
   $user = $ast_db_user;
   $password = $ast_db_password;
   $charset = 'utf8';

   $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
   $opt = [
       PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
       PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
       PDO::ATTR_EMULATE_PREPARES   => false,
   ];
   try {
        $dbh = new PDO($dsn, $user, $password);
  } catch (PDOException $e) {
        die('Подключение не удалось: ' . $e->getMessage());
  }

    $stmt = $dbh->query('SELECT lastdate  FROM synclog order by lastdate desc');
    if ($stmt->rowCount() > 0)
    {
        $row = $stmt->fetch();
        $lastdate = $row['lastdate'];
    } else  {
      $lastdate = '2000-01-01';
    }

    $syncquery = "select distinct uniqueid, recordingfile from cdr where did  in ('74952216556', '73432047604') and calldate >= '2018-07-24' and posted is NULL and dst in  ('206', '404', '401',  '304', '305', '208', '201', '205', '213' ) order by calldate";
    if (DEBUG) echo $syncquery.PHP_EOL;
    $mainstmt = $dbh->query($syncquery);
	  if (DEBUG) print("Количество строк для вставки:\n ");
	  if (DEBUG) print($mainstmt->rowCount().PHP_EOL);
    while ($row = $mainstmt->fetch())
    {
        $linkedid = $row['uniqueid'];
        //$dst = normPhone($row['dst']);
        //$src = normPhone($row['src']);
        $callid = $row['uniqueid'];
        //$started = $row['calldate'];
        //$duration = $row['billsec'];
        //$status = $row['disposition'];
		    //print("$src, $dst, $status".PHP_EOL);
        //if (strlen($src) > 2 && strlen($dst) > 2) {
        if (TRUE) {
          //if ((strlen($dst) == 3) && strlen($src) > 3 && $dst < 200 && $status == 'ANSWERED') {
          // if (TRUE) {
            //incoming
            $direction = 'incoming';
            if (DEBUG) echo PHP_EOL.PHP_EOL."call start ----->".PHP_EOL.PHP_EOL;
            $answered_query = "select cid_num, eventtime  from cel where linkedid = '".$linkedid."' and eventtype in ('ANSWER') and length(cid_name) = 3 order by eventtime limit 1;";
            if (DEBUG) echo $answered_query.PHP_EOL;
            $answered_stmt = $dbh->query($answered_query);
            $answered_row = $answered_stmt->fetch();
            $dst = $answered_row['cid_num'];
            $started = $answered_row['eventtime'];
            $recording = 'http://192.168.200.6/crmrecords/'.date('Y', strtotime($started)).'/'.date('m',strtotime($started)).'/'.date('d',strtotime($started)).'/'.$row['recordingfile'];
            $source_query = "select cid_num, eventtime from cel where linkedid = '".$linkedid."' and eventtype in ('CHAN_START') and length(cid_num) > 6;";
            if (DEBUG) echo $source_query.PHP_EOL;
            $source_stmt = $dbh->query($source_query);
            $source_row = $source_stmt->fetch();
            $src = $source_row['cid_num'];
            $hg_query = "select cid_num, eventtime  from cel where linkedid = '".$linkedid."' and eventtype in ('HANGUP') and length(cid_name) = 3 order by eventtime desc limit 1;";
            if (DEBUG) echo $hg_query.PHP_EOL;
            $hg_stmt = $dbh->query($hg_query);
            $hg_row = $hg_stmt->fetch();
            $hg_time = $hg_row['eventtime'];
            $duration = strtotime($hg_time) - strtotime($started);
            if ($dst == NULL) {
              $status = 'NO ANSWER';
              $started = $source_row['eventtime'];
              $duration = 0;
            } else {
              $status = 'ANDWERED';
            }
            if (DEBUG) echo 'duration:'.$duration.PHP_EOL;
            if(sendApiRequest($callid, $direction, $src, $dst, $started, $duration, $status, $recording, $crm_host)) {
                $curdate = date("Y-m-d H:i:s");
                //$stmt = $dbh->exec("delete from synclog");
                //$stmt = $dbh->exec("insert into synclog (lastdate) values ('$started')");
                $stmt = $dbh->exec("update cdr set posted = 1 where uniqueid = '$callid'");
                echo "update cdr set posted = 1 where uniqueid = '$linkedid'".PHP_EOL;
            }
            echo '$callid, $direction, $src, $dst, $started, $duration, $status'.PHP_EOL;
            echo  $callid.'  '.$direction.'  '.$src.'  '.$dst.'  '.$started.'  '.$duration.'  '.$status;



        } else {
          $stmt = $dbh->exec("update cdr set posted = 1 where uniqid = $callid");
        }
    }
    $syncquery = "select * from cdr where cnum in ('206', '404', '401',  '304', '305', '208', '201', '205', '213' )  and length(dst) > 4 and calldate >= '2018-07-24' and posted is NULL;";
    # outgoing
    $direction = 'outgoing';
    if (DEBUG) echo $syncquery.PHP_EOL;
    $mainstmt = $dbh->query($syncquery);
	  if (DEBUG) print("Количество строк для вставки:\n ");
	  if (DEBUG) print($mainstmt->rowCount().PHP_EOL);
    while ($row = $mainstmt->fetch())
    {
        if (DEBUG) echo(PHP_EOL.PHP_EOL."outgoing call start".PHP_EOL.PHP_EOL);
        $linkedid = $row['uniqueid'];
        $dst = normPhone($row['dst']);
        $src = normPhone($row['cnum']);
        $callid = $row['uniqueid'];
        $started = $row['calldate'];
        $duration = $row['billsec'];
        $status = $row['disposition'];
        $recording = 'http://192.168.200.6/crmrecords/'.date('Y', strtotime($started)).'/'.date('m',strtotime($started)).'/'.date('d',strtotime($started)).'/'.$row['recordingfile'];
        echo '$callid, $direction, $src, $dst, $started, $duration, $status'.PHP_EOL;
        echo  $callid.'  '.$direction.'  '.$src.'  '.$dst.'  '.$started.'  '.$duration.'  '.$status.' '.$recording.PHP_EOL;
        if (strlen($src) > 2 && strlen($dst) > 5 && is_numeric($dst)) {
          if(sendApiRequest($callid, $direction, $src, $dst, $started, $duration, $status, $recording, $crm_host)) {
            $curdate = date("Y-m-d H:i:s");
            //$stmt = $dbh->exec("delete from synclog");
            //$stmt = $dbh->exec("insert into synclog (lastdate) values ('$started')");
            $stmt = $dbh->exec("update cdr set posted = 1 where uniqueid = '$callid'");
            if (DEBUG)  echo "update cdr set posted = 1 where uniqueid = '$callid'".PHP_EOL;
          }
        } else {
            $stmt = $dbh->exec("update cdr set posted = 1 where uniqid = $callid");
        }
      echo $src.'-'.$dst.PHP_EOL;
    }

?>
