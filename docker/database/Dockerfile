FROM ubuntu

RUN apt-get -y update
RUN apt-get -y update --fix-missing


#Mysql
RUN apt-get install mysql-server -y

# CURL
RUN apt-get install curl -y

# Mail
RUN apt-get install msmtp -y
RUN apt-get install mutt -y

# Cron jobs
RUN apt-get install cron -y
COPY ./config/crontab /etc/crontab

#Backup script
COPY ./script/backup.sh /usr/bin/backup.sh
RUN chmod +x /usr/bin/backup.sh

# For inspection
RUN apt-get install -y procps
RUN apt-get install -y nano

# Supervisor
RUN apt-get install supervisor -y
RUN mkdir -p /etc/supervisor.d/
COPY ./config/supervisor.conf /etc/supervisor.d/supervisord.ini

CMD ["supervisord", "-c", "/etc/supervisor.d/supervisord.ini"]





