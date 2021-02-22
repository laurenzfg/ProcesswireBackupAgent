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