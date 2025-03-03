<?php

namespace Drupal\phonenumber_validation\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\phonenumber\Element\Phone;

/**
 * Provides phone element validation.
 *
 * Usage example:
 *
 * @code
 * $form['phone'] = [
 *   '#type' => 'phone',
 *   '#title' => $this->t('Phone number'),
 *    // Add #element_validate to your form element.
 *   '#element_validate' => [['Drupal\phonenumber_validation\Element\PhoneValidation', 'validatePhone']],
 *    // Customize validation settings. If not, global settings will be in use.
 *   '#element_validate_settings' => [
 *     // By default input format should be consistent with E164 standard.
 *     'valid_format' => PhoneNumberFormat::E164,
 *     // By default all countries are valid.
 *     'valid_countries' => [],
 *   ],
 * ];
 * @endcode
 *
 * @see Drupal\phonenumber\Element\Phone
 *
 * @FormElement("phone")
 */
class PhoneValidation extends Phone {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processPhoneValidation'],
      ],
      '#element_validate' => [
        [$class, 'validatePhone'],
      ],
      '#phone' => [],
    ];
  }

  /**
   * SMS Phone Number element process callback.
   *
   * @param array $element
   *   Element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array $complete_form
   *   Complete form.
   *
   * @return array
   *   Processed array.
   */
  public static function processPhoneValidation(array &$element, FormStateInterface $form_state, array &$complete_form) {
    return parent::processPhone($element, $form_state, $complete_form);
  }

  /**
   * Form element validation handler.
   *
   * Note that #maxlength and #required is validated by _form_validate()
   * already.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array $complete_form
   *   The complete form.
   */
  public static function validatePhone(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $settings = $element['#phone'];

    if (!empty($settings['validate'])) {
      // Get validation service.
      $service = \Drupal::service('phonenumber_validation.validator');

      // Normalize value.
      $value = $element['#value'];

      // Set local and phone number from given value.
      $phone = $value['phone_number'] ?? '';
      $local = $value['local_number'] ?? '';

      // Check if number is valid (if not empty).
      if ($local !== '' && !$service->isValid($phone, $element['#element_validate_settings']['format'], $element['#element_validate_settings']['country'])) {
        $form_state->setError($element, t('The phone number %phone is not valid.', ['%phone' => $phone]));
      }
    }

    return $element;
  }

  /**
   * Get currently entered phone number, given the form element.
   *
   * @param array $element
   *   Phone number form element.
   * @param bool $input_value
   *   Whether to use the input value or the default value, TRUE = input value.
   *
   * @return \libphonenumber\PhoneNumber|null
   *   Phone number. Null if empty, or not valid, phone number.
   */
  public static function getPhoneNumber(array $element, $input_value = TRUE) {

    // Get validation service.
    /** @var \Drupal\phonenumber_validation\PhoneValidatorInterface $validator */
    $validator = \Drupal::service('phonenumber_validation.validator');

    if ($input_value) {
      $values = !empty($element['#value']['local_number']) ? $element['#value'] : [];
    }
    else {
      $values = !empty($element['#default_value']['local_number']) ? $element['#default_value'] : [];
    }

    if ($values) {
      $settings = $element['#phone'];
      $extension = NULL;
      if ($settings['extension_field']) {
        $extension = $values['extension'] ?? NULL;
      }
      return $validator->getPhoneNumber($values['local_number'], mb_strtoupper($values['country_iso2']), $extension);
    }

    return NULL;
  }

}
