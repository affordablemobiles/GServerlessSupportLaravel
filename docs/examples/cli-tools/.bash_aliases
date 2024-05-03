alias phpdockercreatefolders='mkdir -p ~/.config ~/.composer ~/.cache/composer ~/cloudsql && touch ~/.git-credentials ~/.gitconfig'

alias cloud_sql_proxy='phpdockercreatefolders && docker run -it -v ~/.config:/.config -v ~/cloudsql:/cloudsql -w / --user $(id -u):$(id -g) gcr.io/cloud-sql-connectors/cloud-sql-proxy:latest --credentials-file /.config/gcloud/application_default_credentials.json --unix-socket /cloudsql'

alias php83='phpdockercreatefolders && docker run -it -v "$PWD":/usr/src/app -v ~/.config:/.config -v ~/.git-credentials:/.git-credentials -v ~/.gitconfig:/.gitconfig -v ~/.composer:/.composer -v ~/.cache/composer:/.cache/composer -v ~/cloudsql:/cloudsql -w /usr/src/app --user $(id -u):$(id -g) gcr.io/gae-runtimes/buildpacks/php83/builder:latest php'
alias php83composer='phpdockercreatefolders && docker run -it -v "$PWD":/usr/src/app -v ~/.config:/.config -v ~/.git-credentials:/.git-credentials -v ~/.gitconfig:/.gitconfig -v ~/.composer:/.composer -v ~/.cache/composer:/.cache/composer -v ~/cloudsql:/cloudsql -w /usr/src/app --user $(id -u):$(id -g) gcr.io/gae-runtimes/buildpacks/php83/builder:latest composer'
alias php83fix='phpdockercreatefolders && docker run -it -v "$PWD":/usr/src/app -w /usr/src/app --user $(id -u):$(id -g) gcr.io/gae-runtimes/buildpacks/php83/builder:latest make fix'
alias php83artisan='phpdockercreatefolders && docker run -it -v "$PWD":/usr/src/app -v ~/cloudsql:/cloudsql -w /usr/src/app --user $(id -u):$(id -g) gcr.io/gae-runtimes/buildpacks/php83/builder:latest php artisan'
alias php83test='phpdockercreatefolders && docker run --network=host -v "$PWD":/usr/src/app -v ~/cloudsql:/cloudsql -w /usr/src/app --user $(id -u):$(id -g) gcr.io/gae-runtimes/buildpacks/php83/builder:latest php artisan test'

alias php81='phpdockercreatefolders && docker run -it -v "$PWD":/usr/src/app -v ~/.config:/.config -v ~/.git-credentials:/.git-credentials -v ~/.gitconfig:/.gitconfig -v ~/.composer:/.composer -v ~/.cache/composer:/.cache/composer -v ~/cloudsql:/cloudsql -w /usr/src/app --user $(id -u):$(id -g) gcr.io/gae-runtimes/buildpacks/php81/builder:latest php'
alias php81composer='phpdockercreatefolders && docker run -it -v "$PWD":/usr/src/app -v ~/.config:/.config -v ~/.git-credentials:/.git-credentials -v ~/.gitconfig:/.gitconfig -v ~/.composer:/.composer -v ~/.cache/composer:/.cache/composer -v ~/cloudsql:/cloudsql -w /usr/src/app --user $(id -u):$(id -g) gcr.io/gae-runtimes/buildpacks/php81/builder:latest composer'
alias php81fix='phpdockercreatefolders && docker run -it -v "$PWD":/usr/src/app -w /usr/src/app --user $(id -u):$(id -g) gcr.io/gae-runtimes/buildpacks/php81/builder:latest make fix'
alias php81artisan='phpdockercreatefolders && docker run -it -v "$PWD":/usr/src/app -v ~/cloudsql:/cloudsql -w /usr/src/app --user $(id -u):$(id -g) gcr.io/gae-runtimes/buildpacks/php81/builder:latest php artisan'
alias php81test='phpdockercreatefolders && docker run --network=host -v "$PWD":/usr/src/app -v ~/cloudsql:/cloudsql -w /usr/src/app --user $(id -u):$(id -g) gcr.io/gae-runtimes/buildpacks/php81/builder:latest php artisan test'

alias php74='phpdockercreatefolders && docker run -it -v "$PWD":/usr/src/app -v ~/.config:/.config -v ~/.git-credentials:/.git-credentials -v ~/.gitconfig:/.gitconfig -v ~/.composer:/.composer -v ~/.cache/composer:/.cache/composer -v ~/cloudsql:/cloudsql -w /usr/src/app --user $(id -u):$(id -g) gcr.io/gae-runtimes/buildpacks/php74/builder:latest php'
alias php74composer='phpdockercreatefolders && docker run -it -v "$PWD":/usr/src/app -v ~/.config:/.config -v ~/.git-credentials:/.git-credentials -v ~/.gitconfig:/.gitconfig -v ~/.composer:/.composer -v ~/.cache/composer:/.cache/composer -v ~/cloudsql:/cloudsql -w /usr/src/app --user $(id -u):$(id -g) gcr.io/gae-runtimes/buildpacks/php74/builder:latest composer'
alias php74fix='phpdockercreatefolders && docker run -it -v "$PWD":/usr/src/app -w /usr/src/app --user $(id -u):$(id -g) gcr.io/gae-runtimes/buildpacks/php74/builder:latest make fix'
alias php74artisan='phpdockercreatefolders && docker run -it -v "$PWD":/usr/src/app -v ~/cloudsql:/cloudsql -w /usr/src/app --user $(id -u):$(id -g) gcr.io/gae-runtimes/buildpacks/php74/builder:latest php artisan'
alias php74test='phpdockercreatefolders && docker run --network=host -v "$PWD":/usr/src/app -v ~/cloudsql:/cloudsql -w /usr/src/app --user $(id -u):$(id -g) gcr.io/gae-runtimes/buildpacks/php74/builder:latest php artisan test'

alias npmdockercreatefolders='mkdir -p ~/.npm && touch ~/.npmrc'

alias npm='npmdockercreatefolders && docker run -it -v "$PWD":/usr/src/app -w /usr/src/app -v ~/.npm:/tmp/.npm -v ~/.npmrc:/tmp/.npmrc -e HOME=/tmp --user $(id -u):$(id -g) gcr.io/cloud-builders/npm:lts'