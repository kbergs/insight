<?php

namespace Drupal\phonenumber\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\phonenumber\Plugin\Field\FieldType\PhoneItem;

/**
 * Provides a form element for entering an international phone number.
 *
 * Provides an HTML5 input element with type of "tel". It provides no special
 * validation.
 *
 * Properties:
 * - #phone
 *    - allowed_countries.
 *    - allowed_types.
 *    - placeholder.
 *    - extension_field.
 *    - phone_size.
 *    - extension_size.
 * - #size: The size of the input element in characters.
 * - #pattern: A string for the native HTML5 pattern attribute.
 *
 * Usage example:
 *
 * @code
 * $form['phone'] = [
 *   '#type' => 'phone',
 *   '#title' => $this->t('Phone'),
 *   '#pattern' => '[^\d]*',
 * ];
 * @endcode
 *
 * @see \Drupal\Core\Render\Element
 *
 * @FormElement("phone")
 */
class Phone extends FormElement {

  const PHONE_MAX_LENGTH = 16;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = static::class;
    return [
      '#input' => TRUE,
      '#size' => 30,
      '#autocomplete_route_name' => FALSE,
      '#process' => [
        [$class, 'processAutocomplete'],
        [$class, 'processPattern'],
        [$class, 'processPhone'],
      ],
      '#element_validate' => [
        [$class, 'validatePhone'],
      ],
      '#phone' => [],
    ];
  }

  /**
   * Processes a phone number element.
   */
  public static function processPhone(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $element['#tree'] = TRUE;
    $field_name = $element['#name'];
    $field_path = implode('][', $element['#parents']);
    $id = $element['#id'];

    // Ensure element has default value and phone settings.
    $element += [
      '#default_value' => [],
      '#phone' => [],
    ];

    $errors = $form_state->getErrors();
    foreach ($errors as $path => $message) {
      if (!(strpos($path, $field_path) === 0)) {
        unset($errors[$path]);
      }
    }

    // Get phone settings.
    $settings = $element['#phone'];

    // Get default values.
    $value = $element['#value'];

    $element['#prefix'] = "<div class=\"phone-number-field form-item $field_name\" id=\"$id\">";
    $element['#suffix'] = '</div>';

    $element['local_number'] = [
      '#type' => 'tel',
      '#description' => $element['#description'] ?? '',
      '#title' => $element['#title'],
      '#title_display' => $element['#title_display'],
      '#size' => PhoneItem::MAX_LENGTH,
      '#attributes' => [
        'class' => ['form-phone', 'local-number'],
      ],
      '#wrapper_attributes' => [
        'class' => ['form-item--local-number'],
      ],
      '#default_value' => !empty($value['phone_number']) ? '+' . $value['phone_number'] : NULL,
      '#maxlength' => PhoneItem::MAX_LENGTH,
      '#field_suffix' => '<div class="phone-error-msg">Invalid phone number.</div>',
      '#required' => $element['#required'],
    ];

    if (isset($settings['extension_field']) && $settings['extension_field']) {
      $element['extension'] = [
        '#type' => 'textfield',
        '#default_value' => !empty($value['extension']) ? $value['extension'] : NULL,
        '#title' => t('Extension'),
        '#title_display' => 'invisible',
        '#size' => $settings['extension_size'] ?? 5,
        '#maxlength' => 40,
        '#attributes' => [
          'class' => ['extension'],
          'placeholder' => t('Ext.'),
        ],
        '#wrapper_attributes' => [
          'class' => ['form-item--extension'],
        ],
      ];
    }

    $hidden_fields = ['phone_number', 'country_code', 'country_iso2'];
    foreach ($hidden_fields as $field) {
      $element[$field] = [
        '#type' => 'hidden',
        '#value' => !empty($value[$field]) ? $value[$field] : NULL,
        '#default_value' => !empty($value[$field]) ? $value[$field] : NULL,
        '#attributes' => ['class' => 'temporary-phone'],
      ];
    }

    if (!empty($element['#description'])) {
      $element['description']['#markup'] = '<div class="description">' . $element['#description'] . '</div>';
    }

    if (!empty($element['#states'])) {
      $element['local_number']['#states'] = $element['#states'];
      if (!empty($element['#description'])) {
        $element['description']['#states'] = $element['#states'];
      }
    }

    // Attach utils libraries and settings.
    $module_handler = \Drupal::service('module_handler');
    $module_path = base_path() . $module_handler->getModule('phonenumber')->getPath();
    $utils_script = $module_path . '/lib/js/utils.js';

    // Prepare GeoLocation API settings.
    $settings['geolocation_api'] = $settings['geolocation_api'] ?? 'ipapi';
    $geolocation = phonenumber_geo_ip_lookup_services();
    $geolocation_api = [
      'name' => $settings['geolocation_api'],
      'url' => $geolocation[$settings['geolocation_api']]['url'],
      'script' => $geolocation[$settings['geolocation_api']]['script'],
      'type' => $geolocation[$settings['geolocation_api']]['type'],
    ];

    // Add geolocation API key.
    if (isset($geolocation[$settings['geolocation_api']]['signup']) && $geolocation[$settings['geolocation_api']]['signup'] && isset($settings['geolocation_key']) && !empty(trim($settings['geolocation_key']))) {
      $geolocation_api['apiKey'] = trim($settings['geolocation_key']);
    }

    // Get phone mask number formatter.
    $mask_formatter = !isset($settings['mask_formatter']) || $settings['mask_formatter'];

    // Phone default country.
    $default_country = $settings['initial_country'] ?? 'auto';

    // Phone top list country.
    $default_preferred = [
      "us",
      "gb",
    ];
    $preferred_countries = $element['#phone']['preferred_countries'] ?? $default_preferred;
    if (isset($settings['initial_country']) && $settings['initial_country'] != 'auto' && !in_array($settings['initial_country'], $preferred_countries)) {
      $preferred_countries = array_merge([$settings['initial_country']], $preferred_countries);
    }

    $only_countries = [];
    if ($settings['countries'] == 'include' && isset($settings['only_countries']) && count($settings['only_countries'])) {
      $only_countries = array_keys($settings['only_countries']);
    }

    $exclude_countries = [];
    if ($settings['countries'] == 'exclude' && isset($settings['exclude_countries']) && count($settings['exclude_countries'])) {
      $exclude_countries = array_keys($settings['exclude_countries']);
    }

    $national_mode = $settings['national_mode'] ?? TRUE;
    if (!$settings['allow_dropdown'] && !$settings['show_flags']) {
      $national_mode = FALSE;
      // Prevent error on auto country select
      // when dropdown and flags not available same time.
      $default_country = "";
    }

    // Prepare library options from phone settings.
    $options = [
      // Whether to allow dropdown. If disabled, there is no dropdown arrow,
      // and the selected flag is not clickable. Also, we display the selected
      // flag on the right instead because it is just a marker of state.
      'allowDropdown' => $settings['allow_dropdown'] ?? TRUE,

      // Fix the dropdown width to the input width (rather than being as wide
      // as the longest country name).
      'fixDropdownWidth' => $settings['fix_dropdown_width'] ?? TRUE,

      // Set the input's placeholder to an example number for the selected
      // country, and update it if the country changes. You can specify
      // the number type using the placeholderNumberType option.
      'autoPlaceholder' => $settings['auto_placeholder'] ?? 'polite',

      // Additional classes to add to the (injected) wrapper <div>.
      'containerClass' => $settings['container_class'] ?? '',

      // Change the placeholder generated by autoPlaceholder.
      // Must return a string.
      'customPlaceholder' => $settings['custom_placeholder'] ?? '',

      // Expects a node e.g. document.body. Instead of putting the country
      // dropdown next to the input, append it to the specified node, and it
      // will then be positioned absolutely next to the input using JavaScript.
      'dropdownContainer' => NULL,

      // In dropdown, display all countries except the ones you specify here.
      'excludeCountries' => array_map('strtolower', $exclude_countries),

      // Automatically format the number as the user types. This feature will
      // be disabled if the user types their own formatting characters.
      'formatAsYouType' => $settings['format_as_you_type'] ?? TRUE,

      // Format the input value (according to the nationalMode option)
      // during initialisation, and on setNumber.
      'formatOnDisplay' => $settings['format_on_display'] ?? TRUE,

      // When setting initialCountry to "auto", you must use this option to
      // specify a custom function that looks up the user's location, and then
      // calls the success callback with the relevant country code.
      'geoIpLookup' => $default_country === 'auto',

      // Creates a hidden input which gets populated with the full
      // international number on submit. You must provide a function (which
      // receives the main telephone input name as an argument, in case that's
      // useful) and it must return the name to use for the hidden input.
      'hiddenInput' => "phone_number",

      // Set the initial country selection by specifying its country code.
      // You can also set it to "auto", which will look up the user's country
      // based on their IP address (requires the geoIpLookup option).
      // Note: that the "auto" option will not update the country selection
      // if the input already contains a number.
      'initialCountry' => mb_strtolower($default_country),

      // Allow to localise/customize of country names and other plugin text.
      // To localise a country name, the key should be the iso2 country code,
      // and the value should be the localised country name.
      'i18n' => $settings['i18n'] ?? [],

      // Format numbers in the national format, rather than the international
      // format. This applies to placeholder numbers, and when displaying
      // user's existing numbers.
      'nationalMode' => $national_mode,

      // In the dropdown, display only the countries you specify.
      'onlyCountries' => array_map('strtolower', $only_countries),

      // Specify one of the keys from the global enum
      // intlTelInputUtils.numberType e.g. "FIXED_LINE" to set the number
      // type to use for the placeholder.
      'placeholderNumberType' => $settings['placeholder_number_type'] ?? 'MOBILE',

      // Specify the countries to appear at the top of the list.
      // Note that this option is not compatible with the new country search
      // feature, and as such will be phased out.
      'preferredCountries' => array_map('strtolower', $preferred_countries),

      // Display the country dial code next to the selected flag.
      'separateDialCode' => $settings['separate_dial_code'] ?? FALSE,

      // Only allow certain chars e.g. a plus followed by numeric digits,
      // and cap at max valid length.
      'strictMode' => $settings['strict_mode'] ?? FALSE,

      // Set this too false to hide the flags e.g. for political reasons.
      // Must be used in combination with separateDialCode option,
      // or with setting allowDropdown to false.
      'showFlags' => $settings['show_flags'] ?? TRUE,

      // Add a search input to the top of the dropdown,
      // so users can filter the displayed countries.
      'countrySearch' => $settings['country_search'] ?? TRUE,

      // Specify the path to the libphonenumber script to enable
      // validation/formatting etc.
      'utilsScript' => $utils_script,

      // The number type to enforce during validation.
      'validationNumberType' => "MOBILE",

      // Add mask and format based on country
      // international phone number pattern.
      'maskFormatter' => $mask_formatter,

      // Remove zero from Placeholder beginning for local number.
      'removeStartZero' => $settings['remove_start_zero'] ?? TRUE,

      // Display inline phone input validation error and
      // prevent form submission in client side.
      'showError' => $settings['show_error'] ?? FALSE,

      // IP geolocation API for lookup user's country code.
      'geoLocationApi' => $geolocation_api,
    ];

    // Attach phone options.
    $element['#attached']['drupalSettings']['phone']['options'] = $options;

    // Attach phone widget and number formatter library.
    if ($mask_formatter) {
      $element['#attached']['library'][] = 'phonenumber/phonenumber.formatter';
    }
    $element['#attached']['library'][] = 'phonenumber/phone';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input) {
      $settings = !empty($element['#phone']) ? $element['#phone'] : [];
      $settings += [
        'allowed' => 'all',
        'countries' => [],
        'extension_field' => FALSE,
      ];

      $extension = $settings['extension_field'] ? $input['extension'] : NULL;
      $result = [
        'phone_number' => $input['phone_number'],
        'local_number' => $input['local_number'],
        'country_code' => $input['country_code'],
        'country_iso2' => $input['country_iso2'],
        'extension' => $extension,
      ];
    }
    else {
      $result = !empty($element['#default_value']) ? $element['#default_value'] : '';
    }

    return $result;
  }

  /**
   * Form element validation handler for #type 'phone'.
   *
   * That #maxlength and #required is validated by _form_validate() already.
   */
  public static function validatePhone(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $field_label = $element['#field_title'] ?? $element['#title'];
    $tree_parents = $element['#parents'];
    $input = NestedArray::getValue($form_state->getUserInput(), $tree_parents) ?? [];

    // Get phone settings.
    $settings = $element['#phone'];

    if (!empty($input['local_number'])) {
      $local_number = $input['local_number'];
      $national_mode = $settings['national_mode'];
      if (!$national_mode) {
        if (!empty($input['country_code'])) {
          $country_code = '+' . $input['country_code'];
          // Remove country code from the beginning of local number.
          if (strpos($local_number, $country_code) === 0) {
            $local_number = trim(substr($local_number, strlen($country_code)));
          }
        }
      }

      // Assign the cleaned local number and other
      // fields back to the input array.
      $input['local_number'] = $local_number;
      $form_state->setValueForElement($element, $input);
    }

    // Check if the phone number has been provided.
    if (!empty($input['phone_number'])) {
      $phone_number = $input['phone_number'];

      // Remove spaces, dashes, parentheses, and other non-numeric characters.
      $cleaned_number = preg_replace('/[^\d]/', '', $phone_number);

      // Check if the cleaned number contains only digits.
      if (!ctype_digit($cleaned_number)) {
        // Set an error if the phone number contains invalid characters.
        $form_state->setError($element, t('The phone number %number contains invalid characters. Only digits are allowed.', ['%number' => $phone_number]));
        return;
      }

      // Assign the cleaned number and other fields back to the input array.
      $input['phone_number'] = $cleaned_number;
      $form_state->setValueForElement($element, $input);
    }
    elseif (!empty($element['#required'])) {
      // Set an error if the phone number is required but not provided.
      $form_state->setError($element, t('Phone number in %field is required.', ['%field' => $field_label]));
    }
  }

}
