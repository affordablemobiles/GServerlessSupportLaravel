ARG IMAGE=eu.gcr.io/demo/container
ARG TAG=tag
FROM ${IMAGE}:${TAG}

USER root

ADD composer-install.sh /usr/local/bin/

RUN cd /usr/local/bin && \
    chmod +x composer-install.sh && \
    ./composer-install.sh && \
    ln -s /usr/local/bin/composer.phar /usr/local/bin/composer

USER www-data
