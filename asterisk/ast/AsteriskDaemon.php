<?php
namespace Asterisk;

use PAMI\Client\Impl\ClientImpl as PamiClient;
use PAMI\Message\Event\EventMessage;
use PAMI\Message\Event\HangupEvent;

use PAMI\Message\Event\NewstateEvent;
use PAMI\Message\Event\OriginateResponseEvent;
use PAMI\Message\Action\OriginateAction;
use React\EventLoop\Factory;

class AsteriskDaemon {
    private $asterisk;
    private $server;
    private $loop;
    private $interval = 0.1;
    private $retries = 10;

    private $options = array(
        'host' => '127.0.0.1',
        'scheme' => 'tcp://',
        'port' => 5038,
        'username' => 'admin',
        'secret' => ' c204fa88c3de1af258673aa7c36dafd3',
        'connect_timeout' => 10000,
        'read_timeout' => 10000
    );

    private $opened = FALSE;
    private $runned = FALSE;

    public function __construct(Server $server)
    {
        $this->server = $server;
        $this->asterisk = new PamiClient($this->options);
        $this->loop = Factory::create();

        $this->asterisk->registerEventListener(new AsteriskEventListener($this->server),
                function (EventMessage $event) {
            return $event instanceof NewstateEvent
                    || $event instanceof HangupEvent;
        });

        $this->asterisk->open();
        $this->opened = TRUE;
        $asterisk = $this->asterisk;
        $retries = $this->retries;
        $this->loop->addPeriodicTimer($this->interval, function () use ($asterisk, $retries) {
            try {
                $asterisk->process();
            } catch (Exception $exc) {
                if ($retries-- <= 0) {
                    throw new \RuntimeException('Exit from loop', 1, $exc);
                }
                sleep(10);
            }
        });
    }

    public function __destruct() {
        if ($this->loop && $this->runned) {
            $this->loop->stop();
        }

        if ($this->asterisk && $this->opened) {
            $this->asterisk->close();
        }
    }

    public function run() {
        $this->runned = TRUE;
        $this->loop->run();
    }

    public function getLoop() {
        return $this->loop;
    }
}
