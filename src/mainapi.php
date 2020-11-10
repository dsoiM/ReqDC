<?php

class MainAPI
{

    /**
     *
     * @var RequestHttp
     */
    public $request;

    /**
     *
     * @var Throwable
     */
    private $e;

    /**
     *
     * @var integer
     */
    public $responseCode = 200;

    public $authuserId;

    public $authpw;

    /**
     *
     * @var string
     */
    public $respContent;

    /**
     *
     * @var Execution
     */
    public $execution;

    public $urlPathPartsArray;

    public $tenantId;

    public $contentType;

    public $requestFromUI;

    // ScheduleId is generated on non synchronous requests
    public $scheduleId;

    public function handle()
    {
        try {
            ob_start();
            
            header('X-Powered-By: ReqDC Platform');
            
            Log::info('Received: ' . $_SERVER['REQUEST_URI']);

            $this->checkForValidRequest();
            $this->handleSessionFromUI();
            $this->handleHashedAPISession();
            $this->parseURL();
            $this->setTenantIdFromHttpURL();
            $this->loginAndAuthorizeTenant();
            $this->setContentType();

            if ($this->isControlRequest()) {
                $this->handleControl();
            } else {
                $this->handleImplementation();
            }
        } catch (InvalidCredentialsException $e) {
            Session::destroy();
            $this->e = $e;
            $this->setInvalidCredentialsException();
        } catch (GenericImplementationException $e) {
            $this->e = $e;
            $this->setGenericImplementationException();
        } catch (HaltableException $e) {
            $this->e = $e;
            $this->setHaltableException();
        } catch (GenException $e) {
            $this->e = $e;
            $this->setGenException();
        } catch (Throwable $e) {
            $this->e = $e;
            $this->setUnexpectedException();
            Mail::sendAlertFromException($e);
        }

        if ($this->e) {
            Log::removeExecution();
            Log::error($this->e);
        }

        $this->serveResponse();
    }

    private function setContentType()
    {
        $this->contentType = $this->getContentTypeFromHeader();
    }

    private function getContentTypeFromHeader()
    {
        $header = strtolower($this->getHeader('CONTENT-TYPE'));
        if (stripos($header, 'json') !== false) {
            return 'JSON';
        } elseif (stripos($header, 'xml') !== false) {
            return 'XML';
        }
        return null;
    }

    private function getHeader($getname)
    {
        $h = function_exists('getallheaders') ? getallheaders() : $_SERVER['MOCKUPHEADERS'];
        foreach ($h as $name => $value) {
            if (strtolower($getname) == strtolower($name)) {
                return $value;
            }
        }
        return false;
    }

    public function parseURL()
    {
        $this->urlPathPartsArray = su::getUrlParts(self::getRequestUrlWithoutAPI());
    }

    private function isControlRequest()
    {
        if (strtolower($this->urlPathPartsArray[2]) == 'control') {
            return true;
        }
        return false;
    }

    private function handleControl()
    {
        $c = new ControlAPI();
        // fug yea reference
        $c->mainAPI = &$this;
        $c->main();
    }
    
    public static function getRequestUrlWithoutAPI() {
        return str_replace('/api/', '/', $_SERVER['REQUEST_URI']);
    }

    public function handleImplementation(Request $request = null)
    {
        // Bring request in from ControlAPI
        if ($request) {
            $this->request = $request;
        } else {
            $this->request = RequestHttp::generate($this->tenantId, $this);
        }

        if (su::toBool(su::getRequestValue('synchronous'))) {
            // Synchronous requests go from start to finish in the same thread
            $this->execution = Execution::newFromRequest($this->request);
            Log::debug('This be synchronous');
            $this->execution->start();
            $this->respContent = $this->execution->responseContent;
        } else {
            // Non-synchronous requests create a schedule entry which will be picked up ScheduleService
            $this->scheduleId = Schedule::createFromRequest($this->request);
            $this->respContent = $this->generateResponseForAsync();
        }
    }

