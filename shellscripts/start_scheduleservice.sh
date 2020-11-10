#!/bin/sh
php src/scheduleservice.php >>/var/log/reqdc/scheduleservice.log 2>&1 & 
sleep .2
php src/scheduleservice.php >>/var/log/reqdc/scheduleservice.log 2>&1 &
sleep .2
php src/scheduleservice.php >>/var/log/reqdc/scheduleservice.log 2>&1 &
sleep .2
php src/scheduleservice.php >>/var/log/reqdc/scheduleservice.log 2>&1 &
sleep .2
php src/scheduleservice.php >>/var/log/reqdc/scheduleservice.log 2>&1 &
sleep .2
php src/scheduleservice.php >>/var/log/reqdc/scheduleservice.log 2>&1 &

