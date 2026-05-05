# Google Cloud VM Deploy

This app is designed to run on a small VM with Docker Compose.

## Services
- `web`: Laravel HTTP server on port `8080`
- `mqtt`: long-running MQTT listener using `php artisan mqtt:listen`

## Notes
- SQLite is stored in a Docker volume.
- MongoDB is expected to be provided externally.
- If you use Google Cloud, a small VM is usually a better fit than Cloud Run for the MQTT listener.
