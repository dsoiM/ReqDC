<?php
use MongoDB\BSON\ObjectId;
use MongoDB\Model\BSONDocument;
use MongoDB\BSON\Timestamp;

/**
 * Everything that happens on timed interval is in schedule collection,
 * it can be a request, execution or implementation that is scheduled (RLY!?)
 *
 * This table will get extremely heavy polling, so it might be good idea to store it in redis?
 */
class Schedule
{

    private $_id;

    // First thing that happens is that node saves its name in this field
    // This is the most vital field that prevents race conditions. When this field is updated, the clause must
    // include that the field must be empty upon updating. If that update failed we know that another node
    // already picked it up
    public $pickedUpBy;

    // Can be one of: Request, CronExpression
    public $targetType;

    // ID of the target type
    public $targetId;

    // Needs to be known
    public $tenantId;

    // This expresses when stuff will go down. If this field is empty, it means that schedule will be run only once
    public $cronExpression;

    // Timestamp, only needed if cron expression is not null
    public $lastRun;

    /**
     * When this schedule is due to run next, must be always defined
     */
    public $nextRun;

    /**
     * Everywhere else referred as POID
     * 
     * This ID defines a single message queue, all messages with same ID will be queued.
     * Usually contains implementationId_threadNumber but can be anything in custom cases
     * 
     * Another name for this could be Thread ID or thread queue ID
     */
    public $preserveOrderId;

    /**
     * If this contains a timestamp, it means that there is an Run going on for this scheduleId and other may not be started with same Id
     *
     * @var Timestamp
     */
    public $POStarted;

    /**
     * Max schedule lifetime has only meaning when POStarted is not null.
     * Then lifetime will mean that once that amount of seconds has passed since POStarted, the schedule entry will be forcibly removed
     * and then it allows
     *
     *
     * @var int
     */
    public $POStartedMaxLifetime;

    /**
     * Id of implementation, needed for order preservation and cron expression
     *
     * @var string
     */
    public $implementationId;

    /**
     * This is needed for cron expression timed entries - we need to know what access rights to use when doing Run
     *
     * @var string
     */
    public $userId;

    private function __construct(MongoDB\BSON\ObjectId $id = null)
    {
        $this->_id = $id;
    }

    /**
     * This type is one-off which will be deleted immediately after picked up
     *
     * @param Request $req
     * @param string $when
     * @param string $preserveOrderId
     */
    public static function createFromRequest(Request $req, $when = 'now', $preserveOrderId = null)
    {
        Log::info('Creating new scheduled exec for existing request ' . $req->getId() . 'at: ' . $when);
        $sched = new Schedule();
        if ($preserveOrderId === null) {
            $preserveOrderId = $sched->getNextPOIDForImplementationId($req->getImplementation()->getId(), $req->getImplementation()->getMaxPOIDNumber());         
        }
        $sched->setPreserveOrderId($preserveOrderId);
        $sched->setTargetId($req->getId());
        $sched->setTargetType('Request');
        $sched->setNextRun(su::timeToBSON($when));
        $sched->setTenantId($req->getTenantId());
        $sched->setPOStartedMaxLifetime($req->getImplementation()
            ->getMaxSchedulepickupLifeTimeSeconds());
        $sched->setImplementationId($req->getImplementation()
            ->getId());
        $sched->setUserId(session::getUser()->getId());
        $sched->saveToDb();
        return $sched->_id;
    }

    /**
     * This entry remains in the DB until it is manually deleted
     *
     * This just gets on keeping updated by different methods
     *
     * @param string $cronExpr
     * @param string $implementationId
     *
     * @return ObjectId
     */
    public static function setFromCronExpression($implementationId, $cronExpr)
    {
        Log::info('Creating new or updating permanent schedule with cron expression');
        
        if (session::ifUserIsSelectedTenantAdmin() !== true) {
            throw new AccessDeniedException('Setting cron expression schedule is only for tenant admins');
        }
        
        
        try {
            $sched = Schedule::getByImplementationIdCronScheduled($implementationId);
        } catch (NotFoundException $e) {
            $sched = new Schedule();
        }

        $implObj = Implementation::getById($implementationId);
        $preserveOrderId = $implementationId . '_cron';
        $sched->setPreserveOrderId($preserveOrderId);
        $sched->setCronExpression($cronExpr);
        $sched->setTargetType('Implementation');
        $sched->setTargetId($implementationId);
        $nextRun = self::getNextRunFromCron($cronExpr);
        $sched->setNextRun(su::timeToBSON($nextRun));
        $sched->setTenantId(session::getSelectedTenantId());
        $sched->setPOStartedMaxLifetime($implObj->getMaxSchedulepickupLifeTimeSeconds());
        $sched->setImplementationId($implementationId);
        $sched->setUserId(session::getUser()->getId());
        $sched->saveToDb();
        return $sched->getId();
    }

