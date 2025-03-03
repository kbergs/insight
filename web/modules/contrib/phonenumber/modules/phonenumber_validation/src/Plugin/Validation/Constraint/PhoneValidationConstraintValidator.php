<?php

namespace Drupal\phonenumber_validation\Plugin\Validation\Constraint;

use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\phonenumber_validation\PhoneValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the PhoneValidationConstraint constraint.
 */
class PhoneValidationConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Phone number validator service.
   *
   * @var \Drupal\phonenumber_validation\PhoneValidator
   */
  protected $phoneValidator;

  /**
   * Constructs a new PhoneValidationConstraintValidator.
   *
   * @param \Drupal\phonenumber_validation\PhoneValidator $phone_validator
   *   Phone number validation service.
   */
  public function __construct(PhoneValidator $phone_validator) {
    $this->phoneValidator = $phone_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('phonenumber_validation.validator'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    try {
      $phone = $value->getValue();
    }
    catch (\InvalidArgumentException $e) {
      return;
    }

    /** @var \Drupal\field\Entity\FieldConfig $field */
    $field = $value->getFieldDefinition();

    // Check if the field is empty and not required.
    if (!$field->isRequired() && empty(trim($phone['local_number']))) {
      return;
    }

    // Check if the field allows storing third party settings.
    if (!$field instanceof ThirdPartySettingsInterface) {
      return;
    }

    $settings = $field->getThirdPartySettings('phonenumber_validation');
    // If no settings are found, skip validation.
    if (empty($settings)) {
      return;
    }

    // Validate number against validation settings.
    if (!$this->phoneValidator->isValid(
      $phone['phone_number'],
      $field->getThirdPartySetting('phonenumber_validation', 'format'),
      $field->getThirdPartySetting('phonenumber_validation', 'country')
    )) {
      $this->context->addViolation($constraint->message, ['@number' => $phone['phone_number']]);
    }
  }

}
