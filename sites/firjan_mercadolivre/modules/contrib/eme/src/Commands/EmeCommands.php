<?php

namespace Drupal\eme\Commands;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\Variable;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\eme\Component\TemporaryExport;
use Drupal\eme\Eme;
use Drupal\eme\Export\ExportPluginInterface;
use Drupal\eme\Export\ExportPluginManager;
use Drupal\eme\ExportException;
use Drupal\eme\InterfaceAwareExportBatchRunner;
use Drupal\eme\Utility\EmeCollectionUtils;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Drush commands of Entity Migrate Export.
 */
class EmeCommands extends DrushCommands {

  /**
   * Info about discovered previous exports.
   *
   * @var array[]
   */
  protected $discoveredExports;

  /**
   * List of the discovered modules.
   *
   * @var string[]
   */
  protected $discoveredModules;

  /**
   * The export batch runner.
   *
   * @var \Drupal\eme\InterfaceAwareExportBatchRunner
   */
  protected $batchRunner;

  /**
   * The exportable content entity types (prepared labels keyed by the ID).
   *
   * @var string[]|\Drupal\Core\StringTranslation\TranslatableMarkup[]
   */
  protected $contentEntityTypes;

  /**
   * The export plugin manager.
   *
   * @var \Drupal\eme\Export\ExportPluginManager
   */
  protected $exportPluginManager;

  /**
   * Persistent lock.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The Drupal file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Construct an EmeCommands instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The module extension list service.
   * @param \Drupal\eme\InterfaceAwareExportBatchRunner $batch_runner
   *   The export batch runner.
   * @param \Drupal\eme\Export\ExportPluginManager $export_plugin_manager
   *   The export plugin manager.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock_persistent
   *   The lock backend.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The Drupal file system service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleExtensionList $module_list, InterfaceAwareExportBatchRunner $batch_runner, ExportPluginManager $export_plugin_manager, LockBackendInterface $lock_persistent, FileSystemInterface $file_system) {
    parent::__construct();
    $this->discoveredExports = EmeCollectionUtils::getExports($module_list);
    $this->discoveredModules = array_keys($module_list->reset()->getList());
    $this->contentEntityTypes = EmeCollectionUtils::getContentEntityTypes($entity_type_manager);
    $this->batchRunner = $batch_runner;
    $this->exportPluginManager = $export_plugin_manager;
    $this->lock = $lock_persistent;
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Releases a dead EME process lock.
   *
   * @command eme:release-lock
   *
   * @aliases emerl
   */
  public function releaseLock() {
    $this->lock->release(Eme::LOCK_NAME);
  }

  /**
   * Removes temporary export from Drupal's temporary directory.
   *
   * @command eme:cleanup
   *
   * @aliases emecl
   */
  public function cleanup() {
    (new TemporaryExport($this->fileSystem->getTempDirectory()))->reset();
  }

