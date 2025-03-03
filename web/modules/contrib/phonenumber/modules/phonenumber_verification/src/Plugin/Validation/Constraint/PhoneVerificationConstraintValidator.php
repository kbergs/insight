<?php

namespace Drupal\phonenumber_verification\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\phonenumber_validation\Exception\PhoneNumberException;
use Drupal\phonenumber_validation\PhoneValidatorInterface;
use Drupal\phonenumber_verification\PhoneVerifierInterface;
use libphonenumber\PhoneNumberFormat;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the PhoneVerificationConstraint constraint.
 *
 * Validates:
 *   - Number validity.
 *   - Allowed country.
 *   - Uniqueness.
 *   - Verification flood.
 *   - Phone number verification.
 */
class PhoneVerificationConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The phone field validation utility.
   *
   * @var \Drupal\phonenumber_validation\PhoneValidatorInterface
   */
  protected $phoneValidator;

  /**
   * The phone field verification utility.
   *
   * @var \Drupal\phonenumber_verification\PhoneVerifierInterface
   */
  protected $phoneVerifier;

  /**
   * Constructs a new PhoneVerificationConstraintValidator.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\phonenumber_validation\PhoneValidatorInterface $phone_validator
   *   The phone field validation utility.
   * @param \Drupal\phonenumber_verification\PhoneVerifierInterface $phone_verifier
   *   The phone field verification utility.
   */
  public function __construct(AccountProxyInterface $current_user, PhoneValidatorInterface $phone_validator, PhoneVerifierInterface $phone_verifier) {
    $this->currentUser = $current_user;
    $this->phoneValidator = $phone_validator;
    $this->phoneVerifier = $phone_verifier;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('phonenumber_validation.validator'),
      $container->get('phonenumber_verification.verifier')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($item, Constraint $constraint) {
    /** @var \Drupal\phonenumber_verification\Plugin\Field\FieldType\PhoneVerificationItem $item */
    $values = $item->getValue();
    $required = $item->getFieldDefinition()->isRequired();

    // Skip validation if the field is not required and empty.
    if ((!$required && empty($values['local_number'])) || (empty($values['phone_number']) && empty($values['local_number']))) {
      return;
    }

    // Get the field label and entity information for error messages.
    $field_label = $item->getFieldDefinition()->getLabel();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $item->getEntity();
    $entity_type = $entity->getEntityType()->getSingularLabel();

    // Get allowed countries for phone numbers from the field settings.
    $allowed_countries = $item->getFieldDefinition()->getThirdPartySetting('phonenumber_validation', 'country');

    // Get verification settings from the field settings.
    $verify = $item->getFieldDefinition()->getSetting('verify');

    // Check if the phone number needs to be unique.
    $unique = $item->getFieldDefinition()->getFieldStorageDefinition()->getSetting('unique');

    // Check if Two-Factor Authentication (TFA) is enabled for this field.
    $tfa = $item->get('tfa')->getValue();

    try {
      // Get the phone number object, throwing an exception if it is invalid.
      $phone_number = $item->getPhoneNumber(TRUE);

      // Get the country of the phone number.
      $country = $this->phoneValidator->getCountry($phone_number);

      // Format the phone number for display in error messages.
      $display_number = $this->phoneValidator->libUtil()->format($phone_number, PhoneNumberFormat::NATIONAL);

      // Check if the phone number is from an allowed country.
      if ($allowed_countries && !in_array($country, $allowed_countries)) {
        $this->context->addViolation($constraint->allowedCountry, [
          '@value' => $this->phoneValidator->getCountryName($country),
          '@field_name' => mb_strtolower($field_label),
        ]);
      }
      else {
        // Check if the current user has permission to bypass verification.
        $bypass_verification = $this->currentUser->hasPermission('bypass phonenumber verification requirement');

        // Attempt to verify the phone number.
        $verification = $item->verify();

        // Check for flood and verification errors.
        if ($verification === -1) {
          // Too many attempts to verify the phone number.
          $this->context->addViolation($constraint->flood, [
            '@value' => $display_number,
            '@field_name' => mb_strtolower($field_label),
          ]);
        }
        elseif ($verification === FALSE) {
          // Verification failed.
          $this->context->addViolation($constraint->verification, [
            '@value' => $display_number,
            '@field_name' => mb_strtolower($field_label),
          ]);
        }
        elseif (!$verification && !$bypass_verification && ($tfa || $verify === PhoneVerifierInterface::PHONE_NUMBER_VERIFY_REQUIRED)) {
          // Verification is required but not completed.
          $this->context->addViolation($constraint->verifyRequired, [
            '@value' => $display_number,
            '@entity_type' => $entity_type,
            '@field_name' => mb_strtolower($field_label),
          ]);
        }
        elseif ($unique && !$item->isUnique($unique)) {
          // The phone number must be unique, but it is not.
          $this->context->addViolation($constraint->unique, [
            '@value' => $display_number,
            '@entity_type' => $entity_type,
            '@field_name' => mb_strtolower($field_label),
          ]);
        }
      }
    }
    catch (PhoneNumberException $e) {
      // Handle phone number validation exceptions.
      $this->context->addViolation($constraint->validity, [
        '@value' => $values['local_number'],
        '@entity_type' => $entity_type,
        '@field_name' => mb_strtolower($field_label),
        '@message' => $e->getMessage(),
      ]);
    }
  }

}
