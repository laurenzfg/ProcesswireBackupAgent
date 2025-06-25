<?php

require_once 'vendor/autoload.php';

// DB and AWS credentials are saved there
require_once 'secrets.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\S3\MultipartUploader;

ini_set('max_execution_time', 300);
header('Cache-Control: no-cache, must-revalidate, max-age=0');

// Add a logging function at the top
function log_message($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logfile = __DIR__ . '/backup.log';
    $entry = "[$timestamp][$level] $message" . PHP_EOL;
    file_put_contents($logfile, $entry, FILE_APPEND);
}

function make_backup_files ($mysqlHostName, $mysqlDatabaseName, $mysqlUserName, $mysqlPassword, $filenamePrefix) {
    log_message("Starting make_backup_files with prefix: $filenamePrefix");
    $dumpname = $filenamePrefix . '_sqldump.sql';
    $sitetarname = $filenamePrefix . '_site.tgz';
    
    $command='mysqldump --opt -h' .$mysqlHostName .' -u' .$mysqlUserName .' -p' .$mysqlPassword .' ' .$mysqlDatabaseName .' > ./' . $dumpname;
    log_message("Executing command: $command", 'DEBUG');
    $output = array();
    exec($command, $output, $worked);
    log_message("mysqldump return code: $worked", $worked === 0 ? 'INFO' : 'ERROR');
    switch($worked) {
        case 1:
            log_message('Could not make SQL dump', 'ERROR');
            http_response_code(500);
            echo 'Could not make SQL dump'. PHP_EOL;
            return -1;
            break;
        case 2:
            log_message('Could not make SQL dump due to DB connection issues.', 'ERROR');
            http_response_code(500);
            echo 'Could not make SQL dump due to DB connection issues.'. PHP_EOL;
            return -1;
            break;
    }

    $gzipCommand = 'gzip -f ./'.$dumpname;
    log_message("Executing command: $gzipCommand", 'DEBUG');
    $output = array();
    exec($gzipCommand, $output, $worked);
    log_message("gzip return code: $worked", $worked === 0 ? 'INFO' : 'ERROR');
    if ($worked > 0) {
        log_message('Could not compress SQL dump', 'ERROR');
        http_response_code(500);
        echo 'Could not compress SQL dump'. PHP_EOL;
        return -1;
    }

    $tarCommand = 'tar -zvc -C ../ -f ./' . $sitetarname . ' site';
    log_message("Executing command: $tarCommand", 'DEBUG');
    $output = array();
    exec($tarCommand, $output, $worked);
    log_message("tar return code: $worked", $worked === 0 ? 'INFO' : 'ERROR');
    if ($worked > 0) {
        log_message('Could not make site folder dump', 'ERROR');
        http_response_code(500);
        echo 'Could not make site folder dump'. PHP_EOL;
        return -1;
    }
    log_message("make_backup_files completed successfully for prefix: $filenamePrefix");
    return 0;
}

function upload_to_aws ($s3Client, $awsBucketName, $filenamePrefix) {
    log_message("Starting upload_to_aws for prefix: $filenamePrefix");
    $dumpfilename = $filenamePrefix . '_sqldump.sql.gz';
    $sitetarfilename = $filenamePrefix . '_site.tgz';

    $dumpname = './' . $dumpfilename;
    $sitetarname = './' . $sitetarfilename;

    $dumpstream = fopen($dumpname, 'rb');
    $sitetarstream = fopen($sitetarname, 'rb');

    log_message("Uploading $dumpfilename to AWS S3");
    perform_aws_multipart_upload($s3Client, $awsBucketName, $dumpfilename, $dumpstream);
    log_message("Uploading $sitetarfilename to AWS S3");
    perform_aws_multipart_upload($s3Client, $awsBucketName, $sitetarfilename, $sitetarstream);

    fclose($dumpstream);
    fclose($sitetarstream);
    log_message("upload_to_aws completed for prefix: $filenamePrefix");
}

