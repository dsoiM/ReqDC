<?php

/**
 * Execution describes what is being done or was done.
 *
 *
 * @author dso
 *
 */
class Execution
{

    /**
     *
     * @var MongoDB\BSON\ObjectId
     */
    private $_id = null;

    /**
     *
     * @var implementation
     */
    protected $implementation;

    /**
     *
     * @var request
     */
    protected $request;

    /**
     * Raw response content is here if such is generated on synchronous execution, it is not necessary.
     */
    public $responseContent;

    /**
     * Raw header
     */
    public $responseContentTypeHeader;

    public $implementationId;

    public $requestId;

    public $tenantId;

    public $scheduled = false;

    // Unhandled
    const STATUS_ERR = 'ERROR';

    // Request malformed
    const STATUS_HALTED = 'HALTED';

    const STATUS_STARTED = 'STARTED';

    const STATUS_DONE = 'DONE';

    public $startTime;

    public $endTime;

    /**
     *
     * @var string
     */
    public $status;

    public $errorCode;

    /**
     * Hostname where being executed
     */
    public $node;
    
    /**
     * POID is brought in from the schedule
     * @var string
     */
    public $POID;

    private function __construct(MongoDB\BSON\ObjectId $id = null)
    {
        $this->_id = $id;
    }

    /**
     * This is the standard entry point for Execution startup.
     * All you need is a request, which has already the implementation and tenant figured out
     *
     * But what to do when we want to do sub-requests or sub-executions.
     *
     * Lets say handling 100 users in one big execution is not viable, so we must split it to smaller chunks
     *
     * Is it so that we are using one request for all subexecutions - but if so, how do we feed data
     * as data is only in request
     *
     *
     * @param Request $request
     * @return Execution
     */
    public static function newFromRequest(Request $request)
    {
        $exc = new Execution();
        $impl = $request->getAndSetImplementation();
        $exc->setImplementation($impl);
        $exc->setImplementationId($impl->getId());
        $exc->node = gethostname();
        $exc->scheduleId = session::getScheduleId();

        $exc->setRequest($request);
        $exc->setRequestId($request->getId());

        $exc->setTenantId($request->getTenantId());
        return $exc;
    }

    /**
     * Returns all log rows from DB as an array for this execution
     *
     * @return string[]
     */
    public function getLogs()
    {
        return log::getForExecution($this->getId());
    }

    /**
     * Instanciates an execution by ID
     *
     * @param mixed $id
     * @throws Exception
     * @return Execution
     */
    public static function getById($id)
    {
        $res = DB::exc()->findOne([
            '_id' => su::strToObjectId($id)
        ] + db::tenantFilter());
        if (!$res) {
            throw new NotFoundException('Execution not found by ID: ' . $id);
        }
        return Execution::byDoc($res);
    }

    /**
     *
     * @param MongoDB\BSON\ObjectId $id
     * @return Execution[]|boolean
     */
    public static function getAllByRequestId(MongoDB\BSON\ObjectId $requestId)
    {
        $res = DB::exc()->find([
            'requestId' => $requestId,
            'tenantId' => Session::getSelectedTenantId()
        ]);
        if ($res) {
            $allObjs = [];
            foreach ($res as $ex) {
                $allObjs[] = Execution::byDoc($ex);;
            }
        } else {
            return false;
        }
        return $allObjs;
    }
    
    private static function byDoc($ex) {
        $obj = new Execution($ex->_id);
        foreach ($ex as $k => $v) {
            $obj->{$k} = $v;
        }
        return $obj;
    }

    public static function getListingForViewTable(MongoDB\BSON\UTCDateTime $beg, MongoDB\BSON\UTCDateTime $end, $status, $implId, int $limit)
    {
        $findQuery = db::tenantFilter() + [

            'startTime' => [
                '$gte' => $beg,
                '$lt' => $end
            ]
        ];
        // TODO: Change this to use aggregation and retrieve the request using lookup
        if (! empty($status)) {
            $findQuery['status'] = $status;
        }
        if (! empty($implId)) {
            $findQuery['implementationId'] = $implId;
        }

        return DB::exc()->find($findQuery, db::sortByIdAsc() + [
            'limit' => $limit
        ])->toArray();
    }

    public function setResponseByArray(array $array)
    {
        $this->responseContent = su::arrayToJSONorXML($array, $this->request->getContentType());
    }

    public function handleResponse()
    {
        if (empty($this->responseContent)) {
            $this->setDefaultResponse();
        }
    }

    /**
     * Overwrites the execution object in DB
     * or creates if it doesn't exist
     */
    public function saveToDB()
    {
        if ($this->getId() instanceof MongoDB\BSON\ObjectId) {
            $replaceResult = DB::exc()->replaceOne([
                '_id' => $this->getId()
            ], $this);
            if (! $replaceResult->isAcknowledged()) {
                throw new Exception('Failed to update exec to DB');
            }
            Log::debug('Execution updated to DB: ' . $this->getId());
        } else {
            // New entry
            $insertOneResult = DB::exc()->insertOne($this);
            $this->_id = $insertOneResult->getInsertedId();
            Log::debug('New Execution saved to db: ' . $this->getId());
        }
    }

    public function setDefaultResponse()
    {
        $defaultRespArray = [
            'requestId' => $this->request->getId()->__toString(),
            'executionId' => $this->getId()->__toString()
        ];

        $this->responseContent = (su::arrayToJSONorXML($defaultRespArray, $this->request->getContentType()));
    }

