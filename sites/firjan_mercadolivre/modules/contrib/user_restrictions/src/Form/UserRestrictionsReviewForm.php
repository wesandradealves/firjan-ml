<?php

namespace Drupal\user_restrictions\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\user_restrictions\Entity\UserRestrictionInterface;
use Drupal\user_restrictions\Entity\UserRestrictions;
use Drupal\user_restrictions\UserRestrictionTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Review user restriction.
 */
class UserRestrictionsReviewForm extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The user restriction type manager.
   *
   * @var \Drupal\user_restrictions\UserRestrictionTypeManagerInterface
   */
  protected $typeManager;

  /**
   * Constructs a new UserRestrictionsReviewForm instance.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\user_restrictions\UserRestrictionTypeManagerInterface $type_manager
   *   The user restriction type manager.
   */
  public function __construct(Connection $connection, UserRestrictionTypeManagerInterface $type_manager) {
    $this->connection = $connection;
    $this->typeManager = $type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('user_restrictions.type_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_restrictions_review_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, UserRestrictionInterface $user_restrictions = NULL) {
    $pattern = $user_restrictions->getPattern();
    $rule_type = $user_restrictions->getRuleType();
    $access_type = $user_restrictions->getAccessType();
    $expiry = $user_restrictions->getExpiry();

    // Show basic information about restriction.
    $form['info'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Rule type'),
        $this->t('Pattern'),
        $this->t('Access type'),
        $this->t('Expiry'),
      ],
      '#rows' => [
        [
          $user_restrictions->label(),
          $this->typeManager->getType($rule_type)->getLabel(),
          $pattern,
          $access_type ? $this->t('Whitelisted') : $this->t('Blacklisted'),
          $expiry == UserRestrictions::NO_EXPIRY ? $this->t('Never') : date('Y-m-d H:i:s', $expiry),
        ],
      ],
    ];

    // Get counts of total and active users.
    $query = $this->connection->select('users_field_data', 'u');
    $query->addExpression('COUNT(*)');
    $query->condition('u.uid', 0, '>');
    $count_total = $query->execute()->fetchField();
    $query = $this->connection->select('users_field_data', 'u');
    $query->addExpression('COUNT(*)');
    $query->condition('u.uid', 0, '>');
    $query->condition('u.status', 1);
    $count_total_active = $query->execute()->fetchField();

    // Get counts of total and active users matching pattern.
    $field = 'u.' . $rule_type;
    $query = $this->connection->select('users_field_data', 'u');
    $query->addField('u', 'uid');
    $query->condition('u.uid', 0, '>');
    $query->condition($field, $pattern, 'REGEXP');
    $matching_uids = $query->execute()->fetchCol();
    $count_matching = count($matching_uids);
    $query = $this->connection->select('users_field_data', 'u');
    $query->addField('u', 'uid');
    $query->condition('u.uid', 0, '>');
    $query->condition('u.status', 1);
    $query->condition($field, $pattern, 'REGEXP');
    $matching_active_uids = $query->execute()->fetchCol();
    $count_matching_active = count($matching_active_uids);

    // Show counts of total users and users matching pattern.
    $form['count'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Total users'),
        $this->t('Active users'),
        $this->t('Blocked users'),
        $this->t('Total users matching pattern'),
        $this->t('Active users matching pattern'),
        $this->t('Blocked users matching pattern'),
      ],
      '#rows' => [
        [
          $count_total,
          $count_total_active,
          $count_total - $count_total_active,
          $count_matching,
          $count_matching_active,
          $count_matching - $count_matching_active,
        ],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Skip actions when restriction type or access is not supported.
    if (!in_array($rule_type, ['name', 'mail'])) {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('This restriction type is not supported'),
        '#disabled' => TRUE,
      ];
    }
    elseif ($access_type != UserRestrictions::BLACKLIST) {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('This access type is not supported'),
        '#disabled' => TRUE,
      ];
    }

    // Show dynamic actions based on current counts.
    $form['actions']['block'] = [
      '#type' => 'submit',
      '#name' => 'block',
      '#value' => $this->t('Block @count users matching pattern', [
        '@count' => $count_matching_active,
      ]),
      '#button_type' => 'primary',
      '#disabled' => empty($count_matching_active),
    ];
    $form['actions']['delete'] = [
      '#type' => 'submit',
      '#name' => 'delete',
      '#value' => $this->t('Delete @count users matching pattern', [
        '@count' => $count_matching,
      ]),
      '#button_type' => 'danger',
      '#disabled' => empty($count_matching),
    ];

    // Store user IDs for submit action.
    $form['block_uids'] = [
      '#type' => 'value',
      '#value' => $matching_active_uids,
    ];
    $form['delete_uids'] = [
      '#type' => 'value',
      '#value' => $matching_uids,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $action = $form_state->getTriggeringElement()['#name'];
    $uids = $form_state->getValue($action . '_uids');

    // Use batch processing for updating a large number of users.
    $batch = [
      'operations' => [],
      'finished' => [$this, 'batchFinished'],
    ];
    foreach (array_chunk($uids, 50) as $chunk) {
      $batch['operations'][] = [[$this, 'batchProcess'], [$action, $chunk]];
    }
    batch_set($batch);
  }

  /**
   * Executes a batch operation.
   *
   * @param string $action
   *   The operation action.
   * @param array $uids
   *   The operation user IDs.
   * @param array|\ArrayAccess $context
   *   An array of contextual key/values.
   */
  public function batchProcess($action, array $uids, &$context) {
    $users = User::loadMultiple($uids);
    // Process action for the current batch of users.
    foreach ($users as $user) {
      if ($action === 'block') {
        $user->block();
        $user->save();
      }
      elseif ($action === 'delete') {
        $user->delete();
      }
    }
  }

  /**
   * Reports the status of batch operation.
   *
   * @param bool $success
   *   Whether or not the batch was successful.
   */
  public function batchFinished($success) {
    if ($success) {
      $this->messenger()->addStatus($this->t('The update was successful.'));
    }
    else {
      $this->messenger()->addError($this->t('An error occurred.'));
    }
  }

}
