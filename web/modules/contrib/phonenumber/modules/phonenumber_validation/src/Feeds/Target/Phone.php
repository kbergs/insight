<?php

namespace Drupal\phonenumber_validation\Feeds\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\ConfigurableTargetInterface;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;
use Drupal\phonenumber_validation\PhoneValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a phone field validation mapper.
 *
 * @FeedsTarget(
 *   id = "phone",
 *   field_types = {"phone"}
 * )
 */
class Phone extends FieldTargetBase implements ConfigurableTargetInterface, ContainerFactoryPluginInterface {

  /**
   * The phone field validation utility.
   *
   * @var \Drupal\phonenumber_validation\PhoneValidatorInterface
   */
  protected $phoneValidator;

  /**
   * Constructs a Phone object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\phonenumber_validation\PhoneValidatorInterface $phone_validator
   *   The phone number validation service.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, PhoneValidatorInterface $phone_validator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->phoneValidator = $phone_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('phonenumber_validation.validator')
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
    $extension = !empty($values['extension']) ? $values['extension'] : NULL;
    if (!empty($values['local_number']) && !empty($values['country_iso2'])) {
      $phone_number = $this->phoneValidator->getPhoneNumber($values['local_number'], mb_strtoupper($values['country_iso2']), $extension);
    }
    else {
      $phone_number = $this->phoneValidator->getPhoneNumber($values['phone_number'], NULL, $extension);
    }

    if ($phone_number) {
      $values['phone_number'] = $this->phoneValidator->getCallableNumber($phone_number);
      $values['local_number'] = $this->phoneValidator->getLocalNumber($phone_number, TRUE);
      $values['country_code'] = $this->phoneValidator->getCountryCode($phone_number);
      $values['country_iso2'] = $this->phoneValidator->getCountry($phone_number);
      $values['extension'] = $phone_number->getExtension();
    }
    else {
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
