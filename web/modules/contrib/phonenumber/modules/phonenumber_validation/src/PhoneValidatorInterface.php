<?php

namespace Drupal\phonenumber_validation;

use libphonenumber\PhoneNumber;

/**
 * Performs phone field validation interface.
 */
interface PhoneValidatorInterface {

  /**
   * Specifies the sms_phone number was verified.
   */
  const PHONE_NUMBER_VERIFIED = 1;

  /**
   * Specifies the sms_phone number was not verified.
   */
  const PHONE_NUMBER_NOT_VERIFIED = 0;

  /**
   * Specifies the tfa was enabled.
   */
  const PHONE_NUMBER_TFA_ENABLED = 1;

  /**
   * Specifies the tfa was disabled.
   */
  const PHONE_NUMBER_TFA_DISABLED = 0;

  /**
   * Get libphonenumber Util instance.
   *
   * @return \libphonenumber\PhoneNumberUtil
   *   Libphonenumber utility instance.
   */
  public function libUtil();

  /**
   * Check if number is valid for given settings.
   *
   * @param string $value
   *   Phone number.
   * @param int $format
   *   Supported input format.
   * @param array $country
   *   (optional) List of supported countries. If empty all countries are valid.
   *
   * @return bool
   *   Boolean representation of validation result.
   */
  public function isValid($value, $format, array $country = []);

  /**
   * Check phone number validity.
   *
   * @param string $number
   *   Number.
   * @param null|string $country
   *   (Optional) Country.
   * @param null|string $extension
   *   (Optional) Extension.
   * @param null|array $types
   *   (Optional) An array of allowed PhoneNumberType constants.
   *   Only consider number valid if it is one of these types.
   *   See \libphonenumber\PhoneNumberType for available type contants.
   *
   * @throws \Drupal\phone_number\Exception\CountryException
   *   Thrown if phone number is not valid because its country and the country
   *   provided do not match.
   * @throws \Drupal\phone_number\Exception\ParseException
   *   Thrown if phone number could not be parsed, and is thus invalid.
   * @throws \Drupal\phone_number\Exception\TypeException
   *   Thrown if phone number is an invalid type.
   *
   * @return \libphonenumber\PhoneNumber
   *   Libphonenumber Phone number object.
   */
  public function checkPhoneNumber($number, $country = NULL, $extension = NULL, $types = NULL);

  /**
   * Get a phone number object.
   *
   * @param string $number
   *   Number.
   * @param null|string $country
   *   Country.
   * @param null|string $extension
   *   Extension.
   * @param null|string $types
   *   Type.
   *
   * @return \libphonenumber\PhoneNumber|null
   *   Phone Number object if successful.
   */
  public function getPhoneNumber($number, $country = NULL, $extension = NULL, $types = NULL);

  /**
   * Get local number.
   *
   * Local number is the national number with the national dialling prefix
   * prepended when required/appropriate.
   *
   * @param \libphonenumber\PhoneNumber $phone_number
   *   Phone number object.
   * @param bool $strip_non_digits
   *   Strip non-digits from the local number.  Optioinal, defaults to FALSE.
   * @param bool $strip_extension
   *   Strip extension from the local number.  Optioinal, defaults to TRUE.
   *
   * @return string
   *   Local number.
   */
  public function getLocalNumber(PhoneNumber $phone_number, $strip_non_digits = FALSE, $strip_extension = TRUE);

  /**
   * Get international number.
   *
   * @param \libphonenumber\PhoneNumber $phone_number
   *   Phone number object.
   *
   * @return string
   *   E.164 formatted number.
   */
  public function getCallableNumber(PhoneNumber $phone_number);

  /**
   * Get country code.
   *
   * @param \libphonenumber\PhoneNumber $phone_number
   *   Phone number object.
   *
   * @return string
   *   Country code.
   */
  public function getCountry(PhoneNumber $phone_number);

  /**
   * Get country display name given country code.
   *
   * @param string $country
   *   Country code.
   *
   * @return string
   *   Country name.
   */
  public function getCountryName($country);

  /**
   * Gets the country phone number prefix given a country code.
   *
   * @param \libphonenumber\PhoneNumber $phone_number
   *   Phone number object.
   *
   * @return int
   *   Country phone number prefix (e.g. 972).
   */
  public function getCountryCode(PhoneNumber $phone_number);

  /**
   * Get all supported countries.
   *
   * @param array $filter
   *   Limit options to the ones in the filter.
   *   e.g. ['IL' => 'IL', 'US' => 'US'].
   * @param bool $show_country_names
   *   Whether to show full country name instead of country codes.
   *
   * @return array
   *   Array of options, with country code as keys.
   *   e.g. ['IL' => 'IL (+972)'].
   */
  public function getCountryOptions(array $filter = [], $show_country_names = FALSE);

  /**
   * Get list of countries with country code and leading digits.
   *
   * @return array
   *   Flatten array you can use it directly in select lists.
   */
  public function getCountryList();

}
