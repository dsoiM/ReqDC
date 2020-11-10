<?php

/**
 * Lists many requests in a table or renders details for one request
 *
 *
 * @author dso
 *
 */
class UIQueue extends MainUI
{

    function getName()
    {
        return 'Currently queued requests to be executed';
    }

    function setViewVars()
    {
        $nrlessthan = su::timeToBSON();

        $distinctPreserveOrderIds = Schedule::getDistrinctPreserveOrderIds($nrlessthan);

        $x = [];
        foreach ($distinctPreserveOrderIds as $dpi) {

            /**
             *
             * @var Schedule $p
             */
            $p = Schedule::getNextRunnableByPOID($nrlessthan, $dpi);

            foreach ($p as $entry) {
                $x['tableRows'][$entry['preserveOrderId']]['count'] ++;
                $x['tableRows'][$entry['preserveOrderId']]['implementationId'] = $entry['implementationId'];
                $nr = $entry['nextRun'];
                $oldst = &$x['tableRows'][$entry['preserveOrderId']]['oldest'];
                if (! isset($oldst) || $nr < $oldst) {
                    $oldst = $nr;
                }
            }
        }

        return $x;
    }
}