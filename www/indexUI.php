<?php
chdir('..');
require_once ("autoload.php");
config::setLogfileLocation(Config::get('UILOGFILE'));
MainUI::handle();
