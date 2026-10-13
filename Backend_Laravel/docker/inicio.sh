#!/bin/sh
# Arranque en Render: Apache escucha en el puerto que Render asigna y se cachea la configuración
set -e
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf

# El certificado de Aiven llega como "Secret File" de Render en /etc/secrets/ca.pem
if [ -z "$MYSQL_ATTR_SSL_CA" ] && [ -f /etc/secrets/ca.pem ]; then
  export MYSQL_ATTR_SSL_CA=/etc/secrets/ca.pem
fi

php artisan config:cache
php artisan route:cache
exec apache2-foreground
