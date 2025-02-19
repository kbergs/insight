#!/bin/bash

# Check if the site is already installed
if [ ! -f web/sites/default/settings.php ]; then
  echo "Installing Drupal site..."

  # Install Drupal using Drush
  drush site:install standard \
    --account-name=admin \
    --account-pass=admin \
    --site-name="Insight" \
    --db-url=mysql://db:db@db/db \
    --site-mail=insight@deltavbio.com \
    --locale=en \
    --yes

  echo "Drupal site installed successfully."
else
  echo "Drupal site already installed."
fi