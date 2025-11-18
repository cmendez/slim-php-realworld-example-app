# Usamos la imagen de PHP con Apache integrado (ideal para Render)
FROM php:8.1-apache

# Instalar dependencias del sistema y extensiones
# Agregamos 'unzip' y 'git' para Composer, y las libs de MySQL
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libcurl4-openssl-dev \
    && docker-php-ext-install pdo pdo_mysql opcache curl \
    && a2enmod rewrite

# Configurar Apache para que la raiz sea /public (Estándar en Slim/Laravel)
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar archivos del proyecto
COPY . .

# Instalar dependencias de PHP (Optimizadas para prod)
RUN composer install --no-interaction --optimize-autoloader --no-dev --ignore-platform-reqs

# Permisos para carpetas de escritura (logs, cache, etc)
# Ajusta si Slim usa otra carpeta para logs
RUN chown -R www-data:www-data /var/www/html

# --- CONFIGURACIÓN DEL ENTRYPOINT ---
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Render inyecta la variable PORT, Apache por defecto usa el 80.
# Exponemos el 80 para documentación
EXPOSE 80

# Usamos el script como comando de inicio
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]