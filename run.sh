#!/bin/sh

echo "Starting cron"
mkdir -p /project/var/log
printf '* * * * * echo -e "%s\\t%s\\n" 2>> /project/var/log/cron.log\n' '$(date)' '$(php /project/bin/console cron:run)' > /tmp/crontabwiistock.txt && crontab /tmp/crontabwiistock.txt && rm /tmp/crontabwiistock.txt
crond -d 0
