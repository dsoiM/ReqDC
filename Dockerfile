FROM centos:7
ENV container docker

#Enable systemd for cron
RUN (cd /lib/systemd/system/sysinit.target.wants/; for i in *; do [ $i == \
systemd-tmpfiles-setup.service ] || rm -f $i; done); \
rm -f /lib/systemd/system/multi-user.target.wants/*;\
rm -f /etc/systemd/system/*.wants/*;\
rm -f /lib/systemd/system/local-fs.target.wants/*; \
rm -f /lib/systemd/system/sockets.target.wants/*udev*; \
rm -f /lib/systemd/system/sockets.target.wants/*initctl*; \
rm -f /lib/systemd/system/basic.target.wants/*;\
rm -f /lib/systemd/system/anaconda.target.wants/*;

VOLUME [ "/sys/fs/cgroup" ]


#Set time
RUN rm -rf /etc/localtime
RUN ln -s /usr/share/zoneinfo/UTC /etc/localtime



# Install Apache, cron and tools
RUN yum -y update
RUN yum -y install httpd httpd-tools yum-utils wget nano links openssl dejavu-sans-fonts epel-release 


#Add scheduleservice to crontab

#remove default apache configs
RUN rm -f /etc/httpd/conf.d/welcome.conf
RUN rm -f /etc/httpd/conf.d/userdir.conf
RUN rm -f /etc/httpd/conf.d/autoindex.conf


# Install EPEL Repo
RUN rpm -Uvh https://dl.fedoraproject.org/pub/epel/epel-release-latest-7.noarch.rpm \
 && rpm -Uvh http://rpms.remirepo.net/enterprise/remi-release-7.rpm

# Install PHP and whole bunch of stuff needed
RUN yum-config-manager --enable remi-php73
RUN yum -y install cronie libcurl net-tools git httpd mod_security php php-bcmath php-cli php-common php-devel php-fpm php-gd php-intl php-json php-mbstring php-pdo php-pear.noarch php-pecl-mcrypt php-pecl-zip php-process php-xml gettext composer unzip && yum clean all

#Mongodb client library
RUN pecl install mongodb
RUN echo "extension=mongodb.so" >> `php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||"`

RUN mkdir /var/log/reqdc
COPY composer.json /var/www/reqdc/composer.json
WORKDIR /var/www/reqdc
RUN composer install

ARG XDEBUG_REMOTE_HOST
COPY shellscripts/enable_xdebug_php.sh /tmp/enable_xdebug_php.sh
RUN if [ "x$XDEBUG_REMOTE_HOST" = "x" ] ; then echo Not installing xdebug ; else /tmp/enable_xdebug_php.sh ;fi

#-----Try not to add anything before this line-----

#Create crontab which keeps scheduleservice alive in case it dies
RUN crontab -l | { cat; echo "* * * * * cd /var/www/reqdc/ && shellscripts/start_scheduleservice.sh"; } | crontab -

#directory rights and cleanup BAU
RUN rm -rf /var/www/html/*
RUN chmod a+rwx /var/log/reqdc
RUN chmod a+rwx /var/www/reqdc


#For development mount
RUN echo "formount:x:982:" >> /etc/group
RUN usermod -a -G formount apache

#Environment name and encryption key
ARG DOMAIN
ARG KEY
ARG ENVNAME
RUN echo "$ENVNAME" > /var/www/ENV 
RUN echo "$KEY" > /var/www/KEY

#Dont add anything after copy unless it is needed by copy so dev building  is fast
COPY . /var/www/reqdc
RUN chmod -R a+rwx /var/www/reqdc/*
RUN envsubst < /var/www/reqdc/config/httpd.conf.template > /etc/httpd/conf/httpd.conf


EXPOSE 80

# Start scheduleservice & Apache & crond to keep scheduleservice running
CMD cd /var/www/reqdc/ && sleep 2 && shellscripts/start_scheduleservice.sh && /usr/sbin/crond && /usr/sbin/httpd -D FOREGROUND