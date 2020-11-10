<?php

class ReportData
{

    const ERRORCOLOR = '#a9665a';

    const DONECOLOR = '#62aa5a';

    const HALTEDCOLOR = "#a7a8aa";

    const STARTEDCOLOR = "#535bd0";

    public static function getExecutionDataForPieChart($implementationId, $tenantid, $beginTime, $endTime, $status = null)
    {
        $aggregate = [];
        $aggregate[] = [
            '$match' => [
                'tenantId' => $tenantid,
                'implementationId' => $implementationId,
                'startTime' => [
                    '$gte' => su::timeToBSON($beginTime),
                    '$lt' => su::timeToBSON($endTime)
                ]
            ]
        ];

        if (! empty($status)) {
            $aggregate[0]['$match']['status'] = $status;
        }

        $aggregate[] = [

            '$group' => [
                '_id' => '$status',
                'count' => [
                    '$sum' => 1
                ]
            ]
        ];

        $rows = DB::exc()->aggregate($aggregate);

        $datapoints = [];
        $labels = [];
        $colors = [];
        foreach ($rows as $r) {
            $datapoints[] = $r->count;
            $labels[] = $r->_id;

            $colors[] = self::getColorForStatus($r->_id);
        }

        return [
            'dataPoints' => $datapoints,
            'labels' => $labels,
            'colors' => $colors
        ];
    }

    public static function getColorForStatus($st)
    {
        return constant("self::" . $st . "COLOR");
    }

    /**
     * Time always needs to be midnight at beginning and end
     *
     * @param string $implementationId
     * @param string $tenantid
     * @param string $time
     */
    public static function getExecutionDataByDay($implementationId, $tenantid, $beginTime, $endTime, $status = null)
    {
        $beginTime .= ' midnight';
        $endTime .= ' midnight + 1 days';
        $beg = su::timeToBSON($beginTime);
        $end = su::timeToBSON($endTime);

        $Ibegin = new DateTime($beginTime);
        $Iend = new DateTime($endTime);

        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($Ibegin, $interval, $Iend);

        $datesForReport = [];
        foreach ($period as $dt) {
            $datesForReport[] = su::timeToBSON($dt);
        }

        $aggregate = [
            [
                '$match' => [
                    'tenantId' => $tenantid,
                    'implementationId' => $implementationId,
                    'startTime' => [
                        '$gte' => $beg,
                        '$lt' => $end
                    ]
                ]
            ],
            [
                '$addFields' => [
                    'startTime' => [
                        '$dateFromParts' => [
                            'year' => [
                                '$year' => '$startTime'
                            ],
                            'month' => [
                                '$month' => '$startTime'
                            ],
                            'day' => [
                                '$dayOfMonth' => '$startTime'
                            ]
                        ]
                    ],
                    'dateRange' => $datesForReport
                ]
            ],
            [
                '$unwind' => '$dateRange'
            ],
            [
                '$group' => [
                    '_id' => [
                        'date' => '$dateRange',
                        'status' => '$status'
                    ],
                    'count' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$eq' => [
                                        '$dateRange',
                                        '$startTime'
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ]
                ]
            ],
            [
                '$group' => [
                    '_id' => '$_id.date',
                    'total' => [
                        '$sum' => '$count'
                    ],
                    'byStatus' => [
                        '$push' => [
                            'k' => '$_id.status',
                            'v' => [
                                '$sum' => '$count'
                            ]
                        ]
                    ]
                ]
            ],
            [
                '$sort' => [
                    '_id' => 1
                ]
            ],
            [
                '$project' => [
                    '_id' => 0,
                    'startTime' => '$_id',
                    'totalExecutions' => '$total',
                    'byStatus' => [
                        '$arrayToObject' => [
                            '$filter' => [
                                'input' => '$byStatus',
                                'cond' => '$$this.v'
                            ]
                        ]
                    ]
                ]
            ]
        ];
        if (! empty($status)) {
            $aggregate[0]['$match']['status'] = $status;
        }

        $rows = DB::exc()->aggregate($aggregate);

        $g = [];
        foreach ($rows as $r) {

            $g['total']['dataPoints'][] = $r->totalExecutions;
            $g['done']['dataPoints'][] = su::gis($r->byStatus->DONE, 0);
            $g['error']['dataPoints'][] = su::gis($r->byStatus->ERROR, 0);
            $g['halted']['dataPoints'][] = su::gis($r->byStatus->HALTED, 0);
            $g['labels'][] = su::BSONTimeToString($r->startTime, 'Y-m-d');
        }

        $g['colors'] = [
            self::ERRORCOLOR,
            self::DONECOLOR,
            self::HALTEDCOLOR
        ];

        return $g;
    }
}