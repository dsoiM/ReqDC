<?php

abstract class OutboundRequest
{

    public $currentRetries = 0;

    public $retrySleeptime = 1;

    /**
     * True if request ended up in failure
     *
     * @var boolean
     */
    public $failed;

    /**
     * Max retry seconds
     *
     * @var int
     */
    public $maxRetryTime;

    // Code must define situations when to retry, by default its off
    public $retry = false;

    const USERNAMEKEY = "Username";

    const PASSWORDKEY = "Password";

    const URLKEY = "URL";

    const RETRYMESSAGESKEY = "Backend response retry regex";

    /**
     * Category name in datastorage where params reside
     *
     * @var string
     */
    public $paramsFromCategory;

    protected function __construct()
    {
        $this->maxRetryTime = Config::get('MAXRETRYTIME');
    }

    abstract public function send();

    /**
     *
     * @throws MaxRetriesReachedException
     */
    protected function retryHandling()
    {
        if ($this->retry !== true) {
            return;
        }
        $this->currentRetries ++;
        log::info('Retry has been triggered, sleeping ' . $this->retrySleeptime . ' seconds');
        sleep($this->retrySleeptime);
        $this->retrySleeptime = $this->retrySleeptime * 2;

        if ($this->retrySleeptime > $this->maxRetryTime) {
            $this->retry = false;
            $this->failed = true;
            throw new MaxRetriesReachedException('Tried to access backend ' . $this->paramsFromCategory . ' at URL ' . $this->url . ' but after ' . $this->currentRetries . " tries over " . $this->maxRetryTime . ' seconds still failing');
        }
    }
}
