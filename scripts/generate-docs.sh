#!/bin/bash

apt-get update
apt-get install -y git
mkdir /sami
cd /sami
curl -O http://get.sensiolabs.org/sami.phar
cd /code
git fetch origin master:master
git fetch origin --tags
cp -r /code /sami/code
cd /sami/code
php /sami/sami.phar update -vvv --force /sami/code/.sami-config.php
mv /sami/code/docs /code/docs