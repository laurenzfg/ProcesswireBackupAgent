const https = require('https');

exports.handler = async (event) => {
    
    const response = await new Promise((resolve, reject) => {
        const authstring = 'backup:' + process.env.BACKUP_PW;
        https.get(process.env.BACKUP_URL, { auth: authstring },(res) => {
            const { statusCode } = res;
            
            // Handle errors on the side of the backup agent
            // Any 2xx status code signals a successful response but
            // here we're only checking for 200.
            if (statusCode !== 200) {
                console.warn('Request Failed.\n' + `Status Code: ${statusCode}`);
                // Consume response data to free up memory
                // res.resume();
                // Answer the promise
                resolve({
                   statusCode: 500,
                   body: 'Backup no good'
                });
            }
            
            // Receive Body
            res.setEncoding('utf8');
            let rawData = '';
            res.on('data', (chunk) => { rawData += chunk; });
            res.on('end', () => {
                console.log("BACKUP REPORT\n" + rawData); // log body
                // Answer the promise
                resolve({
                   statusCode: 200,
                   body: 'Backup all good'
                });
            });
        }).on('error', (e) => {
            // deal with errors on the request making side
            console.error(`Got error: ${e.message}`);
            // Answer the promise
            resolve({
               statusCode: 500,
               body: 'Backup no good'
            });
        });
    });
    
    return response;
};