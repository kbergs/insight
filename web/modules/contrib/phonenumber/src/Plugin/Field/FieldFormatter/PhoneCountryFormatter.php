<?php

namespace Drupal\phonenumber\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'phone_country' formatter.
 *
 * @FieldFormatter(
 *   id = "phone_country",
 *   label = @Translation("Country"),
 *   field_types = {
 *     "phone"
 *   }
 * )
 */
class PhoneCountryFormatter extends FormatterBase {

  /**
   * The country manager service.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected $countryManager;

  /**
   * Constructs a FormatterBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Locale\CountryManagerInterface $country_manager
   *   The country manager service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, CountryManagerInterface $country_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
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
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('country_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings() + ['type' => 'name'];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getSettings() + static::defaultSettings();

    $element['type'] = [
      '#type' => 'radios',
      '#options' => [
        'name' => $this->t('Country name'),
        'code' => $this->t('Country code'),
        'iso2' => $this->t('Country ISO'),
      ],
      '#default_value' => $settings['type'],
    ];

    return parent::settingsForm($form, $form_state) + $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $settings = $this->getSettings() + static::defaultSettings();

    $texts = [
      'name' => $this->t('Show as country name'),
      'code' => $this->t('Show as country code'),
      'iso2' => $this->t('Show as country iso2'),
    ];
    $summary[] = $texts[$settings['type']];

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // Initialize the elements array.
    $element = [];

    // Get the settings for the field formatter.
    $settings = $this->getSettings() + static::defaultSettings();

    // Iterate through each field item.
    foreach ($items as $delta => $item) {
      // Get the values from the field item.
      $values = $item->getValue();

      // Skip the item if 'country_code' or 'country_iso2' are empty.
      if (empty($values['country_code']) || empty($values['country_iso2'])) {
        continue;
      }

      // Get the list of countries from the country manager.
      $countries = $this->countryManager->getList();

      // Detect the case format of the keys in the $countries array.
      $first_key = array_key_first($countries);
      if (ctype_upper($first_key)) {
        // If the first key is in uppercase,
        // convert 'country_iso2' to uppercase.
        $country_iso2 = strtoupper($values['country_iso2']);
      }
      elseif (ctype_lower($first_key)) {
        // If the first key is in lowercase,
        // convert 'country_iso2' to lowercase.
        $country_iso2 = strtolower($values['country_iso2']);
      }
      else {
        // If the first key is in mixed case (e.g., ucfirst),
        // convert 'country_iso2' accordingly.
        $country_iso2 = ucfirst(strtolower($values['country_iso2']));
      }

      // Determine the output format based on the settings.
      if ($settings['type'] == 'code') {
        // If the type is 'code', display the country code.
        $element[$delta] = [
          '#plain_text' => $values['country_code'],
        ];
      }
      elseif ($settings['type'] == 'name') {
        // If the type is 'name', display the country name.
        $element[$delta] = [
          '#plain_text' => $countries[$country_iso2] ?? $country_iso2,
        ];
      }
      else {
        // Default case: display the ISO2 country code.
        $element[$delta] = [
          '#plain_text' => $values['country_iso2'],
        ];
      }
    }

    // Return the rendered elements.
    return $element;
  }

}
