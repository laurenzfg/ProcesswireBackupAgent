const AWS = require('aws-sdk');

exports.handler = async (event, context) => {
    
    AWS.config.update({ region: process.env.AWS_REGION });
    const s3 = new AWS.S3({apiVersion: '2006-03-01'});
    const bucketParams = { Bucket: process.env.BUCKET_NAME };
    
    let retain = Number(process.env.BACKUPS_TO_RETAIN);
    
    // If the Promise is rejected, the error is escalated one way up and aws
    // recognizes this as failed
    const response = await new Promise((resolve, reject) => {
        s3.listObjects(bucketParams, function(err, data) {
            if (err) {
                console.error("Error fetching files from S3", err);
                // Answer the promise
                reject('Could not do the housekeeping');
            } else {
                let keys = [];
                data.Contents.forEach((element) => {
                    keys.push(element.Key);
                });
                
                keys.sort();
                
                // No we can do the purge
                // Don't purge the 2*retain newest files
                let purge_params = {
                    Bucket : process.env.BUCKET_NAME,
                    Delete : {
                        Objects: [],
                        Quiet: false
                    }
                };
                
                if (keys.length - 2*retain > 0 ) {
                    for (let i = 0; i < keys.length - 2*retain; i++) {
                        const key = {Key: keys[i]};
                        purge_params.Delete.Objects.push(key);
                    }
                    
                    s3.deleteObjects(purge_params, function(err, data) {
                        if (err) {
                            console.error("Error during S3 delete request", err);
                            // Answer the promise
                            reject('Could not do the housekeeping');
                        } else {
                            console.log("Purging Protocol: " + JSON.stringify(data));
                            resolve ({
                                statusCode: 200,
                                body: 'Housekeeping all good'
                            });
                        }
                   });
                } else {
                    console.log("Nothing to purge");
                    resolve ({
                        statusCode: 200,
                        body: 'House was clean already'
                    });
                }
                
            }
        });
    });
    
    return response;
};
