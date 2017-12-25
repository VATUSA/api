FROM alpine

ENV TIMEZONE            America/Chicago
ENV PHP_MEMORY_LIMIT    128M
ENV MAX_UPLOAD          20M
ENV PHP_MAX_FILE_UPLOAD 200
ENV PHP_MAX_POST       100M

RUN	addgroup -S application && adduser -SG application application && \
    apk update && \
	apk upgrade && \
	apk add --update tzdata && \
	cp /usr/share/zoneinfo/${TIMEZONE} /etc/localtime && \
	echo "${TIMEZONE}" > /etc/timezone && \
	apk add --update \
		php7-pdo \
		php7-pdo_mysql \
		php7-curl \
		php7-xml \
		php7-json \
		php7-phar \
		php7-gmp \
		php7-zlib \
		php7-tokenizer \
		php7-openssl \
		php7-mbstring \
		php7-fpm \
		php7 \
		nginx \
		supervisor && \
	sed -i "s|;*daemonize\s*=\s*yes|daemonize = no|g" /etc/php7/php-fpm.conf && \
	sed -i "s|;*listen\s*=\s*127.0.0.1:9000|listen = 9000|g" /etc/php7/php-fpm.d/www.conf && \
	sed -i "s|;*listen\s*=\s*/||g" /etc/php7/php-fpm.d/www.conf && \
	sed -i "s|;*date.timezone =.*|date.timezone = ${TIMEZONE}|i" /etc/php7/php.ini && \
	sed -i "s|;*memory_limit =.*|memory_limit = ${PHP_MEMORY_LIMIT}|i" /etc/php7/php.ini && \
    sed -i "s|;*upload_max_filesize =.*|upload_max_filesize = ${MAX_UPLOAD}|i" /etc/php7/php.ini && \
    sed -i "s|;*max_file_uploads =.*|max_file_uploads = ${PHP_MAX_FILE_UPLOAD}|i" /etc/php7/php.ini && \
    sed -i "s|;*post_max_size =.*|post_max_size = ${PHP_MAX_POST}|i" /etc/php7/php.ini && \
    sed -i "s|;*cgi.fix_pathinfo=.*|cgi.fix_pathinfo= 0|i" /etc/php7/php.ini && \
    mkdir /etc/supervisor.d && \
    mkdir /var/run/nginx && \
	mkdir /www && \
	chown application:application /www && \
	apk del tzdata && \
    rm -rf /var/cache/apk/*

WORKDIR /www
COPY . /www
COPY resources/docker /

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --install-dir=/usr/local/bin && \
    rm composer-setup.php && \
    chown -R application:application /www

USER application
RUN composer.phar install --no-dev --no-scripts
USER root
RUN rm /usr/local/bin/composer.phar
RUN crontab -l | { cat; echo "*    *    *     *     *    su -c 'cd /www && php artisan schedule:run' www-data"; } | crontab -
RUN crond

ENTRYPOINT ["supervisord", "--nodaemon", "--configuration", "/etc/supervisord.conf"]
