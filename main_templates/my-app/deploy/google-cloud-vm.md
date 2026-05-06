# VPS Deploy

This app is designed to run on a small VM with Docker Compose.

## Public URL
- `https://pulse-node.ddns.net`

## Services
- `web`: Laravel HTTP server on port `8080`
- `mqtt`: long-running MQTT listener using `php artisan mqtt:listen`

## Notes
- Set `APP_URL=https://pulse-node.ddns.net` in the VPS `.env`.
- If you change the VPS IP later, update the No-IP record first.
- The app is served through Caddy on the VPS now, not through a Cloudflare tunnel.
- Caddy listens on ports `80` and `443` and proxies to the Laravel container on `8080`.
- MongoDB is expected to be provided externally.
- If you use Google Cloud, a small VM is usually a better fit than Cloud Run for the MQTT listener.
