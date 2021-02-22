<?php

require_once 'vendor/autoload.php';

// DB and AWS credentials are saved there
require_once 'secrets.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\S3\MultipartUploader;

ini_set('max_execution_time', 300);
header('Cache-Control: no-cache, must-revalidate, max-age=0');

function make_backup_files ($mysqlHostName, $mysqlDatabaseName, $mysqlUserName, $mysqlPassword, $filenamePrefix) {
    $dumpname = $filenamePrefix . '_sqldump.sql';
    $sitetarname = $filenamePrefix . '_site.tgz';
    
    // Call to the system's mysqldump util
    $command='mysqldump --opt -h' .$mysqlHostName .' -u' .$mysqlUserName .' -p' .$mysqlPassword .' ' .$mysqlDatabaseName .' > ../../' . $dumpname;
    exec($command,$output=array(),$worked);
    switch($worked) { // check return code
        case 1:
            http_response_code(500);
            echo 'Could not make SQL dump'. PHP_EOL;
            return -1;
            break;
        case 2:
            http_response_code(500);
            echo 'Could not make SQL dump due to DB connection issues.'. PHP_EOL;
            return -1;
            break;
    }

    // Gzip the SQL dump cause SQL dumps are just a long sequence of SQL statements
    // that is very inefficent. GZIP can help us a lot!
    // SIDE EFFECT: gzip removes the (uncompressed) file! Remainder: {filename}.gz
    exec('gzip -f ../../'.$dumpname,$output=array(),$worked);
    if ($worked > 0) { // check return code
        http_response_code(500);
        echo 'Could not compress SQL dump'. PHP_EOL;
        return -1;
    }

    // Assets etc. are saved in the 'site' folder
    // We make a GZIP compressed tar of everything
    exec('tar -zvc -C ../ -f ../../' . $sitetarname . ' site',$output=array(),$worked);
    if ($worked > 0) { // check return code
        http_response_code(500);
        echo 'Could not make site folder dump'. PHP_EOL;
        return -1;
    }
    return 0;

}

function upload_to_aws ($s3Client, $awsBucketName, $filenamePrefix) {
    $dumpfilename = $filenamePrefix . '_sqldump.sql.gz';
    $sitetarfilename = $filenamePrefix . '_site.tgz';

    $dumpname = '../../' . $dumpfilename;
    $sitetarname = '../../' . $sitetarfilename;

    $dumpstream = fopen($dumpname, 'rb');
    $sitetarstream = fopen($sitetarname, 'rb');

    perform_aws_multipart_upload($s3Client, $awsBucketName, $dumpfilename, $dumpstream);
    perform_aws_multipart_upload($s3Client, $awsBucketName, $sitetarfilename, $sitetarstream);

    fclose($dumpstream);
    fclose($sitetarstream);
}

function perform_aws_multipart_upload ($s3Client, $awsBucketName, $s3filename, $sourcestream) {
    // We'll use the more robust AWS Multipart uploader.
    // Therefore, we need stateful uploader objects

    $uploader = new MultipartUploader($s3Client, $sourcestream, [
        'bucket' => $awsBucketName,
        'key' => $s3filename, // name in AWS
        'before_initiate' => function(\Aws\CommandInterface $command) {
            $command['StorageClass'] = 'STANDARD_IA'; //storage class
        }
    ]);
   
    do {
        try {
            $result = $uploader->upload();
            if ($result["@metadata"]["statusCode"] == '200') {
                print('File ' . $s3filename . ' uploaded to AWS S3.' . PHP_EOL);
            }
        } catch (MultipartUploadException $e) {
            rewind($source);
            $uploader = new MultipartUploader($s3Client, $sourcestream, [
                'state' => $e->getState(),
            ]);
        }
    } while (!isset($result));
}

function delete_backup_files ($filenamePrefix) {
    $dumpname = '../../' . $filenamePrefix . '_sqldump.sql.gz';
    $sitetarname = '../../' . $filenamePrefix . '_site.tgz';

    // to make sure there are no dangling files, try to delete uncompressed dump
    unlink($dumpname);
    unlink($sitetarname);
    // Todo: Check if file exists instead of just surpressing the error
    @unlink('../../' . $filenamePrefix . '_sqldump.sql'); // Don't want to see an error every time
}

// HTTP Basic Auth to prevent abuse
$has_supplied_credentials = !(empty($_SERVER['PHP_AUTH_USER']) && empty($_SERVER['PHP_AUTH_PW']));

$is_not_authenticated = (
    !$has_supplied_credentials ||
    $_SERVER['PHP_AUTH_USER'] != "backup" ||
    $_SERVER['PHP_AUTH_PW']   != $backupPassword
);
if ($is_not_authenticated) {
    header('HTTP/1.1 401 Authorization Required');
    header('WWW-Authenticate: Basic realm="Access denied"');
    exit;
}

// Choose UNIX time as the prefix for all files
$prefix = time();

$retval = make_backup_files($mysqlHostName, $mysqlDatabaseName, $mysqlUserName, $mysqlPassword, $prefix);
if ($retval == 0) {
    echo 'Backup file creation completed.'. PHP_EOL;
} else {
    echo 'Backup file creation unsuccessful.'. PHP_EOL;
    delete_backup_files($prefix); // remove dangling files
    die();
}

upload_to_aws($s3Client, $awsBucketName, $prefix);

delete_backup_files($prefix);

?>