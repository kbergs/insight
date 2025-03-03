<?php

namespace Drupal\phonenumber_verification\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\phonenumber\Plugin\Field\FieldType\PhoneItem;
use Drupal\phonenumber_verification\PhoneVerifierInterface;
use Drupal\user\Entity\User;

/**
 * Plugin implementation of the 'phone_verification' field type.
 *
 * @FieldType(
 *    id = "phone",
 *    label = @Translation("Phone number"),
 *    description = @Translation("This field stores and parses the phone number in international format, local number, country code and iso, verified status, and tfa option for verification phone numbers."),
 *    default_widget = "phone_default",
 *    default_formatter = "phone_international",
 *    constraints = {
 *      "PhoneVerification" = {}
 *    }
 *  )
 */
class PhoneVerificationItem extends PhoneItem {

  /**
   * Indicates whether the phone number is verified.
   *
   * @var bool
   */
  private bool $verified;

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'verified' => FALSE,
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    /** @var \Drupal\phonenumber_verification\PhoneVerifierInterface $verifier */
    $verifier = \Drupal::service('phonenumber_verification.verifier');
    return [
      'verify' => $verifier->isSmsEnabled() ? $verifier::PHONE_NUMBER_VERIFY_OPTIONAL : PhoneVerifierInterface::PHONE_NUMBER_VERIFY_NONE,
      'message' => $verifier::PHONE_NUMBER_DEFAULT_SMS_MESSAGE,
      'length' => $verifier::VERIFICATION_CODE_LENGTH,
      'verify_interval' => $verifier::VERIFY_ATTEMPTS_INTERVAL,
      'verify_count' => $verifier::VERIFY_ATTEMPTS_COUNT,
      'sms_interval' => $verifier::SMS_ATTEMPTS_INTERVAL,
      'sms_count' => $verifier::SMS_ATTEMPTS_COUNT,
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    // Define schema for the 'verified' column.
    $schema['columns']['verified'] = [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 0,
      'description' => "The phone verified status.",
    ];

    // Define schema for the 'tfa' column.
    $schema['columns']['tfa'] = [
      'type' => 'int',
      'not null' => TRUE,
      'default' => 0,
      'description' => "The phone TFA option.",
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    // Define property for 'verified' status.
    $properties['verified'] = DataDefinition::create('boolean')
      ->setLabel(t('Verified status'))
      ->setRequired(FALSE);

    // Define property for 'tfa' option.
    $properties['tfa'] = DataDefinition::create('boolean')
      ->setLabel(t('TFA option'))
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    // Get the verifier service.
    $values = $this->getValue();
    $number = NULL;
    $country = NULL;

    // Check if country ISO code is provided.
    if (!empty($values['country_iso2'])) {
      if (!empty($values['local_number'])) {
        $number = $values['local_number'];
      }
      $country = mb_strtoupper($values['country_iso2']);
    }

    // If no local number, use phone number.
    if (!$number) {
      $number = $values['phone_number'];
    }

    // Get the phone number validator service.
    /** @var \Drupal\phonenumber_validation\PhoneValidatorInterface $validator */
    $validator = \Drupal::service('phonenumber_validation.validator');
    if ($phonenumber_verification = $validator->getPhoneNumber($number, $country)) {
      $this->phone_number = $validator->getCallableNumber($phonenumber_verification);
      $this->country_code = $validator->getCountryCode($phonenumber_verification);
      $this->country_iso2 = $validator->getCountry($phonenumber_verification);
      $this->tfa = !empty($values['tfa']) ? 1 : 0;
      if (isset($values['verified'])) {
        // Set verified status if provided.
        $this->verified = (bool) $values['verified'];
      }
      elseif ($this->verify() === TRUE) {
        $this->verified = TRUE;
      }
      else {
        $this->verified = FALSE;
      }
    }
    else {
      $this->phone_number = NULL;
      $this->local_number = NULL;
    }

    parent::preSave();
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $settings = $this->getSettings();
    $element = parent::storageSettingsForm($form, $form_state, $has_data);

    // Add form element for verified status.
    $element['verified'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Verified number'),
      '#default_value' => $settings['verified'],
      '#description' => $this->t('Check this box if the phone numbers should be verified within this field.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::fieldSettingsForm($form, $form_state);

    // Get the verifier service.
    /** @var \Drupal\phonenumber_verification\PhoneVerifierInterface $verifier */
    $verifier = \Drupal::service('phonenumber_verification.verifier');
    $field = $this->getFieldDefinition();
    $settings = $this->getSettings() + $this->defaultFieldSettings();

    // Add form element for TFA option if applicable.
    if ($form['#entity'] instanceof User && FALSE) {
      $element['tfa'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use this field for two-factor authentication'),
        '#description' => $this->t("If enabled, users will be able to choose whether to use the number for two-factor authentication. Only one field can be set true for this value, verification must be enabled, and the field must have a cardinality of 1. Users are required to verify their number when enabling their two-factor authentication. <a href='https://www.drupal.org/project/tfa' target='_blank'>Two Factor Authentication</a> must be installed, as well as a supported SMS provider such as <a href='https://www.drupal.org/project/smsframework' target='_blank'>SMS Framework</a>."),
        '#default_value' => $this->tfaAllowed() && $verifier->getTfaField() === $this->getFieldDefinition()->getName(),
        '#disabled' => !$this->tfaAllowed(),
      ];

      if ($this->tfaAllowed()) {
        $element['tfa']['#states'] = [
          'disabled' => ['input[name="settings[verify]"]' => ['value' => $verifier::PHONE_NUMBER_VERIFY_NONE]],
        ];
      }
    }

    // Add form element for verification requirement.
    $element['verify'] = [
      '#type' => 'radios',
      '#title' => $this->t('Verification'),
      '#options' => [
        PhoneVerifierInterface::PHONE_NUMBER_VERIFY_NONE => $this->t('None'),
        PhoneVerifierInterface::PHONE_NUMBER_VERIFY_OPTIONAL => $this->t('Optional'),
        PhoneVerifierInterface::PHONE_NUMBER_VERIFY_REQUIRED => $this->t('Required'),
      ],
      '#default_value' => $settings['verify'],
      '#description' => $this->t('Verification requirement. This will send a verification code via SMS to the phone number when the user requests to verify the number as their own. Requires <a href="https://www.drupal.org/project/smsframework" target="_blank">SMS Framework</a> or any other SMS sending module that integrates with the SMS Phone Number module.'),
      '#required' => TRUE,
      '#disabled' => !$verifier->isSmsEnabled(),
    ];

    // Add form element for verification code length.
    $element['length'] = [
      '#type' => 'select',
      '#title' => $this->t('Verification code length'),
      '#options' => [
        '4' => $this->t('Four digits'),
        '6' => $this->t('Six digits'),
      ],
      '#default_value' => $settings['length'],
      '#description' => $this->t('Number of digits to generate in the verification code.'),
    ];

    // Add form element for verification message.
    $element['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Verification Message'),
      '#default_value' => $settings['message'],
      '#description' => $this->t('The SMS message to send during verification. Replacement parameters are available for the verification code (!code) and site name (!site_name). Additionally, tokens are available if the token module is enabled. Be aware that entity values will not be available on entity creation forms as the entity has not been created yet.'),
      '#token_types' => [$field->getTargetEntityTypeId()],
      '#disabled' => !$verifier->isSmsEnabled(),
      '#element_validate' => [
        [
          $this,
          'fieldSettingsFormValidate',
        ],
      ],
      '#states' => [
        'invisible' => [
          '[name="settings[verify]"]' => ['value' => 'none'],
        ],
        'required' => [
          '[name="settings[verify]"]' => ['!value' => 'none'],
        ],
      ],
    ];

    // Add form elements for verification queue settings.
    $element['verification_queue'] = [
      '#type' => 'details',
      '#title' => $this->t('Verification queue'),
      '#open' => TRUE,
    ];

    $element['verification_queue']['verify_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Verify attempts interval'),
      '#description' => $this->t('The number of seconds after receiving the verification code before it can be resent.'),
      '#field_suffix' => $this->t('seconds'),
      '#default_value' => $settings['verify_interval'],
      '#min' => 0,
    ];

    $element['verification_queue']['verify_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Verify attempts count'),
      '#description' => $this->t('The number of times the verification code can be resent. Use -1 for no limit.'),
      '#default_value' => $settings['verify_count'],
      '#min' => -1,
    ];

    $element['verification_queue']['sms_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('SMS attempts interval'),
      '#description' => $this->t('The number of seconds after receiving the SMS before it can be resent.'),
      '#field_suffix' => $this->t('seconds'),
      '#default_value' => $settings['sms_interval'],
      '#min' => 0,
    ];

    $element['verification_queue']['sms_count'] = [
      '#type' => 'number',
      '#title' => $this->t('SMS attempts count'),
      '#description' => $this->t('The number of times the SMS can be resent. Use -1 for no limit.'),
      '#default_value' => $settings['sms_count'],
      '#min' => -1,
    ];

    // Add token validation if the token module is enabled.
    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $element['message']['#element_validate'] = ['token_element_validate'];
      $element['message_token_tree']['token_tree'] = [
        '#theme' => 'token_tree',
        '#token_types' => [$field->getTargetEntityTypeId()],
        '#dialog' => TRUE,
      ];
    }

