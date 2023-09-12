<?php

namespace Drupal\Tests\eme\Kernel;

use Drupal\eme\Export\ExportPluginInterface;
use Drupal\eme\Export\ExportPluginManagerInterface;
use Drupal\eme\Plugin\Eme\Export\JsonFiles;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\eme\Traits\EmeTestSetupTrait;
use Drupal\Tests\eme\Traits\EmeTestTrait;

/**
 * Tests the JsonFile export plugin.
 *
 * @coversDefaultClass \Drupal\eme\Plugin\Eme\Export\JsonFiles
 * @group eme
 */
class JsonMigrationTest extends KernelTestBase {

  use EmeTestSetupTrait;
  use EmeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
    'eme',
    'field',
    'file',
    'filter',
    'image',
    'link',
    'media',
    'menu_link_content',
    'node',
    'path_alias',
    'system',
    'taxonomy',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig([
      'user',
      'comment',
      'field',
      'system',
      'node',
      'taxonomy',
      'media',
      'filter',
    ]);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('file');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('media');

    $this->setupExportVars();
    $this->createTestEntityTypes();
  }

  /**
   * Tests exporting with the JsonFiles plugin.
   */
  public function testJsonMigrationExport() {
    $export_config = [
      'types' => ['node', 'comment', 'user', 'file'],
      'module' => $this->moduleName,
      'name' => $this->moduleHumanName,
      'id-prefix' => $this->migrationPrefix,
      'group' => $this->migrationGroup ,
      'path' => $this->getMigrateExportDestination(),
    ];

    $export_plugin_manager = $this->container->get('eme.export_plugin_manager');
    $this->assertInstanceOf(ExportPluginManagerInterface::class, $export_plugin_manager);
    $export_plugin = $export_plugin_manager->createInstance('json_files', $export_config);

    $this->doExport($export_plugin);

    $expected_module_location = "{$this->getMigrateExportDestination()}/{$this->moduleName}";
    $expected_module_assets = [
      '.',
      '..',
      "{$this->moduleName}.info.yml",
      "{$this->moduleName}.module",
      'src',
    ];
    sort($expected_module_assets);
    $actual_module_assets = scandir($expected_module_location);
    $this->assertEquals($expected_module_assets, $actual_module_assets);

    $this->createDefaultTestContent();
    $export_config['types'][] = 'totally_missing';

    $this->doExport($export_plugin_manager->createInstance('json_files', $export_config));
    $expected_module_assets = array_merge(
      $expected_module_assets,
      ['assets', 'data', 'migrations']
    );
    sort($expected_module_assets);
    $actual_module_assets = scandir($expected_module_location);
    $this->assertEquals($expected_module_assets, $actual_module_assets);

    $this->assertComment1Json(implode('/', [
      $expected_module_location,
      JsonFiles::DATA_SUBDIR,
      'comment',
      'article',
      'comment-1.json',
    ]));
    $this->assertMedia1Json(implode('/', [
      $expected_module_location,
      JsonFiles::DATA_SUBDIR,
      'media',
      'image',
      'media-1.json',
    ]));
  }

  /**
   * Executes a content entity export.
   *
   * @param \Drupal\eme\Export\ExportPluginInterface $export_plugin
   *   The export plugin instance to execute.
   *
   * @todo Remove in https://drupal.org/i/3219969
   */
  protected function doExport(ExportPluginInterface $export_plugin) {
    $context = [];

    foreach ($export_plugin->tasks() as $task) {
      $context = array_filter([
        'finished' => 1,
        'results' => $context['results'] ?? NULL,
      ]);

      do {
        $export_plugin->executeExportTask($task, $context);
      } while ($context['finished'] < 1);
    }
  }

}
