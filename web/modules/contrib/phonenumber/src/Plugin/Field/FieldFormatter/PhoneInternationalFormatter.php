<?php

namespace Drupal\phonenumber\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'phone_international' formatter.
 *
 * @FieldFormatter(
 *   id = "phone_international",
 *   label = @Translation("International number"),
 *   field_types = {
 *     "phone",
 *     "telephone"
 *   }
 * )
 */
class PhoneInternationalFormatter extends FormatterBase {

  /**
   * The display format.
   *
   * @var string
   */
  public $phoneDisplayFormat = 'international';

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings() + ['link' => FALSE, 'title' => ''];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $settings = $this->getSettings() + static::defaultSettings();

    $element['link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display as a TEL link'),
      '#default_value' => $settings['link'],
    ];

    $element['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title to replace basic numeric phone number display'),
      '#default_value' => $this->getSetting('title'),
      '#states' => [
        'enabled' => [
          '[name*="settings_edit_form][settings][link]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::settingsForm($form, $form_state) + $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $settings = $this->getSettings() + static::defaultSettings();

    if (!empty($settings['link'])) {
      $summary[] = $this->t('Show as TEL link');

      if (!empty($settings['title'])) {
        $summary[] = $this->t('Link using text: @title', ['@title' => $settings['title']]);
      }
      else {
        $summary[] = $this->t('Link using provided phone number.');
      }
    }
    else {
      $summary[] = $this->t('Displayed provided phone number as a plain text.');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    $settings = $this->getSettings() + static::defaultSettings();

    $title_setting = trim($settings['title']);

    foreach ($items as $delta => $item) {
      $values = $item->getValue();
      if (empty($values['phone_number']) || empty($values['local_number'])) {
        continue;
      }

      // Get stored phone number in international format.
      $international_number = $item->phone_number;

      // Get stored phone number in local format.
      $national_number = $item->local_number;
      if (strlen($national_number) <= 5) {
        $national_number = substr_replace($national_number, '-', 1, 0);
      }

      // Phone number display format.
      if ($this->phoneDisplayFormat == 'national') {
        $phone_number = '0' . $national_number;
      }
      else {
        $phone_number = '+' . $item->country_code . ' ' . $national_number;
      }
      // Render each element as Tel link.
      if (!empty($settings['link'])) {
        $element[$delta] = [
          '#type' => 'link',
          '#title' => !empty($title_setting) ? $title_setting : $phone_number,
          '#url' => Url::fromUri('tel:' . $international_number),
        ];
      }
      // Render each element as plain text.
      else {
        $element[$delta] = [
          '#plain_text' => $phone_number,
        ];
      }

      if (!empty($item->_attributes)) {
        $element[$delta]['#options'] += ['attributes' => []];
        $element[$delta]['#options']['attributes'] += $item->_attributes;
        // Unset field item attributes since they have been included in the
        // formatter output and should not be rendered in the field template.
        unset($item->_attributes);
      }
    }

    return $element;
  }

}
