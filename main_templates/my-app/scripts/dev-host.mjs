import os from 'node:os';

const explicitHost = (process.env.DEV_HOST || '').trim();
if (explicitHost !== '') {
    console.log(explicitHost);
    process.exit(0);
}

for (const networkInterfaces of Object.values(os.networkInterfaces())) {
    for (const networkInterface of networkInterfaces ?? []) {
        if (networkInterface.family === 'IPv4' && !networkInterface.internal) {
            console.log(networkInterface.address);
            process.exit(0);
        }
    }
}

console.log('127.0.0.1');