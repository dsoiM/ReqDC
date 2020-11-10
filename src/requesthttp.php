<?php

/**
 *
 * RequestHttp comes through standard API as JSON, XML or such
 *
 * @author dso
 *
 */
class RequestHttp extends Request
{

    // / GET, POST, PUT, DELETE
    public $httpRequestType;

    // Contains full url from request with hostname, only with http methods
    public $url;

    // https://api.host.com/tenantID/ImplementationName
    public $urlPathPartsArray = array();

    /**
     * Get request object from HTTP request
     *
     * @return RequestHttp
     */
    public static function generate($tenantId, MainAPI $mainApi)
    {
        Log::debug('New RequestHttp');
        $req = new RequestHttp();

        $req->setContentType($mainApi->contentType);
        $postData = MainAPI::getPostData();
        $req->setPayload($postData);
        $req->setUrl();
        $req->setUserId(Session::getUser()->getId());

        $req->httpRequestType = $_SERVER['REQUEST_METHOD'];
        $req->setTenantId($tenantId);
        $req->time = su::timeToBSON();
        $req->getAndSetImplementation();
        $req->saveToDb();

        return $req;
    }

    /**
     *
     * @param string $id
     * @throws Exception
     * @return RequestHttp
     */
    public function setUrl()
    {
        $this->url = MainAPI::getRequestUrlWithoutAPI();
        $this->urlPathPartsArray = su::getUrlParts($this->url);
    }

    protected function getImplementationClassNameFromHttpURL()
    {
        $className = isset($this->urlPathPartsArray[2]) ? $this->urlPathPartsArray[2] : null;
        if (empty($className)) {
            throw new NotFoundException('Implementation not provided');
        }

        return $className;
    }
}

