echo "Installing debug with remote $XDEBUG_REMOTE_HOST"
yum -y install php-pecl-xdebug.x86_64 
echo "xdebug.remote_enable = 1" >> /etc/php.ini
echo "xdebug.remote_host = $XDEBUG_REMOTE_HOST" >> /etc/php.ini
echo "xdebug.remote_autostart=1" >> /etc/php.ini
