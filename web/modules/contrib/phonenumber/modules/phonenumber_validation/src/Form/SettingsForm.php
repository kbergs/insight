<?php

namespace Drupal\phonenumber_validation\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\phonenumber_validation\PhoneValidator;
use libphonenumber\PhoneNumberFormat;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for default validation settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Validator service.
   *
   * @var \Drupal\phonenumber_validation\PhoneValidator
   */
  protected $validator;

  /**
   * Element Info Manager service.
   *
   * @var \Drupal\Core\Render\ElementInfoManagerInterface
   */
  protected $elementInfoManager;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\phonenumber_validation\PhoneValidator $validator
   *   Phone number validation service.
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $element_info_manager
   *   Collects available render array element types.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typedConfigManager, PhoneValidator $validator, ElementInfoManagerInterface $element_info_manager) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->validator = $validator;
    $this->elementInfoManager = $element_info_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('phonenumber_validation.validator'),
      $container->get('plugin.manager.element_info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'phonenumber_validation_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'phonenumber_validation.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Retrieve configuration object.
    $config = $this->config('phonenumber_validation.settings');

    // Define valid phone number format.
    $form['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Format'),
      '#default_value' => $config->get('format') ?: PhoneNumberFormat::E164,
      '#options' => [
        PhoneNumberFormat::E164 => $this->t('E164'),
        PhoneNumberFormat::NATIONAL => $this->t('National'),
      ],
      '#ajax' => [
        'callback' => [$this, 'ajaxCallback'],
        'wrapper' => 'phonenumber-validation-country',
        'method' => 'replace',
        'effect' => 'fade',
      ],
    ];

    // Define available countries (or country if format = NATIONAL).
    $default_format = $config->get('format') ?: PhoneNumberFormat::E164;
    $current_format = $form_state->getValue('format')
      ?? $form['format']['#default_value']
      ?? $default_format;

    // Add country select element.
    $form['country'] = [
      '#type' => 'select',
      '#title' => $this->t('Valid countries'),
      '#description' => $this->t('If no country is selected, all countries are valid.'),
      '#default_value' => $config->get('country') ?: [],
      '#multiple' => $current_format != PhoneNumberFormat::NATIONAL,
      '#options' => $this->validator->getCountryList(),
      '#prefix' => '<div id="phonenumber-validation-country">',
      '#suffix' => '</div>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Ajax callback for updating the country field when format changes.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An Ajax response object to update the country field.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // Determine if the country field should be multiple.
    $format = $form_state->getValue('format') ?: $form['format']['#default_value'];
    $form['country']['#multiple'] = ($format != PhoneNumberFormat::NATIONAL);

    // Update the country field in the form with the new settings.
    $response->addCommand(new HtmlCommand('#phonenumber-validation-country', $form['country']));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $country = $form_state->getValue('country');

    // Save new config.
    $this->config('phonenumber_validation.settings')
      ->set('format', $form_state->getValue('format'))
      ->set('country', is_array($country) ? $country : [$country])
      ->save();

    // Clear element info cache.
    $this->elementInfoManager->clearCachedDefinitions();

    parent::submitForm($form, $form_state);
  }

}