    public static function delete(MongoDB\BSON\ObjectId $id)
    {
        $res = db::sched()->deleteOne([
            '_id' => $id,
            'tenantId' => session::getSelectedTenantId()
        ]);
        if ($res->getDeletedCount() !== 1) {
            throw new GenException('Failed to delete ' . $id);
        }
    }

    /**
     * Gets all distinct POIDs from schedules where NextRun is less than defined time (usually now) 
     * 
     * @param \MongoDB\BSON\UTCDateTime $nextRunlessthan
     * @param boolean $noTenantFilter
     * @return mixed[]
     */
    public static function getDistrinctPreserveOrderIds(MongoDB\BSON\UTCDateTime $nextRunlessthan, $noTenantFilter = false)
    {
        $tf = [
            'nextRun' => [
                '$lt' => $nextRunlessthan
            ]
        ] + db::tenantFilter($noTenantFilter);

        return db::sched()->distinct('preserveOrderId', $tf);
    }
    
    /**
     * Get all POIDs for implementation, used currently only for finding a free POID
     * 
     * @param string $implementationId
     * @param boolean $noTenantFilter
     * @return mixed[]
     */
    public static function getDistrinctPreserveOrderIdsForImplementation(string $implementationId, $noTenantFilter = false)
    {
        $tf = [
            'implementationId' => $implementationId
        ] + db::tenantFilter($noTenantFilter);
        
        return db::sched()->distinct('preserveOrderId', $tf);
    }
    
    
    /**
     * Gets all distict schedule POIDS from DB and finds the first integer that is free
     * 
     * If none is free, then a random one is returned between 1 and max amount
     * 
     * If max is one then just _1 is returned
     *   
     * @param string $implementationId
     */
    public function getNextPOIDForImplementationId($implementationId,$maxPOIDNumber) {
        
        if ($maxPOIDNumber == 1) {
            return $implementationId. '_1';
        }
        $usedPOIDS = self::getDistrinctPreserveOrderIdsForImplementation($implementationId);
        
        for($i=1;$i<=$maxPOIDNumber;$i++) {
            $tpoid = $implementationId."_".$i;
            if (!in_array($tpoid,$usedPOIDS)) {
                return $tpoid;
            }
        }

        return $implementationId. '_' . mt_rand(1, $maxPOIDNumber);
        
    }
    

    /**
     * *
     * Finds entries from schedule collection that should be processed according to filter criteria
     *
     * THis is the magic query which keeps things in order
     * We sort by POStarted descending, it means that non null values come first and if one does, it means that there is a thread processing for
     * this ID and then we just skip it later
     * Secondary sort value is id descending, which means we'll just pick the oldest one if no Started threads
     *
     * Scheduleservice needs to know what to Run next
     *
     * @param \MongoDB\BSON\UTCDateTime $nrlessthan
     * @param string $dpi
     * @param boolean $onlyOne
     * @param boolean $noTenantFilter
     * @return array|object|NULL|\MongoDB\Driver\Cursor
     */
    public static function getNextRunnableByPOID(MongoDB\BSON\UTCDateTime $nrlessthan, $dpi, $onlyOne = false, $noTenantFilter = false)
    {
        $q = [
            'nextRun' => [
                '$lt' => $nrlessthan
            ],
            'preserveOrderId' => $dpi
        ] + db::tenantFilter($noTenantFilter);

        $f = [
            'sort' => [
                'POStarted' => - 1,
                '_id' => - 1
            ]
        ];

        if ($onlyOne) {
            return db::sched()->findOne($q, $f);
        }
        return db::sched()->find($q, $f);
    }

    /**
     *
     * @throws SaveFailedException
     */
    public function saveToDb()
    {
        if (! empty($this->_id)) {
            $res = db::sched()->replaceOne([
                '_id' => $this->_id
            ], $this);
            if ($res->getModifiedCount() !== 1) {
                throw new SaveFailedException('Could not update');
            }
        } else {
            $res = DB::sched()->insertOne($this);
            $this->_id = $res->getInsertedId();
        }

        Log::debug('Schedule saved to DB - nextrun: ' . su::BSONTimeToString($this->nextRun) . ' type: ' . $this->targetType . ' targetId: ' . $this->targetId. 'POID: '.$this->preserveOrderId);
    }

