<?php

namespace Drupal\phonenumber_verification\Plugin\Validation\Constraint;

use Drupal\phonenumber\Plugin\Validation\Constraint\PhoneConstraint;

/**
 * Phone verification constraint.
 *
 * Includes verification for:
 *   - Number validity.
 *   - Allowed country.
 *   - Uniqueness.
 *   - Verification flood.
 *   - Phone number verification.
 *
 * @Constraint(
 *   id = "PhoneVerification",
 *   label = @Translation("Phone verification constraint", context = "Validation"),
 * )
 */
class PhoneVerificationConstraint extends PhoneConstraint {

  /**
   * The flood message.
   *
   * This message is shown when there are too many verification attempts
   * for a given phone number within a short period.
   *
   * @var string
   */
  public $flood = 'Too many verification attempts for @field_name @value, please try again in a few hours.';

  /**
   * The verification validation message.
   *
   * This message is shown when the verification code entered is invalid.
   *
   * @var string
   */
  public $verification = 'Invalid verification code for @field_name @value.';

  /**
   * The verify required validation message.
   *
   * This message is shown when a phone number needs to be verified
   * but has not been verified yet.
   *
   * @var string
   */
  public $verifyRequired = 'The @field_name @value must be verified.';

  /**
   * Specify the validator class to validate this constraint.
   *
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return '\Drupal\phonenumber_verification\Plugin\Validation\Constraint\PhoneVerificationConstraintValidator';
  }

}
