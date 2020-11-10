<?php
chdir('..');
require_once ("autoload.php");
config::setLogfileLocation(Config::get('APILOGFILE'));

(new MainAPI())->handle();
