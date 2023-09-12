<?php

namespace Drupal\eme\Form;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Url;
use Drupal\eme\Access\TemporarySchemeAccessCheck;
use Drupal\eme\Eme;
use Drupal\eme\Export\ExportPluginManager;
use Drupal\eme\InterfaceAwareExportBatchRunner;
use Drupal\eme\Utility\EmeCollectionUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Form for starting a Content Entity to Migrations batch.
 */
class ExportForm extends ExportFormBase {

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The exportable content entity types (prepared labels keyed by the ID).
   *
   * @var string[]|\Drupal\Core\StringTranslation\TranslatableMarkup[]
   */
  protected $contentEntityTypes;

  /**
   * Construct an ExportForm instance.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The module extension list service.
   * @param \Drupal\eme\InterfaceAwareExportBatchRunner $batch_runner
   *   The export batch runner.
   * @param \Drupal\eme\Export\ExportPluginManager $export_plugin_manager
   *   The export plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager.
   */
  public function __construct(ModuleExtensionList $module_list, InterfaceAwareExportBatchRunner $batch_runner, ExportPluginManager $export_plugin_manager, EntityTypeManagerInterface $entity_type_manager, StreamWrapperManagerInterface $stream_wrapper_manager) {
    parent::__construct($module_list, $batch_runner, $export_plugin_manager);
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->contentEntityTypes = EmeCollectionUtils::getContentEntityTypes($entity_type_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('extension.list.module'),
      $container->get('eme.batch_runner'),
      $container->get('eme.export_plugin_manager'),
      $container->get('entity_type.manager'),
      $container->get('stream_wrapper_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'eme_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL) {
    $temporary_stream_access = (new TemporarySchemeAccessCheck($this->streamWrapperManager))->access();
    if (!$temporary_stream_access->isAllowed()) {
      $form['info'] = [
        '#type' => 'item',
        '#markup' => $this->t('The temporary file directory isn\'t accessible. You must configure it for being able to export content. See the <a href=":system-file-settings-link">File system configuration form</a> for further info.', [
          ':system-file-settings-link' => Url::fromRoute('system.file_system_settings')->toString(),
        ]),
      ];
      return $form;
    }

    $form['#tree'] = FALSE;
    $form['row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['layout-row', 'clearfix']],
    ];
    $form['row']['col_first'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['layout-column', 'layout-column--half'],
      ],
    ];
    $form['row']['col_last'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['layout-column', 'layout-column--half'],
      ],
    ];

    $default_id = Eme::getDefaultId();
    $export_options = array_map(function ($definition) {
      $label_content = [
        'main' => [
          '#markup' => $definition['label'],
        ],
        'description' => [
          '#prefix' => '<br>',
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $definition['description'],
          '#attributes' => ['class' => ['description']],
        ],
      ];
      return \Drupal::service('renderer')->renderRoot($label_content);
    }, $this->exportPluginManager->getDefinitions());

    // If there is only one export plugin, then users don't need to pick one.
    $plugin_options_form_item = [
      '#type' => 'radios',
      '#title' => $this->t('Type of the export'),
      '#options' => $export_options,
      '#required' => TRUE,
    ];
    if (count($export_options) === 1) {
      $plugin_keys = array_keys($export_options);
      $plugin_options_form_item = [
        '#type' => 'value',
        '#value' => reset($plugin_keys),
      ];
    }

    // Export module config, metadata and structure.
    $form['row']['col_first']['plugin'] = $plugin_options_form_item;
    $form['row']['col_first']['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Export ID'),
      '#placeholder' => $default_id,
      '#description' => $this->t("A general ID. Used if one of module name, migration prefix and migration group isn't defined. By default, migration group and migration ID prefix will be this ID. Module name will be <code>[ID]_content</code>."),
      '#required' => FALSE,
      '#machine_name' => [
        'exists' => [get_class($this), 'machineNameExists'],
      ],
      '#attributes' => [
        'data-eme-export-id' => 'eme-id',
      ],
      '#attached' => [
        'library' => ['eme/export-id'],
        'drupalSettings' => [
          'emeExport' => [
            'eme-id' => [
              'source' => [
                '[data-drupal-selector="edit-id"]' => 'rplcmnt',
              ],
              'destination' => [
                '[data-drupal-selector="edit-module"]' => Eme::getModuleName('(rplcmnt)'),
                '[data-drupal-selector="edit-name"]' => [Eme::getModuleHumanName('(rplcmnt)')],
                '[data-drupal-selector="edit-prefix"]' => '(rplcmnt)',
                '[data-drupal-selector="edit-group"]' => '(rplcmnt)',
              ],
            ],
          ],
        ],
      ],
    ];
    $form['row']['col_first']['module'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Module name'),
      '#placeholder' => $default_id . '_content',
      '#description' => $this->t('The <em>machine name</em> of the generated module.'),
      '#required' => FALSE,
      '#machine_name' => [
        'exists' => [get_class($this), 'machineNameExists'],
      ],
    ];
    $form['row']['col_first']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Module human name'),
      '#placeholder' => Eme::getModuleHumanName($default_id),
      '#description' => $this->t('The <em>human-readable</em> name of the generated module'),
    ];
    $form['row']['col_first']['prefix'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Migration prefix'),
      '#placeholder' => $default_id,
      '#description' => $this->t('An ID prefix for the generated migration plugin definitions.'),
      '#required' => FALSE,
      '#machine_name' => [
        'exists' => [get_class($this), 'machineNameExists'],
      ],
    ];
    $form['row']['col_first']['group'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Migration group'),
      '#placeholder' => $default_id,
      '#description' => $this->t('The migration group of generated migration plugin definitions.'),
      '#required' => FALSE,
      '#machine_name' => [
        'exists' => [get_class($this), 'machineNameExists'],
      ],
    ];

    $form['row']['col_last']['types'] = [
      '#type' => 'checkboxes',
      '#options' => $this->contentEntityTypes,
      '#title' => $this->t('Select which content entities should be exported to a migration module'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#op' => 'default',
        '#value' => $this->t('Start export'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $id = empty($form_state->getValue('id'))
      ? Eme::ID
      : $form_state->getValue('id');
    $module_name_to_export = empty($form_state->getValue('module'))
      ? "{$id}_content"
      : $form_state->getValue('module');
    if (in_array($module_name_to_export, $this->discoveredModules)) {
      $form_state->setErrorByName('module', $this->t('A module with name @module-name already exists.', [
        '@module-name' => $module_name_to_export,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = array_filter($form_state->getValues());
    $id = $values['id'] ?? Eme::getDefaultId();

    $export_plugin = $this->exportPluginManager->createInstance(
      $values['plugin'],
      [
        'types' => array_values(array_filter($values['types'])),
        'module' => $values['module'] ?? Eme::getModuleName($id),
        'name' => $values['name'] ?? Eme::getModuleHumanName($id),
        'id-prefix' => $values['prefix'] ?? $id,
        'group' => $values['group'] ?? $id,
        'path' => NULL,
      ]);

    $this->batchRunner->setupBatch(
      $export_plugin,
      [get_class($this), 'finishBatch']
    );
  }

  /**
   * Used by machine name validate.
   */
  public static function machineNameExists($value, $element): bool {
    return FALSE;
  }

}
