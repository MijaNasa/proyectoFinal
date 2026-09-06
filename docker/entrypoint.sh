#!/bin/bash
set -e

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Esperar a que la base de datos esté lista (especialmente si Postgres está despertando en Render)
echo "Verificando conexión con la base de datos..."
for i in $(seq 1 30); do
    if php artisan db:monitor 2>/dev/null || php artisan migrate:status >/dev/null 2>&1; then
        echo "Base de datos conectada exitosamente."
        break
    fi
    echo "Esperando disponibilidad de la base de datos ($i/30)..."
    sleep 2
done

php artisan migrate --force || echo "Aviso: Error en migrate --force, continuando..."
php artisan db:seed --force || echo "Aviso: Error o datos ya existentes en db:seed, continuando..."
php artisan storage:link 2>/dev/null || true

# Asegurar permisos de escritura para www-data sobre archivos generados por artisan en entrypoint
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

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
