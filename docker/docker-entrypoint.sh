#!/bin/sh
set -e

# Instalar dependencias de PHP si no existe la carpeta vendor
if [ ! -d "vendor" ]; then
    echo "Instalando dependencias de Composer..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Generar la llave de la aplicación si no existe en el contenedor
if [ -z "$APP_KEY" ] && [ ! -f ".env" ]; then
    echo "Generando key de la aplicación..."
    php artisan key:generate --force
fi

# Limpiar caché acumulada para evitar problemas de entorno
echo "Limpiando y optimizando caché de Laravel..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Iniciar PHP-FPM (proceso principal)
echo "Iniciando PHP-FPM..."
exec php-fpm
