<?php
const DEBUG = TRUE;

function sendApiRequest ($callid, $direction, $src, $dst, $started, $answered, $completed, $recording, $host) {
  $callid = str_replace('.','',$callid);
  //smarttek
  //test
  //$host = 'https://app.salesap.ru/telephony_external/61237144-ee01-4e45-bb20-60aad2ff2129';
  $ch = curl_init();
  //$completed = DateTime::createFromFormat('Y-m-d H:i:s',$started)->add(new DateInterval('PT'.$duration.'S'))->format(DateTime::ISO8601);
  $fields = array (
    'call_id' => $callid,
    'direction' => $direction,
    'src_phone_number' => normPhone($src),
    'dst_phone_number' => normPhone($dst),
  );
  if ($recording) {
    $fields['recording'] = $recording;
  }
  if ($started) {
    $started = DateTime::createFromFormat('Y-m-d H:i:s',$started)->format(DateTime::ISO8601);
    $fields['started_at'] = $started;
  }
  if ($answered) {
    $answered = DateTime::createFromFormat('Y-m-d H:i:s',$answered)->format(DateTime::ISO8601);
    $fields['answered_at'] = $answered;
  }
  if ($completed) {
    $completed = DateTime::createFromFormat('Y-m-d H:i:s',$completed)->format(DateTime::ISO8601);
    $fields['completed_at'] = $completed;
  }
  // if ($status) {
      // $fields['status'] = $status;
  // }
  //if ($status == 'NO ANSWER') {$fields['status'] = 'no_answer'; }
  $fields_string = http_build_query($fields);
  curl_setopt($ch, CURLOPT_URL,"$host");
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_SSLVERSION, 6);
  curl_setopt($ch, CURLOPT_POSTFIELDS,$fields_string);
  if (DEBUG) echo PHP_EOL;var_dump($fields_string);echo PHP_EOL;
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $server_output = curl_exec ($ch);
  $json = json_decode($server_output);
  echo 'RESPONSE'.var_dump($json); echo PHP_EOL;
  curl_close ($ch);
  if (isset($json->status)) {
      if ($json->status == "success") {
          if (DEBUG) echo 'SUCCESS'; echo PHP_EOL;
          return TRUE;
        } else {
          if (DEBUG) echo 'ERROR';echo PHP_EOL;
          return FALSE;
        }
  }
}

function normPhone($phone) {
  $resPhone = preg_replace("/[^0-9]/", "", $phone);

  if (strlen($resPhone) === 6) {
    $resPhone = '+74712'.$resPhone;
  }
  if (strlen($resPhone) === 10) {
    $resPhone = '+7'.$resPhone;
  }
  if (strlen($resPhone) === 11) {
    $resPhone[0] = preg_replace("/^8/", "7", $resPhone);
  }
  return $resPhone;
}


?>
