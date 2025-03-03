<?php

namespace Drupal\phonenumber_verification\Plugin\TfaValidation;

/**
 * @file
 * PhoneTfa.php
 */

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\phone_number\Exception\PhoneNumberException;
use Drupal\phonenumber_verification\PhoneVerifierInterface;
use Drupal\tfa\Plugin\TfaBasePlugin;
use Drupal\tfa\Plugin\TfaSendInterface;
use Drupal\tfa\Plugin\TfaValidationInterface;
use Drupal\user\UserDataInterface;

/**
 * Class PhoneTfa is a validation and sending plugin for TFA.
 *
 * @package Drupal\phonenumber_verification
 *
 * @ingroup phonenumber_verification
 */
class PhoneTfa extends TfaBasePlugin implements TfaValidationInterface, TfaSendInterface {

  /**
   * The phone field validation utility.
   *
   * @var \Drupal\phonenumber_verification\PhoneVerifierInterface
   */
  public $smsPhoneNumberUtil;

  /**
   * Libphonenumber phone number object.
   *
   * @var \libphonenumber\PhoneNumber
   */
  public $phoneNumber;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity type manager interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The SMS Phone Number config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $smsPhoneNumberConfig;

  /**
   * The length of the verification code.
   *
   * @var int
   */
  protected $codeLength;

  /**
   * Constructs a PhoneTfa object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   * @param \Drupal\encrypt\EncryptionProfileManagerInterface $encryption_profile_manager
   *   The encryption profile manager service.
   * @param \Drupal\encrypt\EncryptServiceInterface $encrypt_service
   *   The encrypt service.
   * @param \Drupal\phonenumber_verification\PhoneVerifierInterface $smsPhoneNumberUtil
   *   The phone verifier service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, UserDataInterface $user_data, EncryptionProfileManagerInterface $encryption_profile_manager, EncryptServiceInterface $encrypt_service, PhoneVerifierInterface $smsPhoneNumberUtil, MessengerInterface $messenger, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $user_data, $encryption_profile_manager, $encrypt_service);
    $this->smsPhoneNumberUtil = $smsPhoneNumberUtil;
    $this->messenger = $messenger;
    $this->logger = \Drupal::logger('phonenumber_verification_tfa');
    $this->entityTypeManager = $entity_type_manager;
    $this->smsPhoneNumberConfig = $config_factory->get('phonenumber_verification.settings');
    $this->codeLength = 4;

    // Retrieve context if available.
    if (!empty($context['validate_context'])) {
      if (!empty($context['validate_context']['code'])) {
        $this->code = $context['validate_context']['code'];
      }
      if (!empty($context['validate_context']['verification_token'])) {
        $this->verificationToken = $context['validate_context']['verification_token'];
      }
    }

    // Check the user's phone number.
    if ($m = $this->smsPhoneNumberUtil->tfaAccountNumber($context['uid'])) {
      try {
        $this->phoneNumber = $this->smsPhoneNumberUtil->checkPhoneNumber($m);
      }
      catch (PhoneNumberException $e) {
        throw new Exception("Two factor authentication failed: \n" . $e->getMessage(), $e->getCode(), $e);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function ready() {
    return $this->smsPhoneNumberUtil->tfaAccountNumber(($this->context['uid'])) ? TRUE : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function begin() {
    if (!$this->code) {
      if (!$this->sendCode()) {
        $this->messenger->addError($this->t('Unable to deliver the code. Please contact support.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array $form, FormStateInterface $form_state) {
    $local_number = $this->smsPhoneNumberUtil->getLocalNumber($this->phoneNumber, TRUE);
    $numberClue = str_pad(substr($local_number, -3, 3), strlen($local_number), 'X', STR_PAD_LEFT);
    $numberClue = substr_replace($numberClue, '-', 3, 0);

    $form['code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Verification Code'),
      '#required' => TRUE,
      '#description' => $this->t('A verification code was sent to %clue. Enter the @length-character code sent to your device.', [
        '@length' => $this->codeLength,
        '%clue' => $numberClue,
      ]),
    ];

    $form['actions']['#type'] = 'actions';

    $form['actions']['login'] = [
      '#type' => 'submit',
      '#value' => $this->t('Verify'),
    ];

    $form['actions']['resend'] = [
      '#type' => 'submit',
      '#value' => $this->t('Resend'),
      '#submit' => ['tfa_form_submit'],
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array $form, FormStateInterface $form_state) {
    // If operation is resend, skip validation.
    if ($form_state->getValue('op') === $form_state->getValue('resend')) {
      return TRUE;
    }
    // Validate the verification code.
    elseif (!$this->verifyCode($form_state->getValue('code'))) {
      $this->errorMessages['code'] = $this->t('Invalid code.');
      return FALSE;
    }
    else {
      return TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array $form, FormStateInterface &$form_state) {
    // Handle resend operation.
    if ($form_state->getValue('op') === $form_state->getValue('resend')) {
      if (!$this->smsPhoneNumberUtil->checkFlood($this->phoneNumber, 'sms')) {
        $this->messenger->addError($this->t('Too many verification code requests, please try again shortly.'));
      }
      elseif (!$this->sendCode()) {
        $this->messenger->addError($this->t('Unable to deliver the code. Please contact support.'), 'error');
      }
      else {
        $this->messenger->addMessage($this->t('Code resent'));
      }
      return FALSE;
    }
    else {
      return parent::submitForm($form, $form_state);
    }
  }

  /**
   * Return context for this plugin.
   *
   * @return array
   *   Context data.
   */
  public function getPluginContext() {
    return [
      'code' => $this->code,
      'verification_token' => !empty($this->verificationToken) ? $this->verificationToken : '',
    ];
  }

  /**
   * Send the code via the client.
   *
   * @return bool
   *   Whether sending SMS was successful.
   */
  public function sendCode() {
    $user = $this->entityTypeManager->getStorage('user')->load($this->context['uid']);
    $this->code = $this->smsPhoneNumberUtil->generateVerificationCode($this->codeLength);
    try {
      $message = $this->smsPhoneNumberConfig->get('tfa_message');
      $message = $message ? $message : PhoneVerifierInterface::PHONE_NUMBER_DEFAULT_SMS_MESSAGE;
      if (!($this->verificationToken = $this->smsPhoneNumberUtil->sendVerification($this->phoneNumber, $message, $this->code, ['user' => $user]))) {
        return FALSE;
      }

      // Log the code sent.
      $this->logger->info('TFA validation code sent to user @uid', ['@uid' => $this->context['uid']]);
      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Send message error to user @uid. Status code: @code, message: @message', [
        '@uid' => $this->context['uid'],
        '@code' => $e->getCode(),
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Verifies the given code with this session's verification token.
   *
   * @param string $code
   *   The verification code.
   *
   * @return bool
   *   Whether the code is valid.
   */
  public function verifyCode($code) {
    return $this->isValid = $this->smsPhoneNumberUtil->verifyCode($this->phoneNumber, $code, $this->verificationToken);
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbacks() {
    return ($this->pluginDefinition['fallbacks']) ?: '';
  }

  /**
   * {@inheritdoc}
   */
  public function purge() {}

}
