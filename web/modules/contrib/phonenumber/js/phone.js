/**
 * @file
 * Contains definition of the behaviour Phone Number.
 */

(function ($, Drupal, drupalSettings, once) {
  "use strict";

  Drupal.behaviors.phoneNumber = {
    attach: function (context, settings) {
      // Get phone field settings from drupalSettings.
      const options = drupalSettings.phone.options;

      // Process each phone number field only once.
      once("phoneNumber", ".form-phone").forEach((field) => {
        // Alter phone library options for the specific field.
        Drupal.behaviors.phoneNumber.alterPhoneLibraryOptions(field, options);

        // Initialize the phone library for the field
        // if it hasn't been processed yet.
        if (!$(field).hasClass('processed')) {
          Drupal.initPhoneNumber(field, options);
        }
      });
    },

    /**
     * Allow to alter phone library options.
     *
     * @param {Object} field
     *   The field element.
     * @param {Object} options
     *   The list of options to init intlTelInput.
     *
     * @return void
     */
    alterPhoneLibraryOptions: (field, options) => {}
  };

  /**
   * Initial phone number by widget settings.
   *
   * @param {Object} field
   *   The field element.
   * @param {Object} options
   *   The list of options to init intlTelInput.
   */
  Drupal.initPhoneNumber = function (field, options) {
    const input = field;
    // Create the jQuery element that will be used in the initial steps.
    let localFieldName = $(field).attr('name');
    let phoneFieldName = localFieldName.replace('local_number', 'phone_number');
    let codeFieldName = localFieldName.replace('local_number', 'country_code');
    let iso2FieldName = localFieldName.replace('local_number', 'country_iso2');

    const $phoneMasked = $(field);
    const $phoneNumber = $('[name="' + phoneFieldName + '"]');
    const $countryCode = $('[name="' + codeFieldName + '"]');
    const $countryIso2 = $('[name="' + iso2FieldName + '"]');
    const $phoneParent = $phoneMasked.closest('div.field--type-phone');
    const $phoneLabels = $phoneParent.find("label");
    const $errorStatus = $phoneParent.find(".phone-error-msg");

    // Set initialCountry by country iso2 default value for existing number.
    if ($countryIso2.length !== 0 && $countryIso2.val() !== '') {
      options.initialCountry = $countryIso2.val();
    }

    // Provide geoIP lookup function if initialCountry is set to 'auto'.
    if (options.initialCountry === 'auto') {
      options.geoIpLookup = function(callback) {
        let remoteApiUrl = options.geoLocationApi.url;
        if (typeof options.geoLocationApi.apiKey !== 'undefined' && options.geoLocationApi.apiKey.length) {
          remoteApiUrl = remoteApiUrl + options.geoLocationApi.apiKey;
        }
        fetch(remoteApiUrl)
          .then(function(response) {
            switch (options.geoLocationApi.type) {
              case 'text':
                return response.text();
              case 'json':
                return response.json();
            }
          })
          .then(function(data) {
            let countryCode = new Function('data', options.geoLocationApi.script);
            callback(countryCode(data));
          })
          .catch(function() { callback("us"); });
      }
    } else {
      options.geoIpLookup = null;
    }

    // Additional options to expand the mask functionality and remove
    // the zero from beginning of local number for phone library.
    let removedZero = options.removeStartZero;
    let isFormatter = options.maskFormatter;
    let ValidErrors = options.showError;

    // Reset function to reset error state on validation.
    const reset = function() {
      $phoneMasked.removeClass("has-error");
      $phoneLabels.removeClass("has-error");
      $errorStatus.addClass("hidden-xs-up");
    };

    options.hiddenInput = function() {
      return options.hiddenInput;
    };

    const iti = intlTelInput(input, options);

    // Phone widget wrapper.
    let $fieldWrapper = $phoneMasked.closest('div.iti');

    // Change hidden inputs provided in phone widget formElement.
    if ($phoneMasked && $fieldWrapper.length > 0) {
      // Move country code and iso2 elements into the phone widget wrapper.
      $phoneNumber.appendTo($fieldWrapper);
      $countryCode.appendTo($fieldWrapper);
      $countryIso2.appendTo($fieldWrapper);
      $phoneMasked.addClass('processed');
    }

    // Get the placeholder attribute from the phone input field.
    let placeHolder = $phoneMasked.attr("placeholder");

    // Get selected country data (ISO2 and dial code) for
    // the initial placeholder value.
    let iso2Data = iti.getSelectedCountryData().iso2;
    let dialCode = iti.getSelectedCountryData().dialCode;

    // Check if intlTelInputUtils and iso2Data are defined,
    // as utils are lazy loaded.
    if (typeof intlTelInputUtils !== 'undefined' && typeof iso2Data !== 'undefined') {
      // An example number in INTERNATIONAL format for the selected country.
      placeHolder = intlTelInputUtils.getExampleNumber(iso2Data, false, intlTelInputUtils.numberFormat.INTERNATIONAL);

      // If nationalMode is enabled, update the placeholder
      // value by removing the dial code.
      if (options.nationalMode) {
        let replace = removedZero ? '' : '0';
        placeHolder = placeHolder.replace("+" + dialCode, replace);
      }
      // Update the placeholder attribute on the phone input field.
      $phoneMasked.attr("placeholder", placeHolder.trim());
    }

    // If formatter is enabled and a placeholder is defined,
    // initialize the phone mask.
    if (isFormatter && typeof placeHolder !== 'undefined') {
      // Remove leading zeros from the placeholder if removedZero is true.
      if (removedZero) {
        placeHolder = placeHolder.replace(/^0+/, '');
      }
      // Create and apply the mask pattern based on
      // the placeholder and strict mode.
      Drupal.maskPlaceholder($phoneMasked, placeHolder.trim(), options.strictMode);
    }

    // Add a blur event listener to the phone input field for validation.
    $phoneMasked.blur(function() {
      // Reset any previous error states.
      reset();

      // If the input field is not empty, validate the phone number.
      if ($phoneMasked.val().trim() && $countryCode.val().trim()) {
        if (iti.isValidNumber()) {
          // If the phone number is valid, set the hidden
          // input value with the full number.
          if (options.nationalMode) {
            $phoneNumber.val('+' + $countryCode.val() + $phoneMasked.val().trim().replace(/\D/g, ''));
          }
          else {
            $phoneNumber.val('+' + $phoneMasked.val().trim().replace(/\D/g, ''));
          }
        }
        else {
          // If the phone number is invalid, set error states.
          $phoneMasked.addClass("has-error");
          $phoneLabels.addClass("has-error");
          $errorStatus.removeClass("hidden-xs-up");
        }
      }
    });

    // Add keyup and change event listeners to the phone input field.
    $phoneMasked.bind('keyup change', function(e) {
      // Reset any previous error states.
      reset();

      // Set the country code and ISO2 if not already set.
      if (!$countryCode.val()) {
        $countryCode.val(iti.getSelectedCountryData().dialCode);
      }
      if (!$countryIso2.val()) {
        $countryIso2.val(iti.getSelectedCountryData().iso2);
      }

      if ($countryCode.val().trim()) {
        // Update the hidden input value with the full number
        // based on the national mode setting.
        if (options.nationalMode) {
          $phoneNumber.val('+' + $countryCode.val() + $phoneMasked.val().trim().replace(/\D/g, ''));
        } else {
          $phoneNumber.val('+' + $phoneMasked.val().trim().replace(/\D/g, ''));
        }
      }

      // Remove leading zero from the input value
      // if removedZero is true.
      if (removedZero) {
        let value = $(this).val().trim();
        if (value[0] == "0") {
          value = value.toString().replace(/^0+/, '');
          $(this).val(value);
        }
        // Update the placeholder by removing leading zeros
        // if the input field is empty.
        if (value.trim().length === 0) {
          let placeHolder = $phoneMasked.attr("placeholder").replace(/^0+/, '');
          $phoneMasked.attr("placeholder", placeHolder.trim());
        }
      }
    });

    // Prevent form submission if there are validation errors.
    if (ValidErrors) {
      $('#edit-submit').click(function(e) {
        $phoneParent.find("label").each(function() {
          if ($(this).hasClass('has-error')) {
            $(this).parent().find('input.local-number').focus();
            e.preventDefault();
            return false;
          }
        });
      });
    }

    // Country change event listener.
    field.addEventListener('countrychange', function() {
      // Update country code and ISO2 values via plugin.
      $countryCode.val(iti.getSelectedCountryData().dialCode);
      $countryIso2.val(iti.getSelectedCountryData().iso2);

      // Get selected country placeholder via plugin example number format.
      let placeHolder = $phoneMasked.attr("placeholder");

      // Remove zero from beginning.
      if (removedZero) {
        placeHolder = placeHolder.replace(/^0+/, '');
        // Update placeholder to no leading zero.
        $phoneMasked.attr("placeholder", placeHolder.trim());
      }

      // Update phone mask number formatter.
      if (isFormatter) {
        setTimeout(function () {
          // Use updated placeholder to define formatting pattern.
          Drupal.maskPlaceholder($phoneMasked, placeHolder, options.strictMode);
        }, 120);
      }
    });
  };

  /**
   * Phone create mask from placeholder.
   *
   * Creates a mask for the phone number input
   * based on the placeholder and mode.
   *
   * @param $phoneMasked
   *   The jQuery object for the phone input field.
   * @param placeHolder
   *   The placeholder string for the phone number input.
   * @param strictMode
   *   A boolean indicating whether strict mode is enabled.
   */
  Drupal.maskPlaceholder = function ($phoneMasked, placeHolder, strictMode) {
    // Determine the character to use for masking based on strict mode.
    const restrict = strictMode ? '9' : '*';

    // Replace numeric characters in the placeholder
    // with the restrict character.
    let maskedPlaceHolder = placeHolder.trim().replace(new RegExp("[0-9]", "g"), restrict);

    // Create the mask pattern by wrapping
    // consecutive restrict characters in {{}}.
    let maskPattern = maskedPlaceHolder.replace(new RegExp(`([${restrict}]+)`, "g"), '{{$1}}');

    // Check if the phone input field already has a mask pattern.
    if ($phoneMasked.data('phone-mask')) {
      // If a mask pattern exists, reset it with the new pattern.
      $phoneMasked.formatter().resetPattern(maskPattern);
    } else {
      // If no mask pattern exists, initialize the formatter
      // with the new pattern.
      $phoneMasked.formatter({
        'pattern': maskPattern
      });
      // Set the data attribute to indicate that
      // the phone input field has a mask pattern.
      $phoneMasked.attr('data-phone-mask', true);
    }
  };

})(jQuery, Drupal, drupalSettings, once);
