#!/bin/bash

#Database config
mysql_host=${MYSQL_HOST}
mysql_database=${MYSQL_DATABASE}
mysql_user=${MYSQL_USER}
mysql_password=${MYSQL_PASSWORD}

#Email config
send_via_email=${SEND_VIA_EMAIL}
send_to=${SEND_TO}

#URL config
send_via_curl=${SEND_VIA_CURL}
backup_post_url=${BACKUP_POST_URL}

timestamp=$(date +"%Y_%m_%d_%H_%M")
file_path="$HOME/backups/"
file_name="$mysql_database"_"$timestamp"".sql.gz"
file_full_path=$file_path$file_name

mysqldump -h "$mysql_host" -u "$mysql_user" --password="$mysql_password" "$mysql_database" | gzip > "$file_full_path"

if [ -f "$file_full_path" ]; then

  if [ "$send_via_email" = "yes" ]; then
      mail_content="database : $mysql_database      timestamp : $timestamp"
      echo "$mail_content" | mutt -a "$file_full_path" -s "Backup $mysql_database" -- "$send_to"
  fi

  if [ "$send_via_curl" = "yes" ]; then
      curl -X POST -F 'backup=@'"$file_path$file_name" "$backup_post_url"
  fi

fi


