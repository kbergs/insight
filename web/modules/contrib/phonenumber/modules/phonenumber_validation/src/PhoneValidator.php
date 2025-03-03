<?php

namespace Drupal\phonenumber_validation;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\phonenumber_validation\Exception\CountryException;
use Drupal\phonenumber_validation\Exception\ParseException;
use Drupal\phonenumber_validation\Exception\PhoneNumberException;
use Drupal\phonenumber_validation\Exception\TypeException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

/**
 * Performs phone field validation class.
 */
class PhoneValidator implements PhoneValidatorInterface {

  /**
   * The phone number utility library.
   *
   * @var \libphonenumber\PhoneNumberUtil
   */
  public $phoneUtils;

  /**
   * Country Manager service.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  public $countryManager;

  /**
   * Validator constructor.
   */
  public function __construct(CountryManagerInterface $country_manager) {
    $this->phoneUtils = PhoneNumberUtil::getInstance();
    $this->countryManager = $country_manager;
  }

  /**
   * Strip non-digits from a string.
   *
   * @param string $string
   *   The input string, potentially with non-digits.
   *
   * @return string
   *   The input string with non-digits removed.
   */
  protected function stripNonDigits($string) {
    return preg_replace('~\D~', '', $string);
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
  public function isValid($value, $format, array $country = []) {

    try {
      // Get default country.
      $default_region = ($format == PhoneNumberFormat::NATIONAL) ? reset($country) : NULL;
      // Parse to object.
      $number = $this->phoneUtils->parse($value, $default_region);
    }
    catch (\Exception $e) {
      // If number could not be parsed by phone utils that's a one good reason
      // to say it's not valid.
      return FALSE;
    }
    // Perform basic phonenumber validation.
    if (!$this->phoneUtils->isValidNumber($number)) {
      return FALSE;
    }

    // If country array is not empty and default region can be loaded
    // do region matching validation.
    // This condition is always TRUE for national phone number format.
    if (!empty($country) && $default_region = $this->phoneUtils->getRegionCodeForNumber($number)) {
      // Check if number's region matches list of supported countries.
      if (array_search($default_region, $country) === FALSE) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function checkPhoneNumber($number, $country = NULL, $extension = NULL, $types = NULL) {

    try {
      if (!$number) {
        return FALSE;
      }
    }
    catch (NumberParseException $e) {
      throw new PhoneNumberException('Phone number is empty.');
    }

    try {
      /** @var \libphonenumber\PhoneNumber $phone_number */
      $phone_number = $this->phoneUtils->parse($number, $country);
      if ($extension) {
        $phone_number->setExtension($extension);
      }
    }
    catch (NumberParseException $e) {
      throw new ParseException('Invalid number', 0, $e);
    }

    try {
      $number_country = $this->phoneUtils->getRegionCodeForNumber($phone_number);

      if ($country && ($number_country != $country)) {
        return $number_country;
      }
    }
    catch (CountryException $e) {
      throw new CountryException("Phone number's country and the country provided do not match", $e);
    }

    try {
      $number_type = $this->phoneUtils->getNumberType($phone_number);

      if ($types && !in_array($number_type, $types)) {
        return $number_type;
      }
    }
    catch (TypeException $e) {
      throw new TypeException("Phone number's type is not allowed", $e);
    }

    return $phone_number;
  }

  /**
   * {@inheritdoc}
   */
  public function getPhoneNumber($number, $country = NULL, $extension = NULL, $types = NULL) {
    try {
      return $this->checkPhoneNumber($number, $country, $types);
    }
    catch (PhoneNumberException $e) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLocalNumber(PhoneNumber $phone_number, $strip_non_digits = FALSE, $strip_extension = TRUE) {
    if ($strip_extension) {
      $copy = clone $phone_number;
      $copy->clearExtension();
      $local = $this->phoneUtils->format($copy, PhoneNumberFormat::NATIONAL);
    }
    else {
      $local = $this->phoneUtils->format($phone_number, PhoneNumberFormat::NATIONAL);
    }

    if ($local && $strip_non_digits) {
      $local = $this->stripNonDigits($local);
    }

    return $local;
  }

  /**
   * {@inheritdoc}
   */
  public function getCallableNumber(PhoneNumber $phone_number) {
    return $phone_number ? $this->phoneUtils->format($phone_number, PhoneNumberFormat::E164) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCountry(PhoneNumber $phone_number) {
    return $phone_number ? $this->phoneUtils->getRegionCodeForNumber($phone_number) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCountryName($country) {
    $countries = $this->countryManager->getList();

    return !empty($countries[$country]) ? $countries[$country] : $country;
  }

  /**
   * {@inheritdoc}
   */
  public function getCountryCode(PhoneNumber $phone_number) {
    $country = $this->getCountry($phone_number);
    return $this->phoneUtils->getCountryCodeForRegion($country);
  }

  /**
   * {@inheritdoc}
   */
  public function getCountryOptions(array $filter = [], $show_country_names = FALSE) {
    $libUtil = $this->phoneUtils;
    $regions = $libUtil->getSupportedRegions();
    $countries = [];

    foreach ($regions as $country) {
      $code = $libUtil->getCountryCodeForRegion($country);
      if (!$filter || !empty($filter[$country])) {
        $name = $this->getCountryName($country);
        $countries[$country] = ($show_country_names && $name) ? "$name (+$code)" : "$country (+$code)";
      }
    }

    asort($countries);
    return $countries;
  }

  /**
   * {@inheritdoc}
   */
  public function getCountryList() {
    $regions = [];
    foreach ($this->countryManager->getList() as $region => $name) {
      $region_meta = $this->phoneUtils->getMetadataForRegion($region);
      if (is_object($region_meta)) {
        $regions[$region] = (string) new FormattableMarkup('@country - +@country_code', [
          '@country' => $name,
          '@country_code' => $region_meta->getCountryCode(),
        ]);
      }
    }
    return $regions;
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeOptions() {
    $options = [];
    foreach (PhoneNumberType::values() as $type => $label) {
      switch ($type) {
        case PhoneNumberType::FIXED_LINE:
          $options[$type] = $this->t('Fixed line');
          break;

        case PhoneNumberType::MOBILE:
          $options[$type] = $this->t('Mobile');
          break;

        case PhoneNumberType::FIXED_LINE_OR_MOBILE:
          $options[$type] = $this->t('Fixed line or mobile');
          break;

        case PhoneNumberType::TOLL_FREE:
          $options[$type] = $this->t('Toll-free');
          break;

        case PhoneNumberType::PREMIUM_RATE:
          $options[$type] = $this->t('Premium rate');
          break;

        case PhoneNumberType::SHARED_COST:
          $options[$type] = $this->t('Shared cost');
          break;

        case PhoneNumberType::VOIP:
          $options[$type] = $this->t('VOIP');
          break;

        case PhoneNumberType::PERSONAL_NUMBER:
          $options[$type] = $this->t('Personal number');
          break;

        case PhoneNumberType::PAGER:
          $options[$type] = $this->t('Pager');
          break;

        case PhoneNumberType::UAN:
          $options[$type] = $this->t('UAN');
          break;

        case PhoneNumberType::UNKNOWN:
          $options[$type] = $this->t('Unknown');
          break;

        case PhoneNumberType::EMERGENCY:
          $options[$type] = $this->t('Emergency');
          break;

        case PhoneNumberType::VOICEMAIL:
          $options[$type] = $this->t('Voicemail');
          break;

        case PhoneNumberType::SHORT_CODE:
          $options[$type] = $this->t('Short code');
          break;

        case PhoneNumberType::STANDARD_RATE:
          $options[$type] = $this->t('Standard rate');
          break;

        default:
          // At the time of writing this is everyting, but let's make sure we're
          // covered if types are ever added/changed in the upstream library.
          $options[$type] = $label;
      }
    }
    return $options;
  }

}
