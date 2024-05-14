#!/bin/sh

echo "Starting cron"
echo "* * * * * php /project/bin/console cron:run > /tmp/cronlog.txt 2>&1" > /tmp/crontabwiistock.txt && crontab /tmp/crontabwiistock.txt && rm /tmp/crontabwiistock.txt
crond -d 0
