#!/bin/sh
cd ..
./shellscripts/kill_scheduleservice.sh
sleep 4
./shellscripts/start_scheduleservice.sh
sleep 1


rm -rf testresults
if [ -z "$1" ]
  then
    ../reqdcvendor/bin/phpunit tests  --debug --verbose
  else 
    if [ "$1" = "html" ]
      then
	../reqdcvendor/bin/phpunit tests  --debug --verbose --coverage-html testresults
      else
        ../reqdcvendor/bin/phpunit $1  --debug --verbose
    fi
fi



