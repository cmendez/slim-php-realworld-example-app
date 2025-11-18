# Usamos la imagen de PHP con Apache integrado
FROM php:8.1-apache

# Instalar dependencias del sistema y extensiones
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libcurl4-openssl-dev \
    && docker-php-ext-install pdo pdo_mysql opcache curl \
    && a2enmod rewrite

# Configurar Apache para que la raiz sea /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# --- NUEVO: Permitir el uso de .htaccess (AllowOverride All) ---
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

# Instalar dependencias de PHP (Optimizadas para prod)
RUN composer install --no-interaction --optimize-autoloader --no-dev --ignore-platform-reqs

# Permisos
RUN chown -R www-data:www-data /var/www/html

# Script de inicio
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Render inyecta la variable PORT, Apache por defecto usa el 80.
# Exponemos el 80 para documentación
EXPOSE 80

# Usamos el script como comando de inicio
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]