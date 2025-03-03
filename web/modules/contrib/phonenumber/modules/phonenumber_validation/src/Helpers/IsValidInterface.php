<?php

declare(strict_types=1);

namespace Drupal\phonenumber_validation\Helpers;

/**
 * Interface to validate and format phone numbers.
 */
interface IsValidInterface {

  /**
   * Validates whether the phone number is valid.
   *
   * @param string $number
   *   The phone number to validate.
   *
   * @return bool
   *   TRUE if the number is valid, FALSE otherwise.
   */
  public function isValidNumber(string $number): bool;

  /**
   * Formats the phone number.
   *
   * @param string $number
   *   The phone number to format.
   *
   * @return string
   *   The formatted phone number.
   */
  public function formatNumber(string $number): string;

}
