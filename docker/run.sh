#!/bin/bash

#exit if any command returns non-zero
set -e

#prints a trace of each command (shown with a '+' in front of the command)
set -x

if [ "x$APP_ENV" = "x" ] ; then
  echo "Error : APP_ENV variable not set!" 1>&2
  exit -1
fi

if [ "$INSTALL" == "yes" ] ; then
    if [ "$APP_ENV" != "dev" ] ; then
        composer install --no-dev --no-interaction --optimize-autoloader
    else
        composer install --no-interaction --optimize-autoloader
    fi

    mkdir -p app/cache
    mkdir -p app/data
    setfacl -R -m u:"www-data":rwX -m u:`whoami`:rwX app/cache app/data
    setfacl -dR -m u:"www-data":rwX -m u:`whoami`:rwX app/cache app/data
fi

if [ "$APP_ENV" == "dev" ] ; then
    app/console doctrine:database:create --env=$APP_ENV -n -q
fi

if [ -f app/data/app.sqlite ]; then
    chmod g+w app/data/app.sqlite
fi

app/console orm:schema-tool:update --force --env=$APP_ENV -n

php-fpm &
nginx -g "daemon off;"
