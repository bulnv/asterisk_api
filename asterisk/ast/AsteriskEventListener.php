<?php namespace Asterisk;

use PAMI\Message\Event\EventMessage;
use PAMI\Listener\IEventListener;
use PAMI\Message\Event\NewstateEvent;
use PAMI\Message\Event\HangupEvent;
use PAMI\Message\Event\OriginateResponseEvent;

class AsteriskEventListener implements IEventListener
{
    private $server;

    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    public function handle(EventMessage $event)
    {
        var_dump($event);
        // getChannelState 6 = Up getChannelStateDesc()
        // TODO можно попробовать событие BridgeEnterEvent
        if ($event instanceof NewstateEvent && $event->getChannelState() == 6) {
            $client = $this->server->getClientById($event->getCallerIDNum());
            if (!$client) {
                return;
            }
            echo "bingo up";
            var_dump($event);
            $client->setMessage($event);
        // TODO можно попробовать событие BridgeLeaveEvent
        } elseif ($event instanceof HangupEvent) {
            $client = $this->server->getClientById($event->getCallerIDNum());
            if (!$client) {
                return;
            }
            var_dump($event);
            $client->setMessage($event);
        }
    }
}
