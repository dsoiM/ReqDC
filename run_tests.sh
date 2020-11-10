#!/bin/bash
docker exec -it $(docker ps | grep reqdc | cut -d' ' -f1) /bin/bash -c 'cd /var/www/reqdc/shellscripts ; /var/www/reqdc/shellscripts/run_tests_in_container.sh '