    public function setErrorResponse($msg)
    {
        $resparr = [
            'errorMessage' => $msg,
            'errorCode' => $this->errorCode
        ];
        $contentType = null;
        if ($this->getRequest() instanceof Request) {
            $resparr['requestId'] = $this->getRequest()
                ->getId()
                ->__toString();
            $contentType = $this->getRequest()->getContentType();
        }

        $resparr['executionId'] = $this->getId()->__toString();
        $this->responseContent = su::arrayToJSONorXML($resparr, $contentType);
    }

    public static function externalException(Throwable $e)
    {
        $ex = Session::getKeyRequestScope('currentlyExecutedExecution');
        if ($ex instanceof Execution) {
            $ex->exceptionHandler($e);
        }
    }

    /**
     * In case something goes wrong, this is run
     *
     * @param Throwable $e
     */
    public function exceptionHandler(Throwable $e, $errorCode = 500)
    {
        $this->errorCode = $errorCode;
        if ($e instanceof HaltableException) {
            $this->setStatus(Execution::STATUS_HALTED);
            // Send email here to customer contacts?
            Log::warn($e);
        } else {
            $this->setStatus(Execution::STATUS_ERR);
            // Send email here to internal contacts?
            Log::error($e);
        }
        $this->setErrorResponse($e->getMessage(), $this->getRequest(), $this);

        $this->setEndTime(su::timeToBSON());
        Log::removeExecution();
        $this->saveToDB();
    }

    /**
     * Prepares implementation, logger and fires up the implementation execute function
     *
     * Saves execution to db before and after with applicable timestamps and statuses
     */
    public function start()
    {
        try {

            $this->setStatus(self::STATUS_STARTED);
            $this->setStartTime(su::timeToBSON());
            $this->saveToDB();
            $this->getImplementation()->setExecution($this);
            $this->getImplementation()->prepareLogger();

            Log::info('Starting execution for implementation ' . get_class($this->getImplementation()));

            Session::setKeyReqestScope('currentlyExecutedExecution', $this);
            Session::setKeyReqestScope('mostRecentExecution', $this);  // This is needed mostly in unittests
            
            $this->getImplementation()->execute();

            Session::setKeyReqestScope('currentlyExecutedExecution', null);

            Log::info('Finished execution for implementation ' . get_class($this->getImplementation()));
            $this->handleResponse();
            $this->setStatus(self::STATUS_DONE);
            $this->setEndTime(su::timeToBSON());
            Log::removeExecution();
            $this->saveToDB();
        } catch (HaltableException $e) {
            $this->exceptionHandler($e, 412);
            throw $e;
        } catch (GenException $e) {
            $this->exceptionHandler($e, 400);
            throw $e;
        } catch (Throwable $e) {
            $this->exceptionHandler($e, 500);
            throw $e;
        }
    }

    /**
     *
     * @return MongoDB\BSON\ObjectId
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     *
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     *
     * @return int
     */
    public function getTenantId()
    {
        return $this->tenantId;
    }

    /**
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     *
     * @param mixed $implementationId
     */
    public function setImplementationId($implementationId)
    {
        $this->implementationId = $implementationId;
    }

    /**
     *
     * @param MongoDB\BSON\ObjectId $requestId
     */
    public function setRequestId(MongoDB\BSON\ObjectId $requestId)
    {
        $this->requestId = $requestId;
    }

    /**
     *
     * @param mixed $tenantId
     */
    public function setTenantId($tenantId)
    {
        $this->tenantId = $tenantId;
    }

    /**
     * Using a reference to original implementation object
     *
     * @param implementation $impl
     */
    public function setImplementation(implementation $impl)
    {
        $this->implementation = &$impl;
    }

    /**
     *
     * @return implementation
     */
    public function getImplementation()
    {
        if (! $this->implementation) {
            $this->setImplementation(Implementation::getById($this->implementationId));
        }
        return $this->implementation;
    }

    /**
     * Using a pointer so that the request is not cloned when accessed
     *
     * @param request $req
     */
    public function setRequest(request $req)
    {
        $this->request = &$req;
    }

    /**
     *
     * @return Request
     */
    public function getRequest($allowFail = false)
    {
        if (! $this->request) {
            try {
                $this->setRequest(Request::getById($this->requestId));
            } catch (Throwable $e) {
                Log::error($e);

                if ($allowFail !== true) {
                    throw $e;
                }
            }
        }

        return $this->request;
    }

    /**
     *
     * @return \MongoDB\BSON\UTCDateTime
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     *
     * @return \MongoDB\BSON\UTCDateTime
     */
    public function getEndTime()
    {
        return $this->endTime;
    }

    /**
     *
     * @param \MongoDB\BSON\UTCDateTime $startTime
     */
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;
    }

    /**
     *
     * @param \MongoDB\BSON\UTCDateTime $endTime
     */
    public function setEndTime($endTime)
    {
        $this->endTime = $endTime;
    }

    /**
     *
     * @return boolean
     */
    public function getscheduled()
    {
        return $this->scheduled;
    }

    /**
     *
     * @param boolean $wasScheduled
     */
    public function setscheduled($scheduled)
    {
        $this->scheduled = $scheduled;
    }
    /**
     * @return string
     */
    public function getPOID()
    {
        return $this->POID;
    }

    /**
     * @param string $POID
     */
    public function setPOID($POID)
    {
        $this->POID = $POID;
    }

}