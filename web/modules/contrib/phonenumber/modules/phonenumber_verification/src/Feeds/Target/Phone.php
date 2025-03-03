<?php

namespace Drupal\phonenumber_verification\Feeds\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\phonenumber_validation\Feeds\Target\Phone as PhoneValidation;
use Drupal\phonenumber_validation\PhoneValidatorInterface;
use Drupal\phonenumber_verification\PhoneVerifierInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a phone field verification mapper.
 *
 * @FeedsTarget(
 *   id = "phone",
 *   field_types = {"phone"}
 * )
 */
class Phone extends PhoneValidation implements ContainerFactoryPluginInterface {

  /**
   * The phone field validation utility.
   *
   * @var \Drupal\phonenumber_validation\PhoneValidatorInterface
   */
  protected $phoneValidator;

  /**
   * The phone field verification utility.
   *
   * @var \Drupal\phonenumber_verification\PhoneVerifierInterface
   */
  protected PhoneVerifierInterface $phoneVerifier;

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
   *   The phone field validation utility.
   * @param \Drupal\phonenumber_verification\PhoneVerifierInterface $phone_verifier
   *   The phone field verification utility.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, PhoneValidatorInterface $phone_validator, PhoneVerifierInterface $phone_verifier) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $phone_validator);

    // Initialize the phone verifier.
    $this->phoneVerifier = $phone_verifier;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('phonenumber_validation.validator'),
      $container->get('phonenumber_verification.verifier')
    );
  }

  /**
   * Prepares the target field for mapping.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return \Drupal\feeds\Feeds\Target\FieldTarget
   *   The prepared target field.
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    return parent::prepareTarget($field_definition)
      ->addProperty('tfa')
      ->addProperty('verified');
  }

  /**
   * Prepares the field values for saving.
   *
   * @param int $delta
   *   The index of the field value in the array.
   * @param array &$values
   *   The field values to prepare.
   */
  protected function prepareValue($delta, array &$values) {
    // Call parent method to prepare basic values.
    parent::prepareValue($delta, $values);

    // Proceed if 'verified' or 'tfa' is not empty.
    if (!empty($values['verified']) || !empty($values['tfa'])) {
      $phone_number = FALSE;

      // Check if local number and country code are provided.
      if (!empty($values['local_number']) && !empty($values['country_iso2'])) {
        // Get the phone number object using local number and country code.
        $phone_number = $this->phoneValidator->getPhoneNumber($values['local_number'], mb_strtoupper($values['country_iso2']));
      }
      else {
        // Get the phone number object using the full phone number.
        $phone_number = $this->phoneValidator->getPhoneNumber($values['phone_number']);
      }

      if ($phone_number) {
        // Set TFA value.
        $values['tfa'] = !empty($values['tfa']) ? 1 : 0;

        // If verified, generate and register a verification code and token.
        if (!empty($values['verified'])) {
          $code = $this->phoneVerifier->generateVerificationCode();
          $token = $this->phoneVerifier->registerVerificationCode($phone_number, $code);
          $values['verification_code'] = $code;
          $values['verification_token'] = $token;
        }
        // Set verified status to 0.
        $values['verified'] = 0;
      }
    }
  }

}
