# Journal Administrator Guide

## Requirements

- Nextcloud 34
- PHP 8.0 to 8.3
- Nextcloud Text enabled

## Application identity

- Display name: `Journal`
- Application ID: `journalnotes`
- PHP namespace: `OCA\JournalNotes`
- Database table: `oc_journalnotes`
- User storage folder: `Journal`

## Enable the application

```bash
sudo -u www-data php /var/www/html/nextcloud/occ \
  app:enable journalnotes
