<?php
require 'vendor/autoload.php';
require 'utils.php';
require 'config.php';

use PAMI\Client\Impl\ClientImpl as PamiClient;
use PAMI\Message\Event\EventMessage;
use PAMI\Listener\IEventListener;
$options = array(
    'host' => '127.0.0.1',
    'scheme' => 'tcp://',
    'port' => $ami_port,
    'username' => $ami_user,
    'secret' => $ami_secret,
    'connect_timeout' => 10,
    'read_timeout' => 10
);
$pamiClient = new PamiClient($options);
// Open the connection
$pamiClient->open();
$pamiClient->registerEventListener(function (EventMessage $event) {
    if($event->getName() == 'BridgeEnter'){
      var_dump($event);
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
