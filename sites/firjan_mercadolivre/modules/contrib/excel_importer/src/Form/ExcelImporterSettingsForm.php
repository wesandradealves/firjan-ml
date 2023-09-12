<?php

namespace Drupal\excel_importer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form to configure Excel Importer module settings.
 */
class ExcelImporterSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'excel_importer_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'excel_importer.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $types = node_type_get_names();
    $config = $this->config('excel_importer.settings');

    $form['excel_importer_introduction'] = [
      '#type' => 'text_format',
      '#title' => t('Provide introductory text on how to use the form'),
      '#default_value' => $config->get('introduction'),
      '#description' => t('Use this to provide resources such as links to template Excel files.'),
      '#format' => NULL,
      '#weight' => 0,
    ];

    $form['excel_importer_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select the content types to be available'),
      '#default_value' => $config->get('allowed_types'),
      '#options' => $types,
      '#description' => t('Users will be able to use Excel Importer to migrate date into these Content types.'),
      '#weight' => 1,
    ];
    $form['array_filter'] = ['#type' => 'value', '#value' => TRUE];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $introduction = $form_state->getValue('excel_importer_introduction');
    $allowed_types = array_filter($form_state->getValue('excel_importer_types'));

    // @todo Save both value and format
    $this->config('excel_importer.settings')
      ->set('introduction', $introduction['value'])
      ->save();

    sort($allowed_types);
    $this->config('excel_importer.settings')
      ->set('allowed_types', $allowed_types)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
