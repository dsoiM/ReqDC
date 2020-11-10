<?php


class UIDataStorage extends MainUI
{

    function getName()
    {
        return 'Data storage';
    }

    function setViewVars()
    {
        $x = [];

        $rows = DataStorage::getListingForViewTable()->toArray();
        
        foreach ($rows as $r) {
            if(DataStorage::isTypeEncrypted($r->type)) {
                $r->value="****";
            }
        }

        $x['tableRows'] = $rows;




        return $x;
    }
}