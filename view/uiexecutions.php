<?php

class UIExecutions extends MainUI
{

    public $resultLimit = 100;

    function getName()
    {
        return 'Executions';
    }

    function setViewVars()
    {
        $data = [];
        $data = array_merge($data, $this->getDatePickerFields());
        $data['implementationFilter'] = su::getRequestValue('implementationFilter');
        $selection = su::gis($this->urlParts[2]);
        if ($selection) {
            return array_merge($data, $this->renderOne($selection));
        } else {
            $listData = $this->renderList($data['dataBeginDate'], $data['dataEndDate'], $data['statusFilter'],$data['implementationFilter']);
            return array_merge($data, $listData);
        }
    }

    public function renderOne($selection)
    {
        $obj = Execution::getById(su::strToObjectId($selection));

        return [
            'id' => $obj->getId(),
            'obj' => $obj,
            'implname' => $obj->getImplementation()->getName(),
            'logArray' => $obj->getLogs(),
            'type' => 'one',
            'viewnamepostfix' => ': ' . $obj->getId()
        ];
    }

    public function renderList($beg, $end, $status = null,$implId = null)
    {
        $rows = Execution::getListingForViewTable(su::timeToBSON($beg), su::timeToBSON($end), $status, $implId, $this->resultLimit);
        foreach ($rows as &$r) {
            try {
                $req = Request::getById($r->requestId);
                
                //TODO: Change this to use aggregation and retrieve the request using lookup
                $r['requestObj'] = $req;
            } catch (Throwable $e){
                //not everyone has request.. or do they? I think they do
                //What happens here?
                throw $e;
            }
            $r['color'] = ReportData::getColorForStatus($r->status);

        }
        return [
            'tableRows' => $rows,
            'type' => 'list',
            'resultLimit' => $this->resultLimit

        ];
    }
}