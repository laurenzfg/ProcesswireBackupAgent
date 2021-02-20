# ProcesswireBackupAgent
A script which backs up ProcessWire and uploads the backup to Amazon (TM) S3.

The backup agent can be used on a PHP host which allows mysqldump to be run
through a call to PHP's exec.

To build the project, you need a host with a PHP cli.

Execute:

    curl -sS https://getcomposer.org/installer | php
    php composer.phar install