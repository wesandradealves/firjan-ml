<?php

declare(strict_types=1);

namespace Drupal\eme\Export;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\Variable;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\Exception\FileWriteException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eme\Component\TemporaryExport;
use Drupal\eme\Eme;
use Drupal\eme\ExportException;
use Drupal\eme\ReferenceDiscovery\DiscoveryPluginManagerInterface;
use Drupal\eme\Utility\EmeModuleFileUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for entity export plugins.
 */
abstract class ExportPluginBase extends PluginBase implements ExportPluginInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Module extension list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * The reference discovery plugin manager.
   *
   * @var \Drupal\eme\ReferenceDiscovery\DiscoveryPluginManager
   */
  protected $discoveryPluginManager;

  /**
   * Entity memory cache.
   *
   * @var \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface
   */
  protected $entityMemoryCache;

  /**
   * The logger to use.
   *
   * @var \Psr\Log\LoggerInterface|null
   */
  protected $logger;

  /**
   * Whether translations should be included or not.
   *
   * @var bool
   */
  protected $includeTranslations = TRUE;

  /**
   * The temporary export module being created.
   *
   * @var \Drupal\eme\Component\TemporaryExport
   */
  protected $export;

  /**
   * Constructs an export plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The module extension list service.
   * @param \Drupal\eme\ReferenceDiscovery\DiscoveryPluginManagerInterface $discovery_plugin_manager
   *   The reference discovery plugin manager.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $entity_memory_cache
   *   Entity memory cache.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, LockBackendInterface $lock, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FileSystemInterface $file_system, ModuleExtensionList $module_list, DiscoveryPluginManagerInterface $discovery_plugin_manager, MemoryCacheInterface $entity_memory_cache) {
    $configuration += [
      'types' => [],
      'module' => Eme::getModuleName(),
      'name' => Eme::getModuleHumanName(),
      'id-prefix' => Eme::getDefaultId(),
      'group' => Eme::getDefaultId(),
      'path' => NULL,
    ];
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->lock = $lock;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->fileSystem = $file_system;
    $this->moduleExtensionList = $module_list;
    $this->discoveryPluginManager = $discovery_plugin_manager;
    $this->entityMemoryCache = $entity_memory_cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('lock.persistent'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('file_system'),
      $container->get('extension.list.module'),
      $container->get('eme.discovery_plugin_manager'),
      $container->get('entity.memory_cache')
    );
  }

  /**
   * The ordered tasks which create the migration export.
   *
   * @return string[]
   *   An array of method names which are invoked to complete the export.
   */
  public function tasks(): array {
    return [
      'initializeExport',
      'discoverContentReferences',
      'writeMigrationPlugins',
      'buildModule',
      'finishExport',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setLogger(LoggerInterface $logger = NULL): void {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function hasLogger(): bool {
    return !is_null($this->logger);
  }

  /**
   * Initializes the export process.
   *
   * @param \ArrayAccess $context
   *   The batch context.
   */
  protected function initializeExport(\ArrayAccess &$context): void {
    $this->sendMessage($this->t('Initializing the export process.'), $context);
    $this->temporaryExport()->reset();
    $this->doLock();
    $context['finished'] = 1;
  }

  /**
   * Discovers referred content entities as a batch operation.
   *
   * @param \ArrayAccess $context
   *   The batch context.
   */
  protected function discoverContentReferences(\ArrayAccess &$context): void {
    $sandbox = &$context['sandbox'];
    if (!isset($sandbox['entities_to_export'])) {
      $this->sendMessage($this->t('Discovering content references...'), $context);
      $sandbox['entities_to_export'] = [];
      $sandbox['entities_checked'] = [];
      $context['results']['discovered'] = [];

      foreach ($this->configuration['types'] as $entity_type) {
        $entities = $this->loadEntitiesByType($entity_type);
        $sandbox['entities_to_export'] += $entities;
      }

      $sandbox['progress'] = 0;
      $sandbox['total'] = count($sandbox['entities_to_export']);
    }

    $this->sendMessage($this->t('Discovering content references: (@processed/@total)', [
      '@processed' => $sandbox['progress'],
      '@total' => $sandbox['total'],
    ]), $context);

    $unchecked = array_diff($sandbox['entities_to_export'], $sandbox['entities_checked']);
    if ($current = reset($unchecked)) {
      $sandbox['progress'] += 1;

      // @todo Also check translations.
      [
        $entity_type_id,
        $entity_id,
      ] = explode(':', $current);
      $entity_bundle = explode(':', $current)[2] ?? NULL;
      $eme_relation_id = implode(':', array_filter([
        $entity_type_id,
        $entity_bundle,
      ]));

      $context['results']['relations'][$eme_relation_id] = $context['results']['relations'][$eme_relation_id] ?? [];

      $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
      $related_entities = $this->getRelatedContentEntities($entity);

      // Add discovered referenced entities to the export array.
      if ($related_entities['direct']) {
        $direct_ref_eme_ids = array_map(
          [get_class($this), 'typeAndBundleFromEmeId'],
          array_values($related_entities['direct'])
        );
        $context['results']['relations'][$eme_relation_id] = array_unique(
          array_merge(
            $context['results']['relations'][$eme_relation_id],
            $direct_ref_eme_ids
          )
        );
      }

      if ($related_entities['reverse']) {
        $reverse_ref_eme_ids = array_map(
          [get_class($this), 'typeAndBundleFromEmeId'],
          $related_entities['reverse']
        );

        foreach ($reverse_ref_eme_ids as $ref_relation_id) {
          $context['results']['relations'][$ref_relation_id] = array_unique(
            array_merge(
              $context['results']['relations'][$ref_relation_id] ?? [],
              [$eme_relation_id]
            )
          );
        }
      }

      $sandbox['entities_to_export'] += $related_entities['direct'] + $related_entities['reverse'];
      $sandbox['total'] = count($sandbox['entities_to_export']);

      // Add entity to the results.
      $context['results']['discovered'] += [$current => $current];
      $sandbox['entities_checked'][$current] = $current;
    }

    if ($sandbox['progress'] < $sandbox['total']) {
      $context['finished'] = $sandbox['progress'] / $sandbox['total'];
      $this->flushEntityMemoryCache($sandbox['progress']);
    }
    else {
      // Remove ignored entities.
      $ignored = [
        'user:0',
      ];
      foreach ($ignored as $item) {
        unset($context['results']['discovered'][$item]);
      }
      natsort($context['results']['discovered']);
      $context['finished'] = 1;
      $this->flushEntityMemoryCache();
    }
  }

  /**
   * Generates the migration plugin definitions.
   *
   * @param \ArrayAccess $context
   *   The batch context.
   */
  protected function writeMigrationPlugins(\ArrayAccess &$context): void {
    $sandbox = &$context['sandbox'];
    if (!isset($sandbox['total'])) {
      $this->sendMessage($this->t('Generating the migration plugin definitions.'), $context);
      $sandbox['plugins_to_write'] = array_keys($context['results']['relations'] ?? []);
      $sandbox['progress'] = 0;
      $sandbox['total'] = count($sandbox['plugins_to_write']);
    }

    $this->sendMessage($this->t('Generating the migration plugin definitions: (@processed/@total).', [
      '@processed' => $sandbox['progress'],
      '@total' => $sandbox['total'],
    ]), $context);

    // Create the migration plugin definition (a Yaml).
    if ($current_type_and_bundle = array_shift($sandbox['plugins_to_write'])) {
      $type_and_bundle_pieces = explode(':', $current_type_and_bundle);
      $migration_id = $this->getMigrationId($type_and_bundle_pieces[0], $type_and_bundle_pieces[1] ?? NULL);

      $plugin_definition = $this->createMigrationPluginDefinition($current_type_and_bundle, $context['results'] ?? []);

      $this->temporaryExport()->addFileWithContent(
        Eme::MIGRATION_DIR . "/$migration_id.yml",
        Yaml::encode($plugin_definition)
      );

      $context['results']['migration_ids'][] = $migration_id;
      $sandbox['progress'] += 1;
    }

    if ($sandbox['progress'] < $sandbox['total']) {
      $context['finished'] = $sandbox['progress'] / $sandbox['total'];
      $this->flushEntityMemoryCache($sandbox['progress']);
    }
    else {
      $context['finished'] = 1;
      $this->flushEntityMemoryCache();
    }
  }

  /**
   * Builds the exported module, meaning its info and module file (if needed).
   *
   * @param \ArrayAccess $context
   *   The batch context.
   */
  protected function buildModule(\ArrayAccess &$context): void {
    $this->sendMessage($this->t('Finalize the module.'), $context);
    $module = $this->configuration['module'];
    $module_list = $this->moduleExtensionList->reset()->getList();
    $module_exists = array_key_exists($module, $module_list);
    $migration_ids = $context['results']['migration_ids'] ?? [];
    natsort($migration_ids);
    sort($this->configuration['types']);
    $info_yaml = [
      'name' => $this->configuration['name'],
      'type' => 'module',
      'description' => 'Generated with EME module',
      'core_version_requirement' => '^8.9 || ^9 || ^10',
      'scenarios_module' => $module,
      'dependencies' => [],
      'eme_settings' => [],
    ];
    $module_file_content = EmeModuleFileUtils::getBareModuleFile($module);

    if ($module_exists) {
      $module_path = $module_list[$module]->getPath();
      $info_yaml = Yaml::decode(file_get_contents(implode('/', [
        $module_path,
        "$module.info.yml",
      ])));

      // The module file (if it exists) should be kept.
      $module_file_path = implode('/', [
        $module_path,
        "$module.module",
      ]);
      if (file_exists($module_file_path)) {
        $module_file_content = file_get_contents($module_file_path);
      }

      // Let's delete the previous migration definitions.
      foreach ($info_yaml['eme_settings']['migrations'] ?? [] as $migration_id) {
        // Delete data sources.
        $yaml_path = implode('/', [
          $module_path,
          Eme::MIGRATION_DIR,
          "$migration_id.yml",
        ]);

        if (file_exists($yaml_path)) {
          $this->fileSystem->deleteRecursive($yaml_path);
        }
      }
    }

    $dependencies = array_unique(
      array_merge(
        $info_yaml['dependencies'] ?? [],
        ['drupal:migrate']
      )
    );
    natsort($dependencies);
    $info_yaml['dependencies'] = array_values($dependencies);
    $info_yaml['eme_settings'] = [
      'plugin' => $this->getPluginId(),
      'migrations' => array_values($migration_ids),
      'types' => array_values($this->configuration['types']),
      'id-prefix' => $this->configuration['id-prefix'],
      'group' => $this->configuration['group'],
    ];

    // Add the alterer.
    EmeModuleFileUtils::ensureUseDeclaration(
      "Drupal\\{$module}\\ModuleImplementsAlterer",
      $module_file_content
    );
    EmeModuleFileUtils::ensureFunctionUsedInHook(
      'hook_module_implements_alter',
      ['&$implementations', '$hook'],
      'ModuleImplementsAlterer::alter($1, $2)',
      $module,
      $module_file_content
    );
    $this->temporaryExport()->addFileWithContent(
      'src/ModuleImplementsAlterer.php',
      EmeModuleFileUtils::moduleImplementsAltererClass($module)
    );

    // Add info Yaml and the module file.
    $this->temporaryExport()->addFileWithContent(
      "$module.info.yml",
      Yaml::encode($info_yaml)
    );
    $this->temporaryExport()->addFileWithContent(
      "$module.module",
      $module_file_content
    );

    $context['finished'] = 1;
    $context['results']['redirect'] = empty($this->configuration['path']);
  }

  /**
   * Moves the export to the codebase or creates an archive for downloading.
   *
   * @param \ArrayAccess $context
   *   The batch context.
   */
  protected function finishExport(\ArrayAccess &$context): void {
    if ($destination = $this->configuration['path']) {
      $this->sendMessage($this->t('Copy the export module to codebase.'), $context);
      $module = $this->configuration['module'];
      $module_list = $this->moduleExtensionList->reset()->getList();
      $module_exists = array_key_exists($module, $module_list);
      $module_path = $module_exists
        ? $module_list[$module]->getPath()
        : implode('/', [$destination, $module]);
      if (!$this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        throw new \RuntimeException(sprintf("Cannot prepare the directory '%s'", $destination));
      }

      // If destination is not absolute and isn't a stream, add DRUPAL_ROOT.
      if (
        !($scheme = StreamWrapperManager::getScheme($module_path)) &&
        strpos($module_path, DIRECTORY_SEPARATOR) !== 0
      ) {
        $module_path = DRUPAL_ROOT . DIRECTORY_SEPARATOR . $module_path;
      }

      if (!$this->temporaryExport()->move($module_path)) {
        throw new FileWriteException(sprintf("Cannot move the temporary archive to Drupal codebase."));
      }
    }
    else {
      $this->sendMessage($this->t('Create the export module archive.'), $context);
      $this->temporaryExport()->createTemporaryArchive();
    }

    $context['finished'] = 1;
    $this->releaseLock();
  }

  /**
   * {@inheritdoc}
   */
  final public function executeExportTask(string $export_task, &$context): void {
    // In order for being able to validate an export plugin, steps are only
    // allowed to get an \ArrayAccess instance as parameter.
    // This helper callback translates a Drupal Form API batch context with type
    // 'array' to an \ArrayObject and keeps sync its values with the original
    // batch context.
    $array_access_context = is_array($context)
      ? new \ArrayObject($context)
      : $context;
    try {
      $this->$export_task($array_access_context);
      if (is_array($context)) {
        foreach ($array_access_context as $key => $value) {
          $context[$key] = $value;
        }
      }
      return;
    }
    catch (\Throwable $exception) {
    }

    $this->releaseLock();
    if (!empty($exception)) {
      throw new ExportException(sprintf("Unexpected error while processing %s.", Variable::export($export_task)), 1, $exception);
    }
  }

  /**
   * Entity loader.
   *
   * @param string $entity_type
   *   Content Entity Type which entities should be loaded.
   *
   * @return array
   *   List of content entity IDs.
   */
  protected function loadEntitiesByType($entity_type): array {
    if (in_array($entity_type, Eme::getExcludedTypes(), TRUE)) {
      return [];
    }
    if (!($definition = $this->entityTypeManager->getDefinition($entity_type, FALSE))) {
      return [];
    }
    if (!$definition instanceof ContentEntityTypeInterface) {
      return [];
    }
    $entity_storage = $this->entityTypeManager->getStorage($entity_type);
    assert($entity_storage instanceof EntityStorageInterface);
    // @todo Add real permission checks for this module.
    $entity_ids = $entity_storage->getQuery()
      ->accessCheck(FALSE)
      ->execute();

    $entity_eme_ids = self::getEmeIds($entity_storage->loadMultiple($entity_ids));
    return array_combine($entity_eme_ids, $entity_eme_ids);
  }

  /**
   * Discovers related entities of the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   *   A content entity.
   *
   * @return array
   *   An array of related entities, grouped by the relation type: 'direct' and
   *   'reverse'.
   */
  protected function getRelatedContentEntities(ContentEntityBase $entity): array {
    $related_entities = [
      'direct' => [],
      'reverse' => [],
    ];
    foreach ($this->discoveryPluginManager->getDirectReferenceDiscoveryPluginInstances() as $plugin) {
      $related_entities['direct'] = array_merge(
        $related_entities['direct'],
        $plugin->fetchReferences($entity)
      );
    }
    foreach ($this->discoveryPluginManager->getReverseReferenceDiscoveryPluginInstances() as $plugin) {
      $related_entities['reverse'] = array_merge(
        $related_entities['reverse'],
        $plugin->fetchReverseReferences($entity)
      );
    }

    foreach ($related_entities as $type => $related_entities_per_type) {
      $unique_eme_ids = self::getEmeIds($related_entities_per_type);
      $related_entities[$type] = array_combine($unique_eme_ids, $unique_eme_ids);
    }

    return $related_entities;
  }

  /**
   * Returns the internal EME IDs of the given content entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   The entities.
   *
   * @return string[]
   *   The EME IDs.
   */
  protected static function getEmeIds(array $entities): array {
    $entity_eme_ids = array_reduce($entities, function (array $carry, EntityInterface $entity) {
      if (!$entity instanceof ContentEntityInterface) {
        return $carry;
      }
      $id_parts = [
        'type' => $entity->getEntityTypeId(),
        'id' => $entity->id(),
      ];
      if (
        $entity->getEntityType()->getKey('bundle') &&
        $entity->getEntityType()->getBundleEntityType()
      ) {
        $id_parts['bundle'] = $entity->bundle();
      }
      $carry[] = implode(':', $id_parts);
      return $carry;
    }, []);

    return array_unique($entity_eme_ids);
  }

  /**
   * Returns entity type ID and bundle from an EME ID.
   *
   * @param string $eme_id
   *   The EME ID.
   *
   * @return string
   *   An array of the entity type ID and bundle.
   */
  protected static function typeAndBundleFromEmeId(string $eme_id): string {
    $parts = explode(':', $eme_id);
    return implode(':', array_filter([
      $parts[0],
      $parts[2] ?? NULL,
    ]));
  }

  /**
   * Helper which returns special entity keys.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string|null $key
   *   The key. Defaults to "id".
   *
   * @return string|false
   *   The entity specific key.
   */
  protected function getEntityTypeKey($entity_type_id, $key = 'id') {
    return $this->entityTypeManager->getDefinition($entity_type_id)->getKey($key);
  }

  /**
   * Creates an array that represents a migration plugin definition.
   *
   * @param string $type_and_bundle
   *   The entity type and bundle which should get a migration plugin
   *   definition. If there is a bundle, then it is separated from the entity
   *   type id by a colon, e.g: "user"; "node:article".
   * @param array $results
   *   The results of the previously executed export tasks.
   *
   * @return array
   *   A migration plugin definition as array.
   */
  protected function createMigrationPluginDefinition(string $type_and_bundle, array $results): array {
    $type_and_bundle_pieces = explode(':', $type_and_bundle);
    $entity_type_id = $type_and_bundle_pieces[0];
    $bundle = $type_and_bundle_pieces[1] ?? NULL;
    $migration_id = $this->getMigrationId($entity_type_id, $bundle);
    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle ?? $entity_type_id);

    $destination_plugin_base = $this->getEntityTypeKey($entity_type_id, 'revision')
      ? 'entity_complete'
      : 'entity';
    if ($entity_type_id === 'paragraph') {
      $destination_plugin_base = 'entity_reference_revisions';
    }
    $destination_plugin = implode(PluginBase::DERIVATIVE_SEPARATOR, [
      $destination_plugin_base,
      $entity_type_id,
    ]);

    // For first, let's create the skeleton of the migration plugin
    // definition.
    $plugin_definition = [
      'label' => implode(' ', array_filter([
        'Import',
        $entity_type_id,
        $bundle,
      ])),
      'migration_tags' => [
        'Drupal ' . explode('.', \Drupal::VERSION)[0],
        'Content',
        $this->configuration['name'],
        $this->configuration['group'],
      ],
      'migration_group' => $this->configuration['group'],
      'id' => $migration_id,
      'source' => [],
      'process' => array_combine(array_keys($field_definitions), array_keys($field_definitions)),
      'destination' => [
        'plugin' => $destination_plugin,
        'translations' => $this->entityTypeManager->getDefinition($entity_type_id)->getKey('langcode')
          ? $this->includeTranslations
          : FALSE,
      ],
      'migration_dependencies' => [],
    ];

    $migration_dependencies = [
      'required' => [],
      'optional' => [],
    ];

    // Dependencies.
    foreach ($results['relations'][$type_and_bundle] as $entity_dependency) {
      $dependency_type = 'optional';
      if (
        // Comments need a preexisting author.
        ($entity_type_id === 'comment' && $entity_dependency === 'user') ||
        // The migration of users without missing user picture throws notice.
        ($entity_type_id === 'user' && $entity_dependency === 'file') ||
        // Crop entities should be migrated before files.
        ($entity_type_id === 'file' && strpos($entity_dependency, 'crop:') === 0)
      ) {
        $dependency_type = 'required';
      }
      $dependency_migration_id = call_user_func_array(
        [$this, 'getMigrationId'],
        explode(':', $entity_dependency)
      );

      $migration_dependencies[$dependency_type] = array_unique(
        array_merge(
          $migration_dependencies[$dependency_type] ?? [],
          [$dependency_migration_id]
        )
      );
    }

    sort($migration_dependencies['optional']);
    sort($migration_dependencies['required']);
    $plugin_definition['migration_dependencies'] = $migration_dependencies;

    return $plugin_definition;
  }

  /**
   * Returns the ID of a content entity migration plugin definition.
   *
   * @param string $entity_type_id
   *   The entity type ID of the entity, e.g. "node".
   * @param string|null $bundle
   *   The bundle ID of the entity, e.g. "article".
   *
   * @return string
   *   The ID of a content entity migration plugin definition.
   */
  private function getMigrationId(string $entity_type_id, string $bundle = NULL): string {
    return implode('_', array_filter([
      $this->configuration['id-prefix'],
      $entity_type_id,
      $bundle,
    ]));
  }

  /**
   * Sends a message to the current "UI".
   *
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   The message.
   * @param array|\ArrayAccess $context
   *   A batch context.
   * @param bool $forced
   *   Whether the message should be sent not only for the first round.
   */
  protected function sendMessage($message, &$context, bool $forced = FALSE): void {
    $no_message_was_set_before = empty($context['sandbox']['message_set']);
    $context['sandbox']['message_set'] = TRUE;
    if ($this->hasLogger() && ($no_message_was_set_before || $forced)) {
      $this->logger->notice($message);
    }

    if ($no_message_was_set_before || !($context instanceof \DrushBatchContext)) {
      $context['message'] = $message;
    }
  }

  /**
   * Locks the export process.
   */
  protected function doLock(): void {
    if (!$this->lock->acquire(Eme::LOCK_NAME)) {
      throw new ExportException('An another process is already exporting content.');
    }
  }

  /**
   * Releases the lock of the export process.
   */
  protected function releaseLock(): void {
    $this->lock->release(Eme::LOCK_NAME);
  }

  /**
   * Determines if an export is already running.
   *
   * @return bool
   *   TRUE if an export is already running, FALSE if not.
   */
  public function alreadyProcessing(): bool {
    return !$this->lock->lockMayBeAvailable(Eme::LOCK_NAME);
  }

  /**
   * Drops static entity memory cache (per 10 iteration).
   *
   * @param int|null $progress
   *   The current progress. If this is NULL, then the cache will be dropped
   *   without any condition.
   */
  protected function flushEntityMemoryCache(int $progress = NULL): void {
    if (
      $progress === NULL ||
      fmod((float) $progress, 10.0) < 0.01
    ) {
      $this->entityMemoryCache->deleteAll();
    }
  }

  /**
   * Returns the actual export module.
   *
   * @return \Drupal\eme\Component\TemporaryExport
   *   The export module.
   */
  protected function temporaryExport() {
    if ($this->export === NULL) {
      $this->export = new TemporaryExport($this->fileSystem->getTempDirectory());
    }

    return $this->export;
  }

}