    /**
     * Blurts out JSON or XML document with only the requestId element as nothing else can be known
     * in async/scheduled request
     *
     * @return string
     */
    public function generateResponseForAsync()
    {
        $defaultRespArray = [
            'requestId' => $this->getRequest()
                ->getId()
                ->__toString(),
            'scheduleId' => $this->scheduleId->__toString()
        ];

        return (su::arrayToJSONorXML($defaultRespArray, $this->request->getContentType()));
    }

    private function setTenantIdFromHttpURL()
    {
        $tenantId = $this->urlPathPartsArray[1];
        if (empty($tenantId)) {
            throw new MalformedUrlException('Tenant not provided');
        }
        $this->tenantId = $tenantId;
    }

    /**
     * Authenticates Http Requests to API and authorizes
     * Also checks for CSRF if session already exists.
     *
     * @throws InvalidCredentialsException
     * @return User
     */
    private function loginAndAuthorizeTenant()
    {
        $userObj = Session::getUser();

        if ($userObj instanceof User && $userObj->isAuthenticated() === true) {
            if ($this->requestFromUI === true) {
                $token = $this->getHeader('CSRFTOKEN');
                if (! Session::isValidCSRF($token)) {
                    throw new CSRFException('Unauthorized or missing parameter');
                }
            }
            return;
        }

        try {
            if (! empty($this->authuserId) && ! $this->authuserId == '') {
                $userObj = user::getById($this->authuserId);
            }
        } catch (Throwable $e) {
            throw new InvalidCredentialsException('userId or password incorrect');
        }

        if (! $userObj || empty($this->authpw)) {
            throw new InvalidCredentialsException('userId or password incorrect');
        }

        //1: Authenticate
        $userObj->authenticate($this->authpw);
        //2: Authorize
        $userObj->selectTenantAndSaveToSession($this->tenantId, true);

        Log::info('Authenticated successfully as ' . $userObj->getId());
    }

    /**
     * If PHP session id comes in custom header from browser, we'll use it as session
     *
     * @return NULL
     */
    private function handleSessionFromUI()
    {
        if (isset($_COOKIE['PHPSESSID']) && session_status() == PHP_SESSION_NONE) {
            // So we use only session when it already exists thru UI. Session will not be started via API-only call
            Session::startSession();
            $this->requestFromUI = true;
        }
    }

    /**
     * We create a hash for session id for API accounts and then use the hash so we dont need
     * to get everything again from DB during auth.
     * Everything is ready in the session, included behind
     * the ID of SHA256 hash.
     *
     * It uses version ref in the hash that changes with each commit so all API sessions are re-generated
     * whenever new version is published, this does not apply to UI sessions
     */
    private function handleHashedAPISession()
    {
        $this->authpw = su::gis($_SERVER['PHP_AUTH_PW']);
        $this->authuserId = su::gis($_SERVER['PHP_AUTH_USER']);

        if (session_status() == PHP_SESSION_NONE && ! empty($this->authpw) && ! empty($this->authuserId)) {
            $hashedId = 'api_' . hash('sha256', mb_strtolower($this->authuserId) . su::getVersionRef() . $this->authpw);
            session::startSession(true, true, $hashedId);
        }
    }

    /**
     * Request validity checking related things and also servers CORS headers if neededd
     *
     * We can ignore all other request methods, GET, POST and OPTIONS are supported
     */
    private function checkForValidRequest()
    {

        // We will set these Access control headers only if user sends request to API via UI session.
        // Browser needs these
        // These headers need to be here due to incoming API requests from the APP
        // The headers will be checked first at OPTIONS request and then also again
        // in the immediate POST that will follow
        $meth = &$_SERVER['REQUEST_METHOD'];

        $opt = (isset($meth) && $meth === 'OPTIONS') ?: false;
        if (isset($_COOKIE['PHPSESSID']) || $opt) {
            $this->serveCORSHeaders();
            if ($opt) {
                // With options request we just serve the CORS headers and then we are done, no reason to even attempt to do
                // anything else like auth or stuff
                exit();
            }
        }

        if (! isset($meth) || empty($meth) || ($meth !== 'GET' && $meth !== 'POST')) {
            throw new GenException('Request method not valid');
        }

        // Build here other protection, maybe a honeypot to catch those pesky scanners and other malicious peoples
    }

