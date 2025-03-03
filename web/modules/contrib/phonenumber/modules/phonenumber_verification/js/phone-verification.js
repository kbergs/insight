/**
 * @file
 * Provides widget behaviours for phone number verification.
 */

(($, Drupal, once) => {
  /**
   * Handles the phone element verification.
   *
   * @type {{attach: Drupal.behaviors.phoneNumberVerification.attach}}
   */
  Drupal.behaviors.phoneNumberVerification = {
    /**
     * Attaches phone number verification behaviours to the context.
     *
     * @param context
     *   The context to which the behaviours are attached.
     * @param settings
     *   The settings for the behaviours.
     */
    attach(context, settings) {

      // Get phone verification queue options from Drupal settings.
      const options = drupalSettings.phoneVerification.options;

      // Add a keyup event listener to the local number input field.
      // This shows the send button and hides the verified status when the input value changes.
      once('phoneVerification', '.phone-number-field .local-number', context).forEach((value) => {
        const $input = $(value);
        let val = $input.val();

        $input.keyup(() => {
          if (val !== $input.val()) {
            val = $input.val();
            $input
              .parents('.phone-number-field')
              .find('.send-button')
              .addClass('show');
            $input
              .parents('.phone-number-field')
              .find('.verified')
              .addClass('hide');
          }
        });
      });

      // Add a change event listener to the country select field.
      // This shows the send button and hides the verified status when the selected country changes.
      once('phoneVerification', '.phone-number-field .country', context).forEach(
        (value) => {
          const $input = $(value);
          let val = $(value).val();
          $(value).change(function() {
            if (val !== $(this).val()) {
              val = $(this).val();
              $input
                .parents('.phone-number-field')
                .find('.send-button')
                .addClass('show');
              $input
                .parents('.phone-number-field')
                .find('.verified')
                .addClass('hide');
            }
          });
        },
      );

      // Initialize code input behaviours based on the options.
      Drupal.phoneVerificationCodeInput(context, options);

      // Add a change event listener to the send button.
      // This disables the button and starts the countdown when the button is clicked.
      let $sendCode = $(once('phone-verification', '.phone-number-field .send-button', context));

      $sendCode.on(
        'change',
        function (event) {
          $(this).addClass('disabled');
          $(this).attr('disabled', 'disabled');
          Drupal.phoneVerificationCountdown(options['verify_interval'], $(this));
          event.preventDefault();
        });

      // Show the verification prompt if required by settings.
      if (settings.phoneNumberVerificationPrompt) {
        $(`#${settings.phoneNumberVerificationPrompt} .verification`).addClass('show');
        $(`#${settings.phoneNumberVerificationPrompt} .verification input[type="text"]`).val('');
      }

      // Hide the verification prompt if required by settings.
      if (settings.phoneNumberHideVerificationPrompt) {
        $(`#${settings.phoneNumberHideVerificationPrompt} .verification`).removeClass('show');
      }

      // Show the verified status if the phone number is verified.
      if (settings.phoneNumberVerified) {
        $(`#${settings.phoneNumberVerified} .send-button`).removeClass('show');
        $(`#${settings.phoneNumberVerified} .verified`).addClass('show');
      }

    },
  };

  /**
   * Phone resend verification countdown.
   *
   * @param {number} duration
   *   The interval time in seconds.
   * @param {Object} display
   *   The jQuery object for the button display text.
   */
  Drupal.phoneVerificationCountdown = function (duration, display) {
    let timer = duration, minutes, seconds;

    let interval = setInterval(function () {

      // Calculate minutes and seconds remaining.
      minutes = parseInt(timer / 60, 10);
      seconds = parseInt(timer % 60, 10);

      // Format the time values for display.
      minutes = minutes < 10 ? "0" + minutes : minutes;
      seconds = seconds < 10 ? "0" + seconds : seconds;

      // Update the button text with the remaining time.
      display.val(Drupal.t('Try resend: !time', { '!time': minutes !== '00' ? minutes + ":" + seconds : seconds + 's' }));

      // Check if the timer has reached zero.
      if (--timer < 0) {
        display.val(Drupal.t('Resend code'));
        timer = duration;
        clearInterval(interval);
        display.removeAttr('disabled');
        return;
      }

    }, 1000);
  };

  /**
   * Initializes phone verification code input behaviours.
   *
   * @param {Object} context
   *   The context to which the behaviours are attached.
   * @param {Object} options
   *   The options for the phone verification.
   */
  Drupal.phoneVerificationCodeInput = function (context, options) {
    const inputElements = context.querySelectorAll('input.code-input');

    // Separate verification code input.
    if (options['separate_verification_code']) {
      const $codeInput = $('.verification .code-input');

      once('verification-code', $codeInput).forEach((element, index) => {
        // Add event listener for keydown event to handle backspace navigation between inputs.
        $(element).on(
          'keydown',
          function (event) {
            // If backspace is pressed and the current field is empty, focus the previous input.
            if (event.keyCode === 8 && event.target.value === '') {
              $codeInput[Math.max(0, index - 1)].focus();
            }
          });

        // Add event listener for keypress event to restrict input to digits only.
        $(element).on(
          'keypress',
          function (event) {
            // If the letter is not digit then display error and don't type anything.
            if (event.which != 8 && event.which != 0 && (event.which < 48 || event.which > 57)) {
              // Display error message.
              return false;
            }
          });

        // Add event listener for input, keyup, and change events to handle input value and focus navigation.
        $(element).on(
          'input keyup change',
          function (event) {
            let $inputParent = $(this).closest('.verification.split');
            this.value = this.value.replace(/[^0-9\.]/g, '');
            // take the first character of the input
            // this actually breaks if you input an emoji like 👨‍👩‍👧‍👦....
            // but I'm willing to overlook insane security code practices.
            const [first, ...rest] = event.target.value;
            // first will be undefined when backspace was entered, so set the input to "".
            event.target.value = first ?? '';
            const lastInputBox = index === $codeInput.length - 1;
            const didInsertContent = first !== undefined;
            if (didInsertContent && !lastInputBox) {
              // Continue to input the rest of the string.
              $codeInput[index + 1].removeAttribute("disabled");
              $codeInput[index + 1].focus();
              $codeInput[index + 1].value = rest.join('');
              $codeInput[index + 1].dispatchEvent(new Event('input'));
            }
            let $codeVerify = $inputParent.find('.code-verify');
            let values = '';
            $.each($codeInput, function (key, value) {
              values += $(value).val();
            });
            $codeVerify.val(values);
          });

        // Add event listener for paste event to handle pasting of verification code.
        $(element).on(
          'paste',
          function (event) {
            const clip = event.originalEvent.clipboardData.getData('text').trim();
            // Allow numbers only, Invalid. Exit here.
            if (!/\d{6}/.test(clip)) return event.preventDefault();
            // Split string to Array or characters.
            const s = [...clip];
            // Populate inputs. Focus last input.
            $codeInput.removeAttr("disabled");
            $codeInput.val(i => s[i]).eq(5).focus();
          });
      });
    }
    else {
      // Initialize mask formatter for single verification code input.
      $('.verification .code-input').formatter({
        'pattern': '{{999999}}'
      });
      $('.verification .code-input').attr('data-code-mask', true);
    }

  };

})(jQuery, Drupal, once);
