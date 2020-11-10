<?php

class UISchedules extends MainUI
{

    public $resultLimit = 100;

    function getName()
    {
        return 'Scheduled implementation executions';
    }

    function setViewVars()
    {
        //Schedule::createFromCronExpression('* * * * *', 'UnitTestImplementation1');
        
        $rows = Schedule::getSchduleCronListingForView();
        
        return ['tableRows' => $rows,'implementationIds' => session::getUser()->getAllowedImplementationIds()];
   
    }
}