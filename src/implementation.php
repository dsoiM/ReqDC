<?php

/**
 *
 * Implementation describes what should be done
 *
 * Extending class can:
 * Set response: $this->execution->setResponseByArray()
 *
 * @author dso
 *
 */
abstract class Implementation
{

    /**
     *
     * If implementation is being executed, it must know its execution, but its not mandatory
     * if the implementation object instance exists for other purposes
     *
     * Also request can be accessed from execution
     *
     *
     * @var Execution
     */
    protected $execution;

    /**
     * 
     * Biggest possible number assigned to POID
     * Max running executions amount, also known as threads, comes normally from db implementation definition but can be overwritten
     * 
     *
     * @var int
     */
    public $maxPOIDNumber;

    /**
     * Comes normally from config but can be overwritten
     *
     * @var int
     */
    public $maxSchedulepickupLifeTimeSeconds;

    public $id;

    public $name;

    public $description;

    public $notificationEmailRecipients;

    /**
     * Request must be set
     *
     * @throws ImplementationNotAllowedGenException
     */
    public function checkTenantPermissionFromRequest($request)
    {
        if (! in_array(get_class($this), Session::getUser()->getAllowedTenantData()[$request->getTenantId()]->getAllowedImplementations())) {
            throw new ImplementationNotAllowedGenException('This implementation is not allowed');
        }
    }

    /**
     *
     * @return string
     */
    public function getId()
    {
        return get_class($this);
    }

    public function setExecution(&$execution)
    {
        $this->execution = &$execution;
    }

    /**
     *
     * @return Execution
     */
    public function getExecution()
    {
        return $this->execution;
    }

    /**
     * Set logger ID's correctly
     */
    public function prepareLogger()
    {
        Log::setExecutionId($this->getExecution()->getId());
        Log::setRequestId($this->getExecution()
            ->getRequest()
            ->getId());
        Log::setImplementationId($this->getid());
    }

    /**
     * Execute is always called from execution class
     */
    abstract public function execute();

    /**
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Instanciation always via static methods
     */
    private function __construct()
    {}

    /**
     *
     * @param string $implName
     * @throws NotFoundException
     * @return Implementation
     */
    public static function getById($implName)
    {
        if (! in_array($implName, Session::getUser()->getAllowedImplementationIds())) {
            throw new AccessDeniedException('Implementation ' . $implName . ' not found');
        }
        $implFile = 'Implementations/' . $implName . '.php';
        if (file_exists($implFile)) {
            require_once $implFile;
        }
        $implClasses = @class_parents($implName);
        if (! $implClasses || ! in_array('Implementation', $implClasses)) {
            throw new NotFoundException('Implementation ' . $implName . ' not found');
        }

        $res = db::impl()->findOne([
            'id' => $implName
        ]);

        if ($res) {
            $obj = new $implName();
            foreach ($res as $k => $v) {
                $obj->{$k} = $v;
            }
        } else {
            throw new NotFoundException('Implementation ' . $implName . ' not found in DB');
        }

        return $obj;
    }

    /**
     * Gets maxthreadcount from implementation
     *
     * @return int
     */
    public function getMaxPOIDNumber()
    {
        if (is_numeric($this->maxPOIDNumber)) {
            return $this->maxPOIDNumber;
        }
        return Config::get('MAXPOIDNUMBER');
    }

    /**
     * Gets sdsa from implementation
     *
     * @return int
     */
    public function getMaxSchedulepickupLifeTimeSeconds()
    {
        if (is_numeric($this->maxSchedulepickupLifeTimeSeconds)) {
            return $this->maxSchedulepickupLifeTimeSeconds;
        } else {
            return config::get('MAXSCHEDULEPICKUPLIFETIMESECONDS');
        }
    }

    /**
     */
    /**
     *
     * @return mixed
     */
    public function getNotificationEmailRecipients()
    {
        return $this->notificationEmailRecipients;
    }

    /**
     *
     * @param mixed $notificationEmailRecipients
     */
    public function setNotificationEmailRecipients($notificationEmailRecipients)
    {
        $this->notificationEmailRecipients = $notificationEmailRecipients;
    }

    /**
     *
     * @param number $maxPOIDNumber
     */
    public function setMaxPOIDNumber($maxPOIDNumber)
    {
        $this->maxPOIDNumber = $maxPOIDNumber;
    }

    /**
     *
     * @param number $maxSchedulepickupLifeTimeSeconds
     */
    public function setMaxSchedulepickupLifeTimeSeconds($maxSchedulepickupLifeTimeSeconds)
    {
        $this->maxSchedulepickupLifeTimeSeconds = $maxSchedulepickupLifeTimeSeconds;
    }

    /**
     *
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     *
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     *
     * @param mixed $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }
    
    public function getCurrentRawPayload() {
        return $this->getExecution()->getRequest()->payload;
    }
    
}

