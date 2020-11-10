<?php
chdir('..');
require_once ("src/autoload.php");
config::setLogfileLocation(Config::get('UILOGFILE'));
MainUI::handle();