  /**
   * Exports content to migration.
   *
   * @param array $options
   *   An associative array of options whose values come from cli.
   *
   * @option types
   *   IDs of entity types to export, separated by commas.
   * @option destination
   *   The destination of the module. Defaults to 'modules/custom'.
   * @option module
   *   The name of the module to export to.
   * @option name
   *   The human name of the module to export to.
   * @option id-prefix
   *   The "base" ID of the generated migrations.
   * @option group
   *   The group of the generated migrations.
   * @option update
   *   The name of the module to update.
   * @option id
   *   The base ID for the module name, the migration ID prefix and the
   *   migration group.
   * @option plugin
   *   The ID of the export plugin to use.
   * @option use-batch
   *   Use Drupal batch.
   *
   * @usage eme:export --id demo --types node,block_content
   *   Export all custom blocks, nodes and their dependencies to a new module at
   *   location DRUPAL_ROOT/modules/custom with name "demo_content", and with
   *   the migration group "demo".
   *
   * @usage eme:export --update demo_content
   *   Refresh the previously created export which module name is
   *   "demo_content".
   *
   * @command eme:export
   *
   * @aliases emex
   */
  public function export(array $options = [
    'types' => NULL,
    'destination' => NULL,
    'module' => NULL,
    'name' => NULL,
    'id-prefix' => NULL,
    'id' => NULL,
    'group' => NULL,
    'update' => NULL,
    'plugin' => 'json_files',
  ]) {
    $given_options = array_filter($options);
    $types_to_export = !empty($given_options['types'])
      ? array_filter(explode(',', $given_options['types']))
      : NULL;
    $id = $given_options['id'] ?? Eme::getDefaultId();
    $module_name = $given_options['module'] ?? Eme::getModuleName($id);
    $human_name = $given_options['name'] ?? Eme::getModuleHumanName($id);
    $migration_prefix = $given_options['id-prefix'] ?? $id;
    $migration_group = $given_options['group'] ?? $id;
    $export_type = $given_options['plugin'];
    $destination = !empty($options['destination'])
      ? trim($given_options['destination'], '/')
      : 'modules/custom';

    // Update does not allows override anything but the exported entity types.
    if ($options['update']) {
      if (!array_key_exists($options['update'], $this->discoveredExports)) {
        $this->logger()->error(dt('The specified export module does not exist.'));
        return;
      }
      $module_name = $options['update'];
      // Update does not allows override anything but the exported entity types.
      $types_to_export = $types_to_export ?? $this->discoveredExports[$module_name]['types'];
      [
        'name' => $human_name,
        'id-prefix' => $migration_prefix,
        'group' => $migration_group,
        'path' => $destination,
      ] = $this->discoveredExports[$module_name];
      $export_type = $this->discoveredExports[$module_name]['plugin'] ?? 'json_files';
    }

    if (!$options['update'] && array_key_exists($module_name, $this->discoveredExports)) {
      $this->logger()->error(dt("An export with '@module' module name already exist. Consider choosing a different module name or delete the preexisting export", [
        '@module' => $module_name,
      ]));
      return;
    }

    // Validate entity type IDs.
    if (empty($types_to_export) || !is_array($types_to_export)) {
      $this->logger()->error(dt('No entity types were provided.'));
      return;
    }
    $missing_mistyped_ignored = array_reduce($types_to_export, function (array $carry, string $entity_type_id) {
      if (!isset($this->contentEntityTypes[$entity_type_id])) {
        $carry[] = $entity_type_id;
      }
      return $carry;
    }, []);
    if (!empty($missing_mistyped_ignored)) {
      $this->logger()->error(dt('The following entity type IDs cannot be found or are set to be ignored during content export: @entity-types.', [
        '@entity-types' => implode(', ', $missing_mistyped_ignored),
      ]));
      return;
    }

    // Validate export destination.
    if (empty($destination)) {
      $this->logger()->error(dt('Destination of the export module must be provided.'));
      return;
    }

    // Validate module name.
    if (empty($options['update']) && array_key_exists($module_name, $this->discoveredModules)) {
      $this->logger()->error(dt('A module with name @module-name already exists on the file system. You should pick a different module name.', [
        '@module-name' => $module_name,
      ]));
      return;
    }

    $export_config = [
      'types' => $types_to_export,
      'module' => $module_name,
      'name' => $human_name,
      'id-prefix' => $migration_prefix,
      'group' => $migration_group,
      'path' => $destination,
    ];

    try {
      $export_plugin = $this->exportPluginManager->createInstance($export_type, $export_config);
    }
    catch (PluginException $exception) {
      $this->logger()->error($exception->getMessage());
      return;
    }

    $this->logger()->debug(dt("The export process will use the '@plugin-id' export plugin with config:\n  @config", [
      '@plugin-id' => $export_plugin->getPluginId(),
      '@config' => Variable::export($export_config, '  '),
    ]));

    $started = microtime(TRUE);

    if (empty($given_options['use-batch'])) {
      $this->exportWithoutBatch($export_plugin);
    }
    else {
      $this->exportWithBatch($export_plugin);
    }

    $this->output()->write(dt('🎉 Export finished.'));
    if ($this->output()->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
      $ended = microtime(TRUE);
      $this->output()->write(' ');
      $this->output()->write(dt('The export process took @seconds seconds.', [
        '@seconds' => round($ended - $started, 2),
      ]));
    }
    $this->output()->writeln('');
  }

