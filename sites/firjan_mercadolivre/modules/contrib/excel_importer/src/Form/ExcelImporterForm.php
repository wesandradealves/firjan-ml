<?php

namespace Drupal\excel_importer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Provides an Excel Importer form.
 */
class ExcelImporterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'excel_importer_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('excel_importer.settings');

    $form = [
      '#attributes' => ['enctype' => 'multipart/form-data'],
    ];

    $form['file_upload_details'] = [
      '#markup' => $config->get('introduction'),
    ];

    $validators = [
      'file_validate_extensions' => ['xlsx'],
    ];

    $form['excel_file'] = [
      '#type' => 'managed_file',
      '#name' => 'excel_file',
      '#title' => t('Please provide the file'),
      '#size' => 20,
      '#description' => t('Use <em>xlsx</em> file format only. The file size should not exceed <em>@file_size</em>.', ['@file_size' => ini_get('upload_max_filesize')]),
      '#upload_validators' => $validators,
      '#upload_location' => 'public://content/excel_files/',
      '#required' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('excel_file') && $form_state->getTriggeringElement()['#name'] != 'excel_file_remove_button') {
      $file = \Drupal::entityTypeManager()->getStorage('file')
        ->load($form_state->getValue('excel_file')[0]);

      $full_path = $file->get('uri')->value;
      $file_name = basename($full_path);

      $config = \Drupal::config('excel_importer.settings');
      $types = $config->get('allowed_types', []);

      try {
        $input_file_name = \Drupal::service('file_system')->realpath('public://content/excel_files/' . $file_name);
        $spreadsheet = IOFactory::load($input_file_name);
        $sheets = $spreadsheet->getAllSheets();
        $valid_sheets_count = 0;

        foreach ($sheets as $available_sheet) {
          if (in_array($available_sheet->getTitle(), $types)) {
            $valid_sheets_count++;
          }
        }

        if ($valid_sheets_count == 0) {
          $form_state->setErrorByName('excel_file', t('The file needs to contains at least one sheet with a valid content type name (machine name). Please check if the sheet names of your file match with the content types allowed in the Excel Importer administration settings (admin/config/content/excel_importer).'));
        }
      }
      catch (Exception $e) {
        \Drupal::logger('type')->error($e->getMessage());
        \Drupal::messenger()->addError(t('Unable to process the form. Please try again!'));
        \Drupal::messenger()->addError($e->getMessage());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $saved_entities = 0;
    $file = \Drupal::entityTypeManager()->getStorage('file')
      ->load($form_state->getValue('excel_file')[0]);

    $full_path = $file->get('uri')->value;
    $file_name = basename($full_path);

    $config = \Drupal::config('excel_importer.settings');
    $types = $config->get('allowed_types', []);

    try {
      $input_file_name = \Drupal::service('file_system')->realpath('public://content/excel_files/' . $file_name);
      $spreadsheet = IOFactory::load($input_file_name);
      $sheet_data = [];

      foreach ($types as $type) {
        $sheet = $spreadsheet->getSheetByName($type);
        $names = [];

        if ($sheet) {
          foreach ($sheet->getRowIterator() as $key => $row) {
            $cell_iterator = $row->getCellIterator();
            $cell_iterator->setIterateOnlyExistingCells(FALSE);
            $cells = [];

            // @todo Find a better solution
            if (!$this->isRowEmpty($row)) {
              foreach ($cell_iterator as $cell_key => $cell) {
                $hasValue = strlen(trim($cell->getValue()));

                if ($key == 2 && $hasValue) {
                  $field_label = $cell->getValue();

                  if ($this->isValidField($type, $field_label)) {
                    $names[$cell_key] = $field_label;
                  }
                  else {
                    \Drupal::messenger()
                      ->addError(t('The field <strong>"@field"</strong> is not in the <strong>"@content_type"</strong> content type.', [
                        '@field' => $field_label,
                        '@content_type' => $type,
                      ]));

                    return FALSE;
                  }
                }

                if ($key > 2 && !empty($names[$cell_key])) {
                  if (!$this->isRequireFieldProvided($type, $names[$cell_key], $cell->getValue())) {
                    \Drupal::messenger()
                      ->addError(t('The <strong>"@field"</strong> data is required field for the <strong>"@content_type"</strong> content type. <br/> See <strong>Sheet:</strong> @content_type, <strong>Row:</strong> @row_number', [
                        '@field' => $names[$cell_key],
                        '@content_type' => $type,
                        '@row_number' => $key,
                      ]));

                    return FALSE;
                  }

                  if (!$this->isCorrectDataType($type, $names[$cell_key], $cell->getValue())) {
                    \Drupal::messenger()
                      ->addError(
                              t(
                                  'The <strong>"@field"</strong> should be a number for the <strong>"@content_type"</strong> content type. <br/> See <strong>Sheet:</strong> @content_type, <strong>Row:</strong> @row_number, <strong>Value:</strong> @value',
                                  [
                                    '@field' => $names[$cell_key],
                                    '@content_type' => $type,
                                    '@row_number' => $key,
                                    '@value' => $cell->getValue(),
                                  ]
                                          )
                          );

                    return FALSE;
                  }

                  if ($this->isTaxonomyReference($type, $names[$cell_key]) && !$this->isValidTaxonomyReference($type, $names[$cell_key], $cell->getValue())) {
                    \Drupal::messenger()
                      ->addError(
                              t(
                                  'The <strong>"@field"</strong> is not found in the referenced taxonomy term for the <strong>"@content_type"</strong> content type. <br/> See <strong>Sheet:</strong> @content_type, <strong>Row:</strong> @row_number, <strong>Value:</strong> @value',
                                  [
                                    '@field' => $names[$cell_key],
                                    '@content_type' => $type,
                                    '@row_number' => $key,
                                    '@value' => $cell->getValue(),
                                  ]
                                          )
                          );

                    return FALSE;
                  }

                  // All is well, add data to collector.
                  $cells[$names[$cell_key]] = $this->getCorrectValue($type, $names[$cell_key], $cell->getValue());
                }
              }

              if ($key > 2) {
                $cells['type'] = $type;
                $sheet_data[$type][] = $cells;
              }
            }
          }
        }
      }

      foreach ($sheet_data as $sheet_content_types) {
        foreach ($sheet_content_types as $sheet_content_type) {
          $node = \Drupal::entityTypeManager()->getStorage('node')->create($sheet_content_type);
          if (!strlen(trim($node->getTitle()))) {
            $node->setTitle($node->type->entity->label() . ' ' . date('Y-m-d'));
          }
          $node->save();
          $saved_entities++;
        }
      }

      \Drupal::messenger()->addMessage(t('Excel File Imported Successfully</br><strong>@number</strong> entries saved.', ['@number' => $saved_entities]));

    }
    catch (Exception $e) {
      \Drupal::logger('type')->error($e->getMessage());
    }
  }

  /**
   * A method to test if a field is a taxonomy reference field.
   */
  private function isTaxonomyReference($bundle, $field) {
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);

    if (array_key_exists($field, $definitions) && isset($definitions[$field]->getSettings()['target_type']) && $definitions[$field]->getSettings()['target_type'] == 'taxonomy_term') {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * A method to test if a field is an entity reference field.
   */
  private function isEntityReference($bundle, $field) {
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);

    if (array_key_exists($field, $definitions) && $definitions[$field]->getType() == 'entity_reference') {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * A method to get the vocabulary of a taxonomy term.
   */
  private function getVocabulary($bundle, $field) {
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);
    return $this->arrayKeyFirst($definitions[$field]->getSettings()['handler_settings']['target_bundles']);
  }

  /**
   * A method to test if a field is a taxonomy reference field.
   */
  private function isValidTaxonomyReference($bundle, $field, $value) {
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);

    if ($definitions[$field]->getSettings()['handler_settings']['auto_create']) {
      return TRUE;
    }
    elseif ($this->arrayKeyFirst(taxonomy_term_load_multiple_by_name($value, $this->getVocabulary($bundle, $field)))) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * A method to test if a field is found in the content type.
   */
  private function isValidField($bundle, $field) {
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);

    if (array_key_exists($field, $definitions)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * A method to test if a field is required and provided in the sheet.
   */
  private function isRequireFieldProvided($bundle, $field, $value) {
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);

    if (array_key_exists($field, $definitions) && (($definitions[$field]->isRequired() && !empty($value)) || (!$definitions[$field]->isRequired()))) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * A method to test if a field is the correct data type.
   *
   * The only thing to check here if the field is supposed to be a number but
   * the data is different (string).
   */
  private function isCorrectDataType($bundle, $field, $value) {
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);

    if (array_key_exists($field, $definitions) && ((!empty($value) && $definitions[$field]->getType() == 'integer' && is_numeric($value)) || empty($value) || ($definitions[$field]->getType() != 'integer'))) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * A method to test if a row is empty, meaning all cells are empty.
   */
  private function isRowEmpty($row) {
    $cell_iterator = $row->getCellIterator();
    $cell_iterator->setIterateOnlyExistingCells(FALSE);
    $cells = [];

    foreach ($cell_iterator as $cell_key => $cell) {
      $value = $cell->getValue();
      if (strlen(trim($value))) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Get either the raw value or the tid for taxonomy term field cell.
   */
  private function getCorrectValue($bundle, $field, $value) {
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);

    if ($this->isEntityReference($bundle, $field)) {
      $target_type = $definitions[$field]->getSettings()['target_type'];

      if ($target_type == 'taxonomy_term') {
        $vid = $this->getVocabulary($bundle, $field);
        $tid = $this->arrayKeyFirst(taxonomy_term_load_multiple_by_name($value, $vid));
        $canAutoCreate = $definitions[$field]->getSettings()['handler_settings']['auto_create'];

        if ($canAutoCreate && empty($tid)) {
          $value = $this->createTaxonomyTerm($value, $vid);
        }
        else {
          $value = $tid;
        }
      }
      elseif ($value && ($target_type == 'user' || $target_type == 'node')) {
        $target_field = $target_type == 'user' ? 'mail' : 'title';
        $value = $this->arrayKeyFirst(\Drupal::entityTypeManager()->getStorage($target_type)->loadByProperties([$target_field => $value]));
      }
    }
    elseif ($definitions[$field]->getType() == 'daterange') {
      $date_values = explode(",", $value);
      $value = [
        'value' => trim($date_values[0]),
        'end_value' => trim($date_values[1]),
      ];
    }
    elseif (in_array($definitions[$field]->getType(), ['integer', 'float', 'decimal'])) {
      $value = !empty($value) ? $value : 0;
    }

    return $value;
  }

  /**
   * Polyfil for array_key_first.
   */
  private function arrayKeyFirst(array $arr) {
    foreach ($arr as $key => $unused) {
      return $key;
    }
    return NULL;
  }

  /**
   * Create a taxonomy term and return the tid.
   */
  public function createTaxonomyTerm($name, $vid) {
    $term = Term::create(
          [
            'name' => $name,
            'vid' => $vid,
          ]
      );
    $term->save();
    return $term->id();
  }

}
