# ProcesswireBackupAgent
A script which backs up ProcessWire and uploads the backup to Amazon (TM) S3.

The backup agent can be used on a PHP host which allows mysqldump to be run
through a call to PHP's exec.

To build the project, you need a host with a PHP cli.

Execute:

    curl -sS https://getcomposer.org/installer | php
    php composer.phar install

*Warning*: Currently the backup agent is heavily biased towards a specific directory structure:

    -- /
    |
    |---- site/
    |---- Backup_Agent /
    |-------- BackupAgent Files

Once the main backup script is invoked, both the SQL dump and the compressed site folder
are saved to the root.
To make this script portable, it must allow other folder arrangements. 