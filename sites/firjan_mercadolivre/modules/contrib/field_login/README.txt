CONTENTS OF THIS FILE
---------------------

* Introduction
* Requirements
* Installation
* Installation with Composer
* Configuration
* Maintainers


INTRODUCTION
------------

This module allows users to log in with field address
minimal configurations.

 * For a full description of the module visit:
  https://www.drupal.org/project/field_login

 * To submit bug reports and feature suggestions, or to track changes visit:
  https://www.drupal.org/project/issues/field_login


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

Install the field login module as you would normally install a contributed Drupal
module. Visit https://www.drupal.org/node/1897420 for further information.


INSTALLATION WITH COMPOSER
--------------------------

We recommend using Composer to download Field Login module.
composer require 'drupal/field_login:^1.0';


CONFIGURATION
--------------

Go to "/admin/config/people/accounts/field-login" for the configuration screen,
   available configuraitons:
  * Select login field address: This option enables the user to login to the field address
  * Override login form: This option allows you to override the login form
    username title/description.
  * Login form username title: Override the username field title.
  * Login form username description: Override the username field description.

MAINTAINERS
-----------

This module was created by gaoxiang, a drupal developer.

 * Gao Xiang - https://www.drupal.org/u/qiutuo
