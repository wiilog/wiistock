#!/bin/bash
# démarrer sshd
/usr/sbin/sshd -D &

# démarrer php-fpm
php-fpm
