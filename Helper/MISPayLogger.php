<?php

namespace MISPay\MISPayMethodDynamicCallback\Helper;

use Psr\Log\LoggerInterface;

class MISPayLogger
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var string
     */
    protected string $trackId;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->trackId = 'NONE';
    }

    protected function getPrefix()
    {
        return $this->trackId ? 'trackId: ' . $this->trackId . ' | ' : '';
    }

    public function setTrackId(string $trackId)
    {
        $this->trackId = $trackId;
    }

    public function info($message)
    {
        $this->logger->info($this->getPrefix() . $message);
    }

    public function debug($message)
    {
        $this->logger->debug($this->getPrefix() . $message);
    }

    public function error($message)
    {
        $this->logger->error($this->getPrefix() . $message);
    }
}
