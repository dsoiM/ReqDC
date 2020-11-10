<?php

class UIHome extends MainUI
{

    function getName()
    {
        return 'Home';
    }

    function setViewVars()
    {
        
        $changeTenant = su::getRequestValue('changeTenant');
        if ($changeTenant) {
            session::getUser()->changeTenant($changeTenant);
            header('Location: ' . '/');
            return;
        }
        
        
        $data = [];
        $data = array_merge($data,$this->getDatePickerFields());


        foreach (Session::getUser()->getAllowedImplementationIds() as $implId) {
            $implName = Implementation::getById($implId)->getName();
            $data['ImplReports'][$implId]['piechart'] = ReportData::getExecutionDataForPieChart($implId, Session::getSelectedTenantId(),$data['dataBeginDate'],$data['dataEndDate'],$data['statusFilter']);
            $data['ImplReports'][$implId]['daychart'] = ReportData::getExecutionDataByDay($implId, Session::getSelectedTenantId(),$data['dataBeginDate'],$data['dataEndDate'],$data['statusFilter']);
            $data['ImplReports'][$implId]['id'] = $implId;
            $data['ImplReports'][$implId]['name'] = $implName;
        }

        return $data;
    }
}