import os from 'node:os';

const explicitHost = (process.env.DEV_HOST || '').trim();
if (explicitHost !== '') {
    console.log(explicitHost);
    process.exit(0);
}

function isPrivateIPv4(address) {
    return (
        address.startsWith('10.') ||
        address.startsWith('192.168.') ||
        /^172\.(1[6-9]|2\d|3[0-1])\./.test(address)
    );
}

let fallbackHost = '127.0.0.1';

for (const networkInterfaces of Object.values(os.networkInterfaces())) {
    for (const networkInterface of networkInterfaces ?? []) {
        if (networkInterface.family !== 'IPv4' || networkInterface.internal) {
            continue;
        }

        if (isPrivateIPv4(networkInterface.address)) {
            console.log(networkInterface.address);
            process.exit(0);
        }

        fallbackHost = networkInterface.address;
    }
}

console.log(fallbackHost);
