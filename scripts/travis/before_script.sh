#! /bin/bash

set -x

originalDirectory=$(pwd)

composer self-update

cd ..

# checkout mediawiki
wget https://github.com/wikimedia/mediawiki-core/archive/master.tar.gz
tar -zxf master.tar.gz
rm master.tar.gz
mv mediawiki-master wiki

cd wiki

if [ $DBTYPE == "mysql" ]
  then
    mysql -e 'CREATE DATABASE its_a_mw;'
fi

composer install --no-dev
php maintenance/install.php --dbtype $DBTYPE --dbuser root --dbname its_a_mw --dbpath $(pwd) --pass nyan TravisWiki admin

echo 'error_reporting(E_ALL| E_STRICT);' >> LocalSettings.php
echo 'ini_set("display_errors", 1);' >> LocalSettings.php
echo '$wgShowExceptionDetails = true;' >> LocalSettings.php
echo '$wgDevelopmentWarnings = true;' >> LocalSettings.php
echo '$wgLanguageCode = "en";' >> LocalSettings.php

echo 'require_once( "$IP/extensions/Memento/Memento.php" );' >> LocalSettings.php

php maintenance/update.php --quick