function perform_aws_multipart_upload ($s3Client, $awsBucketName, $s3filename, $sourcestream) {
    log_message("Starting AWS multipart upload for $s3filename");
    $uploader = new MultipartUploader($s3Client, $sourcestream, [
        'bucket' => $awsBucketName,
        'key' => $s3filename,
        'before_initiate' => function(\Aws\CommandInterface $command) {
            $command['StorageClass'] = 'STANDARD_IA';
        }
    ]);
   
    do {
        try {
            $result = $uploader->upload();
            if ($result["@metadata"]["statusCode"] == '200') {
                log_message("File $s3filename uploaded to AWS S3.");
                print('File ' . $s3filename . ' uploaded to AWS S3.' . PHP_EOL);
            }
        } catch (MultipartUploadException $e) {
            log_message("Multipart upload exception for $s3filename: " . $e->getMessage(), 'ERROR');
            rewind($source);
            $uploader = new MultipartUploader($s3Client, $sourcestream, [
                'state' => $e->getState(),
            ]);
        }
    } while (!isset($result));
    log_message("AWS multipart upload completed for $s3filename");
}

function delete_backup_files ($filenamePrefix) {
    log_message("Deleting backup files for prefix: $filenamePrefix");
    $dumpname = './' . $filenamePrefix . '_sqldump.sql.gz';
    $sitetarname = './' . $filenamePrefix . '_site.tgz';

    if (file_exists($dumpname)) {
        unlink($dumpname);
        log_message("Deleted file: $dumpname");
    } else {
        log_message("File not found for deletion: $dumpname", 'WARNING');
    }
    if (file_exists($sitetarname)) {
        unlink($sitetarname);
        log_message("Deleted file: $sitetarname");
    } else {
        log_message("File not found for deletion: $sitetarname", 'WARNING');
    }
    $uncompressed = './' . $filenamePrefix . '_sqldump.sql';
    if (file_exists($uncompressed)) {
        unlink($uncompressed);
        log_message("Deleted file: $uncompressed");
    } else {
        log_message("File not found for deletion: $uncompressed", 'WARNING');
    }
}

// HTTP Basic Auth to prevent abuse
$has_supplied_credentials = !(empty($_SERVER['PHP_AUTH_USER']) && empty($_SERVER['PHP_AUTH_PW']));

log_message("HTTP request received from IP: " . $_SERVER['REMOTE_ADDR']);

$is_not_authenticated = (
    !$has_supplied_credentials ||
    $_SERVER['PHP_AUTH_USER'] != "backup" ||
    $_SERVER['PHP_AUTH_PW']   != $backupPassword
);
if ($is_not_authenticated) {
    log_message("Authentication failed for user: " . ($_SERVER['PHP_AUTH_USER'] ?? 'N/A'), 'WARNING');
    header('HTTP/1.1 401 Authorization Required');
    header('WWW-Authenticate: Basic realm="Access denied"');
    exit;
} else {
    log_message("Authentication successful for user: " . $_SERVER['PHP_AUTH_USER']);
}

// Choose UNIX time as the prefix for all files
$prefix = time();
log_message("Backup process started with prefix: $prefix");

$retval = make_backup_files($mysqlHostName, $mysqlDatabaseName, $mysqlUserName, $mysqlPassword, $prefix);
if ($retval == 0) {
    log_message("Backup file creation completed for prefix: $prefix");
    echo 'Backup file creation completed.'. PHP_EOL;
} else {
    log_message("Backup file creation unsuccessful for prefix: $prefix", 'ERROR');
    echo 'Backup file creation unsuccessful.'. PHP_EOL;
    delete_backup_files($prefix); // remove dangling files
    log_message("Exiting due to backup file creation failure.", 'ERROR');
    die();
}

upload_to_aws($s3Client, $awsBucketName, $prefix);

log_message("Backup files uploaded to AWS for prefix: $prefix");
delete_backup_files($prefix);
log_message("Backup process completed for prefix: $prefix");

?>
