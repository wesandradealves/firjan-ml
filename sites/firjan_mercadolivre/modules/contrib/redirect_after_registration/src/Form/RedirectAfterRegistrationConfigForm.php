<?php

namespace Drupal\redirect_after_registration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class RedirectAfterRegistrationConfigForm.
 */
class RedirectAfterRegistrationConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'redirect_after_registration.redirectafterregistrationconfig',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'redirect_after_registration_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('redirect_after_registration.redirectafterregistrationconfig');
    $form['destination'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Destination'),
      '#description' => $this->t('Destination to redirect to after registration. Must be internal path, with leading slash.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('destination'),
    ];

    $form['suppress_admin'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Do not redirect on admin form'),
      '#description' => $this->t('Checking this box will cause the redirect destination to apply only to anonymous user registration.'),
      '#default_value' => $config->get('suppress_admin'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('redirect_after_registration.redirectafterregistrationconfig')
      ->set('destination', $form_state->getValue('destination'))
      ->set('suppress_admin', $form_state->getValue('suppress_admin'))
      ->save();
  }

}
