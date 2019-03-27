<?php

namespace SonicSearch;

use Socket\Raw\Socket;
use RuntimeException;

abstract class Channel
{

    /**
     * @var Socket
     */
    private $socket;

    /**
     * @var float
     */
    protected $readTimeout;

    /**
     * @var string
     */
    protected $password;

    /**
     * Client constructor.
     * @param string $address address of Sonic server
     * @param integer $port port of Sonic server
     * @param string $password auth_password of Sonic server
     * @param float $connectionTimeout connection timeout in seconds
     * @param float $readTimeout read timeout in seconds
     */
    public function __construct($address, $port, $password, $connectionTimeout, $readTimeout)
    {
        $this->readTimeout = $readTimeout;
        $this->password = $password;

        $socketFactory = new \Socket\Raw\Factory();
        $this->socket = $socketFactory->createClient("$address:$port", $connectionTimeout);
        $resp = $this->readBuffer();
        if (!preg_match('%^CONNECTED%', $resp)) {
            throw new RuntimeException("unexpected response: $resp");
        }
    }

    /**
     * Send a raw command.
     * @param string $cmd
     */
    protected function send($cmd)
    {
        $this->socket->send("$cmd\r\n", MSG_NOSIGNAL);
    }

    /**
     * Read buffer until the delimiter character.
     * @param string $delimiter
     * @return string
     */
    protected function readBuffer($delimiter = "\n")
    {
        $resp = '';
        $timePassed = 0;
        $timeout = $this->readTimeout * 1e6;
        do {
            $c = $this->socket->recv(1, MSG_NOSIGNAL);
            $resp .= $c;
            usleep(1000);
            $timePassed += 1000;
            if ($timePassed > $timeout) {
                throw new RuntimeException('read timeout');
            }
        } while ($c !== $delimiter);
        return $resp;
    }

    protected function assertOK()
    {
        $resp = $this->readBuffer();
        if (preg_match("%^OK\r\n$%", $resp) === false) {
            throw new RuntimeException("unexpected response: $resp");
        }
    }

    protected function assertResult()
    {
        $resp = $this->readBuffer();
        if (preg_match("%^RESULT ([0-9]+)\r\n$%", $resp, $matches) === false) {
            throw new RuntimeException("unexpected response: $resp");
        }

        return $matches[1];
    }

    protected function start($mode)
    {
        $this->send("START $mode $this->password");
        $resp = $this->readBuffer();
        if (!preg_match('%^STARTED%', $resp)) {
            throw new RuntimeException("unexpected response: $resp");
        }
    }

    public function ping()
    {
        $this->send('PING');
        $resp = $this->readBuffer();
        if (!preg_match('%^PONG%', $resp)) {
            throw new RuntimeException("unexpected response: $resp");
        }
    }

    public function quit()
    {
        $this->send('QUIT');
        $resp = $this->readBuffer();
        if (!preg_match('%^ENDED%', $resp)) {
            throw new RuntimeException("unexpected response: $resp");
        }
    }
}