FROM php:8.2-apache

# Copiar código al servidor web
COPY . /var/www/html/

# Permisos de lectura
RUN chown -R www-data:www-data /var/www/html

# Exponer puerto
EXPOSE 80