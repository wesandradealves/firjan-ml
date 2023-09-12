<?php

namespace Drupal\eme\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form for previously exported content migration modules.
 */
class Collection extends ExportFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'eme_collection_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['table'] = $this->getTableSkeleton();

    foreach ($this->discoveredExports as $export_settings) {
      [
        'plugin' => $plugin_id,
        'types' => $types,
        'module' => $module,
        'name' => $human_name,
        'group' => $group,
        'migrations' => $migrations,
      ] = $export_settings;
      $plugin_label = $this->exportPluginManager->hasDefinition($plugin_id)
        ? $this->exportPluginManager->getDefinition($plugin_id)['label']
        : NULL;
      $form['table'][$module]['name'] = [
        'name' => [
          '#markup' => $human_name,
        ],
        'machine_name' => [
          '#prefix' => '<br>',
          '#type' => 'html_tag',
          '#tag' => 'code',
          '#value' => $module,
        ],
      ];
      $form['table'][$module]['group'] = [
        '#markup' => $group,
      ];
      $form['table'][$module]['plugin'] = [
        'plugin_name' => [
          '#markup' => $plugin_label,
        ],
        'plugin_machine_name' => [
          '#type' => 'html_tag',
          '#tag' => 'code',
          '#value' => $plugin_id,
          '#suffix' => $this->t('(missing)'),
          '#access' => $plugin_label === NULL,
        ],
      ];
      $form['table'][$module]['initial_types'] = [
        '#markup' => implode(', ', $types),
      ];
      $form['table'][$module]['migrations'] = [
        '#markup' => implode(', ', $migrations),
      ];

      $form['table'][$module]['operations'] = [
        '#type' => 'submit',
        '#name' => $module,
        '#value' => $this->t('Reexport'),
        '#disabled' => !$plugin_label,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $plugin_id = $form_state->getTriggeringElement()['#name'];

    if (
      array_key_exists($plugin_id, $this->discoveredExports) &&
      array_key_exists('types', $this->discoveredExports[$plugin_id])
    ) {
      $export_plugin = $this->exportPluginManager->createInstance(
        $this->discoveredExports[$plugin_id]['plugin'] ?? 'json_files',
        $this->discoveredExports[$plugin_id]
      );

      $this->batchRunner->setupBatch(
        $export_plugin,
        [get_class($this), 'finishBatch']
      );
    }
  }

  /**
   * Returns skeleton for example tables.
   */
  public function getTableSkeleton() {
    return [
      '#type' => 'table',
      '#empty' => $this->t('No content export module available.'),
      '#header' => [
        [
          'data' => $this->t('Name'),
        ],
        [
          'data' => $this->t('Group'),
          'class' => [RESPONSIVE_PRIORITY_MEDIUM],
        ],
        [
          'data' => $this->t('Export plugin'),
        ],
        [
          'data' => $this->t('Entity type IDs'),
          'class' => [RESPONSIVE_PRIORITY_MEDIUM],
        ],
        [
          'data' => $this->t('Migration IDs'),
          'class' => [RESPONSIVE_PRIORITY_LOW],
        ],
        [
          'data' => $this->t('Operations'),
        ],
      ],
    ];
  }

}
