# PhoneNumber
Defines a field type for international phone numbers.

## Introduction

The Phone Number module provides a robust field for phone numbers, supporting
mobile and local formats. It includes a user-friendly widget for country
selection, displaying flags and providing example formats in placeholders.
Additional features include setting default countries and automatic selection
based on IP Geolocation.

## Features

- **Versatile Field:**
Supports both mobile and local phone number formats.
- **Country Selection:**
User-friendly widget for selecting country, displaying flags.
- **Automatic Formatting:**
Automatically formats phone numbers based on selected country.
- **Input Validation:**
Restricts input to numeric characters only, preventing letters.
- **Unique Numbers:**
Ensures phone numbers are unique in the system.
- **Default Country:**
Ability to set a default country for phone number entry.
- **Geolocation Support:**
Automatically selects country based on IP Geolocation.
- **Customizable Placeholders:**
Example formats in placeholders for user guidance.
- **Phone Number Validation:**
Uses giggsey/libphonenumber-for-php for advanced validation.
- **Phone Verification:**
Integrates with SMS frameworks for mobile number verification.

## Requirements

Phone Verification: Requires an SMS framework for mobile number verification.
Phone Number Validation: Requires giggsey/libphonenumber-for-php,
a PHP library for validating mobile numbers.
- [giggsey/libphonenumber-for-php](https://github.com/giggsey/libphonenumber-for-php)


## Installation

1. **Download the PhoneNumber Module:**
  - [PhoneNumber Module](https://www.drupal.org/project/phonenumber)

2. **Extract and Place in Contributed Modules Directory:**
  - `/modules/contrib/phonenumber`

3**Enable the PhoneNumber Module:**

## Maintainers

Current module maintainer:

- **Mahyar Sabeti:** [Mahyar Sabeti on Drupal.org](https://www.drupal.org/u/mahyarsbt)

If you need further information or assistance, feel free to ask!
