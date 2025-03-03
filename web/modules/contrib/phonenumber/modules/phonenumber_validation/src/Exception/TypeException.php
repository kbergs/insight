<?php

namespace Drupal\phonenumber_validation\Exception;

/**
 * An exception thrown when the number is found to be of an invalid type.
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
   *   (optional) The previous exception used for the exception chaining.
   */
  public function __construct($message = "", $type = NULL, $code = 0, \Exception $previous = NULL) {
    parent::__construct($message, $code, $previous);

    $this->type = $type;
  }

  /**
   * Get the invalid phone number's type.
   *
   * @return int|null
   *   The invalid phone number's type or NULL if not set.
   */
  public function getType() {
    return $this->type;
  }

}
