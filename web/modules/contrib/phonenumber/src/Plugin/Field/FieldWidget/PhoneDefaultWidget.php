<?php

namespace Drupal\phonenumber\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'phone_default' widget.
 *
 * @FieldWidget(
 *   id = "phone_default",
 *   label = @Translation("Phone number"),
 *   description = @Translation("Phone number field default widget."),
 *   field_types = {
 *     "phone",
 *     "telephone"
 *   }
 * )
 */
class PhoneDefaultWidget extends WidgetBase {

  /**
   * The country manager.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected CountryManagerInterface $countryManager;

  /**
   * Constructs a PhoneDefaultWidget object.
   *
   * @param string $plugin_id
   *   The plugin ID for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Locale\CountryManagerInterface $country_manager
   *   The country manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, CountryManagerInterface $country_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->countryManager = $country_manager;
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
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
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
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    // Initialize the settings form elements.
    $element = parent::settingsForm($form, $form_state);
    $field_settings = $this->getFieldSettings();
    $allowed_countries = $this->countryManager->getList();
    $preferred_countries = $this->getSetting('preferred_countries');
    $initial_country = $this->getSetting('initial_country');

    // Filter allowed countries based on field settings.
    if ($field_settings['allowed'] != 'all' && is_array($field_settings['countries']) && count($field_settings['countries'])) {
      $countries = $field_settings['countries'];
      $allowed_countries = $field_settings['allowed'] == 'include' ? array_intersect_key($allowed_countries, $countries) : array_diff_key($allowed_countries, $countries);
      $preferred_countries = in_array($preferred_countries, $allowed_countries) ? $preferred_countries : array_diff_key($preferred_countries, $countries);
    }

    // Set initial country to 'auto' if not in allowed countries.
    $initial_country = !in_array($initial_country, $allowed_countries) ? $initial_country : 'auto';
    $element['initial_country'] = [
      '#type' => 'select',
      '#title' => $this->t('Default country'),
      '#options' => ['auto' => $this->t("Auto user's country (geoIPLookup)")] + $allowed_countries,
      '#default_value' => $initial_country,
      '#description' => $this->t("Specify a default selection country. You can also set it to <em>Auto</em>, which will look up the user's country based on their IP address."),
      '#states' => [
        'invisible' => [
          '[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][geolocation]"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $element['preferred_countries'] = [
      '#type' => 'select',
      '#title' => $this->t('Top list countries'),
      '#multiple' => TRUE,
      '#options' => $allowed_countries,
      '#default_value' => $preferred_countries,
      '#description' => $this->t('Specify the countries to appear at the top of the list. If none is selected, it will follow alphabetical order.'),
    ];

    $element['allow_dropdown'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow dropdown'),
      '#default_value' => $this->getSetting('allow_dropdown'),
      '#description' => $this->t('If disabled, there is no dropdown arrow and the selected flag is not clickable. Display the selected flag on the right because it is just a status indicator.'),
    ];

    $element['fix_dropdown_width'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Fix dropdown width'),
      '#default_value' => $this->getSetting('fix_dropdown_width'),
      '#description' => $this->t('Fix the dropdown width to the input width (rather than being as wide as the longest country name).'),
    ];

    $element['separate_dial_code'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Separate dial code'),
      '#default_value' => $this->getSetting('separate_dial_code'),
      '#description' => $this->t('Display the country dial code next to the selected flag.'),
    ];

    $element['show_flags'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show flags'),
      '#default_value' => $this->getSetting('show_flags'),
      '#description' => $this->t('Disable to hide the flags e.g. for political reasons. Must be used in combination with "Separate dial code" option, or with setting "Allow dropdown" to disable.'),
    ];

    $element['country_search'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Country search'),
      '#default_value' => $this->getSetting('country_search'),
      '#description' => $this->t('Add a search input to the top of the dropdown, so users can filter the displayed countries.'),
    ];

    $element['format_as_you_type'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Format as you type'),
      '#default_value' => $this->getSetting('format_as_you_type'),
      '#description' => $this->t('Automatically format the number as the user types. This feature will be disabled if the user types their own formatting characters.'),
    ];

    $element['format_on_display'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Format on display'),
      '#default_value' => $this->getSetting('format_on_display'),
      '#description' => $this->t('Format the input value (according to the nationalMode option) during initialisation.'),
    ];

    $element['remove_start_zero'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove start zero'),
      '#default_value' => $this->getSetting('remove_start_zero'),
      '#description' => $this->t('Remove zero from input and placeholder beginning for local number.'),
    ];

    $element['mask_formatter'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Mask formatter'),
      '#default_value' => $this->getSetting('mask_formatter'),
      '#description' => $this->t('Enable to add mask and format based on country international phone number pattern. Force to enter the phone number only according to the pattern and prevent entering letters and other special characters.'),
    ];

    $element['show_error'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show inline error'),
      '#default_value' => $this->getSetting('show_error'),
      '#description' => $this->t('Enable inline form error display on phone input and prevent form submission on client side if phone format is not valid.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $placeholder = $this->getSetting('placeholder');
    if (!empty($placeholder)) {
      $summary[] = $this->t('Placeholder: @placeholder', ['@placeholder' => $placeholder]);
    }
    else {
      $summary[] = $this->t('No placeholder');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $item = $items[$delta];
    $phone_number = $item->local_number ? preg_replace('/\s+/', '', $item->local_number) : '';

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $items->getEntity();
    $settings = $this->getSettings() + static::defaultSettings();
    $settings = array_merge($this->getFieldSettings(), $settings);

    $element += [
      '#type' => 'phone',
      '#description' => $element['#description'],
      '#phone' => [
        'allow_dropdown' => (bool) $settings['allow_dropdown'] ?? TRUE,
        'fix_dropdown_width' => (bool) $settings['fix_dropdown_width'] ?? TRUE,
        'container_class' => $settings['container_class'] ?? '',
        'format_as_you_type' => (bool) $settings['format_as_you_type'] ?? TRUE,
        'format_on_display' => (bool) $settings['format_on_display'] ?? TRUE,
        'initial_country' => $settings['initial_country'] ?? 'auto',
        'preferred_countries' => $settings['preferred_countries'] ?? [],
        'country_order' => $settings['country_order'] ?? NULL,
        'countries' => $settings['allowed'] ?? 'all',
        'only_countries' => $settings['allowed'] === 'include' ? $settings['countries'] : NULL,
        'exclude_countries' => $settings['allowed'] === 'exclude' ? $settings['countries'] : NULL,
        'localized_countries' => $settings['enabled_localisation'] && count($settings['localized_countries']) ? $settings['localized_countries'] : [],
        'national_mode' => (bool) $settings['national_mode'] ?? TRUE,
        'separate_dial_code' => (bool) $settings['separate_dial_code'] ?? FALSE,
        'show_flags' => (bool) $settings['show_flags'] ?? TRUE,
        'country_search' => (bool) $settings['country_search'] ?? TRUE,
        'strict_mode' => (bool) $settings['strict_mode'] ?? TRUE,
        'validation_number_type' => $settings['validation_number_type'] ?? 'MOBILE',
        'placeholder_number_type' => $settings['placeholder_number_type'] ?? 'MOBILE',
        'auto_placeholder' => $settings['auto_placeholder'] ?? 'polite',
        'custom_placeholder' => $settings['custom_placeholder'] ?? NULL,
        'use_fullscreen_popup' => (bool) $settings['use_fullscreen_popup'] ?? TRUE,
        'remove_start_zero' => (bool) $settings['remove_start_zero'] ?? TRUE,
        'mask_formatter' => (bool) $settings['mask_formatter'] ?? TRUE,
        'show_error' => (bool) $settings['show_error'] ?? FALSE,
        'geolocation_api' => $settings['geo_ip_lookup'] ?? 'ipapi',
        'geolocation_key' => $settings['api_key'] ?? '',
        'token_data' => !empty($entity) ? [$entity->getEntityTypeId() => $entity] : [],
        'extension_field' => $settings['extension_field'] ?? FALSE,
      ],
      '#default_value' => [
        'local_number' => $phone_number ?? NULL,
        'phone_number' => $item->phone_number ?? NULL,
        'country_code' => $item->country_code ?? NULL,
        'country_iso2' => $item->country_iso2 ?? NULL,
        'extension' => $item->extension ?? NULL,
      ],
      '#field_suffix' => '<div class="phone-error-msg">Invalid phone number.</div>',
    ];

    return $element;
  }

}
