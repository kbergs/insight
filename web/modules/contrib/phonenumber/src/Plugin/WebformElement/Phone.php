<?php

namespace Drupal\phonenumber\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\webform\Plugin\WebformElementBase;
use Drupal\webform\WebformSubmissionInterface;
use libphonenumber\PhoneNumberFormat;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'phone' webform element.
 *
 * @WebformElement(
 *   id = "phone",
 *   label = @Translation("Phone number"),
 *   description = @Translation("Provides a form element for input of a phone number."),
 *   category = @Translation("Advanced elements"),
 *   composite = TRUE,
 * )
 */
class Phone extends WebformElementBase {

  /**
   * The phone verification service.
   *
   * @var null|\Drupal\phonenumber_verification\PhoneVerifierInterface
   */
  protected $phoneVerifier;

  /**
   * The country manager.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected $countryManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    // Check if the phonenumber_verification module
    // exists and get the verifier service.
    $instance->phoneVerifier = ($instance->moduleHandler->moduleExists('phonenumber_verification'))
      ? $container->get('phonenumber_verification.verifier')
      : NULL;
    $instance->countryManager = $container->get('country_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultProperties() {
    $properties = [
      'strict_mode' => FALSE,
      'national_mode' => TRUE,
      'allowed' => 'all',
      'country_order' => [],
      'countries' => [],
      'geo_ip_lookup' => 'ipapi',
      'api_key' => '',
      'container_class' => '',
      'extension_field' => FALSE,
      // Form display.
      'initial_country' => 'auto',
      'preferred_countries' => ['US', 'GB'],
      'allow_dropdown' => TRUE,
      'fix_dropdown_width' => TRUE,
      'separate_dial_code' => FALSE,
      'format_as_you_type' => TRUE,
      'format_on_display' => TRUE,
      'show_flags' => TRUE,
      'country_search' => TRUE,
      'use_fullscreen_popup' => TRUE,
      'remove_start_zero' => TRUE,
      'mask_formatter' => TRUE,
      'show_error' => FALSE,
      'auto_placeholder' => 'polite',
      'placeholder_number_type' => 'MOBILE',
      'custom_placeholder' => '',
    ] + parent::defineDefaultProperties() + $this->defineDefaultMultipleProperties();

    // Add support for phonenumber_verification.module.
    if ($this->moduleHandler->moduleExists('phonenumber_verification')) {
      $properties += [
        'verify' => 'none',
        'message' => "Your verification code from !site_name:\n!code",
        'length' => '4',
        'verify_interval' => '60',
        'verify_count' => '5',
      ];
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // Call the parent form method.
    $form = parent::form($form, $form_state);

    // Get the list of countries.
    $countries = $this->countryManager->getList();

    // Define the phone settings fieldset.
    $form['phone'] = [
      '#type' => 'details',
      '#title' => $this->t('Phone number settings'),
      '#open' => TRUE,
    ];

    // Define the national mode checkbox.
    $form['phone']['national_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('National mode'),
      '#description' => $this->t('Enable national mode to format the phone number in the national format without the country code. Disable it to use the international format with the country code.'),
    ];

    // Define the national format item.
    $form['phone']['national_format'] = [
      '#type' => 'item',
      '#description' => $this->t('Format phone numbers in the national format (without the country code). This option is recommended for better user experience as national formats are usually more familiar to users.'),
      '#states' => [
        'visible' => [
          ':input[name="properties[national_mode]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    // Define the allowed countries radio buttons.
    $form['phone']['allowed'] = [
      '#type' => 'radios',
      '#title' => $this->t('Allowed countries'),
      '#options' => [
        'all' => $this->t('All countries'),
        'include' => $this->t('Only selected countries'),
        'exclude' => $this->t('Exclude selected countries'),
      ],
    ];

    // Define the countries select box.
    $form['phone']['countries'] = [
      '#type' => 'select',
      '#title' => $this->t('Countries'),
      '#title_display' => 'invisible',
      '#options' => $countries,
      '#multiple' => TRUE,
      '#states' => [
        'invisible' => [
          '[name="properties[allowed]"]' => ['value' => 'all'],
        ],
      ],
    ];

    // Get geolocation API services and configure geo IP lookup options.
    $geolocation_api = phonenumber_geo_ip_lookup_services();
    $geolocation_titles = array_column($geolocation_api, 'title');
    $geo_ip_lookup = array_combine(array_keys($geolocation_api), $geolocation_titles);

    $form['phone']['geo_ip_lookup'] = [
      '#type' => 'select',
      '#title' => $this->t('IP Geolocation API service'),
      '#options' => $geo_ip_lookup,
      '#description' => $this->t("Specify an IP Geolocation API service to determine the user's location based on their IP address. This is used to automatically set the default country."),
    ];

    // Define the API key text field.
    $signup = array_column($geolocation_api, 'signup');
    $api_key_services = array_filter(array_combine(array_keys($geolocation_api), $signup));
    $api_key_states = [];
    foreach ($api_key_services as $service => $value) {
      $api_key_states['visible'][]['[name="properties[geo_ip_lookup]"]'] = ['value' => $service];
      $api_key_states['required'][]['[name="properties[geo_ip_lookup]"]'] = ['value' => $service];
    }
    $form['phone']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#description' => $this->t('Enter the API key for the selected IP Geolocation API service.'),
      '#states' => $api_key_states,
    ];

    // Define the custom container class text field.
    $form['phone']['container_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom container class'),
      '#description' => $this->t("Enter additional classes to add to the phone field's parent container. Separate each class with a space."),
    ];

    // Define the enable extension field checkbox.
    $form['phone']['extension_field'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable extension field'),
      '#description' => $this->t('Enable this option to collect phone number extensions along with the main phone number.'),
    ];

    // Add support for phonenumber_verification.module.
    if ($this->moduleHandler->moduleExists('phonenumber_verification')) {
      // @todo Remove FALSE after port of TFA for drupal 8 is available.
      if ($form['#entity'] instanceof User && FALSE) {
        $form['phone']['tfa'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Use this field for two-factor authentication'),
          '#description' => $this->t('Enable this option to allow users to use this phone number for two-factor authentication.'),
          '#disabled' => !$this->tfaAllowed(),
        ];

        if ($this->tfaAllowed()) {
          $form['phone']['tfa']['#states'] = [
            'disabled' => ['input[name="settings[verify]"]' => ['value' => $this->phoneVerifier::PHONE_NUMBER_VERIFY_NONE]],
          ];
        }
      }

      $form['phone']['verify'] = [
        '#type' => 'radios',
        '#title' => $this->t('Verification'),
        '#options' => [
          $this->phoneVerifier::PHONE_NUMBER_VERIFY_NONE => $this->t('None'),
          $this->phoneVerifier::PHONE_NUMBER_VERIFY_OPTIONAL => $this->t('Optional'),
          $this->phoneVerifier::PHONE_NUMBER_VERIFY_REQUIRED => $this->t('Required'),
        ],
        '#description' => $this->t('Specify whether verification of the phone number is required, optional, or not required.'),
        '#required' => TRUE,
        '#disabled' => !$this->phoneVerifier->isSmsEnabled(),
      ];

      $form['phone']['length'] = [
        '#type' => 'select',
        '#title' => $this->t('Verification code length'),
        '#options' => [
          '4' => $this->t('Four digits'),
          '6' => $this->t('Six digits'),
        ],
        '#description' => $this->t('Select the length of the verification code sent via SMS.'),
      ];

      $form['phone']['message'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Verification message'),
        '#description' => $this->t('Enter the SMS message to be sent during verification. Use !code for the verification code and !site_name for the site name.'),
        '#disabled' => !$this->phoneVerifier->isSmsEnabled(),
        '#states' => [
          'invisible' => [
            '[name="properties[verify]"]' => ['value' => 'none'],
          ],
          'required' => [
            '[name="properties[verify]"]' => ['!value' => 'none'],
          ],
        ],
      ];

      $form['phone']['verification_queue'] = [
        '#type' => 'details',
        '#title' => $this->t('Verification queue'),
        '#open' => TRUE,
      ];

      $form['phone']['verification_queue']['verify_interval'] = [
        '#type' => 'number',
        '#title' => $this->t('Verification attempts interval'),
        '#description' => $this->t('Enter the number of seconds between verification attempts.'),
        '#field_suffix' => $this->t('seconds'),
        '#min' => 0,
      ];

      $form['phone']['verification_queue']['verify_count'] = [
        '#type' => 'number',
        '#title' => $this->t('Verification attempts count'),
        '#description' => $this->t('Enter the maximum number of verification attempts allowed. Use -1 for no limit.'),
        '#min' => -1,
      ];

      if ($this->moduleHandler->moduleExists('token')) {
        $form['phone']['message']['#element_validate'] = ['token_element_validate'];
        $form['phone']['message_token_tree']['token_tree'] = [
          '#theme' => 'token_tree',
          '#dialog' => TRUE,
        ];
      }
    }

    // Form display settings.
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $form_state->getFormObject()->getWebform();

    $allowed_countries = $countries;
    $preferred_countries = $webform->getSetting('preferred_countries');
    $initial_country = $webform->getSetting('initial_country');

    if ($webform->getSetting('allowed') != 'all' && is_array($webform->getSetting('countries')) && count($webform->getSetting('countries'))) {
      $countries = $webform->getSetting('countries');
      $allowed_countries = $webform->getSetting('allowed') == 'include' ? array_intersect_key($allowed_countries, $countries) : array_diff_key($allowed_countries, $countries);
      $preferred_countries = in_array($preferred_countries, $allowed_countries) ? $preferred_countries : array_diff_key($preferred_countries, $countries);
    }

    $initial_country = !in_array($initial_country, $allowed_countries) ? $initial_country : 'auto';
    $form['form']['display_container']['initial_country'] = [
      '#type' => 'select',
      '#title' => $this->t('Default country'),
      '#options' => ['auto' => $this->t("Automatically determine the user's country based on IP address")] + $allowed_countries,
      '#default_value' => $initial_country,
      '#description' => $this->t("Specify a default country for the phone number field. You can also set it to 'Auto' to automatically determine the user's country based on their IP address."),
    ];

    $form['form']['display_container']['preferred_countries'] = [
      '#type' => 'select',
      '#title' => $this->t('Top list countries'),
      '#multiple' => TRUE,
      '#options' => $allowed_countries,
      '#default_value' => $preferred_countries,
      '#description' => $this->t('Select the countries to appear at the top of the list. If none are selected, countries will be listed alphabetically.'),
    ];

    $form['form']['display_container']['allow_dropdown'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow dropdown'),
      '#description' => $this->t('Enable the dropdown to allow users to select the country code. Disable it to prevent the dropdown arrow and make the flag non-clickable.'),
    ];

    $form['form']['display_container']['fix_dropdown_width'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Fix dropdown width'),
      '#description' => $this->t('Fix the dropdown width to match the input width, rather than being as wide as the longest country name.'),
    ];

    $form['form']['display_container']['separate_dial_code'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Separate dial code'),
      '#description' => $this->t('Display the selected country dial code next to the flag.'),
    ];

    $form['form']['display_container']['show_flags'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show flags'),
      '#description' => $this->t('Display the country flags. Disable this option if you do not want to show flags for political reasons.'),
    ];

    $form['form']['display_container']['country_search'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Country search'),
      '#description' => $this->t('Add a search input to the top of the dropdown to allow users to filter the country list.'),
    ];

    $form['form']['display_container']['format_as_you_type'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Format as you type'),
      '#description' => $this->t('Automatically format the phone number as the user types. This feature will be disabled if the user enters their own formatting characters.'),
    ];

    $form['form']['display_container']['format_on_display'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Format on display'),
      '#description' => $this->t('Format the phone number input value according to the national mode option during initialization.'),
    ];

    $form['form']['display_container']['remove_start_zero'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove start zero'),
      '#description' => $this->t('Remove the leading zero from the input and placeholder for local numbers.'),
    ];

    $form['form']['display_container']['mask_formatter'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Mask formatter'),
      '#description' => $this->t("Enable masking and formatting based on the country's international phone number pattern. This forces users to enter the phone number according to the pattern and prevents the entry of letters and other special characters."),
    ];

    $form['form']['display_container']['show_error'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show inline error'),
      '#description' => $this->t('Enable inline form error display for the phone input and prevent form submission on the client side if the phone format is invalid.'),
    ];

    $form['form']['display_container']['placeholder_number_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Placeholder number type'),
      '#description' => $this->t('Select the type of number to be used for the placeholder.'),
      '#options' => phonenumber_number_types(),
    ];

    $form['form']['display_container']['custom_placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom placeholder'),
      '#description' => $this->t('Enter custom placeholder text to display inside the field until a value is entered. Leave blank to use sample numbers for each country.'),
    ];

    $form['form']['display_container']['toggle_theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Toggle theme'),
      '#options' => [
        'light' => $this->t('Light'),
        'dark' => $this->t('Dark'),
        'iphone' => $this->t('iPhone'),
        'modern' => $this->t('Modern'),
        'soft' => $this->t('Soft'),
      ],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Call the parent method to ensure basic validation.
    parent::validateConfigurationForm($form, $form_state);

    // Get the values of default country and
    // allowed countries from the form state.
    $default_country = $form_state->getValue('default_country');
    $allowed_countries = $form_state->getValue('allowed_countries');

    // Validate that the default country is within the allowed countries.
    if (!empty($allowed_countries) && !in_array($default_country, $allowed_countries)) {
      $form_state->setErrorByName('phone][default_country', $this->t('Default country is not in one of the allowed countries.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setDefaultValue(array &$element) {
    // Call the parent method to ensure basic default value setting.
    parent::setDefaultValue($element);

    // Define the default settings for the phone element.
    $settings = [
      'default_country' => !empty($element['#default_country']) ? $element['#default_country'] : 'US',
    ];

    // Handle different cases for the default value of the element.
    if (isset($element['#default_value']) && is_string($element['#default_value'])) {
      // If the default value is a string,
      // wrap it in an array with a 'country' key.
      $element['#default_value'] = [
        'country' => $element['#default_value'],
      ];
    }
    // Force set with settings, as NULL + an array doesn't result in an array.
    // If the value is an empty array, this is fine too.
    elseif (empty($element['#default_value'])) {
      // If the default value is empty, set it to the default country.
      $element['#default_value'] = [
        'country' => $settings['default_country'],
      ];
    }
    // The code doesn't seem to end up here, but keep this in case
    // '#default_value' isset, is not a string and assume it will be an array.
    else {
      // Ensure the default value has a country key with the default country.
      $element += [
        '#default_value' => [
          'country' => $settings['default_country'],
        ],
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$element, WebformSubmissionInterface $webform_submission = NULL) {
    // Call the parent prepare method.
    parent::prepare($element, $webform_submission);

    // Define settings for the phone element.
    $settings = [
      'allow_dropdown' => $element['#allow_dropdown'] ?? TRUE,
      'fix_dropdown_width' => $element['#fix_dropdown_width'] ?? TRUE,
      'auto_placeholder' => $element['#auto_placeholder'] ?? 'polite',
      'container_class' => $element['#container_class'] ?? '',
      'custom_placeholder' => $element['#custom_placeholder'] ?? NULL,
      'format_as_you_type' => $element['#format_as_you_type'] ?? TRUE,
      'format_on_display' => $element['#format_on_display'] ?? TRUE,
      'initial_country' => $element['#initial_country'] ?? 'auto',
      'preferred_countries' => $element['#preferred_countries'] ?? [],
      'country_order' => $element['#country_order'] ?? NULL,
      'countries' => $element['#allowed'] ?? 'all',
      'only_countries' => isset($element['#allowed']) && $element['#allowed'] === 'include' ? $element['#countries'] : NULL,
      'exclude_countries' => isset($element['#allowed']) && $element['#allowed'] === 'exclude' ? $element['#countries'] : NULL,
      'national_mode' => $element['#national_mode'] ?? TRUE,
      'placeholder_number_type' => $element['#placeholder_number_type'] ?? 'MOBILE',
      'separate_dial_code' => $element['#separate_dial_code'] ?? FALSE,
      'show_flags' => $element['#show_flags'] ?? TRUE,
      'country_search' => $element['#country_search'] ?? TRUE,
      'use_fullscreen_popup' => $element['#use_fullscreen_popup'] ?? TRUE,
      'remove_start_zero' => $element['#remove_start_zero'] ?? TRUE,
      'mask_formatter' => $element['#mask_formatter'] ?? TRUE,
      'show_error' => $element['#show_error'] ?? FALSE,
      'geolocation_api' => $element['#geo_ip_lookup'] ?? 'ipapi',
      'geolocation_key' => $element['#api_key'] ?? '',
      'token_data' => !empty($entity) ? [$entity->getEntityTypeId() => $entity] : [],
      'extension_field' => $element['#extension_field'] ?? FALSE,
    ];

    // Merge settings into the element's #phone property.
    $element += [
      '#phone' => [
        'allow_dropdown' => (bool) $settings['allow_dropdown'] ?? TRUE,
        'fix_dropdown_width' => (bool) $settings['fix_dropdown_width'] ?? TRUE,
        'auto_placeholder' => $settings['auto_placeholder'] ?? 'polite',
        'container_class' => $settings['container_class'] ?? '',
        'custom_placeholder' => $settings['custom_placeholder'] ?? NULL,
        'format_as_you_type' => (bool) $settings['format_as_you_type'] ?? TRUE,
        'format_on_display' => (bool) $settings['format_on_display'] ?? TRUE,
        'initial_country' => $settings['initial_country'] ?? 'auto',
        'preferred_countries' => $settings['preferred_countries'] ?? [],
        'country_order' => $settings['country_order'] ?? NULL,
        'countries' => $settings['allowed'] ?? 'all',
        'only_countries' => isset($settings['allowed']) && $settings['allowed'] === 'include' ? $settings['countries'] : NULL,
        'exclude_countries' => isset($settings['allowed']) && $settings['allowed'] === 'exclude' ? $settings['countries'] : NULL,
        'national_mode' => (bool) $settings['national_mode'] ?? TRUE,
        'placeholder_number_type' => $settings['placeholder_number_type'] ?? 'MOBILE',
        'separate_dial_code' => (bool) $settings['separate_dial_code'] ?? FALSE,
        'show_flags' => (bool) $settings['show_flags'] ?? TRUE,
        'country_search' => (bool) $settings['country_search'] ?? TRUE,
        'use_fullscreen_popup' => (bool) $settings['use_fullscreen_popup'] ?? TRUE,
        'remove_start_zero' => (bool) $settings['remove_start_zero'] ?? TRUE,
        'mask_formatter' => (bool) $settings['mask_formatter'] ?? TRUE,
        'show_error' => (bool) $settings['show_error'] ?? FALSE,
        'geolocation_api' => $settings['geolocation_api'] ?? 'ipapi',
        'geolocation_key' => $settings['geolocation_key'] ?? '',
        'token_data' => !empty($entity) ? [$entity->getEntityTypeId() => $entity] : [],
        'extension_field' => $settings['extension_field'] ?? FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function formatHtmlItem(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    // Get the value of the element from the webform submission.
    $value = $this->getValue($element, $webform_submission, $options);

    // If the value is empty, return an empty string.
    if (empty($value['value'])) {
      return '';
    }

    // Determine the phone number display format.
    $format = $this->getItemFormat($element);
    $phoneDisplayFormat = NULL;
    switch ($format) {
      case 'phone_number_international':
        $phoneDisplayFormat = PhoneNumberFormat::INTERNATIONAL;
        break;

      case 'phone_number_local':
        $phoneDisplayFormat = PhoneNumberFormat::NATIONAL;
        break;
    }

    // Check if the phone number should be displayed as a link.
    $as_link = !empty($element['#as_link']);

    // Get the phone number extension if available.
    $extension = NULL;
    if (!empty($element['#extension_field']) && isset($value['extension'])) {
      $extension = $value['extension'];
    }

    // Get the formatted phone number.
    if ($phone_number = $this->phoneNumberUtil->getPhoneNumber($value['value'], NULL, $extension)) {
      if (!empty($as_link)) {
        // If the phone number should be displayed as a link.
        $element = [
          '#type' => 'link',
          '#title' => $this->phoneNumberUtil->libUtil()->format($phone_number, $phoneDisplayFormat),
          '#url' => Url::fromUri($this->phoneNumberUtil->getRfc3966Uri($phone_number)),
        ];
      }
      else {
        // If the phone number should be displayed as plain text.
        $element = [
          '#plain_text' => $this->phoneNumberUtil->libUtil()->format($phone_number, $phoneDisplayFormat),
        ];
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemDefaultFormat() {
    // Return the default format for phone numbers.
    return 'phone_international';
  }

  /**
   * {@inheritdoc}
   */
  public function getItemFormats() {
    // Return the available formats for phone numbers.
    return parent::getItemFormats() + [
      'phone_international' => $this->t('International'),
      'phone_local' => $this->t('Local number'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function formatText(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    // Get the value of the element from the webform submission.
    $data = $webform_submission->getData($element['#webform_key']);
    // Return the phone number value if available.
    return !empty($data['value']) ? $webform_submission->getData($element['#webform_key'])['value'] : '';
  }

}
