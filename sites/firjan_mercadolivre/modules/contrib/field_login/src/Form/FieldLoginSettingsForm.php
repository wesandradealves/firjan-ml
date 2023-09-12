<?php

namespace Drupal\field_login\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class FieldLoginSettingsForm.
 */
class FieldLoginSettingsForm extends ConfigFormBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'field_login.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'field_login_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('field_login.settings');

    //Exclude default field list
    $remove_field = [
      'uuid' => 'uuid',
      'langcode' => 'langcode',
      'preferred_langcode' => 'preferred_langcode',
      'preferred_admin_langcode' => 'preferred_admin_langcode',
      'pass' => 'pass',
      'timezone' => 'timezone',
      'status' => 'status',
      'created' => 'created',
      'changed' => 'changed',
      'access' => 'access',
      'login' => 'login',
      'init' => 'init',
      'roles' => 'roles',
      'default_langcode' => 'default_langcode',
    ];

    //Login field options
    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('user', 'user');
    $field_options = [];
    foreach ($fields as $field_name => $field) {
      if ($remove_field[$field_name]) {
        unset($field_name);
      }
      elseif (!empty($field_name)) {
        $label = (string) $field->getLabel();
        $field_options[$field_name] = isset($label) ? $this->t($label) : $this->t($field_name);
      }
    }

    $form['fields'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Login Configurations'),
      '#open' => TRUE,
    ];

    $form['fields']['login_field'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enable login field'),
      '#default_value' => !empty($config->get('login_field')) ? $config->get('login_field'): ['name', 'mail'],
      '#description' => $this->t('Select the user login field.'),
      '#options' => $field_options,
      '#required' => TRUE,
    ];

    $form['fields']['override_login_labels'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Override login form'),
      '#default_value' => !empty($config->get('override_login_labels')) ? $config->get('override_login_labels'): 1,
      '#description' => $this->t('This option allows you to override the login form username title/description.'),
    ];

    $form['fields']['login_username_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Override label'),
      '#default_value' => !empty($config->get('login_username_title')) ? $config->get('login_username_title'): $this->t('Login by username/email/field address.'),
      '#states' => [
        'visible' => [
          ':input[name="override_login_labels"]' => ['checked' => TRUE],
        ],
      ],
      '#description' => $this->t('Override the username field title.'),
    ];

    $form['fields']['login_username_description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Override description'),
      '#default_value' => !empty($config->get('login_username_description')) ? $config->get('login_username_description'): $this->t('You can use your username or email of field address to login.'),
      '#states' => [
        'visible' => [
          ':input[name="override_login_labels"]' => ['checked' => TRUE],
        ],
      ],
      '#description' => $this->t('Override the username field description.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config('field_login.settings');

    foreach ($form_state->getValues() as $field_name => $field_value) {
      if ($field_name === 'login_field') {
        $config->set($field_name, array_filter($field_value));
      }else{
        $config->set($field_name, $field_value);
      }
    }

    $config->save();
  }

}
