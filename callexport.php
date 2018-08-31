<?php

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

function sendApiRequest ($callid, $direction, $src, $dst, $started, $duration, $status, $recording) {
  $callid = str_replace('.','',$callid);
  $host = 'https://app.salesap.ru/telephony_external/032ec6ed-aa26-448e-bb71-653c8b21d74a';
  $ch = curl_init();
  $completed = DateTime::createFromFormat('Y-m-d H:i:s',$started)->add(new DateInterval('PT'.$duration.'S'))->format(DateTime::ISO8601);
  $started = DateTime::createFromFormat('Y-m-d H:i:s',$started)->format(DateTime::ISO8601);
  $fields = array (
    'call_id' => $callid,
    'direction' => $direction,
    'src_phone_number' => $src,
    'dst_phone_number' => $dst,
    'started_at' => $started,
    'completed_at' => $completed,
    'recording' => $recording,
  );
  if ($status == 'NO ANSWER') {$fields['status'] = 'no_answer'; }
  $fields_string = http_build_query($fields);
  curl_setopt($ch, CURLOPT_URL,"$host");
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS,$fields_string);
  if (DEBUG) echo PHP_EOL;var_dump($fields_string);echo PHP_EOL;
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $server_output = curl_exec ($ch);
  $json = json_decode($server_output);
  if (DEBUG) echo 'RESPONSE'.var_dump($json); echo PHP_EOL;
  curl_close ($ch);
  if (isset($json->id)) {
    if (DEBUG) echo 'SUCCESS'; echo PHP_EOL;
    return TRUE;
  } else {
    if (DEBUG) echo 'ERROR';echo PHP_EOL;
    return FALSE;
  }
}

   if (!lock('callexport')) { exit; }
   $host = '127.0.0.1';
   $db   = 'asteriskcdrdb';
   $user = 'crmuser';
   $password = 'crmypassword!@#';
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

    $syncquery = "select distinct uniqueid from cdr where did  = '74952216556' and calldate > '2018-04-01' and posted is NULL order by calldate";
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
        $recording = 'http://212.49.114.150:8181/crmrecords/'.year($row['calldate']).'/'.month($row['calldate']).'/'.day($row['calldate']).'/'.$row['recordingfile'];
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
            if ($dst == NULL) {
              $status = 'NO ANSWER';
              $started = $source_row['eventtime'];
            } else {
              $status = 'ANDWERED';
            }
            $duration = strtotime($hg_time) - strtotime($started);
            if (DEBUG) echo 'duration:'.$duration.PHP_EOL;
            if(sendApiRequest($callid, $direction, $src, $dst, $started, $duration, $status, $recording)) {
                $curdate = date("Y-m-d H:i:s");
                //$stmt = $dbh->exec("delete from synclog");
                //$stmt = $dbh->exec("insert into synclog (lastdate) values ('$started')");
                $stmt = $dbh->exec("update cdr set posted = 1 where uniqueid = '$callid'");
                echo "update cdr set posted = 1 where uniqueid = '$linkedid'".PHP_EOL;
            }
            echo '$callid, $direction, $src, $dst, $started, $duration, $status'.PHP_EOL;
            echo  $callid.'  '.$direction.'  '.$src.'  '.$dst.'  '.$started.'  '.$duration.'  '.$status. ' '.$recording;



        } else {
          $stmt = $dbh->exec("update cdr set posted = 1 where uniqid = $callid");
        }
    }
    $syncquery = "select * from cdr where cnum like '4__' and length(dst) > 4 and calldate > '2018-04-20' and posted is NULL;";
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
        echo '$callid, $direction, $src, $dst, $started, $duration, $status'.PHP_EOL;
        echo  $callid.'  '.$direction.'  '.$src.'  '.$dst.'  '.$started.'  '.$duration.'  '.$status.PHP_EOL;
        if (strlen($src) > 2 && strlen($dst) > 5 && is_numeric($dst)) {
          if(sendApiRequest($callid, $direction, $src, $dst, $started, $duration, $status)) {
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
