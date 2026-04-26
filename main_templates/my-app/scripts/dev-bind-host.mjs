const explicitBindHost = (process.env.DEV_BIND_HOST || '').trim();

if (explicitBindHost !== '') {
    console.log(explicitBindHost);
    process.exit(0);
}

console.log('0.0.0.0');
