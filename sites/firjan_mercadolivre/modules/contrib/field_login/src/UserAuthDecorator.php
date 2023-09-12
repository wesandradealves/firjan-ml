<?php

namespace Drupal\field_login;

use Drupal\user\UserAuthInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Validates user authentication credentials.
 */
class UserAuthDecorator implements UserAuthInterface {

  use DependencySerializationTrait;

  /**
   * The original user authentication service.
   *
   * @var \Drupal\user\UserAuthInterface
   */
  protected $userAuth;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a UserAuth object.
   *
   * @param \Drupal\user\UserAuthInterface $user_auth
   *   The original user authentication service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_managerk
   *   The entity type manager.
   */
  public function __construct(UserAuthInterface $user_auth, EntityTypeManagerInterface $entity_type_manager) {
    $this->userAuth = $user_auth;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate($inputName, $password) {
    $config = \Drupal::configFactory()->get('field_login.settings');
    $login_field = $config->get('login_field');

    if (!empty($inputName) && $inputName) {
      foreach ($login_field as $field) {
        $loginName = $field === 'mail' ? filter_var($inputName, FILTER_VALIDATE_EMAIL) : $inputName;
        if ($account_search = $this->entityTypeManager->getStorage('user')->loadByProperties([$field => $loginName])) {
          $account = reset($account_search);
          $username = $account->getAccountName();
        }
      }
    }
    return $this->userAuth->authenticate($username, $password);
  }
}
