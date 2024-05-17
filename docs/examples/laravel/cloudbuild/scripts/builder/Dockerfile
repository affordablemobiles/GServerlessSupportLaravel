ARG IMAGE=eu.gcr.io/demo/container
ARG TAG=tag
FROM ${IMAGE}:${TAG}

USER root

ADD git-credential-gcloud.sh /usr/local/bin/

RUN chmod +x /usr/local/bin/git-credential-gcloud.sh && \
    echo >> /etc/gitconfig && \
    echo "[credential \"https://source.developers.google.com\"]" >> /etc/gitconfig && \
    echo "    helper = gcloud.sh" >> /etc/gitconfig

ADD composer-install.sh /usr/local/bin/

RUN cd /usr/local/bin && \
    chmod +x composer-install.sh && \
    ./composer-install.sh && \
    ln -s /usr/local/bin/composer.phar /usr/local/bin/composer

USER www-data
