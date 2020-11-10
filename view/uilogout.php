<?php

class UILogout extends MainUI
{

    function getName()
    {
        return 'Logout';
    }

    function setViewVars()
    {
        Session::destroy();
        return ['session' => ['userId' => null]];
    }
}