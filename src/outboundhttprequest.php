<?php
use Curl\Curl;

class OutboundHttpRequest extends OutboundRequest
{

    /**
     *
     * @var \Curl\Curl
     */
    public $curl;

    public $url;

    public $username;

    public $password;

    public $body;

    public $httpMethod = "post";

    /**
     * Creates object and preps everything for post
     * send() must be called afterwards
     *
     * @param string $body
     * @param string $paramsFromCategory
     * @param string $url
     * @return OutboundHttpRequest
     */
    public static function post($body, $paramsFromCategory, $urlpostfix = '', $contentType = 'application/json')
    {
        $r = new OutboundHttpRequest();
        $r->paramsFromCategory = $paramsFromCategory;
        $r->username = DataStorage::get($paramsFromCategory, static::USERNAMEKEY, false);
        $r->password = DataStorage::get($paramsFromCategory, static::PASSWORDKEY, false);
        $r->url = DataStorage::get($paramsFromCategory, static::URLKEY) . $urlpostfix;
        $r->body = $body;

        /**
         *
         * @var \Curl\Curl
         */
        $r->curl = new \Curl\Curl();
        if (! empty($r->username) || ! empty($r->password)) {
            $r->curl->setBasicAuthentication($r->username, $r->password);
        }
        $r->curl->setHeader('Content-Type', $contentType);
        $r->curl->setOpt(CURLOPT_RETURNTRANSFER, true);
        $r->curl->setOpt(CURLOPT_FOLLOWLOCATION, true);
        $r->curl->setDefaultJsonDecoder(true);
        return $r;
    }

    /**
     *
     * @throws OutboundHttpRequestException
     * @return Curl
     */
    public function send()
    {
        try {

            do {
                $this->failed = false;
                $this->retry = false;
                log::debug('Doing HTTP post to ' . $this->url);
                $this->curl->post($this->url, $this->body);
                if ($this->curl->isCurlError()) {
                    $this->failed = true;
                    $curlErrorCode = $this->curl->getCurlErrorCode();
                    log::error('cURL error with code ' . $curlErrorCode . ': ' . $this->curl->getCurlErrorMessage());
                    // For time being only retry connection timeout and couldnt connect
                    if ($curlErrorCode == 28 || $curlErrorCode == 7) {
                        $this->retry = true;
                    }
                } elseif ($this->curl->isHttpError()) {
                    $this->failed = true;
                    log::error('HTTP error with code ' . $this->curl->getHttpStatusCode() . ': ' . $this->curl->getHttpErrorMessage());
                    // 5xx Server errors can be retried
                    if (substr($this->curl->getHttpStatusCode(), 0, 1) == '5') {
                        $this->retry = true;
                    }
                }
                $this->handleBackendResposeRetry();

                $this->retryHandling();
            } while ($this->retry === true);

            if ($this->failed === true) {
                throw new OutboundHttpRequestException('Unable to do HTTP operation to backend "' . $this->paramsFromCategory . '" at url ' . $this->url);
            }

            log::info('HTTP Post done with status ' . $this->curl->getHttpStatusCode() . ' to ' . $this->url . '');

            return $this->curl;
        } catch (Throwable $e) {

            $m = $e->getMessage() . ' Errormessage: ' . $this->curl->getErrorMessage();
            if ($e instanceof CustomerNotifiedException) {
                Mail::sendTenantNotification($m, $this->paramsFromCategory);
            }
            log::error($m);
            throw new OutboundHttpRequestException($m);
        }
    }

    private function handleBackendResposeRetry()
    {
        $backendRetryregex = DataStorage::get($this->paramsFromCategory, static::RETRYMESSAGESKEY, false);
        if (empty($backendRetryregex)) {
            return false;
        }

        log::debug('Checking backend retry response condition: ' . $backendRetryregex . ' Data: ' . $this->curl->getRawResponse());
        $retryMsg = preg_match($backendRetryregex, $this->curl->getRawResponse());

        // Match succeeds
        if ($retryMsg === 1) {
            log::warn('Backend message ' . $this->curl->getRawResponse() . ' matches to retry regex ' . $backendRetryregex . ' . Enabling retry');
            $this->retry = true;
            $this->failed = true;

            // No match
        } elseif ($retryMsg === 0) {

            // Error, like wrong regex
        } elseif ($retryMsg === false) {
            throw new CustomerNotifiedException('Unable to use regex ' . $backendRetryregex . ' for message level retrying at ' . $this->paramsFromCategory . '. Please check syntax');
        }
    }
    
}