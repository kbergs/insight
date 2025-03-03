<?php

namespace Drupal\phonenumber\Exception;

/**
 * Exception thrown during phone number testing if the number is invalid.
 */
class PhoneException extends \RuntimeException {

  /**
   * An Invalid phone number error code.
   */
  const ERROR_INVALID_NUMBER = 1;

  /**
   * A phone number of the wrong type error code.
   */
  const ERROR_WRONG_TYPE = 2;

  /**
   * A phone number from an unauthorized country error code.
   */
  const ERROR_WRONG_COUNTRY = 3;

  /**
   * A missing phone number error code.
   */
  const ERROR_NO_NUMBER = 4;

}
