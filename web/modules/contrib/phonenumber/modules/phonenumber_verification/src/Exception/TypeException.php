<?php

namespace Drupal\phone_number\Exception;

/**
 * Exception thrown for invalid phone number type.
 */
class TypeException extends PhoneNumberException {

  /**
   * The invalid phone number's type.
   *
   * @var int
   *
   * @see \libphonenumber\PhoneNumberType
   */
  protected $type;

  /**
   * Constructs a new TypeException instance.
   *
   * @param string $message
   *   (optional) The Exception message to throw.
   * @param int $type
   *   (optional) The invalid phone number's type. A
   *   \libphonenumber\PhoneNumberType constant.
   * @param int $code
   *   (optional) The Exception code.
   * @param \Exception $previous
   *   (optional) The previous exception used for exception chaining.
   */
  public function __construct($message = "", $type = NULL, $code = 0, \Exception $previous = NULL) {
    // Call the parent constructor to initialize the base exception class.
    parent::__construct($message, $code, $previous);

    // Set the invalid phone number type.
    $this->type = $type;
  }

  /**
   * Get the invalid phone number's type.
   *
   * @return int
   *   The invalid phone number's type.
   */
  public function getType() {
    return $this->type;
  }

}
