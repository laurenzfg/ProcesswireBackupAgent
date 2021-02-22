const AWS = require('aws-sdk');

exports.handler = async (event, context) => {
    
    AWS.config.update({ region: process.env.AWS_REGION });
    const s3 = new AWS.S3({apiVersion: '2006-03-01'});
    const bucketParams = { Bucket: process.env.BUCKET_NAME };
    
    let retain = Number(process.env.BACKUPS_TO_RETAIN);
    
    const response = await new Promise((resolve, reject) => {
        s3.listObjects(bucketParams, function(err, data) {
            if (err) {
                console.error("Error fetching files", err);
                resolve ({
                    statusCode: 500,
                    body: 'Housekeeper no good'
                });
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

                console.log(purge_params);
                
                if (keys.length - 2*retain > 0 ) {
                    for (let i = 0; i < keys.length - 2*retain; i++) {
                        const key = {Key: keys[i]};
                        purge_params.Delete.Objects.push(key);
                    }
                    
                    console.log(JSON.stringify(purge_params));
                    
                    s3.deleteObjects(purge_params, function(err, data) {
                        if (err) {
                            console.error(err, err.stack);
                            resolve ({
                                statusCode: 500,
                                body: 'Housekeeper no good'
                            });
                        } else {
                            resolve ({
                                statusCode: 200,
                                body: 'Housekeeper all good'
                            });
                            console.log(data);
                        }
                   });
                } else {
                    console.log("Nothing to purge");   
                }
                
            }
        });
    });
    
    return response;
};
