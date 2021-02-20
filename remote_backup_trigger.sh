#!/bin/sh


curl -L https://REMOTE_URL/make_sql_dumpbkak.php
curl --ftp-ssl --insecure --user ftpuser:ftppw --output dump.sql.gz ftp://FTPSERVER/dump.sql.gz
curl --ftp-ssl --insecure --user ftpuser:ftpw --output site.tgz ftp://FTPSERVER/site.tgz
aws s3 cp ./dump.sql.gz s3://S3BUCKET/dump.sql.gz
aws s3 cp ./site.tgz s3://S3BUCKET/site.tgz

rm dump.sql.gz site.tgz