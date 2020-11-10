<?php

/**
 * This class is "thread controller". It is always running on all nodes and it picks up things to do
 * such as QUEUE -status executions
 *
 * and also it picks up scheduled executions or scheduled requests
 *
 * Must make sure that race conditions do not happen
 *
 * new table must be made which can contain scheduled executions or requests. Scheduling is also used for
 * asynchronous RequestHttp's
 * 
 * All database calls from this class must bypass tenant filters since we are processing all tenants at the same time without making distinction
 * 
 * 
 * 
 *
 * @author dso
 *
 */
class ScheduleService
{

    /**
     * Hostname + guid
     *
     * @var string
     */
    public $id;

    public static function main()
    {
        ob_start();
        require_once 'src/autoload.php';
        // We need to overwrite max execution time to be infinite in scheduleservice
        ini_set('max_execution_time', 0);
        su::endOB();
        global $exitService;
        try {

            Config::setLogfileLocation(Config::get('SCHEDULESERVICELOGFILE'));
            $SS = new ScheduleService();
            $SS->exitIfAlreadyRunning();
            $SS->id = gethostname() . '_' . su::guidv4();
            pcntl_signal(SIGTERM, "ScheduleService::signalHandler");

            Log::info('Scheduleservice starting at ID: ' . $SS->id);

            // Main loop
            while ($exitService !== true) {
                $SS->findAndLaunchOrderPreserved();
                sleep(config::get('SCHEDULESERVICECYCLESLEEP'));
                // Add a little bit of random to it so all nodes get work
                time_nanosleep(0, mt_rand(0, 50000000));
                pcntl_signal_dispatch();
                
            }

        } catch (Throwable $e) {
            Log::error($e);
            Mail::sendAlertFromException($e);
        }
    }

    public static function signalHandler($signo)
    {
        switch ($signo) {
            case SIGTERM:
                // handle shutdown tasks
                global $exitService;
                $exitService = true;
                Log::warn('Received kill signal, exiting main loop');
                break;

            default:
            // handle all other signals
        }
    }

    public function exitIfAlreadyRunning()
    {
        $pids = null;
        $x = exec("ps aux | grep -i 'src/scheduleservice' | grep -v grep", $pids);
        if (count($pids) < Config::get('MAXSCHEDULESERVICES') +1) {
            Log::debug("ScheduleService not running or less than allowed, allowing this instance to start up");
        } else {
            Log::info("ScheduleService already running, exiting this clone");
            exit();
        }
    }

    /**
     * Goes through schedule table, first gets distinct preserveOrderIds and then finds
     * the first processable entry for each.
     */
    public function findAndLaunchOrderPreserved(array $distinctPreserveOrderIds = null)
    {
        $nrlessthan = su::timeToBSON();

        if ($distinctPreserveOrderIds == null) {
            $distinctPreserveOrderIds = Schedule::getDistrinctPreserveOrderIds($nrlessthan, true);
        }

        $processingQueuesActive = 0;

        foreach ($distinctPreserveOrderIds as $dpi) {


            /**
             * Its not actually object, just array
             * we get only one, because the next one with same POID will be launched immediately after the previous one has
             * completed
             *
             * @var Schedule $p
             */
            $p = Schedule::getNextRunnableByPOID($nrlessthan, $dpi, true, true);

            $maxTimeReached = false;

            // Absolutely nothing scheduled or running
            if (! $p) {
                return;
            }

            // True if the shcedule has been picked up
            if ($p->POStarted !== null) {
                // True if max time seconds has been surpassed
                $max = $p->POStartedMaxLifetime;
                $curr = time();
                $postartedEntry = su::BSONTimeToString($p->POStarted, 'U');
                $currexecTimeSecs = $curr - $postartedEntry;
                if (is_numeric($max) && ($currexecTimeSecs > $max)) {
                    // TODO test that this actually works, its crucial part
                    log::warn('POStarted OVER MAX TIME! Running forced finalize: ' . $p->_id . ' : ' . $dpi);
                    $maxTimeReached = true;
                } else {
                    $processingQueuesActive ++;
                    continue;
                }
            }

            // Pick the schedule for myself
            $pickupresult = Schedule::pickScheduleForMe($p, $this->id);

            if ($pickupresult->getModifiedCount() !== 1) {
                Log::debug('Someone else picked up target ' . $p->targetId . ', continuing');
                continue;
            }

            if ($maxTimeReached) {
                Schedule::finalizeRun($p->_id, true, true);
                continue;
            }

            // Handle the ones which are executed only once
            $this->runOne($p);
        }

        if ($processingQueuesActive > 0) {
            Log::info('Active processing queues: ' . $processingQueuesActive);
        }
    }



    private function runOne($p)
    {
        $status = null;
        $output = null;
        $cmd = 'php ' . Config::get('ROOTPATH') . 'src/run.php ' . $p->targetType . ' ' . $p->targetId . ' ' . $p->_id . ' >>' . Config::get('RUNLOGFILE') . ' 2>&1 &';
        log::info('RUN.php cmd: ' . $cmd);
        exec($cmd, $output, $status);

        if ($status !== 0) {
            throw new SchedulerException('Could not start Run php: ' . implode('|', $output));
        }
    }
}
if (isset($_SERVER['argv']) && $_SERVER['argv'][0] == 'src/scheduleservice.php') {
    ScheduleService::main();
}