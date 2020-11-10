<?php

class Config
{

    /**
     * These are defined ALWAYS by environment specific config
     */
    const ENV = null;

    const MONGODBURL = null;

    const UIURL = null;

    const APIURL = null;

    const MAINURL = null;

    const COOKIEDOMAIN = null;

    const MAILFROM = null;

    const MAILSERVER = null;

    const MAILUSERNAME = null;

    //This must be encrypted via the internal encryption mechanism
    const MAILPASSWORD = null;

    const UNITTESTSECRET = null;
    
    const SYSTEMADMINEMAILS = [
        'no@notrealemailxxxemail.com'
    ];
    
    
    // These usually not, but can be overwritten:
    const SENDMAILS = false;

    const ENVIRONMENTFILE = '/var/www/ENV';

    const SCHEDULESERVICECYCLESLEEP = 2;

    const MAXPOIDNUMBER = 20;

    const ROOTPATH = "/var/www/reqdc/";

    const UIPATH = "/var/www/reqdc/view/";

    const SCHEDULESERVICELOGFILE = '/var/log/reqdc/scheduleservice.log';

    const RUNLOGFILE = '/var/log/reqdc/run.log';

    const UILOGFILE = '/var/log/reqdc/ui.log';

    const APILOGFILE = '/var/log/reqdc/api.log';

    const IMPLEMENTATIONAPILOGFILE = '/var/log/reqdc/implementationapi.log';

    const CONTROLAPILOGFILE = '/var/log/reqdc/controlapi.log';

    const UNITTESTLOGFILE = '/var/log/reqdc/unittest.log';

    const KEYFILE = '/var/www/KEY';

    const MAXSCHEDULEPICKUPLIFETIMESECONDS = 600;

    const MAXRETRYTIME = 60;

    const MAXPHPEXECUTIONTIME = 60;

    const SESSIONLIFETIME = 7200;
    
    const MAXAUTHATTEMPTS = 10;
    CONST AUTHLOCKOUTTIMESECONDS = 300;
    const MAXSCHEDULESERVICES = 1;

    /**
     * Get a configuration value from current environment config
     * Environment is defined in ENV vile
     *
     *
     *
     * @param string $v
     * @throws Exception
     * @return mixed
     */
    public static function get($v)
    {
        $env = Session::getKeyRequestScope('ENVFILE');
        if (! $env) {
            $env = trim(file_get_contents(static::ENVIRONMENTFILE));
            if (empty($env)) {
                throw new CriticalException('Env file not found');
            }
            Session::setKeyReqestScope('ENVFILE', $env);
            require_once ('config/config_' . $env . '.php');
        }

        // Env based config
        if (defined("Config_$env::$v")) {
            return constant("Config_$env::$v");
        }

        // Generic config value
        if (defined("Config::$v")) {
            return constant("Config::$v");
        }

        // Nothing found
        return null;
    }

    public static function getSecret($v)
    {
        $val = Session::getKeyRequestScope('DECRYPTEDSECRETS');
        if (! $val) {
            $val = Su::decrypt(config::get($v));
            Session::setKeyReqestScope('DECRYPTEDSECRETS', $val);
        }
        return $val;
    }

    /**
     * Return encryption key
     * Only from encrypt and decrypt funcs
     *
     * @return string
     */
    public static function getEncKey()
    {
        $key = Session::getKeyRequestScope('ENCKEY');
        if (! $key) {
            $key = trim(file_get_contents(config::get('KEYFILE')));
            if (empty($key)) {
                throw new Exception('Key file not found');
            }
            Session::setKeyReqestScope('ENCKEY', $key);
        }
        return $key;
    }

    public static function setLogfileLocation($fileFullPath)
    {
        ini_set('error_log', $fileFullPath);
    }
}

