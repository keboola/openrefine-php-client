#!/bin/bash

curl -O http://get.sensiolabs.org/sami.phar
git fetch origin master:master
git fetch origin --tags
php sami.phar update --force .sami-config.php