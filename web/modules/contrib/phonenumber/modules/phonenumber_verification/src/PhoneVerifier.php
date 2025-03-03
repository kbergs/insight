<?php

namespace Drupal\phonenumber_verification;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Drupal\phonenumber_validation\PhoneValidator;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;

/**
 * Performs phone field verification utility class.
 */
class PhoneVerifier implements PhoneVerifierInterface {

  use StringTranslationTrait;

  /**
   * The phone number validator service.
   *
   * @var \Drupal\phonenumber_validation\PhoneValidator
   */
  protected $phoneValidator;

  /**
   * The token generator for generating CSRF tokens.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $tokenGenerator;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The phone number utility library.
   *
   * @var \libphonenumber\PhoneNumberUtil
   */
  public $phoneUtils;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  public $moduleHandler;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  public $configFactory;

  /**
   * The flood service.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  public $flood;

  /**
   * The field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  public $fieldMananger;

  /**
   * The country manager service.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  public $countryManager;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  public $token;

  /**
   * PhoneUtil constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   Field manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler.
   * @param \Drupal\Core\Locale\CountryManagerInterface $country_manager
   *   Country manager.
   * @param \Drupal\phonenumber_validation\PhoneValidator $phone_validator
   *   Phone number validation service.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $tokenGenerator
   *   The token generator for generating CSRF tokens.
   * @param \Drupal\Core\Database\Connection $connection
   *   The Connection object containing the key-value tables.
   * @param \Drupal\Core\Utility\Token $token
   *   Token service.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   Flood manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityFieldManagerInterface $field_manager, ModuleHandlerInterface $module_handler, CountryManagerInterface $country_manager, PhoneValidator $phone_validator, CsrfTokenGenerator $tokenGenerator, Connection $connection, Token $token, FloodInterface $flood) {
    $this->phoneUtils = PhoneNumberUtil::getInstance();
    $this->configFactory = $config_factory;
    $this->fieldMananger = $field_manager;
    $this->moduleHandler = $module_handler;
    $this->countryManager = $country_manager;
    $this->phoneValidator = $phone_validator;
    $this->tokenGenerator = $tokenGenerator;
    $this->database = $connection;
    $this->token = $token;
    $this->flood = $flood;
  }

  /**
   * {@inheritdoc}
   */
  public function libUtil() {
    return $this->phoneUtils;
  }

  /**
   * {@inheritdoc}
   */
  public function isVerified(PhoneNumber $phone_number) {
    // Check if the phone number is marked as verified in the session.
    return !empty($_SESSION['phonenumber_verification'][$this->phoneValidator->getCallableNumber($phone_number)]['verified']);
  }

  /**
   * {@inheritdoc}
   */
  public function isSmsEnabled() {
    // Check if SMS sending is enabled by verifying the SMS callback function.
    return (bool) $this->smsCallback();
  }

  /**
   * {@inheritdoc}
   */
  public function isTfaEnabled() {
    // Check if TFA and SMS are enabled in the configuration.
    return $this->configFactory->get('tfa.settings')->get('enabled') && $this->isSmsEnabled();
  }

