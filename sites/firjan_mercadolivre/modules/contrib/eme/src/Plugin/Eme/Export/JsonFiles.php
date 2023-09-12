<?php

namespace Drupal\eme\Plugin\Eme\Export;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\TypedData\Type\IntegerInterface;
use Drupal\eme\Export\ExportPluginBase;
use Drupal\eme\ExportException;
use Drupal\eme\Utility\EmeModuleFileUtils;
use Drupal\file\Entity\File;

/**
 * Export plugin for migrations with local JSON file source.
 *
 * @Export(
 *   id = "json_files",
 *   label = @Translation("JSON files source"),
 *   description = @Translation("The exported migration contains the source data as well.")
 * )
 */
class JsonFiles extends ExportPluginBase {

  /**
   * The data source directory.
   *
   * @const string
   */
  const DATA_SUBDIR = 'data';

  /**
   * The directory of the files.
   *
   * @const string
   */
  const FILE_ASSETS_SUBDIR = 'assets';

  /**
   * Data file extension.
   *
   * @var string
   */
  protected $dataFileExtension = 'json';

  /**
   * {@inheritdoc}
   */
  public function tasks(): array {
    return [
      'initializeExport',
      'discoverContentReferences',
      'writeEntityDataSource',
      'writeMigratedFiles',
      'writeMigrationPlugins',
      'buildModule',
      'finishExport',
    ];
  }

