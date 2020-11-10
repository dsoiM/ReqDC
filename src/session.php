 <?php

class Session
{

    const USER = 'USER';

    const SELECTEDTENANT = 'selectedTenant';

    const MAXCSRFTOKENS = 5;

    /**
     * First attempts to get the key value from request scope globals, if it does not exist, then it attempts to get it from actual
     * session.
     *
     * @param
     *            string
     * @return mixed
     */
    public static function getKey(string $val)
    {
        return isset($_SESSION[$val]) ? $_SESSION[$val] : null;
    }

    /**
     * First attempts to get the key value from request scope globals, if it does not exist, then it attempts to get it from actual
     * session.
     *
     * @param
     *            string
     * @return mixed
     */
    public static function getKeyRequestScope(string $val)
    {
        global $requestScopeGlobals;
        return isset($requestScopeGlobals[$val]) ? $requestScopeGlobals[$val] : null;
    }

    /**
     * Sets a key in session scope and it will remain through the session
     *
     * @param string $key
     * @param mixed $val
     */
    public static function setKey(string $key, $val)
    {
        $_SESSION[$key] = $val;
    }

    /**
     * This actually uses a generic global variable instead of the session so we can call it request scope
     *
     * @param
     *            $key
     * @param
     *            $val
     */
    public static function setKeyReqestScope(string $key, $val)
    {
        global $requestScopeGlobals;
        $requestScopeGlobals[$key] = $val;
    }

    public static function destroy()
    {
        $sessName = session_name();
        if ( isset( $_COOKIE[$sessName] ) ) {
            setcookie( $sessName, "", time()-3600, "/", Config::get('COOKIEDOMAIN'),Config::get('ENV') === 'local' ? false : true,true);
            //clear session from globals
        }
        $_SESSION = array();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
    }

    /**
     * Generic values that are needed for rendering frontend and choices
     * These are not needed or used for API requests
     *
     * @param string $userId
     * @param string $userEmail
     * @param string $tenantId
     *            Selected tenant id
     * @param string $tenantName
     *            Selected tenant name
     * @param array $allowedTenantIds
     * @param array $allowedTenantData
     */
    public static function setUserViewVars(User $user)
    {
        Session::setKey('userViewVars', [
            'isAdmin' => Session::getUser()->admin,
            'userId' => $user->getId(),
            'userEmail' => $user->getEmail(),
            'tenantId' => Session::getSelectedTenantId(),
            'tenantName' => $user->getAllowedTenantData()[Session::getSelectedTenantId()]->name,
            'allowedTenantIds' => $user->getAllowedTenantIds(),
            'allowedTenantData' => $user->getAllowedTenantData(),
            'timezone' => $user->getTimezone(),
            'dateformat' => user::DATEFORMAT
        ]);
    }

    /**
     *
     * @return array
     */
    public static function getUserViewVars()
    {
        return Session::getKey('userViewVars');
    }

    /**
     * Session user is populated only if the user is authenticated
     *
     * @return NULL|user
     */
    public static function getUser()
    {
        return self::getKey(self::USER) ?: null;
    }

    public static function setUser(user $user)
    {
        self::setkey(self::USER, $user);
    }

    /**
     *
     * @return string
     */
    public static function getSelectedTenantId()
    {
        return self::getKey(self::SELECTEDTENANT) ?: null;
    }
     
    public static function ifUserIsSelectedTenantAdmin() {
        return session::getUser()->isTenantAdmin(session::getSelectedTenantId());
        
    }

    /**
     * Set selected tenant for this session..
     * could be that this should be actually user property?
     *
     * @param string $id
     * @param User $user
     * @throws TenantNotAllowedException
     */
    public static function setSelectedTenantId($id, $user)
    {
        if ($user->ifTenantAllowed($id)) {
            self::setkey(self::SELECTEDTENANT, $id);
        } else {
            throw new TenantNotAllowedException('Not allowed to select this tenant: ' . $id);
        }
    }

    /**
     * Return Schedule ID if Run is in question.
     * Otherwise null
     *
     * @return mixed|NULL
     */
    public static function getScheduleId()
    {
        return Session::getKeyRequestScope('runByScheduleId');
    }

    public static function setScheduleId($scheduleId)
    {
        Session::setKeyReqestScope('runByScheduleId', su::strToObjectId($scheduleId));
    }

    /**
     * Sets sessionhandler and starts session
     *
     * We will not use cookies in API sessions, as it is kinda like cached session
     * where we dont need to authenticate the user every time
     */
    public static function startSession($forceStart = false, $noCookies = false, $customSessionID = null)
    {
        if (! isset($_COOKIE['PHPSESSID']) && $forceStart === false) {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (! empty($customSessionID)) {
            session_id($customSessionID);
        }

        $handler = new MongoSessionHandler(DB::session());

        session_set_save_handler($handler);
        $opt = [];

        if ($noCookies === true) {
            $opt = [
                'use_cookies' => 0
            ];
        }
        log::debug('Session starting');

        session_start($opt);
    }

    /**
     * Validate a provided CSRF token, returns true if token is valid and request handling can
     * procceed
     *
     *
     * @return bool
     */
    public static function isValidCSRF($token)
    {
        $arr = Session::getKey('CSRFTokens');
        if (in_array($token, $arr)) {
            return true;
        }
        return false;
    }

    /**
     * Simplified CSRF token handling.
     *
     * We keep 10 tokens active at all times and a new page load (any page load) creates a new token and pushes
     * out the oldest one.
     *
     * Tokens are used only by ajax requests as there are no CSRF-requiring forms or actions in the UI, everything is done with API calls through Control API
     *
     *
     * @return string
     */
    public static function getAndStoreFreshCSRFTokenForIndex()
    {
        $token = su::guidv4();
        $arr = Session::getKey('CSRFTokens');
        if (! is_array($arr)) {
            $arr = [];
        }
        $arr[] = $token;
        while (count($arr) > self::MAXCSRFTOKENS) {
            $nul = array_shift($arr);
        }
        Session::setKey('CSRFTokens', $arr);

        return $token;
    }

    public static function deleteAllForUser($userId)
    {
        return db::session()->deleteMany([
            'userId' => $userId
        ]);
    }
    
    public static function deleteAll() {
        session::destroy();
        return db::session()->deleteMany([]);
        
    }
}