<?php

namespace Drupal\phonenumber\Plugin\Field\FieldFormatter;

/**
 * Plugin implementation of the 'phone_national' formatter.
 *
 * @FieldFormatter(
 *   id = "phone_national",
 *   label = @Translation("National number"),
 *   field_types = {
 *     "phone",
 *     "telephone"
 *   }
 * )
 */
class PhoneNationalFormatter extends PhoneInternationalFormatter {

  /**
   * The display format.
   *
   * @var string
   */
  public $phoneDisplayFormat = 'national';

}
