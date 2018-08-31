<?php namespace Asterisk;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Server implements MessageComponentInterface
{
    /**
     * Клиенты соединения
     * @var SplObjectStorage
     */
    private $clients;
    /**
     * Клиент для подключения к asterisk
     * @var AsteriskDaemon
     */
    private $daemon;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->daemon = new AsteriskDaemon($this);
    }

    function getLoop() {
        return $this->daemon->getLoop();
    }

    public function onOpen(ConnectionInterface $conn)
    {
        //echo "Open\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        //echo "Message\n";
        $json = json_decode($msg);
        if (json_last_error()) {
            echo "Json error: " . json_last_error_msg() . "\n";
            return;
        }
        switch ($json->Action) {
            case 'Register':
                //echo "Register client\n";
                $client = $this->getClientById($json->Id);
                if ($client) {
                    if ($client->getConnection() != $from) {
                        $client->setConnection($from);
                    }
                    $client->process();
                } else {
                    $this->clients->attach(new Client($from, $json->Id));
                }
                break;

            default:
                break;
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        //echo "Close\n";
        $client = $this->getClientByConnection($conn);
        if ($client) {
            $client->closeConnection();
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Error: " . $e->getMessage() . "\n";
        $client = $this->getClientByConnection($conn);
        if ($client) {
            $client->closeConnection();
        }
    }

    /**
     *
     * @param ConnectionInterface $conn
     * @return \Asterisk\Client or NULL
     */
    public function getClientByConnection(ConnectionInterface $conn) {
        $this->clients->rewind();
        while($this->clients->valid()) {
            $client = $this->clients->current();
            if ($client->getConnection() == $conn) {
                //echo "Client found by connection\n";
                return $client;
            }
            $this->clients->next();
        }

        return NULL;
    }

    /**
     *
     * @param string $id
     * @return \Asterisk\Client or NULL
     */
    public function getClientById($id) {
        $this->clients->rewind();
        while($this->clients->valid()) {
            $client = $this->clients->current();
            if ($client->getId() == $id) {
                //echo "Client found by id\n";
                return $client;
            }
            $this->clients->next();
        }

        return NULL;
    }
}