    /**
     * Instanciates a schedule by ID
     *
     * @param string $id
     * @throws GenException
     * @return Schedule
     */
    public static function getById($id, $noTenantFilter = false)
    {
        $id = su::strToObjectId($id);
        $res = DB::sched()->findOne([
            '_id' => $id
        ] + db::tenantFilter($noTenantFilter));
        if (!$res) {
            throw new NotFoundException('Schedule not found by ID: ' . $id);
        }
        return Schedule::byDoc($res);
    }

    /**
     * Instanciates an execution by implementation ID and cron schedule must exist
     *
     * @param string $id
     * @throws GenException
     * @return Schedule
     */
    public static function getByImplementationIdCronScheduled($implementationId)
    {
        $res = DB::sched()->findOne([
            'implementationId' => $implementationId,
            'cronExpression' => [
                '$ne' => null
            ]
        ] + db::tenantFilter());
        if ($res) {
            $obj = Schedule::byDoc($res);
        } else {
            throw new NotFoundException('Schedule not found by Implementation ID: ' . $implementationId);
        }
        return $obj;
    }

    /**
     *
     * @param string $implementationId
     * @throws NotFoundException
     */
    public static function deleteByImplementationIdCronScheduled($implementationId)
    {
        
        if (session::ifUserIsSelectedTenantAdmin() !== true) {
            throw new AccessDeniedException('Deleting cron schedules is only for tenant admins');
        }
        
        $res = db::sched()->deleteOne([
            'implementationId' => $implementationId,
            'cronExpression' => [
                '$ne' => null
            ]
        ] + db::tenantFilter());

        if ($res->getDeletedCount() !== 1) {
            throw new NotFoundException('Could not delete cron schedule by implementation Id ' . $implementationId);
        }
    }

    /**
     *
     * @param BSONDocument $res
     * @return Schedule
     */
    private static function byDoc($res)
    {
        $obj = new Schedule($res->_id);
        foreach ($res as $k => $v) {
            $obj->{$k} = $v;
        }
        return $obj;
    }

    /**
     * Finalize is executed whenever a Run is ended, it must happen ALWAYS, otherwise new Schedules will not be picked for the same POID
     * as the old POID schedule is blocking any new ones.
     *
     *
     * @param string $scheduleId
     * @param boolean $noLaunch
     * @param boolean $noTenantFilter
     * @throws SchedulerException
     */
    public static function finalizeRun($scheduleId, $noLaunch = false, $noTenantFilter = false)
    {
        /**
         * '
         *
         * @var Schedule $p
         */
        $scheduleId = su::strToObjectId($scheduleId);
        $p = db::sched()->findOne([
            '_id' => $scheduleId
        ]);

        if (! $p) {
            throw new SchedulerException('Could not finalize Run: Couldnt find schedule by ID at shutdown: ' . $scheduleId);
        }

        // Cron expression is empty so we know this is one time scheduling and we delete it
        if (empty($p->cronExpression)) {
            // Only once so we delete it
            $result = db::sched()->deleteOne([
                '_id' => $p->_id
            ]);

            if ($result->getDeletedCount() !== 1) {
                // Here we could do a rollback to the nextRun which we updated above?
                throw new SchedulerException('Could not delete schedule entry after picking it up: ' . $p->_id);
            }
        } else {
            // For cron expressions
            // Calculate next run time and save it

            $result = db::sched()->updateOne([
                '_id' => $p->_id
            ] + db::tenantFilter($noTenantFilter), [
                '$set' => [
                    'pickedUpBy' => null,
                    'nextRun' => su::timeToBSON(Schedule::getNextRunFromCron($p->cronExpression)),
                    'POStarted' => null
                ]
            ]);
            if ($result->getModifiedCount() !== 1) {
                throw new SchedulerException('Could not finalizeRun for cron expression entry, couldnt update');
            }
            
        }


        // We fire up a new one immediately so we don't need to wait the ScheduleService 1 second polling
        // This will be basically immediate launching one after another
        if ($noLaunch !== true) {
            $ss = new ScheduleService();
            Log::debug('Firing up next Schedule checkup from ScheduleService for ' . $p->preserveOrderId);
            $ss->findAndLaunchOrderPreserved([
                $p->preserveOrderId
            ]);
        }
    }

    public static function getSchduleCronListingForView()
    {
        return db::sched()->find([
            'cronExpression' => [
                '$ne' => null
            ]
        ] + db::tenantFilter());
    }

