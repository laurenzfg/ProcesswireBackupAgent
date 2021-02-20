<?php

ini_set('max_execution_time', 300);

// DB Login Data Goes Here

include_once 'secrets.php'

//Bei den folgenden Punkten bitte keine Änderung durchführen
//Export der Datenbank und Ausgabe des Status
$command='mysqldump --opt -h' .$mysqlHostName .' -u' .$mysqlUserName .' -p' .$mysqlPassword .' ' .$mysqlDatabaseName .' > ../dump.sql';
exec($command,$output=array(),$worked);
switch($worked){
    case 0:
        break;
    case 1:
        echo 'Could not make SQL dump'. PHP_EOL;
        break;
    case 2:
        echo 'Could not make SQL dump due to DB connection issues.'. PHP_EOL;
        break;
}

exec('gzip -f ../dump.sql',$output=array(),$worked);
switch($worked){
    case 0:
        break;
    default:
        echo 'Could not compress SQL dump'. PHP_EOL;
        break;
}

exec('tar -zcvf ../site.tgz ./site',$output=array(),$worked);
switch($worked){
    case 0:
        break;
    default:
        echo 'Could not make site folder dump'. PHP_EOL;
        break;
}

echo 'Script completed.'. PHP_EOL;
?>