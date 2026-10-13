#!/bin/sh
# Arranque en Render: Apache escucha en el puerto que Render asigna y se cachea la configuración
set -e
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf

# Certificado de Aiven: Render lo entrega como "Secret File" en /etc/secrets/ca.pem, pero ese
# archivo solo lo puede leer root y Apache/PHP corren como www-data. Se copia a una ruta
# legible por la app (sigue fuera de public/, no se puede descargar por web).
if [ -f /etc/secrets/ca.pem ]; then
  mkdir -p /var/www/html/storage/certs
  cp /etc/secrets/ca.pem /var/www/html/storage/certs/ca.pem
  chown -R www-data:www-data /var/www/html/storage/certs
  chmod 640 /var/www/html/storage/certs/ca.pem
  export MYSQL_ATTR_SSL_CA=/var/www/html/storage/certs/ca.pem
  echo "Certificado de Aiven listo."
else
  echo "AVISO: no existe /etc/secrets/ca.pem (Secret File). Sin él no se puede conectar a Aiven."
fi

php artisan config:cache
php artisan route:cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
exec apache2-foreground
