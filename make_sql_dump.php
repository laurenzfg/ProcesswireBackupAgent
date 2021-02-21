<?php

ini_set('max_execution_time', 300);

// DB and AWS credentials are saved there
include_once 'secrets.php';

// Choose UNIX time as the prefix for all files
$prefix = time();

function make_backup_files ($mysqlHostName, $mysqlDatabaseName, $mysqlUserName, $mysqlPassword, $filenamePrefix) {
    $dumpname = $filenamePrefix . '_sqldump.sql';
    $sitetarname = $filenamePrefix . '_site.tgz';
    
    // Call to the system's mysqldump util
    $command='mysqldump --opt -h' .$mysqlHostName .' -u' .$mysqlUserName .' -p' .$mysqlPassword .' ' .$mysqlDatabaseName .' > ../../' . $dumpname;
    exec($command,$output=array(),$worked);
    switch($worked) { // check return code
        case 1:
            echo 'Could not make SQL dump'. PHP_EOL;
            return -1;
            break;
        case 2:
            echo 'Could not make SQL dump due to DB connection issues.'. PHP_EOL;
            return -1;
            break;
    }

    // Gzip the SQL dump cause SQL dumps are just a long sequence of SQL statements
    // that is very inefficent. GZIP can help us a lot!
    // SIDE EFFECT: gzip removes the (uncompressed) file! Remainder: {filename}.gz
    exec('gzip -f ../../'.$dumpname,$output=array(),$worked);
    if ($worked > 0) { // check return code
        echo 'Could not compress SQL dump'. PHP_EOL;
        return -1;
    }

    // Assets etc. are saved in the 'site' folder
    // We make a GZIP compressed tar of everything
    exec('tar -zvc -C ../ -f ../../' . $sitetarname . ' site',$output=array(),$worked);
    if ($worked > 0) { // check return code
        echo 'Could not make site folder dump'. PHP_EOL;
        return -1;
    }
    return 0;

}

$retval = make_backup_files($mysqlHostName, $mysqlDatabaseName, $mysqlUserName, $mysqlPassword, $prefix);
if ($retval == 0) {
    echo 'Backup completed.'. PHP_EOL;
} else {
    echo 'Backup unsuccessful.'. PHP_EOL;
}

?>