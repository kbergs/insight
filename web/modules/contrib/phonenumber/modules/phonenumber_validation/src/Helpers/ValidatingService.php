<?php

declare(strict_types=1);

namespace Drupal\phonenumber_validation\Helpers;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Class Validating Service.
 *
 * Provides utility functions for validating and formatting phone numbers.
 */
class ValidatingService implements IsValidInterface {

  /**
   * Validates an international phone number.
   *
   * @param string $number
   *   The phone number to validate.
   *
   * @return bool
   *   TRUE if the number is valid, FALSE otherwise.
   */
  public function isValidNumber(string $number): bool {
    $phoneUtil = PhoneNumberUtil::getInstance();

    try {
      $parseNumber = $phoneUtil->parse($number);
      return $phoneUtil->isValidNumber($parseNumber);
    }
    catch (NumberParseException $e) {
      // Log the exception message.
      \Drupal::logger('phonenumber')->debug($e->getMessage());
      return FALSE;
    }
  }

  /**
   * Formats an international phone number to E164 format.
   *
   * @param string $number
   *   The phone number to format.
   *
   * @return string
   *   The formatted phone number, or the original number if formatting fails.
   */
  public function formatNumber(string $number): string {
    $phoneUtil = PhoneNumberUtil::getInstance();

    try {
      $numberProto = $phoneUtil->parse($number);
      return $phoneUtil->format($numberProto, PhoneNumberFormat::E164);
    }
    catch (NumberParseException $e) {
      // Log the exception message with details about the problem.
      \Drupal::logger('phonenumber')->error('Problem formatting number: @number. The error given was @error', [
        '@number' => $number,
        '@error'  => $e->getMessage(),
      ]);
      return $number;
    }
  }

}