    /**
     *
     * @param string $cronExpr
     * @return DateTime
     */
    public static function getNextRunFromCron($cronExpr)
    {
        try {
            $cron = \Cron\CronExpression::factory($cronExpr);
        } catch (Throwable $e) {
            throw new GenException($e->getMessage());
        }
        // Here we add 0-30 random seconds just so that everything is not started by the minute
        return $cron->getNextRunDate()->modify("+" . mt_rand(0, 30) . " seconds");
    }
    
    
    /**
     * Used by scheduleservice to pick up a single on to be processed or handled otherwise
     *
     * @param Schedule $p
     * @return \MongoDB\UpdateResult
     */
    public static function pickScheduleForMe($p,$id)
    {
        $currentTime = su::timeToBSON();
        if (empty($id)) {
            $id = gethostname() . '_NOID_' . su::guidv4();
        }
        
        return db::sched()->updateOne([
            '_id' => $p->_id,
            'lastRun' => $p->lastRun,
            'pickedUpBy' => $p->pickedUpBy,
            'nextRun' => $p->nextRun
        ], [
            '$set' => [
                'lastRun' => $currentTime,
                'pickedUpBy' => $id,
                'POStarted' => $currentTime
            ]
        ]);
    }

    /**
     *
     * @return mixed
     */
    public function getPickedUpBy()
    {
        return $this->pickedUpBy;
    }

    /**
     *
     * @return mixed
     */
    public function getTargetType()
    {
        return $this->targetType;
    }

    /**
     *
     * @return mixed
     */
    public function getTargetId()
    {
        return $this->targetId;
    }

    /**
     *
     * @return mixed
     */
    public function getTenantId()
    {
        return $this->tenantId;
    }

    /**
     *
     * @return mixed
     */
    public function getCronExpression()
    {
        return $this->cronExpression;
    }

    /**
     *
     * @return mixed
     */
    public function getLastRun()
    {
        return $this->lastRun;
    }

    /**
     *
     * @return mixed
     */
    public function getNextRun()
    {
        return $this->nextRun;
    }

    /**
     *
     * @return mixed
     */
    public function getPreserveOrderId()
    {
        return $this->preserveOrderId;
    }

    /**
     *
     * @param mixed $pickedUpBy
     */
    public function setPickedUpBy($pickedUpBy)
    {
        $this->pickedUpBy = $pickedUpBy;
    }

    /**
     *
     * @param mixed $targetType
     */
    public function setTargetType($targetType)
    {
        $this->targetType = $targetType;
    }

    /**
     *
     * @param mixed $targetId
     */
    public function setTargetId($targetId)
    {
        $this->targetId = $targetId;
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
     *
     * @param mixed $cronExpression
     */
    public function setCronExpression($cronExpression)
    {
        $this->cronExpression = $cronExpression;
    }

    /**
     *
     * @param mixed $lastRun
     */
    public function setLastRun($lastRun)
    {
        $this->lastRun = $lastRun;
    }

    /**
     *
     * @param mixed $nextRun
     */
    public function setNextRun($nextRun)
    {
        $this->nextRun = $nextRun;
    }

    /**
     *
     * @param mixed $preserveOrderId
     */
    public function setPreserveOrderId($preserveOrderId)
    {
        $this->preserveOrderId = $preserveOrderId;
    }

    /**
     *
     * @return mixed
     */
    public function getPOStarted()
    {
        return $this->POStarted;
    }

    /**
     *
     * @param mixed $POStarted
     */
    public function setPOStarted($POStarted)
    {
        $this->POStarted = $POStarted;
    }

    /**
     *
     * @return number
     */
    public function getPOStartedMaxLifetime()
    {
        return $this->POStartedMaxLifetime;
    }

    /**
     *
     * @param number $POStartedMaxLifetime
     */
    public function setPOStartedMaxLifetime($POStartedMaxLifetime)
    {
        $this->POStartedMaxLifetime = $POStartedMaxLifetime;
    }

    /**
     *
     * @return string
     */
    public function getImplementationId()
    {
        return $this->implementationId;
    }

    /**
     *
     * @param string $implementationId
     */
    public function setImplementationId($implementationId)
    {
        $this->implementationId = $implementationId;
    }

    /**
     *
     * @return \MongoDB\BSON\ObjectId
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     *
     * @param mixed $_id
     */
    public function setId($_id)
    {
        $this->_id = $_id;
    }

    /**
     *
     * @return string
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     *
     * @param string $userId
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
    }
}