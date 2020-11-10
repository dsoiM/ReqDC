<?php

class Log
{

    public $requestId;

    public $implementationId;

    public $executionId;

    public $msg;

    public $time;

    public $level;

    public $pid;

    public $hostname;

    public $userId;

    public static function info($msg)
    {
        self::log($msg, 'INFO');
    }

    public static function debug($msg)
    {
        self::log($msg, 'DEBUG');
    }

    public static function warn($msg)
    {
        self::log($msg, 'WARN');
    }

    public static function error($msg)
    {
        self::log($msg, 'ERROR');
    }

    public static function fatal($msg)
    {
        self::log($msg, 'FATAL');
    }

    protected static function log($msg, $level)
    {
        $log = new Log();

        $logrequestId = self::getRequestId();
        $logimplementationId = self::getImplementationId();
        $logexecutionId = self::getExecutionId();

        if (empty(trim($msg))) {
            // It's just useless logging empty message
            return;
        }
        $log->requestId = $logrequestId;
        $log->implementationId = $logimplementationId;
        $log->executionId = $logexecutionId;
        $log->level = $level;

        if ($msg instanceof HaltableException) {
            $log->msg = $msg->getMessage();

            // If haltables are passed as exception, they will always
            // /get the log level of HALT and it is not
            // possible to manually log at halt level
            $log->level = "HALT";
            $file = basename($msg->getFile());
            $line = $msg->getLine();
        } elseif ($msg instanceof Throwable) {
            $log->msg = $msg->__toString();
            $msgWithNoStackTrace = $msg->getMessage();
            $file = basename($msg->getFile());
            $line = $msg->getLine();
        } else {
            $log->msg = (string) $msg;
            $stuf = debug_backtrace();
            $file = basename($stuf[1]['file']);
            $line = $stuf[1]['line'];
        }

        $log->pid = getmypid();
        $log->hostname = gethostname();
        $log->userId = Session::getUser() ? Session::getUser()->getId() : null;

        $c = " |";
        $errorLog = ini_get('error_log');
        if (empty($errorLog)) {
            $errorLog = '/var/log/php_errors.log';
        }
        error_log(date('Y-m-d\TH:i:s') . $c . $log->hostname . $c . $log->userId . $c . $log->level . $c . $log->pid . $c . "$file:$line" . $c . $log->requestId . $c . $log->executionId . $c . str_replace("\n", " ", $log->msg) . "\n", 3, $errorLog);
        if (isset($msgWithNoStackTrace)) {
            $log->msg = $msgWithNoStackTrace;
        }

        // We don't need to save to db anything else than execution related logs
        // At least for time being. Maybe admins could find it useful to have all logs centralized regardless of node
        if ($logexecutionId instanceof MongoDB\BSON\ObjectId) {
            $log->time = su::timeToBSON();
            $log->saveDb();
        }
    }

    protected function saveDb()
    {
        DB::log()->insertOne($this);
    }

    private function __construct()
    {}

    public static function setRequestId(MongoDB\BSON\ObjectId $id)
    {
        Session::setKeyReqestScope('logrequestId', $id);
    }

    /**
     *
     * @return MongoDB\BSON\ObjectId
     */
    public static function getRequestId()
    {
        return Session::getKeyRequestScope('logrequestId');
    }

    public static function setImplementationId($id)
    {
        Session::setKeyReqestScope('logimplementationId', $id);
    }

    /**
     *
     * @return MongoDB\BSON\ObjectId
     */
    public static function getImplementationId()
    {
        return Session::getKeyRequestScope('logimplementationId');
    }

    public static function setExecutionId(MongoDB\BSON\ObjectId $id)
    {
        Session::setKeyReqestScope('logexecutionId', $id);
    }

    public static function removeExecution()
    {
        Session::setKeyReqestScope('logexecutionId', null);
    }

    /**
     *
     * @return MongoDB\BSON\ObjectId
     */
    public static function getExecutionId()
    {
        return Session::getKeyRequestScope('logexecutionId');
    }

    /**
     *
     * @param \MongoDB\BSON\ObjectId $id
     *            Execution ObjectId
     * @return array
     */
    public static function getForExecution(MongoDB\BSON\ObjectId $id)
    {
        return DB::log()->find([
            'executionId' => $id
        ], [
            'sort' => [
                '_id' => - 1
            ]
        ])->toArray();
    }
}