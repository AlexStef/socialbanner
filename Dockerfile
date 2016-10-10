FROM php:5.6-fpm
MAINTAINER Pierre-Louis Launay <pl.launay@creads.org>

# Configure the container
ENV APP_ENV dev
ENV INSTALL yes
EXPOSE 80 443
VOLUME /usr/share/nginx/html
WORKDIR /usr/share/nginx/html

# Install git, sqlite and nginx
RUN apt-get -yqq update \
    && apt-get -yqq install git sqlite nginx zip php5-sqlite openssl

RUN docker-php-ext-install pdo_mysql

# forward error logs to docker log collector
RUN ln -sf /dev/stderr /var/log/nginx/project_error.log

# Configure the website server, PHP
COPY docker/nginx/socialbanner.conf /etc/nginx/sites-enabled/
COPY docker/nginx/ssl/ /etc/nginx/ssl/
COPY docker/php/php.ini /usr/local/etc/php/
COPY docker/php/custom.ini /usr/local/etc/php/conf.d/
RUN rm /etc/nginx/sites-enabled/default

# Install composer
RUN php -r "readfile('https://getcomposer.org/installer');" | php -- --install-dir=/usr/local/bin --filename=composer \
    && chmod +x /usr/local/bin/composer

# Install the website
COPY . /usr/share/nginx/html

# Launch the php server and the website server
COPY docker/run.sh /run.sh
CMD ["/run.sh"]