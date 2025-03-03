<?php

namespace Drupal\phonenumber_validation\Plugin\Validation\Constraint;

use Drupal\phonenumber\Plugin\Validation\Constraint\PhoneConstraint;

/**
 * Phone validation constraint.
 *
 * @Constraint(
 *   id = "PhoneValidation",
 *   label = @Translation("Phone validation", context = "Validation")
 * )
 */
class PhoneValidationConstraint extends PhoneConstraint {

  /**
   * Validation message for invalid phone numbers.
   *
   * @var string
   */
  public $message = "@number is not a valid phone number.";

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return '\Drupal\phonenumber_validation\Plugin\Validation\Constraint\PhoneValidationConstraintValidator';
  }

}
