<?php

namespace Drupal\phonenumber_verification\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\phonenumber_validation\PhoneValidatorInterface;
use Drupal\phonenumber_validation\Plugin\Field\FieldWidget\PhoneValidationWidget;
use Drupal\phonenumber_verification\Element\PhoneVerification;
use Drupal\phonenumber_verification\PhoneVerifierInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Plugin implementation of the 'phone_default' widget.
 *
 * @FieldWidget(
 *   id = "phone_default",
 *   label = @Translation("Phone number"),
 *   description = @Translation("Phone number field verification widget."),
 *   field_types = {
 *     "phone",
 *     "telephone"
 *   }
 * )
 */
class PhoneVerificationWidget extends PhoneValidationWidget {

  /**
   * The phone field verification utility.
   *
   * @var \Drupal\phonenumber_verification\PhoneVerifierInterface
   */
  protected $phoneVerifier;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, CountryManagerInterface $country_manager, PhoneValidatorInterface $phone_validator, PhoneVerifierInterface $phone_verifier) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $country_manager, $phone_validator);

    $this->phoneVerifier = $phone_verifier;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('country_manager'),
      $container->get('phonenumber_validation.validator'),
      $container->get('phonenumber_verification.verifier')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'separate_verification_code' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    // Add form element for separate verification code input.
    $element['separate_verification_code'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Separate verification code'),
      '#default_value' => $this->getSetting('separate_verification_code'),
      '#description' => $this->t('Display separate input fields for each digit of the verification code.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $item = $items[$delta];
    $entity = $items->getEntity();
    $settings = $this->getFieldSettings();
    $settings += $this->getSettings() + static::defaultSettings();
    $tfa_field = $this->phoneVerifier->getTfaField();

    // Set default values and settings for the phone verification element.
    $element['#default_value']['verified'] = $item->verified ?? FALSE;
    $element['#default_value']['tfa'] = $item->tfa ?? FALSE;
    $element['#phone']['verify'] = ($this->phoneVerifier->isSmsEnabled() && !empty($settings['verify'])) ? $settings['verify'] : PhoneVerifierInterface::PHONE_NUMBER_VERIFY_NONE;
    $element['#phone']['message'] = $settings['message'] ?? NULL;
    $element['#phone']['length'] = $settings['length'] ?? PhoneVerifierInterface::VERIFICATION_CODE_LENGTH;
    $element['#phone']['verify_interval'] = $settings['verify_interval'] ?? PhoneVerifierInterface::VERIFY_ATTEMPTS_INTERVAL;
    $element['#phone']['verify_count'] = $settings['verify_count'] ?? PhoneVerifierInterface::VERIFY_ATTEMPTS_COUNT;
    $element['#phone']['sms_interval'] = $settings['sms_interval'] ?? PhoneVerifierInterface::SMS_ATTEMPTS_INTERVAL;
    $element['#phone']['sms_count'] = $settings['sms_count'] ?? PhoneVerifierInterface::SMS_ATTEMPTS_COUNT;
    $element['#phone']['separate_verification_code'] = (bool) $settings['separate_verification_code'] ?? FALSE;
    $element['#phone']['tfa'] = ($entity->getEntityTypeId() == 'user' && $tfa_field == $items->getFieldDefinition()->getName() && $items->getFieldDefinition()->getFieldStorageDefinition()->getCardinality() == 1) ? TRUE : NULL;

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $error, array $form, FormStateInterface $form_state) {
    $settings = $this->getFieldSettings();
    $settings += $this->getSettings() + static::defaultSettings();
    $queue = [
      'verify_interval' => $settings['verify_interval'],
      'verify_count' => $settings['verify_count'],
      'sms_interval' => $settings['sms_interval'],
      'sms_count' => $settings['sms_count'],
    ];
    $op = PhoneVerification::getOp($element, $form_state);
    $phonenumber_verification = PhoneVerification::getPhoneNumber($element);

    // Check flood limits for verification and SMS attempts.
    if ($op == 'send_verification' && $phonenumber_verification && ($this->phoneVerifier->checkFlood($phonenumber_verification, 'verification', $queue) || $this->phoneVerifier->checkFlood($phonenumber_verification, 'sms', $queue))) {
      return FALSE;
    }

    return parent::errorElement($element, $error, $form, $form_state);
  }

}