    return $element;
  }

  /**
   * Validate callback for Phone field item.
   *
   * @param array $form
   *   Complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function fieldSettingsFormValidate(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (isset($values['settings']['verification_queue'])) {
      $settings = $values['settings'];
      $queue = $values['settings']['verification_queue'];
      $settings = array_merge($settings, $queue);
      unset($settings['verification_queue']);
      $form_state->setValue('settings', $settings);
    }
    $submit_handlers = $form_state->getSubmitHandlers();
    $submit_handlers[] = [
      $this,
      'fieldSettingsFormSubmit',
    ];
    $form_state->setSubmitHandlers($submit_handlers);
  }

  /**
   * Submit callback for phone field item verification.
   *
   * @param array $form
   *   Complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function fieldSettingsFormSubmit(array $form, FormStateInterface $form_state) {
    // Get the verifier service.
    /** @var \Drupal\phonenumber_verification\PhoneVerifierInterface $verifier */
    $verifier = \Drupal::service('phonenumber_verification.verifier');
    $settings = $this->getSettings();
    if (!empty($settings['message'])) {
      $this->t('@message', ['@message' => $settings['message']]);
    }

    $tfa = !empty($this->getSetting('tfa'));
    $field_name = $this->getFieldDefinition()->getName();
    if (!empty($tfa)) {
      $verifier->setTfaField($field_name);
    }
    elseif ($field_name === $verifier->getTfaField()) {
      $verifier->setTfaField('');
    }
  }

  /**
   * Checks if tfa is allowed based on tfa module status and field cardinality.
   *
   * @return bool
   *   True or false.
   */
  public function tfaAllowed() {
    // Get the verifier service.
    /** @var \Drupal\phonenumber_verification\PhoneVerifierInterface $verifier */
    $verifier = \Drupal::service('phonenumber_verification.verifier');
    return $verifier->isTfaEnabled() && ($this->getFieldDefinition()->getFieldStorageDefinition()->getCardinality() == 1);
  }

  /**
   * Is the item's phone number verified.
   *
   * Looks at the field's saved values or current session.
   *
   * @return bool
   *   TRUE if verified, else FALSE.
   */
  public function isVerified() {
    // Get the verifier service.
    /** @var \Drupal\phonenumber_verification\PhoneVerifierInterface $verifier */
    $verifier = \Drupal::service('phonenumber_verification.verifier');

    // Get the entity and its field definitions.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntity();
    $entity_type_id = $entity->getEntityTypeId();
    $id_key = $entity->getEntityType()->getKey('id');

    $field_name = $this->getFieldDefinition()->getName();
    $phonenumber_verification = $this->getPhoneNumber();

    if (!$phonenumber_verification) {
      return FALSE;
    }

    // Check if the phone number is verified in the database.
    /** @var \Drupal\phonenumber_validation\PhoneValidatorInterface $validator */
    $validator = \Drupal::service('phonenumber_validation.validator');
    $verified = (bool) \Drupal::entityQuery($entity_type_id)
      ->condition($id_key, (int) $entity->id())
      ->accessCheck(TRUE)
      ->condition("$field_name.phone_number", $validator->getCallableNumber($phonenumber_verification))
      ->range(0, 1)
      ->condition("$field_name.verified", "1")
      ->count()
      ->execute();

    // Also check if the phone number is verified in the current session.
    $verified = $verified || $verifier->isVerified($phonenumber_verification);

    return $verified;
  }

  /**
   * Performs verification, assuming verification token and code were set.
   *
   * Adds to flood if failed. Will not attempt to verify if number is already
   * verified.
   *
   * @return bool|int|null
   *   TRUE if verification is successful, FALSE if wrong code provided,
   *   NULL if code or token not provided, -1 if it doesn't pass flood check.
   */
  public function verify() {
    // Get the verifier service.
    /** @var \Drupal\phonenumber_verification\PhoneVerifierInterface $verifier */
    $verifier = \Drupal::service('phonenumber_verification.verifier');
    $values = $this->getValue();

    $settings = $this->getSettings();
    $queue = [
      'verify_interval' => $settings['verify_interval'],
      'verify_count' => $settings['verify_count'],
      'sms_interval' => $settings['sms_interval'],
      'sms_count' => $settings['sms_count'],
    ];

    // Get the token and code from the field values.
    $token = !empty($values['verification_token']) ? $values['verification_token'] : NULL;
    $code = !empty($values['verification_code']) ? $values['verification_code'] : NULL;

    // Check if the phone number is already verified.
    if ($this->isVerified()) {
      return TRUE;
    }

    $phonenumber_verification = $this->getPhoneNumber();

    // Validate the verification token and code.
    if (!empty($token) && !empty($code) && $phonenumber_verification) {
      if ($verifier->checkFlood($phonenumber_verification, 'verification', $queue)) {
        return $verifier->verifyCode($phonenumber_verification, $code, $token);
      }
      else {
        return -1;
      }
    }
    else {
      return NULL;
    }
  }

  /**
   * Is sms_phone number unique within the entity/field.
   *
   * Will check against verified numbers only, if specificed.
   *
   * @param int $unique_type
   *   Unique type [PHONE_NUMBER_UNIQUE_YES|PHONE_NUMBER_UNIQUE_YES_VERIFIED].
   *
   * @return bool|null
   *   TRUE for is unique, FALSE otherwise. NULL if phone number is not valid.
   */
  public function isUniqueVerify($unique_type = PhoneVerifierInterface::PHONE_NUMBER_UNIQUE_YES) {
    // Get the verifier service.
    /** @var \Drupal\phonenumber_verification\PhoneVerifierInterface $verifier */
    $verifier = \Drupal::service('phonenumber_verification.verifier');

    // Get the entity and its field definitions.
    $entity = $this->getEntity();
    $field_name = $this->getFieldDefinition()->getName();

    if (!$phonenumber_verification = $this->getPhoneNumber()) {
      return NULL;
    }
    $entity_type_id = $entity->getEntityTypeId();
    $id_key = $entity->getEntityType()->getKey('id');
    // Create an entity query to check for unique phone numbers.
    $query = \Drupal::entityQuery($entity_type_id)
      // The id could be NULL, so we cast it to 0 in that case.
      ->condition($id_key, (int) $entity->id(), '<>')
      ->accessCheck(TRUE)
      ->condition($field_name, $verifier->getCallableNumber($phonenumber_verification))
      ->range(0, 1)
      ->count();

    // Check if the phone number should be unique among verified numbers only.
    if ($unique_type == PhoneVerifierInterface::PHONE_NUMBER_UNIQUE_YES_VERIFIED) {
      $query->condition("$field_name.verified", "1");
      if ($this->isVerified()) {
        $result = !(bool) $query->execute();
      }
      else {
        $result = TRUE;
      }
    }
    else {
      $result = !(bool) $query->execute();
    }

    return $result;
  }

  /**
   * Get phone number object of the current item.
   *
   * @param bool $throw_exception
   *   Whether to throw phone number validity exceptions.
   *
   * @return \libphonenumber\PhoneNumber|null
   *   Phone number object, or null if not valid.
   */
  public function getPhoneNumber($throw_exception = FALSE) {
    // Get the phone number validator service.
    /** @var \Drupal\phonenumber_validation\PhoneValidatorInterface $validator */
    $validator = \Drupal::service('phonenumber_validation.validator');
    $values = $this->getValue();
    $number = '';
    $country = NULL;
    $extension = NULL;
    if (!empty($values['country_iso2'])) {
      if (!empty($values['local_number'])) {
        $number = $values['local_number'];
      }
      $country = mb_strtoupper($values['country_iso2']);
    }

    if (!$number && !empty($values['phone_number'])) {
      $number = $values['phone_number'];
    }

    if (!empty($values['extension'])) {
      $extension = $values['extension'];
    }

    // Check and return the phone number.
    if ($throw_exception) {
      return $validator->checkPhoneNumber($number, $country, $extension);
    }
    else {
      return $validator->getPhoneNumber($number, $country, $extension);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $value = parent::generateSampleValue($field_definition);

    if (!empty($value)) {
      // Get the verifier service.
      /** @var \Drupal\phonenumber_verification\PhoneVerifierInterface $verifier */
      $verifier = \Drupal::service('phonenumber_verification.verifier');

      $settings = [
        'verify' => $verifier->isSmsEnabled() ? $verifier::PHONE_NUMBER_VERIFY_OPTIONAL : $verifier::PHONE_NUMBER_VERIFY_NONE,
      ];

      // Set the verified status based on verification requirement.
      switch ($settings['verify']) {
        case $verifier::PHONE_NUMBER_VERIFY_NONE:
          $value['verified'] = 0;
          break;

        case $verifier::PHONE_NUMBER_VERIFY_OPTIONAL:
          $value['verified'] = rand(0, 1);
          break;

        case $verifier::PHONE_NUMBER_VERIFY_REQUIRED:
          $value['verified'] = 1;
          break;
      }
    }

    return $value;
  }

}
