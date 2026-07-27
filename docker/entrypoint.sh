#!/bin/bash
set -e

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan db:seed --class=AdminSeeder --force
php artisan storage:link 2>/dev/null || true

# Worker de colas en background: en el plan free de Render no hay un servicio
# aparte para procesar jobs (geocodificacion de direcciones, etc.), asi que
# corre en el mismo contenedor junto al servidor web.
php artisan queue:work --tries=3 --sleep=3 --timeout=90 &

# Idem para el scheduler: sin cron del sistema, Schedule::command() (ej.
# ventas:cancelar-expiradas) nunca se dispara. Simulamos cron corriendo
# schedule:run cada 60 segundos en background.
while true; do
    php artisan schedule:run --no-interaction
    sleep 60
done &

exec "$@"