  /**
   * {@inheritdoc}
   */
  public function checkFlood(PhoneNumber $phone_number, string $type = 'verification', array $queue = []) {
    // Use default queue settings if none are provided.
    if (!count($queue)) {
      $queue = [
        'verify_interval' => $this::VERIFY_ATTEMPTS_INTERVAL,
        'verify_count' => $this::VERIFY_ATTEMPTS_COUNT,
        'sms_interval' => $this::SMS_ATTEMPTS_INTERVAL,
        'sms_count' => $this::SMS_ATTEMPTS_COUNT,
      ];
    }

    switch ($type) {
      case 'verification':
        // Check if the verification attempts are within the allowed limit.
        return $this->flood->isAllowed('phonenumber_verification', $queue['verify_count'], $queue['verify_interval'] * 60, $this->phoneValidator->getCallableNumber($phone_number));

      case 'sms':
        // Check if the SMS sending attempts are within the allowed limit.
        return $this->flood->isAllowed('phonenumber_verification_sms', $queue['sms_count'], $queue['sms_interval'], $this->phoneValidator->getCallableNumber($phone_number)) &&
               $this->flood->isAllowed('phonenumber_verification_sms_ip', $queue['sms_count'] * 5, $queue['sms_interval'] * 5);

      default:
        return TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function generateVerificationCode($length = 4) {
    // Generate a random verification code of the specified length.
    return str_pad((string) rand(0, pow(10, $length)), $length, '0', STR_PAD_LEFT);
  }

  /**
   * {@inheritdoc}
   */
  public function registerVerificationCode(PhoneNumber $phone_number, $code) {
    $time = time();
    // Generate a unique token using CSRF token generator.
    $token = $this->tokenGenerator
      ->get(rand(0, 999999999) . $time . 'phonenumber verification token' . $this->phoneValidator->getCallableNumber($phone_number));

    // Generate a hash for the verification code using the token.
    $hash = $this->codeHash($phone_number, $token, $code);

    // Insert the token and hashed verification code into the database.
    $this->database->insert('phonenumber_verification')
      ->fields([
        'token' => $token,
        'timestamp' => $time,
        'verification_code' => $hash,
      ])
      ->execute();

    return $token;
  }

  /**
   * {@inheritdoc}
   */
  public function sendVerification(PhoneNumber $phone_number, $message, $code, array $token_data = []) {
    // Replace placeholders in the message with actual values.
    $message = $this->t('@message', ['@message' => $message]);
    $message = str_replace('!code', $code, $message);
    $message = str_replace('!site_name', $this->configFactory->get('system.site')->get('name'), $message);

    // Replace tokens in the message.
    $message = $this->token->replace($message, $token_data);

    // Register flood events for SMS sending.
    $this->flood->register('phonenumber_verification_sms', $this::SMS_ATTEMPTS_INTERVAL, $this->phoneValidator->getCallableNumber($phone_number));
    $this->flood->register('phonenumber_verification_sms_ip', $this::SMS_ATTEMPTS_INTERVAL * 5);

    // Send the SMS message.
    if ($this->sendSms($this->phoneValidator->getCallableNumber($phone_number), $message)) {
      // If the SMS is sent successfully, register the verification code.
      $token = $this->registerVerificationCode($phone_number, $code);

      // Store the token and verification status in the session.
      $_SESSION['phonenumber_verification'][$this->phoneValidator->getCallableNumber($phone_number)] = [
        'token' => $token,
        'verified' => FALSE,
      ];

      return $token;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function verifyCode(PhoneNumber $phone_number, $code, $token = NULL) {
    // Get the token if not provided.
    $token = $token ? $token : $this->getToken($phone_number);
    if ($code && $token) {
      // Generate a hash for the verification code using the token.
      $hash = $this->codeHash($phone_number, $token, $code);

      // Query the database to check if the token and hash match.
      $query = $this->database->select('phonenumber_verification', 'v');
      $query->fields('v', ['token'])
        ->condition('token', $token)
        ->condition('timestamp', time() - (60 * 60 * 24), '>')
        ->condition('verification_code', $hash);
      $result = $query->execute()->fetchAssoc();

      if ($result) {
        // If the token and hash match, mark the phone number as verified.
        $_SESSION['phonenumber_verification'][$this->phoneValidator->getCallableNumber($phone_number)]['verified'] = TRUE;
        return TRUE;
      }

      // Register a flood event for failed verification attempts.
      $this->flood->register('phonenumber_verification', $this::VERIFY_ATTEMPTS_INTERVAL, $this->phoneValidator->getCallableNumber($phone_number));

      return FALSE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getToken(PhoneNumber $mobile_number) {
    // Get the token for the phone number from the session if it exists.
    if (!empty($_SESSION['phonenumber_verification'][$this->phoneValidator->getCallableNumber($mobile_number)]['token'])) {
      return $_SESSION['phonenumber_verification'][$this->phoneValidator->getCallableNumber($mobile_number)]['token'];
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function codeHash(PhoneNumber $phone_number, $token, $code) {
    // Hash verification code using phone number, secret, token, and code.
    $number = $this->phoneValidator->getCallableNumber($phone_number);
    $secret = $this->configFactory->getEditable('phonenumber_verification.settings')->get('verification_secret');
    return sha1("$number$secret$token$code");
  }

  /**
   * {@inheritdoc}
   */
  public function smsCallback() {
    // Get the callback function for sending SMS.
    $module_handler = $this->moduleHandler;
    $callback = [];

    if ($module_handler->moduleExists('sms')) {
      $callback = 'phonenumber_verification_send_sms';
    }
    // Allow other modules to alter the SMS callback.
    $module_handler->alter('phonenumber_verification_send_sms_callback', $callback);
    return is_callable($callback) ? $callback : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function sendSms($number, $message) {
    // Get the SMS callback function.
    $callback = $this->smsCallback();

    if (!$callback) {
      return FALSE;
    }

    // Call the SMS callback function to send the message.
    return call_user_func($callback, $number, $message);
  }

  /**
   * {@inheritdoc}
   */
  public function tfaAccountNumber($uid) {
    // Load the user entity.
    $user = $this->load($uid);
    $field_name = $this->getTfaField();

    // Check if TFA is enabled and if the user has a TFA-enabled phone number.
    if (
      $this->isTfaEnabled() &&
      $field_name &&
      !empty($user->get($field_name)->getValue()[0]['value']) &&
      !empty($user->get($field_name)->getValue()[0]['tfa'])
    ) {
      return $user->get($field_name)->getValue()[0]['value'];
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getTfaField() {
    // Get the TFA field configuration from the settings.
    $tfa_field = $this->configFactory->get('phonenumber_verification.settings')
      ->get('tfa_field');
    $user_fields = $this->fieldMananger->getFieldDefinitions('user', 'user');
    return $this->isTfaEnabled() && !empty($user_fields[$tfa_field]) ? $tfa_field : '';
  }

  /**
   * {@inheritdoc}
   */
  public function setTfaField($field_name) {
    // Set the TFA field configuration in the settings.
    $this->configFactory->getEditable('phonenumber_verification.settings')
      ->set('tfa_field', $field_name)
      ->save(TRUE);
  }

}
