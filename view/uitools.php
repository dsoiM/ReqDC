<?php

/**
 * Admin tools
 *
 *
 * @author dso
 *
 */
class UITools extends MainUI
{

    function getName()
    {
        return 'Tools';
    }

    function setViewVars()
    {

        $x = [];
        if (Session::getUser()->isAdmin() !== true) {
            return $x;
        }

        if (su::getRequestValue('encryptValue')) {
            $x['encryptedValue'] = su::getRequestValue('encryptedValue') ? su::encrypt(su::getRequestValue('encryptedValue'),su::getRequestValue('encryptedValueKey')) : null ;
        }
        if (su::getRequestValue('generateNewEncryptionKey')) {
            $key = Defuse\Crypto\Key::createNewRandomKey();
            $keyAscii= $key->saveToAsciiSafeString();
            $x['generateNewEncryptionKey'] = $keyAscii;
        }

        if (su::getRequestValue('clearSessions')) {
            session::deleteAll();
        }
        
        return $x;
    }
}