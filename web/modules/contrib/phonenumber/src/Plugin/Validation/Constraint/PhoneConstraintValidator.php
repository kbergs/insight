<?php

namespace Drupal\phonenumber\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\phonenumber\Exception\PhoneException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates a phone number.
 *
 * Validates:
 *   - Number validity.
 *   - Allowed country.
 *   - Verification flood.
 *   - Phone number verification.
 *   - Uniqueness.
 */
class PhoneConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The country manager.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected CountryManagerInterface $countryManager;

  /**
   * Constructs a new PhoneConstraintValidator instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Locale\CountryManagerInterface $country_manager
   *   The country manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CountryManagerInterface $country_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->countryManager = $country_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('country_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($item, Constraint $constraint) {
    /** @var \Drupal\phonenumber\Plugin\Field\FieldType\PhoneItem $item */
    $values = $item->getValue();
    $required = $item->getFieldDefinition()->isRequired();

    // Check if the field is required and if the phone number is provided.
    if ((!$required && isset($values['local_number']) && empty(trim($values['local_number']))) || (empty($values['phone_number']) && empty($values['local_number']))) {
      return;
    }

    $field = $item->getFieldDefinition();

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $item->getEntity();
    $entity_type = $entity->getEntityType()->getSingularLabel();
    $field_label = $field->getLabel();
    $unique = $field->getFieldStorageDefinition()
      ->getSetting('unique');

    // Get the list of all countries from the country manager.
    $countries = $this->countryManager->getList();

    // Initial allowed country with all countries.
    $allowed_countries = $countries;

    // Get the type of allowed and list of countries from settings.
    $allowed_type = $field->getSetting('allowed');
    $country_list = $field->getSetting('countries');

    // Check if the allowed type is not 'all'
    // and if country_list is a non-empty array.
    if ($allowed_type != 'all' && is_array($country_list) && count($country_list)) {
      // Detect the case format of the keys in the $countries array.
      $first_key = array_key_first($countries);
      if (ctype_upper($first_key)) {
        // If the first key is in uppercase,
        // convert the keys in $country_list to uppercase.
        $country_list = array_map('strtoupper', $country_list);
      }
      elseif (ctype_lower($first_key)) {
        // If the first key is in lowercase,
        // convert the keys in $country_list to lowercase.
        $country_list = array_map('strtolower', $country_list);
      }
      else {
        // If the first key is in mixed case (e.g., ucfirst),
        // convert the keys in $country_list accordingly.
        $country_list = array_map(function ($key) {
          return ucfirst(strtolower($key));
        }, $country_list);
      }

      // Determine the list of allowed countries based on
      // the allowed type and list of countries from settings.
      if ($allowed_type == 'include') {
        // If the setting is 'include', get the countries that
        // are in both $countries and $country_list.
        $allowed_countries = array_intersect_key($countries, array_flip($country_list));
      }
      else {
        // If the setting is 'exclude', get the countries
        // that are in $countries but not in $country_list.
        $allowed_countries = array_diff_key($countries, array_flip($country_list));
      }
    }

    try {
      $phone_number = $item->getPhoneNumber();
      $country_iso2 = $item->getCountryIso2();
      $display_number = $phone_number;

      // Check if the field is required and the local number is provided.
      if ($required && isset($values['local_number']) && empty(trim($values['local_number']))) {
        $this->context->addViolation($constraint->required, [
          '@field_name' => $field_label,
        ]);
      }
      // Check if the phone number and country code are valid.
      elseif (($phone_number && !$country_iso2) || ($allowed_countries && !in_array(mb_strtoupper($country_iso2), array_keys($allowed_countries)))) {
        $country_name = $country_iso2 ? $item->getCountryName() : $this->t('Unknown');
        $this->context->addViolation($constraint->allowedCountry, [
          '@value' => $country_name,
          '@field_name' => mb_strtolower($field_label),
        ]);
      }
      // Check for uniqueness.
      else {
        if ($unique && !$item->isUnique()) {
          $this->context->addViolation($constraint->unique, [
            '@value' => $display_number,
            '@entity_type' => $entity_type,
            '@field_name' => mb_strtolower($field_label),
          ]);
        }
      }
    }
    catch (PhoneException $e) {
      // Handle phone number validation exception.
      $this->context->addViolation($constraint->validity, [
        '@value' => $values['local_number'],
        '@entity_type' => $entity_type,
        '@field_name' => mb_strtolower($field_label),
        '@message' => $this->t('Unexpected error for @field_name: @message', [
          '@field_name' => mb_strtolower($field_label),
          '@message' => $e->getMessage(),
        ]),
      ]);
    }
  }

}