  /**
   * Writes entity field values to a JSON file in the export module.
   *
   * @param \ArrayAccess $context
   *   The batch context.
   */
  protected function writeEntityDataSource(\ArrayAccess &$context): void {
    $sandbox = &$context['sandbox'];
    if (!isset($sandbox['entities_to_process'])) {
      $this->sendMessage($this->t('Collecting and writing entity data sources to files.'), $context);
      $sandbox['entities_to_process'] = $context['results']['discovered'];
      $sandbox['total'] = count($sandbox['entities_to_process'] ?? []);
      $sandbox['progress'] = 0;
    }

    $this->sendMessage($this->t('Collecting and writing entity data source to files: (@processed/@total)', [
      '@processed' => $sandbox['progress'],
      '@total' => $sandbox['total'],
    ]), $context);

    if (empty($sandbox['entities_to_process'])) {
      $context['finished'] = 1;
      return;
    }

    $current = array_shift($sandbox['entities_to_process']);
    $sandbox['progress'] += 1;
    [
      $entity_type_id,
      $entity_id,
    ] = explode(':', $current);

    // Get the entity.
    $entity = $this->entityTypeManager->getStorage($entity_type_id)
      ->load($entity_id);
    assert($entity instanceof ContentEntityInterface);
    $bundle = $entity->getEntityType()
      ->getKey('bundle') && $entity->getEntityType()->getBundleEntityType()
      ? $entity->bundle()
      : NULL;

    // Write data.
    $this->temporaryExport()->addFileWithContent(
      $this->getDataPath($entity_type_id, $bundle, $entity_id),
      $this->sourceDataToFileContent($this->getEntityValues($entity, $context))
    );

    if ($bundle) {
      $context['results']['exported_entities'][$entity_type_id][$bundle][] = $entity_id;
    }
    else {
      $context['results']['exported_entities'][$entity_type_id][] = $entity_id;
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
   * Adds files required for file migrations to the export module.
   *
   * @param \ArrayAccess $context
   *   The batch context.
   */
  protected function writeMigratedFiles(\ArrayAccess &$context): void {
    $sandbox = &$context['sandbox'];
    if (!isset($sandbox['total'])) {
      $files = array_values($context['results']['exported_entities']['file'] ?? []);
      $sandbox['files_to_process'] = array_combine($files, $files);
      $sandbox['total'] = count($sandbox['files_to_process']);
      $sandbox['progress'] = 0;
      if (empty($sandbox['files_to_process'])) {
        $context['finished'] = 1;
        return;
      }
      $this->sendMessage($this->t('Copy the necessary files.'), $context);
    }

    $this->sendMessage($this->t('Copy the necessary files: (@processed/@total)', [
      '@processed' => $sandbox['progress'],
      '@total' => $sandbox['total'],
    ]), $context);

    // Add file to the archive.
    $current_file_id = array_shift($sandbox['files_to_process']);
    $file = $this->entityTypeManager->getStorage('file')
      ->load($current_file_id);
    if ($file instanceof File) {
      $file_uri = $file->getFileUri();
      $scheme = StreamWrapperManager::getScheme($file_uri);
      $this->temporaryExport()->addFiles([$file_uri], self::getFileDirectory($scheme), $scheme . '://');
    }
    $sandbox['progress'] += 1;

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
   * {@inheritdoc}
   */
  protected function createMigrationPluginDefinition(string $type_and_bundle, array $results): array {
    $definition = parent::createMigrationPluginDefinition($type_and_bundle, $results);
    $entity_type_id = explode(':', $type_and_bundle)[0];
    $bundle = explode(':', $type_and_bundle)[1] ?? NULL;
    $exported_entities = $results['exported_entities'] ?? [];

    // JsonFileSource creates a single file for every exported entity.
    $entity_ids = $bundle
      ? array_values($exported_entities[$entity_type_id][$bundle])
      : array_values($exported_entities[$entity_type_id]);
    $urls = array_reduce($entity_ids, function (array $carry, $entity_id) use ($entity_type_id, $bundle) {
      $carry[] = implode('/', [
        '..',
        $this->getDataPath($entity_type_id, $bundle, $entity_id),
      ]);
      return $carry;
    }, []);
    natsort($urls);

    $definition['source'] = [
      'plugin' => 'url',
      'data_fetcher_plugin' => 'file',
      'item_selector' => '/',
      'data_parser_plugin' => 'json',
      'urls' => $urls,
    ];

    $entity_definition = $this->entityTypeManager->getDefinition($entity_type_id);
    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle ?? $entity_type_id);
    // Add source ID configuration to the migration source plugin.
    foreach (['id', 'revision', 'langcode'] as $key_name) {
      if ($key = $entity_definition->getKey($key_name)) {
        $key_type = $field_definitions[$key]->getType() === 'integer'
          ? 'integer'
          : 'string';
        $definition['source']['ids'][$key] = [
          'type' => $key_type,
        ];
      }
    }

    foreach ($field_definitions as $field_name => $field_definition) {
      // Media always sets a new revision_created date.
      if ($entity_type_id === 'media' && $field_name === 'revision_created') {
        unset($definition['process']['revision_created']);
        continue;
      }

      $definition['source']['fields'][$field_name] = [
        'name' => $field_name,
        'selector' => '/' . $field_name,
      ];

      // Special fields, where at least one property was serialized (for
      // example, layout builder overrides field).
      $serializetion_map = $bundle
        ? $previous_results['field_property_serialization_map'][$entity_type_id][$bundle][$field_name] ?? NULL
        : $previous_results['field_property_serialization_map'][$entity_type_id][$field_name] ?? NULL;
      if ($serializetion_map) {
        $field_value_process = [
          'plugin' => 'sub_process',
          'source' => $field_name,
          'process' => [
            'section' => [
              'plugin' => 'callback',
              'callable' => 'unserialize',
              'source' => 'section',
            ],
          ],
        ];
        foreach ($serializetion_map['serialized'] as $serialized_prop) {
          $field_value_process['process'][$serialized_prop] = [
            'plugin' => 'callback',
            'callable' => 'unserialize',
            'source' => $serialized_prop,
          ];
        }
        foreach ($serializetion_map['normal'] as $normal_prop) {
          $field_value_process['process'][$normal_prop] = $normal_prop;
        }
        $definition['process'][$field_name] = $field_value_process;
      }
    }

    // File asset migration requires a complex process.
    if ($entity_type_id === 'file' && array_key_exists('uri', $field_definitions)) {
      $preceding_processes = [
        'source_file_scheme' => [
          [
            'plugin' => 'explode',
            'delimiter' => '://',
            'source' => 'uri',
          ],
          [
            'plugin' => 'extract',
            'index' => [0],
          ],
          [
            'plugin' => 'skip_on_empty',
            'method' => 'row',
          ],
        ],
        'source_file_path' => [
          [
            'plugin' => 'explode',
            'delimiter' => '://',
            'source' => 'uri',
          ],
          [
            'plugin' => 'extract',
            'index' => [1],
          ],
          [
            'plugin' => 'skip_on_empty',
            'method' => 'row',
          ],
        ],
        'source_full_path' => [
          [
            'plugin' => 'concat',
            // DIRECTORY_SEPARATOR?
            'delimiter' => '/',
            'source' => [
              'constants/eme_file_path',
              '@source_file_scheme',
              '@source_file_path',
            ],
          ],
        ],
      ];

      $definition['process']['uri'] = [
        'plugin' => 'file_copy',
        'source' => [
          '@source_full_path',
          'uri',
        ],
      ];

      $definition['process'] = array_merge(
        $preceding_processes,
        $definition['process']
      );
    }

    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildModule(\ArrayAccess &$context): void {
    parent::buildModule($context);

    $module = $this->configuration['module'];
    $module_list = $this->moduleExtensionList->reset()->getList();

    if (array_key_exists($module, $module_list)) {
      $module_path = $module_list[$module]->getPath();
      // Delete data source directory.
      $data_dir = implode('/', [
        $module_path,
        self::DATA_SUBDIR,
      ]);
      if (file_exists($data_dir)) {
        $this->fileSystem->deleteRecursive($data_dir);
      }

      // Delete file assets directory.
      $file_dir = implode('/', [
        $module_path,
        self::FILE_ASSETS_SUBDIR,
      ]);
      if (file_exists($file_dir)) {
        $this->fileSystem->deleteRecursive($file_dir);
      }
    }

    // Ensure that the required migration plugin alter hook is properly used.
    $module_content = $this->temporaryExport()->getFileContent("$module.module") ?? EmeModuleFileUtils::getBareModuleFile($module);

    // Add the migration plugin alterer class.
    $this->temporaryExport()->addFileWithContent(
      'src/MigrationPluginAlterer.php',
      self::migrationPluginAltererClass($module)
    );
    EmeModuleFileUtils::ensureUseDeclaration(
      "Drupal\\{$module}\\MigrationPluginAlterer",
      $module_content
    );
    EmeModuleFileUtils::ensureFunctionUsedInHook(
      'hook_migration_plugins_alter',
      ['&$definitions'],
      'MigrationPluginAlterer::alterDefinitions($1)',
      $module,
      $module_content
    );
    $this->temporaryExport()->addFileWithContent("{$module}.module", $module_content);

    // The migration source plugin is provided by Migrate Plus.
    $info_yaml = $this->temporaryExport()->getFileContent("$module.info.yml");
    $info = Yaml::decode($info_yaml);
    $dependencies = array_unique(
      array_merge(
        $info['dependencies'] ?? [],
        ['migrate_plus:migrate_plus']
      )
    );
    natsort($dependencies);
    $info['dependencies'] = array_values($dependencies);
    $this->temporaryExport()->addFileWithContent("$module.info.yml", Yaml::encode($info));

    $context['finished'] = 1;
  }

  /**
   * Converts an entity data array to a file content.
   *
   * @param array[] $entity_revisions
   *   An array of array with the entity (revision) values.
   *
   * @return string
   *   The content of the data file.
   */
  protected function sourceDataToFileContent(array $entity_revisions): string {
    $file_content = json_encode($entity_revisions, JSON_UNESCAPED_UNICODE + JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES);

    if ($file_content === FALSE) {
      throw new ExportException();
    }

    return $file_content . "\n";
  }

  /**
   * Gets the field values of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param array|\ArrayAccess $context
   *   The batch context.
   *
   * @return array[]
   *   An array of array with the entity (revision) values.
   */
  protected function getEntityValues(ContentEntityInterface $entity, &$context): array {
    $entity_values_all = [
      $this->doGetEntityValues($entity, $context),
    ];

    // Add translations.
    foreach ($entity->getTranslationLanguages(FALSE) as $language) {
      $translation = $entity->getTranslation($language->getId());
      assert($translation instanceof ContentEntityInterface);
      $entity_values_all[] = $this->doGetEntityValues($translation, $context);
    }

    return $entity_values_all;
  }

  /**
   * Returns the values of a content entity revision.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param array|\ArrayAccess $context
   *   The batch context.
   *
   * @return array
   *   The field values of the given entity.
   *
   * @todo Provide a way for optionally sanitizing the field values.
   */
  protected function doGetEntityValues(ContentEntityInterface $entity, &$context): array {
    $type = $entity->getEntityTypeId();
    $bundle = $entity->getEntityType()->getKey('bundle') && $entity->getEntityType()->getBundleEntityType()
      ? $entity->bundle()
      : NULL;
    $entity_values = [];
    $entity_fields = $entity->getFields(FALSE);
    $all_fields = $entity->getFields(TRUE);
    $computed_fields = array_diff_key($all_fields, $entity_fields);
    // We will only include "moderation_state".
    if (!empty($computed_fields['moderation_state'])) {
      $entity_fields += [
        'moderation_state' => $computed_fields['moderation_state'],
      ];
    }

    if ($type === 'flagging' && !empty($computed_fields['flagged_entity'])) {
      $entity_fields += [
        'flagged_entity' => $computed_fields['flagged_entity'],
      ];
    }

    foreach ($entity_fields as $field_name => $field) {
      // Media always sets a new revision_created date.
      if ($entity->getEntityTypeId() === 'media' && $field_name === 'revision_created') {
        continue;
      }

      if ($field->isEmpty()) {
        // Some fields do not like missing values, e.g.
        // entity_reference_revisions.
        $entity_values[$field_name] = NULL;
        continue;
      }

      $property_count = count($field->first()->getValue());
      $main_property_name = $field->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getMainPropertyName();
      $property_definitions = $field->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getPropertyDefinitions();
      $field_value = $field->getValue();
      $properties_to_serialize = array_keys(
        array_filter($field_value[0], function ($value) {
          return is_object($value);
        })
      );
      $properties_normal = array_keys(
        array_filter($field_value[0], function ($value) {
          return !is_object($value);
        })
      );

      $complex_prop = !empty($properties_to_serialize) || $property_count > 1 || count($field) > 1 || !$main_property_name;
      $field_value = $complex_prop ? $field_value : $field->{$main_property_name};

      // In some cases, core expects that field property values follow their
      // data type (e.g. taxonomy_build_node_index() expects that the target
      // term ID is integer).
      // Sadly the value getters don't do that.
      if ($complex_prop) {
        foreach ($field_value as $delta => $delta_value) {
          foreach ($delta_value as $property => $prop_value) {
            if (
              in_array(
                IntegerInterface::class,
                class_implements($property_definitions[$property]->getClass())
              )
            ) {
              $field_value[$delta][$property] = (int) $prop_value;
            }
          }
        }
      }
      // This is a simple field: there is only one field item, with one property
      // with a single value.
      elseif (
        in_array(
          IntegerInterface::class,
          class_implements($property_definitions[$main_property_name]->getClass())
        )
      ) {
        $field_value = (int) $field_value;
      }

      if ($properties_to_serialize) {
        foreach ($field_value as $delta => $field_item_value) {
          foreach ($field_item_value as $property => $property_value) {
            if (in_array($property, $properties_to_serialize, TRUE)) {
              $field_value[$delta][$property] = serialize($property_value);
            }
          }
        }

        $field_serialization_map = [
          'serialized' => $properties_to_serialize,
          'normal' => $properties_normal,
        ];

        $preexisting_map = $bundle
          ? $context['results']['field_property_serialization_map'][$type][$bundle][$field_name] ?? NULL
          : $context['results']['field_property_serialization_map'][$type][$field_name] ?? NULL;

        if ($preexisting_map) {
          assert($preexisting_map === $field_serialization_map);
        }

        if ($bundle) {
          $context['results']['field_property_serialization_map'][$type][$bundle][$field_name] = $field_serialization_map;
        }
        else {
          $context['results']['field_property_serialization_map'][$type][$field_name] = $field_serialization_map;
        }
      }

      $entity_values[$field_name] = $field_value;
    }

    return $entity_values;
  }

  /**
   * Returns the directory where the data source of an entity should be saved.
   *
   * @param string $entity_type_id
   *   The entity type ID of the entity, e.g. "node".
   * @param string|null $bundle
   *   The bundle ID of the entity, e.g. "article".
   *
   * @return string
   *   The directory where the data source of an entity should be saved.
   */
  private static function getDataDirectory(string $entity_type_id, string $bundle = NULL): string {
    return implode('/', array_filter([
      self::DATA_SUBDIR,
      $entity_type_id,
      $bundle,
    ]));
  }

  /**
   * Returns the path where the data of the specified entity should be saved.
   *
   * @param string $entity_type_id
   *   The entity type ID of the entity, e.g. "node".
   * @param string|null $bundle
   *   The bundle ID of the entity, e.g. "article".
   * @param string|int $entity_id
   *   The ID of the entity.
   *
   * @return string
   *   The full path where the data of the specified entity should be saved.
   */
  private function getDataPath(string $entity_type_id, $bundle, $entity_id): string {
    return implode('/', [
      self::getDataDirectory($entity_type_id, $bundle),
      "{$entity_type_id}-{$entity_id}.{$this->dataFileExtension}",
    ]);
  }

  /**
   * Returns the directory where files with a specified scheme should be saved.
   *
   * @param string|false $scheme
   *   A scheme.
   *
   * @return string
   *   The directory where files with the specified scheme should be saved;
   *   relative to the generated module's root.
   */
  public static function getFileDirectory($scheme = FALSE): string {
    return implode('/', array_filter([
      self::FILE_ASSETS_SUBDIR,
      $scheme,
    ]));
  }

  /**
   * Returns the content of the migration plugin alterer class.
   *
   * @param string $module_name
   *   The name of the export module.
   *
   * @return string
   *   The content of the migration plugin alterer class.
   */
  protected static function migrationPluginAltererClass(string $module_name): string {
    return <<<EOF
<?php

namespace Drupal\\$module_name;

/**
 * Alters the migration plugin definitions.
 */
class MigrationPluginAlterer {

  /**
   * Alters the migration plugin definitions.
   */
  public static function alterDefinitions(&\$definitions) {
    \$directory_separator = preg_quote(DIRECTORY_SEPARATOR, '/');
    \$module_root = preg_replace('/' . \$directory_separator . 'src$/', '', __DIR__);

    // Update source references in our migrations.
    foreach (\$definitions as \$plugin_id => \$definition) {
      if (\$definition['provider'] !== '$module_name') {
        continue;
      }
      // Set constant for file migration.
      \$definitions[\$plugin_id]['source']['constants']['eme_file_path'] = implode(DIRECTORY_SEPARATOR, [
        \$module_root,
        'assets',
      ]);

      // Set the real path to the data source assets.
      if (!empty(\$definitions[\$plugin_id]['source']['urls'])) {
        \$source_urls = \$definitions[\$plugin_id]['source']['urls'];
        assert(is_array(\$source_urls));
        foreach (\$source_urls as \$key => \$source_url) {
          assert(is_string(\$source_url));
          \$definitions[\$plugin_id]['source']['urls'][\$key] = str_replace(
            '..',
            \$module_root,
            \$source_url
          );
        }
      }
    }
  }

}

EOF;
  }

}
