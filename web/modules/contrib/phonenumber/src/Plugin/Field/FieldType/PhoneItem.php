<?php

namespace Drupal\phonenumber\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'phone' field type.
 *
 * @FieldType(
 *   id = "phone",
 *   label = @Translation("Phone number"),
 *   description = @Translation("This field stores and parses the phone number in international format."),
 *   default_widget = "phone_default",
 *   default_formatter = "phone_international",
 *   constraints = {
 *     "Phone" = {}
 *   }
 * )
 */
class PhoneItem extends FieldItemBase {

  /**
   * The maximum length for a phone value.
   */
  const MAX_LENGTH = 16;

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'unique' => FALSE,
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'strict_mode' => TRUE,
      'national_mode' => TRUE,
      'allowed' => 'all',
      'countries' => [],
      'country_order' => [],
      'geo_ip_lookup' => 'ipapi',
      'api_key' => '',
      'validation_number_type' => 'MOBILE',
      'placeholder_number_type' => 'MOBILE',
      'auto_placeholder' => 'polite',
      'custom_placeholder' => '',
      'container_class' => '',
      'extension_field' => FALSE,
      'enabled_localisation' => FALSE,
      'localized_countries' => [],
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'phone_number' => [
          'type' => 'varchar',
          'length' => self::MAX_LENGTH,
          'description' => "The phone international number.",
        ],
        'local_number' => [
          'type' => 'varchar',
          'length' => 18,
          'description' => "The phone local number.",
        ],
        'country_code' => [
          'type' => 'varchar',
          'length' => 3,
          'description' => "The phone country code.",
        ],
        'country_iso2' => [
          'type' => 'varchar',
          'length' => 2,
          'description' => "The phone country ISO alpha-2.",
        ],
        'extension' => [
          'type' => 'varchar',
          'length' => 40,
          'default' => NULL,
          'description' => "The phone number extension.",
        ],
      ],
      'indexes' => [
        'value' => ['phone_number'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['phone_number'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('International phone number'))
      ->addConstraint('Length', ['max' => self::MAX_LENGTH])
      ->setRequired(FALSE);

    $properties['local_number'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Local number'))
      ->addConstraint('Length', ['max' => 24])
      ->setRequired(FALSE);

    $properties['country_code'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Country dial code'))
      ->addConstraint('Length', ['max' => 3])
      ->setRequired(FALSE);

    $properties['country_iso2'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Country ISO alpha-2'))
      ->addConstraint('Length', ['max' => 2])
      ->setRequired(FALSE);

    $properties['extension'] = DataDefinition::create('string')
      ->setLabel(t('Extension'))
      ->addConstraint('Length', ['max' => 40])
      ->setRequired(FALSE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('phone_number')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * Determines if the phone number is unique within the entity/field.
   *
   * @return bool|null
   *   TRUE if the phone number is unique, FALSE otherwise.
   *   NULL if the phone number is not valid.
   */
  public function isUnique() {
    $entity = $this->getEntity();
    $field_name = $this->getFieldDefinition()->getName();

    $values = $this->getValue();
    $number = $values['phone_number'];
    if (!$number) {
      return NULL;
    }
    $entity_type_id = $entity->getEntityTypeId();
    $id_key = $entity->getEntityType()->getKey('id');
    $query = \Drupal::entityQuery($entity_type_id)
      // The id could be NULL, so we cast it to 0 in that case.
      ->condition($id_key, (int) $entity->id(), '<>')
      ->accessCheck(TRUE)
      ->condition($field_name . '.phone_number', $number)
      ->range(0, 1)
      ->count();

    return !(bool) $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $settings = $this->getSettings();

    $element['unique'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Unique number'),
      '#default_value' => $settings['unique'],
      '#description' => $this->t('Ensure that phone numbers are unique within this field.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    // Get base form from FileItem.
    $element = parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getSettings();

    /** @var \Drupal\Core\Locale\CountryManagerInterface $country_manager */
    $country_manager = \Drupal::service('country_manager');
    $countries = $country_manager->getList();

    $element['strict_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Strict mode'),
      '#description' => $this->t('Enable to enforce input to only numeric characters and an optional leading plus sign. All other characters will be ignored, and the input length will be capped at the maximum valid number length.'),
      '#default_value' => $settings['strict_mode'],
    ];

    $element['national_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('National mode'),
      '#description' => $this->t('Disable national mode if you want to enter the phone number with the country code instead of displaying the country flag. See "Show flag" and "Allow dropdown" in manage form display.'),
      '#default_value' => $settings['national_mode'],
    ];
    $element['national_format'] = [
      '#type' => 'item',
      '#description' => $this->t('Format numbers in the national format (without country code), rather than the international format (with country code) when the country can be selected. This applies to placeholder numbers and when displaying existing numbers. It is recommended to leave this option enabled to encourage users to enter numbers in the national format as this is usually more familiar and creates a better user experience.'),
      '#states' => [
        'visible' => [
          ':input[name="settings[national_mode]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $element['allowed'] = [
      '#type' => 'radios',
      '#title' => $this->t('Allowed countries'),
      '#options' => [
        'all' => $this->t('All countries'),
        'include' => $this->t('Only the selected countries'),
        'exclude' => $this->t('Exclude the selected countries'),
      ],
      '#default_value' => $settings['allowed'],
    ];

    $element['countries'] = [
      '#type' => 'select',
      '#title' => $this->t('Countries'),
      '#title_display' => 'invisible',
      '#options' => $countries,
      '#multiple' => TRUE,
      '#default_value' => $settings['countries'],
      '#states' => [
        'invisible' => [
          '[name="settings[allowed]"]' => ['value' => 'all'],
        ],
      ],
    ];

    $geolocation_api = phonenumber_geo_ip_lookup_services();
    $geolocation_titles = array_column($geolocation_api, 'title');
    $geo_ip_lookup = array_combine(array_keys($geolocation_api), $geolocation_titles);
    $element['geo_ip_lookup'] = [
      '#type' => 'select',
      '#title' => $this->t('IP Geolocation API service'),
      '#options' => $geo_ip_lookup,
      '#default_value' => $settings['geo_ip_lookup'],
      '#description' => $this->t("When setting <strong>Default country</strong> to <em>auto</em>, you must use this option to specify an IP Geolocation API service that looks up the user's location, and then callback with the relevant country code. Leave it to default if you want to set the default country to a specific country."),
    ];

    $signup = array_column($geolocation_api, 'signup');
    $api_key_services = array_filter(array_combine(array_keys($geolocation_api), $signup));
    $api_key_states = [];
    foreach ($api_key_services as $service => $value) {
      $api_key_states['visible'][]['[name="settings[geo_ip_lookup]"]'] = ['value' => $service];
      $api_key_states['required'][]['[name="settings[geo_ip_lookup]"]'] = ['value' => $service];
    }
    $element['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#default_value' => $this->getSetting('api_key'),
      '#description' => $this->t('Additional classes to add to the phone parent div. Separate each class with a space.'),
      '#states' => $api_key_states,
    ];

    $element['validation_number_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Validation number type'),
      '#default_value' => $this->getSetting('validation_number_type'),
      '#description' => $this->t('Specify the type of phone number for validation. This ensures the entered number matches the expected format and length. Set to "None" to allow any type.'),
      '#options' => phonenumber_number_types(),
    ];

    $element['placeholder_number_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Placeholder number type'),
      '#default_value' => $this->getSetting('placeholder_number_type'),
      '#description' => $this->t('Specify the type of number to be used as a placeholder. This will apply a mask and format based on the country international phone number pattern.'),
      '#options' => phonenumber_number_types(),
    ];

    $element['custom_placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom placeholder'),
      '#default_value' => $this->getSetting('custom_placeholder'),
      '#description' => $this->t('Leave blank to display sample numbers for each country. Text that will be shown inside the field until a value is entered. If set, this value or description will be displayed for all countries.'),
    ];

    $element['container_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom container class'),
      '#default_value' => $this->getSetting('container_class'),
      '#description' => $this->t('Additional classes to add to the phone parent div. Separate each class with a space.'),
    ];

    $element['extension_field'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable extension field'),
      '#description' => $this->t('Collect extension along with the phone number.'),
      '#default_value' => $settings['extension_field'],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();

    $constraints = parent::getConstraints();

    $constraints[] = $constraint_manager->create('ComplexData', [
      'phone_number' => [
        'Length' => [
          'max' => self::MAX_LENGTH,
          'maxMessage' => $this->t('%name: the phone number may not be longer than @max characters.', [
            '%name' => $this->getFieldDefinition()->getLabel(),
            '@max' => self::MAX_LENGTH,
          ]),
        ],
      ],
    ]);

    return $constraints;
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
    $values = $this->getValue();
    $number = '';
    if (isset($values['phone_number'])) {
      $number = $values['phone_number'];
    }

    return $number;
  }

  /**
   * Get country ISO alpha-2 of the current item.
   *
   * @return string
   *   Country ISO alpha-2 string, or null.
   */
  public function getCountryIso2() {
    $values = $this->getValue();

    $country_iso2 = NULL;
    if (!empty($values['country_iso2'])) {
      $country_iso2 = $values['country_iso2'];
    }

    return $country_iso2;
  }

  /**
   * Get country code of the current item.
   *
   * @return string
   *   Country code string, or null.
   */
  public function getCountryCode() {
    $values = $this->getValue();

    $country_code = NULL;
    if (!empty($values['country_code'])) {
      $country_code = $values['country_code'];
    }

    return $country_code;
  }

  /**
   * Get country name of the current item.
   *
   * @return string
   *   Country name string, or null.
   */
  public function getCountryName() {
    $values = $this->getValue();

    $country_name = NULL;
    if (!empty($values['country_iso2'])) {
      /** @var \Drupal\Core\Locale\CountryManagerInterface $country_manager */
      $country_manager = \Drupal::service('country_manager');
      $countries = $country_manager->getList();
      $country_name = $countries[mb_strtoupper($values['country_iso2'])];
    }

    return $country_name;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    $values = $this->getValue();
    $required = $this->getFieldDefinition()->isRequired();
    if (!$required && isset($values['local_number']) && empty(trim($values['local_number']))) {
      $this->phone_number = '';
      $this->country_code = '';
      $this->country_iso2 = '';
    }

    parent::preSave();
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values['phone_number'] = rand(pow(10, 8), pow(10, 9) - 1);
    return $values;
  }

}
