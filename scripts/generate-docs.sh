#!/bin/bash
set -e

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

# using nasty hack to avoid sami error
# ERROR: "3" @param tags are expected but only "2" found on "Keboola\OpenRefine\Client::__construct" in /sami/code/src/Keboola/OpenRefine/Client.php:33
php /sami/sami.phar update -vvv --force /sami/code/.sami-config.php || true

mv /sami/code/docs /code/docs