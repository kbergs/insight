<?php

namespace Drupal\phonenumber_validation\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\phonenumber\Plugin\Field\FieldWidget\PhoneDefaultWidget;
use Drupal\phonenumber_validation\PhoneValidatorInterface;
use Drupal\phonenumber_verification\Element\PhoneVerification;
use libphonenumber\PhoneNumberFormat;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Plugin implementation of the 'phone_default' widget.
 *
 * @FieldWidget(
 *   id = "phone_default",
 *   label = @Translation("Phone number"),
 *   description = @Translation("Phone number field validation widget."),
 *   field_types = {
 *     "phone",
 *     "telephone"
 *   }
 * )
 */
class PhoneValidationWidget extends PhoneDefaultWidget {

  /**
   * The phone field validation utility.
   *
   * @var \Drupal\phonenumber_validation\PhoneValidatorInterface
   */
  protected $phoneValidator;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, CountryManagerInterface $country_manager, PhoneValidatorInterface $phone_validator) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $country_manager);

    $this->phoneValidator = $phone_validator;
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
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Set validation on or off per instance.
    $settings = $items->getFieldDefinition()->getThirdPartySettings('phonenumber_validation');
    $format = $this->getThirdPartySetting('phonenumber_validation', 'format', PhoneNumberFormat::E164);
    $country = $this->getThirdPartySetting('phonenumber_validation', 'country') ?? [];

    // Add validate config to phone settings.
    $element['#phone']['validate'] = !empty($settings) ?
      [
        'format' => $format,
        'countries' => $country,
      ]
      : [];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $error, array $form, FormStateInterface $form_state) {
    $op = PhoneVerification::getOp($element, $form_state);
    $phonenumber_verification = PhoneVerification::getPhoneNumber($element);

    if ($op == 'phonenumber_verification_send_verification' && $phonenumber_verification && ($this->phoneVerifier->checkFlood($phonenumber_verification) || $this->phoneVerifier->checkFlood($phonenumber_verification, 'sms'))) {
      return FALSE;
    }

    return parent::errorElement($element, $error, $form, $form_state);
  }

}
