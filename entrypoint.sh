#!/bin/sh

# Salir si hay cualquier error
set -e

echo "🚀 Iniciando despliegue..."

# 1. Ejecutar migraciones de base de datos (Phinx)
echo "🗄️  Ejecutando migraciones..."
vendor/bin/phinx migrate

# 2. Iniciar Apache (en primer plano para que Docker no se cierre)
echo "🌐 Iniciando servidor web..."
docker-php-entrypoint apache2-foreground