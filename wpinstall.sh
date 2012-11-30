#!/bin/sh
if [ ! -z "$1" ]; then

echo "Creating project at $1"

mkdir $1

cd $1

wget http://wordpress.org/latest.tar.gz  

tar -zxvf latest.tar.gz

rm latest.tar.gz

cd wordpress

mkdir environment

cd wp-content/themes

wget -O roots.zip https://github.com/retlehs/roots/zipball/master

unzip roots.zip
mv retlehs-roots* roots
touch roots/.htaccess

rm roots.zip 
rm -r twentyeleven
rm -r twentyten
rm -r twentytwelve

cd ../plugins

wget http://downloads.wordpress.org/plugin/types.zip

unzip types.zip

wget http://downloads.wordpress.org/plugin/contact-form-7.zip

unzip contact-form-7.zip

wget http://downloads.wordpress.org/plugin/options-framework.1.3.zip

unzip options-framework.1.3.zip

wget http://downloads.wordpress.org/plugin/wordpress-seo.1.2.8.7.zip

unzip wordpress-seo.1.2.8.7.zip

wget http://downloads.wordpress.org/plugin/w3-total-cache.0.9.2.4.zip

unzip w3-total-cache.0.9.2.4.zip

wget http://downloads.wordpress.org/plugin/jetpack.2.0.zip

unzip jetpack.2.0.zip

wget http://downloads.wordpress.org/plugin/json-api.1.0.7.zip

unzip json-api.1.0.7.zip

rm types.zip
rm contact-form-7.zip
rm options-framework.1.3.zip
rm wordpress-seo.1.2.8.7.zip
rm w3-total-cache.0.9.2.4.zip
rm jetpack.2.0.zip
rm json-api.1.0.7.zip
rm -r akismet
rm hello.php

cd ../../..

git init

git add .

git commit -m 'Autoinstall complete.'

cd ..

php ~/Projects/Wordpress\ Setup/WP-Composer/wpconfigure.sh path="$1"

else
 echo "Please pass your desired project path as an argument."
fi