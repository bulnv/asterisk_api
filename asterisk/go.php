<?php
require_once implode(DIRECTORY_SEPARATOR, array(__DIR__ , 'vendor', 'autoload.php'));

use Ratchet\Server\EchoServer;
use Asterisk\Server;

try {
    $server = new Server();

    $app = new Ratchet\App('127.0.0.1', 8080, '127.0.0.1', $server->getLoop());
    $app->route('/asterisk', $server, array('*'));
    $app->run();

} catch (Exception $exc) {
    $error = "Exception raised: " . $exc->getMessage()
            . "\nFile: " . $exc->getFile()
            . "\nLine: " . $exc->getLine() . "\n\n";
    echo $error;
    exit(1);
}

?>
