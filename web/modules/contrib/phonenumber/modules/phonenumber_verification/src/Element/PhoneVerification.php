<?php

namespace Drupal\phonenumber_verification\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\phonenumber_validation\Element\PhoneValidation;
use Drupal\phonenumber_validation\Exception\PhoneNumberException;
use Drupal\phonenumber_verification\PhoneVerifierInterface;

/**
 * Provides phone element verification.
 *
 * Properties:
 * - #phone
 *   - allowed_countries.
 *   - allowed_types.
 *   - placeholder.
 *   - extension_field.
 *   - verify.
 *   - tfa.
 *   - message.
 *   - token_data.
 *
 * Example usage:
 *
 * @code
 * $form['phone'] = [
 *   '#type' => 'phone',
 *   '#title' => $this->t('Phone number'),
 * ];
 * @endcode
 *
 * @see Drupal\phonenumber_validation\Element\PhoneValidation
 *
 * @FormElement("phone")
 */
class PhoneVerification extends PhoneValidation {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processPhoneVerification'],
      ],
      '#element_validate' => [
        [$class, 'validatePhoneVerification'],
      ],
      '#phone' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    $result = parent::valueCallback($element, $input, $form_state);
    if ($input) {
      $result['tfa'] = !empty($input['tfa']) ? 1 : 0;
      $result['verified'] = 0;
      $result['verification_token'] = !empty($input['verification_token']) ? $input['verification_token'] : NULL;
      $result['verification_code'] = !empty($input['verification_code']) ? $input['verification_code'] : NULL;
    }

    return $result;
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
  public static function processPhoneVerification(array &$element, FormStateInterface $form_state, array &$complete_form) {
    // Call parent processPhone method for basic phone element processing.
    $element = parent::processPhone($element, $form_state, $complete_form);

    /** @var \Drupal\phonenumber_verification\PhoneVerifierInterface $verifier */
    $verifier = \Drupal::service('phonenumber_verification.verifier');

    // Ensure the element is treated as a tree, allowing nested structure.
    $element['#tree'] = TRUE;

    // Prepare identifiers for element manipulation.
    $field_name = $element['#name'];
    $field_path = implode('][', $element['#parents']);
    $id = $element['#id'];

    // Determine the current operation (e.g., send verification, verify).
    $op = static::getOp($element, $form_state);

    // Retrieve any existing errors from the form state.
    $errors = $form_state->getErrors();

    // Add default phone verification settings to the element.
    $element['#phone'] += [
      'verify' => $verifier::PHONE_NUMBER_VERIFY_NONE,
      'message' => $verifier::PHONE_NUMBER_DEFAULT_SMS_MESSAGE,
      'tfa' => FALSE,
    ];

    $settings = $element['#phone'];
    $value = $element['#value'];

    /** @var \Drupal\phonenumber_validation\PhoneValidatorInterface $validator */
    $validator = \Drupal::service('phonenumber_validation.validator');

    // Determine if the phone number is already verified.
    $verified = FALSE;
    if (!empty($value['phone_number']) && $validator->getPhoneNumber($value['phone_number'])) {
      $verified = ($settings['verify'] != PhoneVerifierInterface::PHONE_NUMBER_VERIFY_NONE) && static::isVerified($element);
    }

    // Add a suffix to the phone field to indicate the verified status.
    $element['phone']['#suffix'] = '<div class="form-item verified ' . ($verified ? 'show' : '') . '" title="' . t('Verified') . '"><span>' . t('Verified') . '</span></div>';

    // Attach necessary libraries for AJAX handling.
    $element['phone']['#attached']['library'] = ['core/drupal.dialog.ajax'];
    $element['phone']['#attached']['library'][] = 'phonenumber_verification/verification';

    // Prepare phone verification settings for the element.
    $options = [
      'separate_verification_code' => $settings['separate_verification_code'],
      'verify_interval' => $settings['verify_interval'],
      'verify_count' => $settings['verify_count'],
      'sms_interval' => $settings['sms_interval'],
      'sms_count' => $settings['sms_count'],
    ];

    // Attach phone verification queue settings to Drupal settings.
    $element['#attached']['drupalSettings']['phoneVerification']['options'] = $options;

    // Add the send verification button if phone verification is enabled.
    if ($settings['verify'] != PhoneVerifierInterface::PHONE_NUMBER_VERIFY_NONE) {
      $element['send_verification'] = [
        '#type' => 'button',
        '#value' => t('Send verification code'),
        '#ajax' => [
          'callback' => 'Drupal\phonenumber_verification\Element\PhoneVerification::verifyAjax',
          'wrapper' => $id,
          'effect' => 'fade',
        ],
        '#name' => implode('__', $element['#parents']) . '__send_verification',
        '#op' => 'send_verification',
        '#attributes' => [
          'class' => [
            !$verified ? 'show' : '',
            'send-button',
          ],
        ],
        '#submit' => [],
      ];

      // Reset the verification code field if it exists in user input.
      $user_input = $form_state->getUserInput();
      $vc_parents = $element['#parents'];
      array_push($vc_parents, 'verification_code');
      if (NestedArray::getValue($user_input, $vc_parents)) {
        NestedArray::setValue($user_input, $vc_parents, '');
        $form_state->setUserInput($user_input);
      }

      // Determine if the verification prompt should be shown.
      $verify_prompt = (!$verified && $op && (!$errors || $op == 'verify'));

      // Add verification code input fields.
      if ($settings['separate_verification_code']) {
        for ($i = 1; $i <= $settings['length']; $i++) {
          $element['verification_code' . $i] = [
            '#type' => 'textfield',
            '#title' => t('Digit @digit', ['@digit' => $i]),
            '#title_display' => 'invisible',
            '#disabled' => !($i == 1),
            '#maxlength' => 1,
            '#attributes' => [
              'class' => ['code-input'],
              'pattern' => '\d',
            ],
          ];

          if ($i == 1) {
            $element['verification_code' . $i]['#prefix'] = '<div class="verification split' . ($verify_prompt ? ' show' : '') . '">';
          }
        }

        $element['verification_code'] = [
          '#type' => 'hidden',
          '#attributes' => [
            'class' => ['code-verify'],
          ],
        ];
      }
      else {
        $element['verification_code'] = [
          '#type' => 'textfield',
          '#title' => t('Verification Code'),
          '#prefix' => '<div class="verification' . ($verify_prompt ? ' show' : '') . '">',
          '#attributes' => [
            'class' => ['code-input'],
            'placeholder' => $settings['length'] == '4' ? '____' : '______',
          ],
        ];
      }

      // Add a description and verify button.
      $element['verify_description'] = [
        '#type' => 'item',
        '#description' => t('A verification code has been sent to your phone. Enter it here.'),
        '#attributes' => [
          'class' => [
            'verify-description',
          ],
        ],
      ];

      $element['verify'] = [
        '#type' => 'button',
        '#value' => t('Verify'),
        '#ajax' => [
          'callback' => 'Drupal\phonenumber_verification\Element\PhoneVerification::verifyAjax',
          'wrapper' => $id,
          'effect' => 'fade',
        ],
        '#name' => implode('__', $element['#parents']) . '__verify',
        '#op' => 'verify',
        '#attributes' => [
          'class' => [
            'verify-button',
          ],
        ],
        '#suffix' => '</div>',
        '#submit' => [],
      ];

      // Add TFA option if applicable.
      if (!empty($settings['tfa'])) {
        $element['tfa'] = [
          '#type' => 'checkbox',
          '#title' => t('Enable two-factor authentication'),
          '#default_value' => !empty($value['tfa']) ? 1 : 0,
          '#prefix' => '<div class="phone-number-tfa">',
          '#suffix' => '</div>',
        ];
      }

      // Store the verification token.
      $storage = $form_state->getStorage();
      $element['verification_token'] = [
        '#type' => 'hidden',
        '#value' => !empty($storage['phone_fields'][$field_path]['token']) ? $storage['phone_fields'][$field_path]['token'] : '',
        '#name' => $field_name . '[verification_token]',
      ];
    }

    // Add a description if provided.
    if (!empty($element['#description'])) {
      $element['description']['#markup'] = '<div class="description">' . $element['#description'] . '</div>';
    }

    return $element;
  }

  /**
   * SMS Phone Number element validate callback.
   *
   * @param array $element
   *   The form element to be validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form array.
   *
   * @return array
   *   The validated element.
   */
  public static function validatePhoneVerification(array &$element, FormStateInterface $form_state, array &$complete_form) {
    // Call parent validatePhone method for basic phone element validation.
    $element = parent::validatePhone($element, $form_state, $complete_form);

    // Retrieve any existing errors from the form state.
    $errors = $form_state->getErrors();

    // If there are errors, return the element without further processing.
    if ($errors) {
      return $element;
    }

    /** @var \Drupal\phonenumber_verification\PhoneVerifierInterface $verifier */
    $verifier = \Drupal::service('phonenumber_verification.verifier');
    $settings = $element['#phone'];
    // Determine the current operation (e.g., send verification, verify).
    $op = static::getOp($element, $form_state);
    $field_label = !empty($element['#field_title']) ? $element['#field_title'] : $element['#title'];
    $tree_parents = $element['#parents'];
    $field_path = implode('][', $tree_parents);
    // Retrieve the user input for the current element.
    $input = NestedArray::getValue($form_state->getUserInput(), $tree_parents);
    $input = $input ? $input : [];
    $phone_number = NULL;
    $token = !empty($element['#value']['verification_token']) ? $element['#value']['verification_token'] : FALSE;
    // Queue settings for verification and SMS attempts.
    $queue = [
      'verify_interval' => $settings['verify_interval'],
      'verify_count' => $settings['verify_count'],
      'sms_interval' => $settings['sms_interval'],
      'sms_count' => $settings['sms_count'],
    ];

    if ($input) {
      /** @var \Drupal\phonenumber_validation\PhoneValidatorInterface $validator */
      $validator = \Drupal::service('phonenumber_validation.validator');

      // Check the phone number and verification settings.
      try {
        $phone_number = $validator->checkPhoneNumber($input['phone_number'], mb_strtoupper($input['country_iso2']));
        $verified = static::isVerified($element);

        // Handle the "send verification" operation.
        if ($op == 'send_verification' && !$verifier->checkFlood($phone_number, 'sms', $queue)) {
          $form_state->setError($element['phone'], t('Too many verification code requests for %field, please try again shortly.', [
            '%field' => $field_label,
          ]));
        }
        // Attempt to send the verification code.
        elseif ($op == 'send_verification' && !$verified && !($token = $verifier->sendVerification($phone_number, $settings['message'], $verifier->generateVerificationCode($settings['length']), $settings['token_data']))) {
          $form_state->setError($element['phone'], t('An error occurred while sending SMS.'));
        }
        // Handle the "verify" operation.
        elseif ($op == 'verify' && !$verified && $verifier->checkFlood($phone_number, 'verification', $queue)) {
          $verification_parents = $element['#array_parents'];
          $verification_element = NestedArray::getValue($complete_form, $verification_parents);
          NestedArray::setValue($complete_form, $verification_parents, $verification_element);
        }
      }
      catch (PhoneNumberException $e) {
        // Errors are already set for this situation in parent::validatePhone().
      }
    }

    // Store the verification token if available.
    if (!empty($token)) {
      $storage = $form_state->getStorage();
      $storage['phone_fields'][$field_path]['token'] = $token;
      $form_state->setStorage($storage);
    }

    return $element;
  }

  /**
   * Phone number verification ajax callback.
   *
   * @param array $complete_form
   *   The complete form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public static function verifyAjax(array $complete_form, FormStateInterface $form_state) {
    // Retrieve the phone verifier service.
    /** @var \Drupal\phonenumber_verification\PhoneVerifierInterface $verifier */
    $verifier = \Drupal::service('phonenumber_verification.verifier');

    // Get the parent element of the triggering element.
    $element = static::getTriggeringElementParent($complete_form, $form_state);
    $tree_parents = $element['#parents'];
    $triggering_element = $form_state->getTriggeringElement();
    // Get the data-drupal-selector attribute for AJAX commands.
    $op_selector = $triggering_element['#attributes']['data-drupal-selector'];
    $field_path = implode('][', $tree_parents);
    // Retrieve the form state storage.
    $storage = $form_state->getStorage();
    // Retrieve the verification token from storage.
    $token = !empty($storage['phone_fields'][$field_path]['token']) ? $storage['phone_fields'][$field_path]['token'] : NULL;
    // Set the verification token in the element.
    $element['verification_token']['#value'] = $token;
    $settings = $element['#phone'];
    // Queue settings for verification and SMS attempts.
    $queue = [
      'verify_interval' => $settings['verify_interval'],
      'verify_count' => $settings['verify_count'],
      'sms_interval' => $settings['sms_interval'],
      'sms_count' => $settings['sms_count'],
    ];
    // Determine the current operation (e.g., send verification, verify).
    $op = static::getOp($element, $form_state);

    // Retrieve any existing errors from the form state.
    $errors = $form_state->getErrors();

    // Add error messages to the Drupal messenger.
    foreach ($errors as $path => $message) {
      if (strpos($path, implode('][', $element['#parents'])) === 0) {
        \Drupal::messenger()->addError($message);
      }
      else {
        unset($errors[$path]);
      }
    }

    // Retrieve the phone number from the element.
    $phone_number = static::getPhoneNumber($element);
    $verified = FALSE;
    $verify_prompt = FALSE;
    if ($phone_number) {
      // Check if the phone number is verified.
      $verified = static::isVerified($element);
      // Check if the verification flood limit is okay.
      $verify_flood_ok = $verified || ($verifier->checkFlood($phone_number, 'verification', $queue));

      if ($verify_flood_ok) {
        // If the operation is to send verification and there
        // are no errors, set the verify prompt.
        if (!$verified && !$errors && ($op == 'send_verification')) {
          $verify_prompt = TRUE;
        }
        // If the operation is to verify, set the verify prompt.
        elseif (!$verified && ($op == 'verify')) {
          $verify_prompt = TRUE;
        }
      }
    }

    // Retrieve the renderer service.
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');

    // Render status messages.
    $status_messages = ['#type' => 'status_messages'];
    $element['#prefix'] .= $renderer->renderRoot($status_messages);

    // Render the element.
    $output = $renderer->renderRoot($element);
    unset($element['_weight']);

    // Create an AJAX response.
    $response = new AjaxResponse();
    // Set the AJAX response attachments.
    $response->setAttachments($element['#attached']);
    // Replace the element with the new output.
    $response->addCommand(new ReplaceCommand(NULL, $output));
    // Trigger a change event on the element.
    $response->addCommand(new InvokeCommand("[data-drupal-selector=\"$op_selector\"]", 'trigger', ['change']));

    $settings = [];

    // Set the appropriate settings based on the verification status.
    if ($verify_prompt) {
      $settings['phoneNumberVerificationPrompt'] = $element['#id'];
    }
    else {
      $settings['phoneNumberHideVerificationPrompt'] = $element['#id'];
    }

    if ($verified) {
      $settings['phoneNumberVerified'] = $element['#id'];
    }

    if (!empty($settings)) {
      $response->addCommand(new SettingsCommand($settings, TRUE), TRUE);
    }

    return $response;
  }

  /**
   * Get form operation name based on the button pressed in the form.
   *
   * @param array $element
   *   SMS Phone Number element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return null|string
   *   Operation name, or null if the button does not belong to the element.
   */
  public static function getOp(array $element, FormStateInterface $form_state) {
    // Retrieve the triggering element from the form state.
    $triggering_element = $form_state->getTriggeringElement();

    // Get the operation (op) from the triggering element, if available.
    $op = !empty($triggering_element['#op']) ? $triggering_element['#op'] : NULL;
    // Get the name of the button that triggered the form submission.
    $button = !empty($triggering_element['#name']) ? $triggering_element['#name'] : NULL;

    // Check if the button name matches any of the expected button names.
    // The expected button names are constructed using the element's parents.
    if (!in_array($button, [
      implode('__', $element['#parents']) . '__send_verification',
      implode('__', $element['#parents']) . '__verify',
    ])) {
      // If the button name does not match, set the operation to NULL.
      $op = NULL;
    }

    return $op;
  }

  /**
   * Get SMS Phone Number element from the currently pressed form button.
   *
   * @param array $complete_form
   *   The complete form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return mixed
   *   The SMS Phone Number form element.
   */
  public static function getTriggeringElementParent(array $complete_form, FormStateInterface $form_state) {
    // Retrieve the triggering element from the form state.
    $triggering_element = $form_state->getTriggeringElement();
    // Get the array of parent elements for the triggering element.
    $parents = $triggering_element['#array_parents'];
    // Remove the last parent from the array (the triggering element itself).
    array_pop($parents);
    // Retrieve the parent element from the complete
    // form using the modified array of parents.
    return NestedArray::getValue($complete_form, $parents);
  }

  /**
   * Gets the verified status of the phone number.
   *
   * Based on default value and verified numbers in the session.
   *
   * @param array $element
   *   The form element.
   *
   * @return bool
   *   TRUE if verified, FALSE otherwise.
   */
  public static function isVerified(array $element) {
    // Retrieve the phone verifier service.
    /** @var \Drupal\phonenumber_verification\PhoneVerifierInterface $verifier */
    $verifier = \Drupal::service('phonenumber_verification.verifier');

    // Retrieve the phone validator service.
    /** @var \Drupal\phonenumber_validation\PhoneValidatorInterface $validator */
    $validator = \Drupal::service('phonenumber_validation.validator');

    // Get the phone number from the element.
    $phone_number = static::getPhoneNumber($element);
    // Get the default phone number from the element.
    $default_phone_number = static::getPhoneNumber($element, FALSE);
    $verified = FALSE;

    if ($phone_number) {
      // Check if the default phone number matches the current
      // phone number and is marked as verified.
      $verified = ($default_phone_number ? $validator->getCallableNumber($default_phone_number) == $validator->getCallableNumber($phone_number) : FALSE) && $element['#default_value']['verified'];
      // Check if the phone number is verified in the session.
      $verified = $verified || $verifier->isVerified($phone_number);
    }

    return $verified;
  }

}
