<?php

namespace Drupal\phonenumber\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validates Phone fields.
 *
 * Includes validation for:
 *   - Number validity.
 *   - Allowed country.
 *   - Verification flood.
 *   - Phone number verification.
 *   - Uniqueness.
 *
 * @Constraint(
 *   id = "Phone",
 *   label = @Translation("Phone number constraint", context = "Validation"),
 * )
 */
class PhoneConstraint extends Constraint {

  /**
   * Error message for required field.
   *
   * @var string
   */
  public $required = '@field_name field is required.';

  /**
   * Error message for unique constraint violation.
   *
   * @var string
   */
  public $unique = 'A @entity_type with @field_name @value already exists.';

  /**
   * Error message for invalid phone number.
   *
   * @var string
   */
  public $validity = 'The @field_name @value is invalid for the following reason: @message.';

  /**
   * Error message for disallowed country.
   *
   * @var string
   */
  public $allowedCountry = 'The country of @value provided for @field_name is not allowed in the list of countries.';

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    // Return the name of the validator class for this constraint.
    return '\Drupal\phonenumber\Plugin\Validation\Constraint\PhoneConstraintValidator';
  }

}
