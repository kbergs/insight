<?php

namespace Drupal\phonenumber\Feeds\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\ConfigurableTargetInterface;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a phone field mapper.
 *
 * @FeedsTarget(
 *   id = "phone",
 *   field_types = {"phone"}
 * )
 */
class Phone extends FieldTargetBase implements ConfigurableTargetInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    return FieldTargetDefinition::createFromFieldDefinition($field_definition)
      ->addProperty('phone_number')
      ->addProperty('local_number')
      ->addProperty('country_code')
      ->addProperty('country_iso2')
      ->addProperty('extension');
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareValue($delta, array &$values) {
    // Check if an extension is provided and set it, otherwise set it to NULL.
    $extension = !empty($values['extension']) ? $values['extension'] : NULL;

    // Determine the phone number based on
    // local number and country code if available.
    if (!empty($values['local_number']) && !empty($values['country_code'])) {
      // Get the phone number using local number and country ISO code.
      $phone_number = $this->phoneNumberUtil->getPhoneNumber($values['local_number'], mb_strtoupper($values['country_iso2']), $extension);
    }
    else {
      // Get the phone number using the provided international phone number.
      $phone_number = $this->phoneNumberUtil->getPhoneNumber($values['phone_number'], NULL, $extension);
    }

    // If a valid phone number is obtained, populate the values array.
    if (!empty($phone_number)) {
      // Get the callable version of the phone number.
      $values['value'] = $this->phoneNumberUtil->getCallableNumber($phone_number);

      // Get the local format of the phone number.
      $values['local_number'] = $this->phoneNumberUtil->getLocalNumber($phone_number, TRUE);

      // Get the country code of the phone number.
      $values['country'] = $this->phoneNumberUtil->getCountry($phone_number);

      // Get the extension of the phone number, if any.
      $values['extension'] = $phone_number->getExtension();
    }
    else {
      // If no valid phone number is obtained, reset the values array.
      $values = [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return '';
  }

}
