<?php

namespace Drupal\phonenumber_verification\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\phonenumber_validation\PhoneValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'phone_verified' formatter.
 *
 * @FieldFormatter(
 *   id = "phone_verified",
 *   label = @Translation("Verified status"),
 *   field_types = {
 *     "phone"
 *   }
 * )
 */
class PhoneVerifiedFormatter extends FormatterBase {

  /**
   * The phone field validation utility.
   *
   * @var \Drupal\phonenumber_validation\PhoneValidatorInterface
   */
  protected $phoneValidator;

  /**
   * Constructs a PhoneVerifiedFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param array $settings
   *   The settings array, containing values for the formatter's settings.
   * @param string $label
   *   The label for the formatter.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\phonenumber_validation\PhoneValidatorInterface $phone_validator
   *   The phone validation service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, PhoneValidatorInterface $phone_validator) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
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
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('phonenumber_validation.validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      /** @var \Drupal\phonenumber_verification\Plugin\Field\FieldType\PhoneVerificationItem $item */
      if ($this->phoneValidator->getPhoneNumber($item->getValue()['phone_number'])) {
        $verified_class = !empty($item->verified) ? ' verified' : '';
        $verified_text = !empty($item->verified) ? $this->t('Verified') : $this->t('Not verified');

        $element[$delta] = [
          '#markup' => '<span class="verified-status' . $verified_class . '">' . $verified_text . '</span>',
        ];
      }
    }

    return $element;
  }

}
