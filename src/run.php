<?php

/**
 * Class that is started from command line to execute standalone threads (Runs) in the background.
 * These executions will never have synchronicity
 * 
 * Params come from command line
 * 
 * @author vehja
 *
 */
class Run
{

    // These parameters are 1 => targetType, 2 => targetId, 3 => scheduleId
    public static function main()
    {
        ob_start();
        
        require_once ("src/autoload.php");
        
        Config::setLogfileLocation(Config::get('RUNLOGFILE'));

        $targetType = $_SERVER['argv'][1];
        $targetId = $_SERVER['argv'][2];
        $scheduleId = $_SERVER['argv'][3];

        try {
            session::setScheduleId($scheduleId);

            if ($targetType === 'Request') {
                run::executeRequest($targetId);
            } elseif ($targetType === 'Implementation') {
                run::executeImplementation($targetId, $scheduleId);
            }
        } catch (Throwable $e) {
            Log::removeExecution();
            Log::error($e);
        }
        su::endOB();
    }

    private static function executeRequest($reqId)
    {
        Log::info('Run starting up with Request: ' . $reqId);
        $req = Request::getById(su::strToObjectId($reqId), true);
        run::auth($req->userId, $req->getTenantId());
        $sche = Schedule::getById(session::getScheduleId(), true);
        $exec = Execution::newFromRequest($req);
        $exec->setscheduled(true);
        $exec->setPOID($sche->getPreserveOrderId());

        $exec->start();
    }

    /**
     * Executes implementation based on a schedule.
     * Authenticates based on the schedule userid and tenantid
     *
     *
     * @param string $implementationId
     * @param string $scheduleId
     */
    private static function executeImplementation($implementationId, $scheduleId)
    {
        $sche = Schedule::getById($scheduleId, true);
        run::auth($sche->userId, $sche->getTenantId());
        $req = RequestDirect::create($sche->getTenantId(), $implementationId);

        run::auth($req->userId, $req->getTenantId());
        $exec = Execution::newFromRequest($req);
        $exec->setscheduled(true);

        $exec->start();
    }

    private static function auth($userId, $tenantId)
    {
        $user = User::getById($userId);
        $user->selectTenantAndSaveToSession($tenantId);
    }
}
if ((isset($_SERVER['argv'][0]) && stripos($_SERVER['argv'][0], 'src/run.php') !== false)) {

    Run::main();
}
