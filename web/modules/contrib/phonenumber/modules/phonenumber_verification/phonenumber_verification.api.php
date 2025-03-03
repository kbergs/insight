<?php

/**
 * @file
 * phonenumber_verification.api.php
 */

/**
 * Set or alter the sms callback for using the verification functionality.
 *
 * Only one sms callback can be defined and it's with this hook.
 *
 * The callback gets the arguments:
 * - string $phonenumber (string, international format)
 * - string $message (string)
 *
 * If a sms module has a function with these two needed arguments, then here
 * is where it should be defined, otherwise a wrapper function can be used.
 *
 * @param string $send_sms_callback
 *   Defaults to 'sms_send' if the SMS Framework module  is enabled.
 */
function hook_phonenumber_verification_send_sms_callback_alter(&$send_sms_callback) {
  $send_sms_callback = 'my_sms_callback';
}
