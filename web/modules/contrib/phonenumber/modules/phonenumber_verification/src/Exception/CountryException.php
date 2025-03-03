<?php

namespace Drupal\phone_number\Exception;

/**
 * Exception thrown for country mismatch in phone number.
 */
class CountryException extends PhoneNumberException {

  /**
   * The invalid phone number's 2-letter country code.
   *
   * @var string
   */
  protected $country;

  /**
   * Constructs a new CountryException instance.
   *
   * @param string $message
   *   (optional) The Exception message to throw.
   * @param string $country
   *   (optional) The invalid phone number's 2-letter country code.
   * @param int $code
   *   (optional) The Exception code.
   * @param \Exception $previous
   *   (optional) The previous exception used for the exception chaining.
   */
  public function __construct($message = "", $country = NULL, $code = 0, \Exception $previous = NULL) {
    // Call the parent constructor to initialize the base exception class.
    parent::__construct($message, $code, $previous);

    // Set the invalid country code.
    $this->country = $country;
  }

  /**
   * Get the invalid phone number's country code.
   *
   * @return string
   *   The invalid phone number's 2-letter country code.
   */
  public function getCountry() {
    return $this->country;
  }

}
