<?php

namespace Drupal\phonenumber_verification;

use libphonenumber\PhoneNumber;

/**
 * Performs phone field verification utility interface.
 */
interface PhoneVerifierInterface {

  /**
   * Phone number is not unique.
   */
  const PHONE_NUMBER_UNIQUE_NO = 0;

  /**
   * Phone number is unique.
   */
  const PHONE_NUMBER_UNIQUE_YES = 1;

  /**
   * Phone number is unique and verified.
   */
  const PHONE_NUMBER_UNIQUE_YES_VERIFIED = 2;

  /**
   * No verification required.
   */
  const PHONE_NUMBER_VERIFY_NONE = 'none';

  /**
   * Optional verification.
   */
  const PHONE_NUMBER_VERIFY_OPTIONAL = 'optional';

  /**
   * Required verification.
   */
  const PHONE_NUMBER_VERIFY_REQUIRED = 'required';

  /**
   * Default SMS message for verification.
   */
  const PHONE_NUMBER_DEFAULT_SMS_MESSAGE = "Your verification code from !site_name:\n!code";

  /**
   * Default verification code length.
   */
  const VERIFICATION_CODE_LENGTH = 4;

  /**
   * Interval for verification attempts.
   */
  const VERIFY_ATTEMPTS_INTERVAL = 60;

  /**
   * Count for verification attempts.
   */
  const VERIFY_ATTEMPTS_COUNT = 5;

  /**
   * Interval for SMS attempts.
   */
  const SMS_ATTEMPTS_INTERVAL = 60;

  /**
   * Count for SMS attempts.
   */
  const SMS_ATTEMPTS_COUNT = 1;

  /**
   * Specifies the phone number was verified.
   */
  const PHONE_NUMBER_VERIFIED = 1;

  /**
   * Specifies the phone number was not verified.
   */
  const PHONE_NUMBER_NOT_VERIFIED = 0;

  /**
   * Specifies TFA was enabled.
   */
  const PHONE_NUMBER_TFA_ENABLED = 1;

  /**
   * Specifies TFA was disabled.
   */
  const PHONE_NUMBER_TFA_DISABLED = 0;

  /**
   * Is the number already verified.
   *
   * @param \libphonenumber\PhoneNumber $phone_number
   *   Phone number object.
   *
   * @return bool
   *   TRUE if verified.
   */
  public function isVerified(PhoneNumber $phone_number);

  /**
   * Checks if SMS sending is enabled.
   *
   * @return bool
   *   TRUE if SMS sending is enabled, FALSE otherwise.
   */
  public function isSmsEnabled();

  /**
   * Checks if TFA is enabled.
   *
   * @return bool
   *   TRUE if TFA is enabled, FALSE otherwise.
   */
  public function isTfaEnabled();

  /**
   * Checks whether there are too many verification attempts against the number.
   *
   * @param \libphonenumber\PhoneNumber $phone_number
   *   Phone number object.
   * @param string $type
   *   Flood type, 'sms' or 'verification'.
   * @param array $queue
   *   Time interval and count for sending 'sms' or 'verification'.
   *
   * @return bool
   *   FALSE for too many attempts on this phone number, TRUE otherwise.
   */
  public function checkFlood(PhoneNumber $phone_number, string $type = 'verification', array $queue = []);

  /**
   * Generates a random numeric string.
   *
   * @param int $length
   *   Number of digits.
   *
   * @return string
   *   Code in length of $length.
   */
  public function generateVerificationCode($length = 4);

  /**
   * Registers code for phone number and returns its token.
   *
   * @param \libphonenumber\PhoneNumber $phone_number
   *   Phone number object.
   * @param string $code
   *   Access code.
   *
   * @return string
   *   43 character token.
   */
  public function registerVerificationCode(PhoneNumber $phone_number, $code);

  /**
   * Send verification code to phone number.
   *
   * @param \libphonenumber\PhoneNumber $phone_number
   *   Phone number object.
   * @param string $message
   *   Drupal translatable string.
   * @param string $code
   *   Code to send.
   * @param array $token_data
   *   Token variables to be used with token_replace().
   *
   * @return bool
   *   Success flag.
   */
  public function sendVerification(PhoneNumber $phone_number, $message, $code, array $token_data = []);

  /**
   * Verifies input code matches code sent to user.
   *
   * @param \libphonenumber\PhoneNumber $phone_number
   *   Phone number object.
   * @param string $code
   *   Input code.
   * @param string|null $token
   *   Verification token, if verification code was not sent in this session.
   *
   * @return bool
   *   TRUE if matches.
   */
  public function verifyCode(PhoneNumber $phone_number, $code, $token = NULL);

  /**
   * Gets token generated if verification code was sent.
   *
   * @param \libphonenumber\PhoneNumber $phone_number
   *   Phone number object.
   *
   * @return string|null
   *   A Drupal token (43 characters).
   */
  public function getToken(PhoneNumber $phone_number);

  /**
   * Generate hash given token and code.
   *
   * @param \libphonenumber\PhoneNumber $phone_number
   *   Phone number object.
   * @param string $token
   *   Token.
   * @param string $code
   *   Verification code.
   *
   * @return string
   *   Hash string.
   */
  public function codeHash(PhoneNumber $phone_number, $token, $code);

  /**
   * Gets SMS callback for sending SMS's.
   *
   * The callback should accept $number and $message, and return status
   * booleans.
   *
   * @return callable
   *   SMS callback.
   */
  public function smsCallback();

  /**
   * Sends an SMS, based on callback provided by smsCallback().
   *
   * @param string $number
   *   A callable number in international format.
   * @param string $message
   *   String message, after translation.
   *
   * @return bool
   *   SMS callback result, TRUE = success, FALSE otherwise.
   */
  public function sendSms($number, $message);

  /**
   * Gets account phone number if TFA was enabled for the user.
   *
   * @param int $uid
   *   User ID.
   *
   * @return string
   *   International number.
   */
  public function tfaAccountNumber($uid);

  /**
   * Gets the TFA field configuration.
   *
   * @return string
   *   Currently configured user field for TFA. '' if not set or TFA is not
   *   enabled.
   */
  public function getTfaField();

  /**
   * Sets the TFA field configuration.
   *
   * @param string $field_name
   *   User field name.
   */
  public function setTfaField($field_name);

}
