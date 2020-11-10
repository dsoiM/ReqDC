<?php
use MongoDB\Model\BSONDocument;
use MongoDB\BSON\UTCDateTime;

class User
{

    const DATEFORMAT = 'Y-m-d H:i:s';

    // Default timezone
    const TIMEZONE = "Europe/London";

    private $_id;

    public $id;

    public $email;

    protected $allowedTenantIds = [];

    protected $allowedImplementationIds = [];

    protected $allowedTenantData = [];

    protected $authenticated = false;
  

    public $name;

    public $admin = false;

    public $timezone = "Europe/Helsinki";
    
    /**
     * 
     * @var int
     */
    public $failedAuthAttempts;
    
    /**
     * 
     * @var UTCDateTime
     */
    public $lockedOutUntil;

    private $password;

    /**
     *
     * @return string[]
     */
    private function __construct()
    {}

    /**
     *
     * @return array|number[]
     */
    public function getAllowedTenantIds()
    {
        return $this->allowedTenantIds;
    }
    


    public function setAllowedTenantIds()
    {
        $allowed = Tenant::getAllowedTenantIdsForUserId($this->id, $this->admin);
        $this->allowedTenantIds = $allowed;
    }

    public function getAllowedImplementationIds()
    {
        return $this->allowedImplementationIds;
    }

    public function setAllowedImplementationIds()
    {
        $allowd = $this->getAllowedTenantData()[Session::getSelectedTenantId()]->getAllowedImplementations();

        $this->allowedImplementationIds = $allowd;
    }

    /**
     * 
     * @param string $email
     * @throws InvalidCredentialsException
     * @return User
     */
    public static function getByEmail($email)
    {
        $DBRow = DB::user()->find([
            'email' => $email
        ]);

        if (! $DBRow) {
            throw new InvalidCredentialsException('User not found by email');
        }

        $usr = new User();
        $usr->byDoc($DBRow);
        return $usr;
    }
    
    /**
     * If user is admin
     * 
     * @return boolean
     */    
    public function isAdmin() {
        
        return $this->admin;
    }


    /**
     * Get user by ID
     *
     * @param string $id
     * @throws Exception
     * @return User
     */
    public static function getById($id)
    {
        $DBRow = DB::user()->findOne([
            'id' => $id
        ]);
        if (! $DBRow) {
            throw new GenException('User not found by ID');
        }
        $usr = new User();
        $usr->byDoc($DBRow);
        return $usr;
    }

    /**
     * Set properties for the user object based on DB document
     *
     * @param BSONDocument $DBRow
     */
    private function byDoc(MongoDB\Model\BSONDocument $DBRow)
    {
        foreach ($DBRow as $k => $v) {
            $this->{$k} = $v;
        }

        $this->password = null;

        // This order is important
        $this->setAllowedTenantIds();
        $this->setAllowedTenantData();
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getOId()
    {
        return $this->_id;
    }

    /**
     * The culmination of authentication phase of any API call, unless session
     * already existed
     *
     * @param string $password
     * @throws InvalidCredentialsException
     */
    public function authenticate($password)

    {
        $this->verifyLockoutStatus();

        if (! password_verify($password, $this->getPasswordHash())) {
            $this->incrementFailedAuthAttempt();
            if ($this->failedAuthAttempts > config::get('MAXAUTHATTEMPTS')) {
                $this->lockAccount();
            }
            throw new InvalidCredentialsException('userId or password incorrect');
        }
        $this->resetFailedAuthAttempt();
        $this->authenticated = true;
        Session::setUser($this);
    }

    private function incrementFailedAuthAttempt()
    {
        db::user()->updateOne([
            '_id' => $this->_id
        ], [
            '$inc' => [
                'failedAuthAttempts' => 1
            ]
        ]);
    }

    private function resetFailedAuthAttempt()
    {
        if ($this->failedAuthAttempts !== 0) {
            db::user()->updateOne([
                '_id' => $this->_id
            ], [
                '$set' => [
                    'failedAuthAttempts' => 0
                ]
            ]);
        }
    }

    private function verifyLockoutStatus()
    {
        if (! empty($this->lockedOutUntil) && $this->lockedOutUntil->toDateTime()->getTimestamp() > time()) {
            throw new InvalidCredentialsException('Account is locked.');
        }
        

    }
    
    private function lockAccount() {
        $lockUntil = time() + (int)config::get('AUTHLOCKOUTTIMESECONDS');
        db::user()->updateOne(['_id' => $this->_id], ['$set' => ['lockedOutUntil' => su::timeToBSON($lockUntil)]]);
        $this->resetFailedAuthAttempt();
        throw new InvalidCredentialsException('Account is locked.');
    }

    public function isAuthenticated()
    {
        return $this->authenticated;
    }
    public static function doUsersExist() {
        $DBRow = DB::user()->findOne();
        if (! $DBRow) {
            return false;
        }
        return true;
        
    }

    public function setAllowedTenantData()
    {
        $ret = [];
        foreach ($this->getAllowedTenantIds() as $allowedCustId) {
            $ret[$allowedCustId] = Tenant::byId($allowedCustId);
        }
        $this->allowedTenantData = $ret;
    }

    public function ifTenantAllowed($tenantId)
    {
        if (! in_array($tenantId, $this->getAllowedTenantIds())) {
            throw new TenantNotAllowedException('This tenant ' . $tenantId . ' is not allowed');
        }
        return true;
    }
    
    public function changeTenant($newTenantId)
    {
        if (in_array($newTenantId, $this->getAllowedTenantIds())) {
            $this->selectTenantAndSaveToSession($newTenantId);
        }
    }
    

    /**
     * 
     * @param string $tenantId
     * @return boolean
     */
    public function isTenantAdmin($tenantId) {
        
        
        if ($this->isAdmin() === true) {
            return true;
        }
        
        $tenantData = $this->getAllowedTenantData();
        if (!isset($tenantData[$tenantId])) {
            log::debug('User cannot be an admin to tenant where the user is not allowed');
            return false;
        }
        if ( in_array($this->getId(), $tenantData[$tenantId]->adminUserIds)) {
            return true;
        }
        return false;
        
    }

    public function getAllowedTenantData()
    {
        return $this->allowedTenantData;
    }

    public function selectTenantAndSaveToSession($tenantId, $apiUsed = false)
    {
        Session::setSelectedTenantId($tenantId, $this);
        $this->setAllowedImplementationIds();
        Session::setUser($this);

        if ($apiUsed === false) {
            Session::setUserViewVars($this);
        }
    }

    public function setPassword($password)
    {
        $hashPw = password_hash($password, PASSWORD_DEFAULT);
        db::user()->updateOne([
            '_id' => $this->_id
        ], [
            '$set' => [
                'password' => $hashPw
            ]
        ]);
        $this->password = $hashPw;
    }

    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Returns the hash from DB, always fresh and it is not stored separately
     */
    public function getPasswordHash()
    {
        $usr = db::user()->findOne([
            '_id' => $this->getOId()
        ]);
        return $usr->password;
    }

    /**
     *
     * @return string
     */
    public function getTimezone()
    {
        return $this->timezone;
    }

    /**
     *
     * @param string $timezone
     */
    public function setTimezone($timezone)
    {
        $this->timezone = $timezone;
    }
}