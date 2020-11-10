FROM centos:7
# Install Apache
RUN yum -y update
RUN yum -y install httpd httpd-tools yum-utils wget nano links openssl dejavu-sans-fonts epel-release

 
RUN rm -f /etc/httpd/conf.d/welcome.conf
RUN rm -f /etc/httpd/conf.d/userdir.conf
RUN rm -f /etc/httpd/conf.d/autoindex.conf


# Install EPEL Repo
RUN rpm -Uvh https://dl.fedoraproject.org/pub/epel/epel-release-latest-7.noarch.rpm \
 && rpm -Uvh http://rpms.remirepo.net/enterprise/remi-release-7.rpm

# Install PHP

RUN yum-config-manager --enable remi-php73
RUN yum -y install libcurl  net-tools git httpd mod_security php php-bcmath php-cli php-common php-devel php-fpm php-gd php-intl php-json php-mbstring php-pdo php-pear.noarch php-pecl-mcrypt php-pecl-zip php-process php-xml

RUN yum -y install gettext composer unzip
RUN rm -rf /var/www/html/*
RUN mkdir /var/log/reqdc
RUN chmod a+rwx /var/log/reqdc
RUN pecl install mongodb
RUN echo "extension=mongodb.so" >> `php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||"`
COPY composer.json /var/www/reqdc/composer.json
WORKDIR /var/www/reqdc
RUN composer install
RUN chmod a+rwx /var/www/reqdc
RUN chmod -R a+rwx /var/www/reqdc/*
#For development mount
RUN echo "formount:x:982:" >> /etc/group
RUN usermod -a -G formount apache
ARG DOMAIN
ARG KEY
ARG ENVNAME
RUN echo "$ENVNAME" > /var/www/ENV 
RUN echo "$KEY" > /var/www/KEY

#Dont add anything after copy unless it is needed by copy
COPY . /var/www/reqdc
RUN envsubst < /var/www/reqdc/config/httpd.conf.template > /etc/httpd/conf/httpd.conf


EXPOSE 80

# Start Apache
CMD ["/usr/sbin/httpd","-D","FOREGROUND"]