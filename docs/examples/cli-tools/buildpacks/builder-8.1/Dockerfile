ARG IMAGE=eu.gcr.io/demo/container
ARG TAG=tag
FROM ${IMAGE}:${TAG}

USER root

RUN pecl install -f opentelemetry

ADD composer-install.sh /usr/local/bin/

RUN cd /usr/local/bin && \
    chmod +x composer-install.sh && \
    ./composer-install.sh && \
    ln -s /usr/local/bin/composer.phar /usr/local/bin/composer

RUN echo >> /etc/php.ini && \
    echo "error_reporting = E_ALL & ~E_STRICT & ~E_DEPRECATED" >> /etc/php.ini

USER www-data
