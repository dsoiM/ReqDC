<?php

/**
 * Lists many requests in a table or renders details for one request
 *
 *
 * @author dso
 *
 */
class UIRequests extends MainUI
{

    function getName()
    {
        return 'Received requests';
    }

    function setViewVars()
    {
        $x = [];
        $x = array_merge($x, $this->getDatePickerFields());
        $selection = su::gis($this->urlParts[2]);
        $rows = Request::getListingForViewTable(su::timeToBSON($x['dataBeginDate']), su::timeToBSON($x['dataEndDate']),$selection);

        
        
        $x['tableRows'] = $rows;


        return $x;
    }
}