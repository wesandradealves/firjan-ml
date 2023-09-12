<?php

namespace Drupal\username_validation\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class UserNameValidationConfig.
 *
 * @package Drupal\user_name_validation\Form
 */
class UserNameValidationConfig extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'username_validation.usernamevalidationconfig',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'username_validation_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('username_validation.usernamevalidationconfig');
    $default_min_char = $config->get('min_char');
    $default_max_char = $config->get('max_char');
    $default_ajax_validation = $config->get('ajax_validation');
    $default_user_label = $config->get('user_label');
    $default_user_desc = $config->get('user_desc');
    $default_avoid_spaces = $config->get('avoid_spaces');

    $form['username_validation_rule'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Username condition'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#tree' => TRUE,
    ];

    $form['username_validation_config'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Username Configuration'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#tree' => TRUE,
    ];

    $form['username_validation_rule']['blacklist_char'] = [
      '#type' => 'textarea',
      '#default_value' => $config->get('blacklist_char'),
      '#title' => $this->t('Blacklist Characters/Words'),
      '#description' => '<p>' . $this->t("Comma separated characters or words to avoided while saving username. Ex: !,@,#,$,%,^,&,*,(,),1,2,3,4,5,6,7,8,9,0,have,has,were,aren't.") . '</p>' . '<p>' . $this->t('If any of the blacklisted characters/words found in username ,would return validation error on user save.') . '</p>',
    ];

    $form['username_validation_rule']['min_char'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Minimum characters"),
      '#required' => TRUE,
      '#description' => $this->t("Minimum number of characters username should contain"),
      '#size' => 12,
      '#maxlength' => 3,
      '#default_value' => isset($default_min_char) ? $default_min_char : '1',
    ];

    $form['username_validation_rule']['max_char'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Maximum characters"),
      '#required' => TRUE,
      '#description' => $this->t("Maximum number of characters username should contain"),
      '#size' => 12,
      '#maxlength' => 3,
      '#default_value' => isset($default_max_char) ? $default_max_char : '128',
    ];

    $form['username_validation_rule']['avoid_spaces'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Avoid spaces"),
      '#description' => $this->t('Avoid spaces in username'),
      '#default_value' => isset($default_avoid_spaces) ? $default_avoid_spaces : '',
    ];

    $form['username_validation_rule']['ajax_validation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Ajax Validation'),
      '#description' => $this->t("By default ajax will be triggered on 'change' event."),
      '#default_value' => isset($default_ajax_validation) ? $default_ajax_validation : '',
    ];

    $form['username_validation_config']['user_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Username Label"),
      '#description' => $this->t("This value will display instead of username in the registration form"),
      '#size' => 60,
      '#maxlength' => 255,
      '#default_value' => isset($default_user_label) ? $default_user_label : '',
    ];

    $form['username_validation_config']['user_desc'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Username description"),
      '#description' => $this->t("This value will display as username description"),
      '#size' => 60,
      '#maxlength' => 255,
      '#default_value' => isset($default_user_desc) ? $default_user_desc : '',
    ];

    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#name' => 'reset',
      '#value' => $this->t('Reset Configuration'),
      '#submit' => ['::clearConfiguration'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $min = $form_state->getValue('username_validation_rule')['min_char'];
    $max = $form_state->getValue('username_validation_rule')['max_char'];
    // Validate field is numerical.
    if (!is_numeric($max)) {
      $form_state->setErrorByName('max_char', $this->t('These value should be Numerical'));
    }

    // Validate field should be between 0 and 128.
    if ($max <= 0 || $max > 128) {
      $form_state->setErrorByName('max_char', $this->t('These value should be between 0 and 128'));
    }

    // Validate field is numerical.
    if (!is_numeric($min)) {
      $form_state->setErrorByName('min_char', $this->t('These value should be Numerical'));
    }

    // Validate field is greater than 1.
    if ($min < 1) {
      $form_state->setErrorByName('min_char', $this->t('These value should be more than 1'));
    }

    // Validate min is less than max value.
    if ($min > $max) {
      $form_state->setErrorByName('min_char', $this->t('Minimum length should not be more than Max length'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('username_validation.usernamevalidationconfig')
      ->set('blacklist_char', $form_state->getValue('username_validation_rule')['blacklist_char'])
      ->set('min_char', $form_state->getValue('username_validation_rule')['min_char'])
      ->set('max_char', $form_state->getValue('username_validation_rule')['max_char'])
      ->set('avoid_spaces', $form_state->getValue('username_validation_rule')['avoid_spaces'])
      ->set('ajax_validation', $form_state->getValue('username_validation_rule')['ajax_validation'])
      ->set('user_label', $form_state->getValue('username_validation_config')['user_label'])
      ->set('user_desc', $form_state->getValue('username_validation_config')['user_desc'])
      ->save();
  }

  /**
   * Delete saved configuration on Clear configuration button press.
   */
  public function clearConfiguration() {
    $this->config('username_validation.usernamevalidationconfig')->delete();
  }

}