  /**
   * Executes a content entity export using batch operations.
   *
   * @param \Drupal\eme\Export\ExportPluginInterface $export_plugin
   *   The export to execute.
   */
  protected function exportWithBatch(ExportPluginInterface $export_plugin) {
    try {
      $this->batchRunner->setupBatch($export_plugin);
      drush_backend_batch_process();
    }
    catch (\Exception $exception) {
      $this->releaseLock();
      // \Consolidation\SiteProcess\ProcessBase throws InvalidArgumentException
      // when the process output is empty – and it typically means that one
      // of the batch tasks failed.
      // EME will suppress these exceptions.
      if (
        get_class($exception) !== \InvalidArgumentException::class ||
        $exception->getMessage() !== 'Output is empty.'
      ) {
        throw $exception;
      }
      throw new ExportException('An exception was thrown while processing the batch.');
    }
  }

  /**
   * Executes a content entity export without using batch operations.
   *
   * @param \Drupal\eme\Export\ExportPluginInterface $export_plugin
   *   The export to execute.
   */
  protected function exportWithoutBatch(ExportPluginInterface $export_plugin) {
    $export_plugin->setLogger($this->logger());
    $tasks = $export_plugin->tasks();
    $context = [];
    $progressbar = new ProgressBar($this->output());
    $set_max_is_callable = is_callable([$progressbar, 'setMaxSteps']);

    foreach ($tasks as $task) {
      if (!$export_plugin->hasLogger()) {
        $this->logger->notice(self::getTaskMessageFallback($task));
      }
      $context = array_filter([
        'finished' => 1,
        'results' => $context['results'] ?? NULL,
      ]);

      try {
        do {
          $export_plugin->executeExportTask($task, $context);
          $total = intval($context['sandbox']['total'] ?? 0);

          if ($total && $progressbar->getMaxSteps() === 0) {
            $progressbar->start($context['sandbox']['total']);
          }
          elseif ($total && $set_max_is_callable && $progressbar->getMaxSteps() !== $total) {
            $progressbar->setMaxSteps($context['sandbox']['total']);
          }

          if (!empty($context['sandbox']['progress'])) {
            $progressbar->setProgress(intval($context['sandbox']['progress']));
          }
        } while ($context['finished'] < 1);
      }
      catch (\Exception $exception) {
        $this->releaseLock();
        if ($exception instanceof ExportException) {
          throw $exception;
        }
        throw new ExportException(sprintf('An exception was thrown while processing step %s.', Variable::export($task)), 1, $exception);
      }

      $progressbar->finish();
      $progressbar->clear();
      if ($set_max_is_callable) {
        $progressbar->setMaxSteps(0);
      }
      else {
        $progressbar = new ProgressBar($this->output());
      }
    }
  }

  /**
   * Creates a human friendly sentence from a method name.
   *
   * @param string $task
   *   The method name of a task. This is ususally a lowerCamelCased string like
   *   'discoverContentEntityReferences'.
   *
   * @return string
   *   The human friendly representation of the task method name, e.g.
   *   'Discover content entity references.'.
   */
  protected static function getTaskMessageFallback(string $task): string {
    $chunks = preg_split('/(?=[A-Z])/', $task);
    $chunks = array_map(function ($chunk) {
      return strtolower($chunk);
    }, $chunks);

    return ucfirst(implode(' ', $chunks)) . '.';
  }

}