    /**
     * These are meant for cross-domain api usage via ajax calls.
     * Browsers need them so they can post to other domains
     */
    private function serveCORSHeaders()
    {

        // These headers need to be here due to incoming API requests from the APP
        // The headers will be checked first at OPTIONS request and then also again
        // in the immediate POST that will follow
        header('Access-Control-Allow-Origin: ' . Config::get('UIURL'));
        header('Access-Control-Allow-Headers: Content-Type, CSRFTOKEN, PHPSESSID');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 600');
        header("Access-Control-Allow-Methods: POST, OPTIONS");
    }

    /**
     * Final part of request handling
     * Echo the stuff out and be good
     */
    private function serveResponse()
    {
        http_response_code($this->responseCode);

        if ($this->contentType === 'XML') {
            $respContentTypeHeader = 'application/xml';
        } elseif ($this->contentType === 'JSON') {
            $respContentTypeHeader = 'application/json';
        } else {
            $respContentTypeHeader = null;
        }

        if ($respContentTypeHeader !== null) {
            header("CONTENT-TYPE: $respContentTypeHeader");
        }
        // New http standard says that headers must be upper case
        su::endOB();
        echo trim($this->respContent);
    }

    private function setGenericImplementationException()
    {
        $this->responseCode = 501;
        $this->setExceptionResponseContent($this->e->getMessage(), $this->responseCode);
    }

    private function setUnexpectedException()
    {
        $this->responseCode = 500;
        if (Config::get('ENV') === 'local') {
            $this->setExceptionResponseContent('Server error: ' . $this->e, $this->responseCode);
        } else {
            $this->setExceptionResponseContent('Server error', $this->responseCode);
        }
    }

    private function setHaltableException()
    {
        $this->responseCode = 412;
        $this->setExceptionResponseContent($this->e->getMessage(), $this->responseCode);
    }

    private function setGenException()
    {
        $this->responseCode = 400;
        $this->setExceptionResponseContent($this->e->getMessage(), $this->responseCode);
    }

    private function setInvalidCredentialsException()
    {
        $this->responseCode = 401;
        $this->setExceptionResponseContent($this->e->getMessage(), $this->responseCode);
    }

    /**
     * The content and the code can come from this class from the try catch blocks
     * OR from the execution which was ongoing and threw exception.
     *
     * Execution is always used as primary and this class as secondary
     *
     *
     * @param string $msg
     * @param string $code
     */
    private function setExceptionResponseContent($msg, $code)
    {
        $this->responseCode = su::gis($this->execution->errorCode) ?: $this->responseCode;

        if (! empty($this->execution->responseContent)) {
            $this->respContent = $this->execution->responseContent;
            return;
        }

        if ($this->getRequest() instanceof Request) {
            $contentType = $this->getRequest()->getContentType();
        } else {
            $contentType = null;
        }

        $resparr = [
            'errorMessage' => $msg,
            'errorCode' => $code
        ];

        if ($this->request instanceof Request) {
            $resparr['requestId'] = $this->getRequest()
                ->getId()
                ->__toString();
        }
        if ($this->execution instanceof Execution) {
            $resparr['executionId'] = $this->execution->getId()->__toString();
        }
        $this->respContent = su::arrayToJSONorXML($resparr, $contentType);
    }

    /**
     * This is not normally needed, only with unittest
     *
     * @return RequestHttp
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Return the HTTP POST BODY
     *
     * @return string
     */
    public static function getPostData()
    {
        if (isset($_ENV["PHPUNITRUNNING"])) {
            return $_SERVER['MOCKUPBODY'];
        } else {
            return file_get_contents("php://input");
        }
    }
}