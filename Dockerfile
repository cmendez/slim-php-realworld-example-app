# Usamos la imagen de PHP 8.5.9 con Apache integrado para Render y localhost
FROM php:8.5.9-apache

# --- NUEVO: Usar configuración de producción de PHP ---
# Esto oculta los Deprecated Warnings y Errors de la respuesta HTTP
# para que tu JSON salga limpio.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Instalar dependencias del sistema y extensiones (usamos apt-get porque es Debian/Apache, no Alpine)
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

# Configurar Apache para que la raiz sea /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# Permitir el uso de .htaccess (AllowOverride All)
# Sin esto, Apache ignora el archivo .htaccess y las rutas dan 404
RUN echo "<Directory /var/www/html/public>" > /etc/apache2/conf-available/override.conf \
    && echo "    AllowOverride All" >> /etc/apache2/conf-available/override.conf \
    && echo "</Directory>" >> /etc/apache2/conf-available/override.conf \
    && a2enconf override

WORKDIR /var/www/html

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar archivos del proyecto
COPY . .

# Añadir el directorio actual como seguro para Git (de tu rama merge)
RUN git config --global --add safe.directory /var/www/html

# Instalar dependencias de PHP (Optimizadas para prod)
RUN composer install --no-interaction --optimize-autoloader --no-dev --ignore-platform-reqs

# Crear las carpetas necesarias ANTES de cambiar sus permisos (de tu rama merge)
RUN mkdir -p /var/www/html/storage \
    && mkdir -p /var/www/html/bootstrap/cache

# Permisos
RUN chown -R www-data:www-data /var/www/html /var/www/html/storage /var/www/html/bootstrap/cache

# Script de inicio
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

# Render inyecta la variable PORT, Apache por defecto usa el 80.
EXPOSE 80

# Usamos el script como comando de inicio
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]