# ProcesswireBackupAgent

A script which backs up ProcessWire and uploads the backup to Amazon (TM) S3.
It assumes that ProcessWire runs on a shared web space (the whole project is a little bit legacy-esque)
and the backup shall be saved on Amazon AWS (TM).
This project is divided into two sub-components.

The first component is the Shared Webspace Backup Agent.
This is a PHP script which compresses the site/ folder into a tar, makes a dump
of the MySQl Database and uploads both archives into an Amazon S3 Bucket.

The second component is a Serverless Application, the Housekeeping App. The app is developed on the Node.js 14.x platform. This unfortunately means we still use AWS SDK v2.
It consists of two lambdas:

 - BackupInvoker: This Lambda invokes the Shared Webspace Backup Agent. You can call it
   e.g. fortnightly via Amazon EventBridge.
   You need to set the environment variables BACKUP_PW with the password to trigger the Shared Webspace Backup Agent
   and BACKUP_URL pointing to the make_backup.php of the Shared Webspace Backup Agent.
 - BackupHousekeeper: This lambda discards old backups. You can call it upon success from BackupInvoker. The lambda assumes to be in the same AWS region as the bucket.
 You need to set the environment variables BUCKET_NAME and BACKUPS_TO_RETAIN respectively. The latter must be an integer.
 **Note**: The algorithm is sorting all the files and remove the (N-2\*retain) oldest files *by filename prefix*. So don't have alien files in the bucket. You are warned :-).
   
Although I am German, I gave zero fucks about Umlauts and everything else in UTF-8. So keep everything ASCII :-).
The Housekeeper will break if you have more than 500 backups due to pagination in the AWS API.
So don't be _unordentlich_.

## Shared Webspace Backup Agent

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

## Authentification

To prevent that an adversary just does backups all the time,
we need to authorize the backup starting process.
For simplicity, this is done with HTTP Basic Auth.
The user must be set to 'backup'.
The password is set in secrets.php

Note that the whole process is led ad absurdum if the backup is not triggered
via HTTPS.

## Amazon S3

This backup agent uses Amazon (TM) S3 as the backupt target.
You need to set up an S3 bucket as the target and create an IAM user
for the backup agent.

The secrets and the bucket name must be saved in secrets.php

I set up my policy as following.
The policy might grant to many permissions, this is my AWS starter project.

    {
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:ListBucket",
                "s3:GetBucketLocation",
                "s3:ListBucketMultipartUploads"
            ],
            "Resource": "arn:aws:s3:::<S3-Bucket-Name>",
            "Condition": {}
        },
        {
            "Effect": "Allow",
            "Action": [
                "s3:AbortMultipartUpload",
                "s3:DeleteObject",
                "s3:DeleteObjectVersion",
                "s3:GetObject",
                "s3:GetObjectAcl",
                "s3:GetObjectVersion",
                "s3:GetObjectVersionAcl",
                "s3:PutObject",
                "s3:PutObjectAcl",
                "s3:PutObjectVersionAcl"
            ],
            "Resource": "arn:aws:s3:::<S3-Bucket-Name>/*",
            "Condition": {}
        },
        {
            "Effect": "Allow",
            "Action": "s3:ListAllMyBuckets",
            "Resource": "*",
            "Condition": {}
        }
    ]
    }

## Housekeeping App


## Todos

 - Currently, a lot like e.g. file names in the shared webspace component is hardcoded.
 - We need to make sure that error is returned if upload to s3 fails by the PHP
 - We need to make the scripts in AWS fail when they fail and NOT just return 500. Because nobody checks for this value. We need to throw an exception instead of just returning 500.