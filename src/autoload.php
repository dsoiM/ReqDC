<?php
spl_autoload_register(function ($class_name) {
    $filename = $class_name . '.php';
    $path = "src/";
    if (file_exists($path.strtolower($filename))) {
        require_once $path.strtolower($filename);
    }
});

register_shutdown_function( "SU::phpShutdownfunction" );
set_error_handler( "SU::unhandledErrorHandler" );
set_exception_handler( "SU::unhandledExceptionHandler" );

ini_set("error_reporting",E_ALL);
ini_set('max_execution_time', config::get('MAXPHPEXECUTIONTIME'));
ini_set('session.gc_maxlifetime', config::get('SESSIONLIFETIME'));
ini_set('session.cookie_lifetime', config::get('SESSIONLIFETIME'));
ini_set('session.cookie_domain', Config::get('COOKIEDOMAIN'));
ini_set( 'session.cookie_httponly', "On" );

// 1/100 means 1% chance to run session cleanup function on each request
ini_set('session.gc_divisor', 100);
ini_set('session.gc_probability', 1);



if (Config::get('ENV') === 'local') {
    ini_set( "display_errors", "On" );
    ini_set("display_startup_errors","On");
} else {
    ini_set("display_errors", "Off" );
    ini_set("display_startup_errors","Off");
    ini_set("expose_php", "Off" );
    ini_set('session.cookie_secure', "On" );
    
}

if (isset($_ENV["PHPUNITRUNNING"])) {
    config::setLogfileLocation(Config::get('UNITTESTLOGFILE'));
    require_once "tests/TestConfig.php";
    
}
require_once 'src/exceptions.php';
require_once ("../reqdcvendor/autoload.php");