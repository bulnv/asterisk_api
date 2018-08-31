<?php function sendApiRequest ($callid, $direction, $src, $dst, $started, $duration, $status, $recording, $host) {
  $callid = str_replace('.','',$callid);
  //smarttek
  //test
  //$host = 'https://app.salesap.ru/telephony_external/61237144-ee01-4e45-bb20-60aad2ff2129';
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

?>
