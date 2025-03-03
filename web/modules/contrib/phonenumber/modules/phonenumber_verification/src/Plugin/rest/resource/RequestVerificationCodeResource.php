<?php

namespace Drupal\phonenumber_verification\Plugin\rest\resource;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\phonenumber_validation\PhoneValidatorInterface;
use Drupal\phonenumber_verification\PhoneVerifierInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Request verification code resource.
 *
 * @RestResource(
 *   id = "request_verification_code",
 *   label = @Translation("SMS Phone Number: request verification code"),
 *   uri_paths = {
 *     "canonical" = "/sms-phone-number/request-code/{number}",
 *   }
 * )
 */
class RequestVerificationCodeResource extends ResourceBase {

  /**
   * The field definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  protected $fieldDefinition;

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The phone field validation utility.
   *
   * @var \Drupal\phonenumber_verification\PhoneVerifierInterface
   */
  protected $phoneValidator;

  /**
   * The phone field verification utility.
   *
   * @var \Drupal\phonenumber_verification\PhoneVerifierInterface
   */
  protected $phoneVerifier;

  /**
   * Constructs a RequestVerificationCodeResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   * @param \Drupal\phonenumber_validation\PhoneValidatorInterface $phone_validator
   *   The phone field validation utility.
   * @param \Drupal\phonenumber_verification\PhoneVerifierInterface $phone_verifier
   *   The phone field verification utility.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, FieldDefinitionInterface $field_definition, AccountProxyInterface $current_user, PhoneValidatorInterface $phone_validator, PhoneVerifierInterface $phone_verifier) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->fieldDefinition = $field_definition;
    $this->currentUser = $current_user;
    $this->phoneValidator = $phone_validator;
    $this->phoneVerifier = $phone_verifier;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('phonenumber_verification'),
      $container->get('current_user'),
      $container->get('phonenumber_validation.validator'),
      $container->get('phonenumber_verification.verifier')
    );
  }

  /**
   * Responds to a GET request to send a verification code.
   *
   * @param string|null $number
   *   The phone number to verify.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @throws PhoneNumberException
   */
  public function get($number = NULL) {
    // Check if the phone number is provided.
    if (!$number) {
      throw new BadRequestHttpException('Phone number not provided.');
    }

    // Add the '+' sign to the phone number.
    $number = "+$number";

    // Validate the phone number.
    $phone_number = $this->phoneValidator->checkPhoneNumber($number);

    // Get the verification settings from the field definition.
    $settings = $this->fieldDefinition->getSettings();
    $queue = [
      'verify_interval' => $settings['verify_interval'],
      'verify_count' => $settings['verify_count'],
      'sms_interval' => $settings['sms_interval'],
      'sms_count' => $settings['sms_count'],
    ];

    // Check for verification flood (too many attempts).
    if (!$this->phoneVerifier->checkFlood($phone_number, 'verification', $queue)) {
      throw new AccessDeniedHttpException('Too many verification attempts, please try again in a few hours.');
    }

    // Check for SMS flood (too many requests).
    if (!$this->phoneVerifier->checkFlood($phone_number, 'sms', $queue)) {
      throw new AccessDeniedHttpException('Too many verification code requests, please try again shortly.');
    }

    // Generate a verification code and send it.
    $message = PhoneVerifierInterface::PHONE_NUMBER_DEFAULT_SMS_MESSAGE;
    $code = $this->phoneVerifier->generateVerificationCode();
    $token = $this->phoneVerifier->sendVerification($phone_number, $message, $code);

    // If sending the SMS fails, throw an error.
    if (!$token) {
      throw new HttpException(500, 'An error occurred while sending SMS.');
    }

    // Return the verification token in the response.
    return new ResourceResponse(json_encode(['verification_token' => $token]));
  }

}
