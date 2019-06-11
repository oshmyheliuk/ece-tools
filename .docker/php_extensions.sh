#!/usr/bin/env sh

PHP_EXT_DIR=/usr/local/etc/php/conf.d/
PHP_EXT_COM_ON=docker-php-ext-enable

if [ -d ${PHP_EXT_DIR} ] && [ hash ${PHP_EXT_COM_ON} 2>/dev/null ] && [[ -v ${PHP_EXTENSIONS} ]]; then
    shopt -q extglob; extglob_set=$?
    ((extglob_set)) && shopt -s extglob
    rm -f "$PHP_EXT_DIR!(zz-magento.ini|zz-xdebug-settings.ini|zz-mail.ini)"
    ((extglob_set)) && shopt -u extglob
    ${PHP_EXT_COM_ON} ${PHP_EXTENSIONS}
fi






