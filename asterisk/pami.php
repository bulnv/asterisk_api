<?php
require 'vendor/autoload.php';
require 'utils.php';
require 'config.php';

const DEBUG = TRUE;

date_default_timezone_set('Europe/Moscow');

use PAMI\Client\Impl\ClientImpl as PamiClient;
use PAMI\Message\Event\EventMessage;
use PAMI\Listener\IEventListener;
use PAMI\Message\Action\GetVarAction;
use PAMI\Message\Action;
use PAMI\Message\Action\BridgeInfoAction;

$options = array(
    'host' => '127.0.0.1',
    'scheme' => 'tcp://',
    'port' => $ami_port,
    'username' => $ami_user,
    'secret' => $ami_secret,
    'connect_timeout' => 10,
    'read_timeout' => 10
);
$uniqIdArr = array();
$pamiClient = new PamiClient($options);
// Open the connection
// ($callid, $direction, $src, $dst, $started, $answered, $completed, $recording, $host)
$pamiClient->open();
$pamiClient->registerEventListener(function (EventMessage $event) use ($pamiClient, $host) {
	  global $asthost;
	  global $uniqIdArr;
      if ($event instanceof PAMI\Message\Event\BridgeEnterEvent or $event instanceof PAMI\Message\Event\BridgeLeaveEvent) {
		if ($event instanceof PAMI\Message\Event\BridgeEnterEvent) {
			$started = date("Y-m-d H:i:s");
			$duration = 0;
			$finished = '';
			if ($event->getBridgeNumChannels() == 2) {
				$answered = date("Y-m-d H:i:s");
				$vars = $event->getAllChannelVariables()[strtolower($event->GetChannel())];
				var_dump($vars);
				if (strlen(trim($vars['realcalleridnum'])) == 3) {
					$cid1 = trim($vars['realcalleridnum']);
					$cid2 = trim($vars['dial_number']);
					$direction = 'outgoing';
				} elseif (strlen(trim(($event->getConnectedLineNum()))) == 3)  {
					$cid1 = trim($event->getCallerIDName());
					$cid2 = trim($event->getConnectedLineNum());
					$direction = 'incoming';
				}	
				$rec_file = end(explode('/',$vars['mixmonitor_filename']));
				$uniqueid = $event->getUniqueid();
				$rec_link = $asthost."/".date("Y")."/".date("m")."/".date("d").'/'.$rec_file;
				
				if (($cid1 && $cid2) and ((strlen($cid1) > 3) or (strlen($cid2) > 3)) and ((strlen($cid1) < 10 ) or (strlen($cid2) < 10)) ) {
				  $response = sendApiRequest($uniqueid, $direction, $cid1, $cid2, '', $answered, '', $rec_link, $host );
				  echo "request_type:".$event->getName().PHP_EOL;;
				  echo "sending request $uniqueid, $direction, $cid1, $cid2, $started, $answered, $finished, $rec_link, $host".PHP_EOL;;
				  echo "response $response".PHP_EOL; 
				}
			}
		} else {
			$started = '';
			$finished = date("Y-m-d H:i:s");
			$duration = 0;
			if ($event->getBridgeNumChannels() <= 1) {
				$vars = $event->getAllChannelVariables()[strtolower($event->GetChannel())];
				if (strlen($vars['realcalleridnum']) > 0 ) {
					var_dump($vars);
					if (strlen(trim($vars['realcalleridnum'])) == 3) {
						$cid1 = trim($vars['realcalleridnum']);
						$cid2 = trim($vars['dial_number']);
						$direction = 'outgoing';
					} elseif (strlen(trim($event->getConnectedLineNum())) == 3 ) {
						$cid1 = trim($event->getCallerIDName());
						$cid2 = trim($event->getConnectedLineNum());
						$direction = 'incoming';
					}	
					$rec_file = end(explode('/',$vars['mixmonitor_filename']));
					$uniqueid = $event->getUniqueid();
					$rec_link = $asthost."/".date("Y")."/".date("m")."/".date("d").'/'.$rec_file;
					
					if (($cid1 && $cid2) and ((strlen($cid1) > 3) or (strlen($cid2) > 3)) and ((strlen($cid1) < 10 ) or (strlen($cid2) < 10)) ) {
					  $response = sendApiRequest($uniqueid, $direction, $cid1, $cid2, '' , '' , $finished, $rec_link, $host );
					  echo "request_type:".$event->getName().PHP_EOL;;
					  echo "sending request $uniqueid, $direction, $cid1, $cid2, '' , '', $finished, $rec_link, $host".PHP_EOL;;
					  echo "response $response".PHP_EOL;
					}
				}
			}
		}
		var_dump($event);
	  }
	  else if ($event instanceof PAMI\Message\Event\NewstateEvent or $event instanceof PAMI\Message\Event\NewextenEvent) {
		if (trim($event->getKey('ChannelStateDesc')) == 'Ring') {
			$started = date("Y-m-d H:i:s");
			$uniqueid = $event->getKey('Uniqueid');
			if (strlen($event->getKey('CallerIDNum')) == 3 ) { //incoming ring
				$cid1 = preg_replace('/[^0-9.]+/', '', $event->getKey('CallerIDNum'));
				$cid2 = preg_replace('/[^0-9.]+/', '', $event->getKey('Exten'));
				$direction = 'outgoing';
			} else {
				$cid1 = preg_replace('/[^0-9.]+/', '', $event->getKey('CallerIDNum'));
				$cid2 = preg_replace('/[^0-9.]+/', '', $event->getKey('Exten'));
				$direction = 'incoming';
			}
			if (($cid1 && $cid2) and ((strlen($cid1) > 3) or (strlen($cid2) > 3)) and ((strlen($cid1) < 10 ) or (strlen($cid2) < 10)) and strlen($cid1) > 1 and strlen($cid2) > 1 ) {
				echo strlen($cid1)." ".strlen($cid2).PHP_EOL;
				if (array_search($uniqueid, $uniqIdArr) === false) {
					echo "firstly meet $uniqueid".PHP_EOL;
					$response = sendApiRequest($uniqueid, $direction, $cid1, $cid2, $started, '', '', $rec_link, $host );
					echo "request_type:".$event->getName().PHP_EOL;;
					echo "sending request $uniqueid, $direction, $cid1, $cid2, $started, '', '', '', $host".PHP_EOL;;
					echo "response $response".PHP_EOL;
					array_unshift($uniqIdArr, $uniqueid);
					$uniqIdArr = array_slice($uniqIdArr, 0, 50);
				} else {
					echo "already saw $uniqueid ... skipping".PHP_EOL;
				}
			}
			var_dump($event);
		} else {
			var_dump($event);
		}
	  }	else if ($event instanceof PAMI\Message\Event\NewchannelEvent) {
        $cid1 = $event->getCallerIDNum();
        $context = $event->getContext();
        $exten = $event->getExtension();
        $uniqueid = $event->getUniqueID();
        $direction = 'incoming';
        $started = date("Y-m-d H:i:s");
        $answered = NULL;
        $finished = NULL;
        if ((strlen($cid1) >= 6) && ($context == 'from-pstn') && (strlen($exten) == 4) ) {
          $response = sendApiRequest($uniqueid, $direction, $cid1, $cid2, $started, $answered, $finished, $rec_link, $host );
          echo "request_type:".$event->getName().PHP_EOL;;
          echo "sending request $uniqueid, $direction, $cid1, $cid2, $started, $answered, $finished, $rec_link, $host".PHP_EOL;;
          echo "response $response".PHP_EOL;
        }
        var_dump($event);
      } else {
		if (! $event instanceof PAMI\Message\Event\VarSetEvent) {
			var_dump($event);
		}
      }
  });
$running = true;
// Main loop
while($running) {
    $pamiClient->process();
    usleep(1000);
}
// Close the connection
$pamiClient->close();
