<?php

/**
 * @file
 * Provides view integration for the PhoneNumber Verification module.
 */

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 */
function phonenumber_verification_field_views_data(FieldStorageConfigInterface $field) {
  $data = views_field_default_views_data($field);

  // Check if the field type is 'phone'.
  if ($field->getType() == 'phone') {
    foreach ($data as $table_name => $table_data) {
      foreach ($table_data as $field_name => $field_data) {
        // Add filter for two-factor authentication status.
        if ($field_name == "{$field->getName()}_tfa") {
          $data[$table_name][$field_name]['filter']['id'] = 'in_operator';
          $data[$table_name][$field_name]['filter']['options callback'] = '\Drupal\phonenumber_verification\Plugin\Field\FieldType\PhoneVerifiactionItem::booleanOptions';
        }
        // Add filter for verification status.
        if ($field_name == "{$field->getName()}_verified") {
          $data[$table_name][$field_name]['filter']['id'] = 'in_operator';
          $data[$table_name][$field_name]['filter']['options callback'] = '\Drupal\phonenumber_verification\Plugin\Field\FieldType\PhoneVerifiactionItem::booleanOptions';
        }
      }
    }
  }

  return $data;
}
