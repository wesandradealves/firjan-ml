<?php

namespace Drupal\Tests\eme\Functional;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\eme\Plugin\Eme\Export\JsonFiles;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\eme\Traits\EmeTestDrushAssertionsTrait;
use Drupal\Tests\eme\Traits\EmeTestSetupTrait;
use Drupal\Tests\eme\Traits\EmeTestTrait;

/**
 * Tests EME and Drush compatibility – verifies usage steps in README.
 *
 * @group eme
 */
class DrushOperationsTest extends BrowserTestBase {

  use EmeTestDrushAssertionsTrait;
  use EmeTestSetupTrait;
  use EmeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
    'eme',
    'eme_test_module_extension_list',
    'file',
    'filter',
    'image',
    'media',
    'menu_link_content',
    'node',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    if (static::isOldDrushVersion()) {
      $module_installer = \Drupal::service('module_installer');
      assert($module_installer instanceof ModuleInstallerInterface);
      $module_installer->install(['migrate_tools']);
    }

    $this->setupExportVars();
  }

  /**
   * Test export with Drush.
   */
  public function testExportDrush() {
    $this->createTestEntityTypes();
    $this->createDefaultTestContent();

    // Let's export the test content.
    $this->drush('eme:export', [], [
      'types' => 'node,comment,user,file',
      'destination' => $this->getMigrateExportDestination(),
      'module' => $this->moduleName,
      'name' => $this->moduleHumanName,
      'id-prefix' => $this->migrationPrefix,
      'group' => $this->migrationGroup,
    ]);
    $this->assertOutputEquals('🎉 Export finished.');

    $module_installer = $this->container->get('module_installer');
    assert($module_installer instanceof ModuleInstallerInterface);
    $module_installer->install([$this->moduleName]);

    $this->drush('migrate:status', [], array_filter([
      'tag' => $this->migrationGroup,
      'fields' => 'id,status,total,imported,unprocessed',
    ]));
    $this->assertDrushOutputHasAllLines([
      "{$this->migrationPrefix}_menu_link_content  Idle  1  0  1",
      "{$this->migrationPrefix}_user               Idle  4  0  4",
      "{$this->migrationPrefix}_file               Idle  2  0  2",
      "{$this->migrationPrefix}_media_image        Idle  1  0  1",
      "{$this->migrationPrefix}_node_article       Idle  1  0  1",
      "{$this->migrationPrefix}_node_page          Idle  1  0  1",
      "{$this->migrationPrefix}_comment_article    Idle  2  0  2",
    ]);

    // Let's export only comments and their dependent entities.
    $this->drush('eme:export', [], [
      'types' => 'comment',
      'update' => $this->moduleName,
    ]);
    $this->assertOutputEquals('🎉 Export finished.');

    $this->drush('cache:rebuild');

    $this->drush('migrate:status', [], [
      'tag' => $this->migrationGroup,
      'fields' => 'id,status,total,imported,unprocessed',
    ]);
    $this->assertDrushOutputHasAllLines([
      "{$this->migrationPrefix}_menu_link_content  Idle  1  0  1",
      "{$this->migrationPrefix}_user               Idle  2  0  2",
      "{$this->migrationPrefix}_file               Idle  1  0  1",
      "{$this->migrationPrefix}_media_image        Idle  1  0  1",
      "{$this->migrationPrefix}_node_article       Idle  1  0  1",
      "{$this->migrationPrefix}_comment_article    Idle  2  0  2",
    ]);

    // Delete the test content.
    $this->deleteTestContent();
    $this->resetAll();

    // Uninstall and reinstall modules.
    $this->resetContentRelatedModules();

    $this->createTestEntityTypes();

    $this->assertEmpty(\Drupal::entityTypeManager()->getStorage('node')->loadMultiple());
    $expected_user_ids = [
      // Anonymous user.
      0 => 0,
      // Root user.
      1 => 1,
    ];
    $this->assertEquals($expected_user_ids, array_keys(\Drupal::entityTypeManager()->getStorage('user')->loadMultiple()));

    // Let's import the test content.
    $this->drush('migrate:import', ['--execute-dependencies'], [
      'tag' => $this->migrationGroup,
    ]);

    $this->drush('migrate:status', [], [
      'tag' => $this->migrationGroup,
      'fields' => 'id,status,total,imported,unprocessed',
    ]);
    $suff = static::isOldDrushVersion() ? '' : '(100%)';
    $this->assertDrushOutputHasAllLines([
      "{$this->migrationPrefix}_menu_link_content  Idle   1   1 $suff   0",
      "{$this->migrationPrefix}_user               Idle   2   2 $suff   0",
      "{$this->migrationPrefix}_file               Idle   1   1 $suff   0",
      "{$this->migrationPrefix}_media_image        Idle   1   1 $suff   0",
      "{$this->migrationPrefix}_node_article       Idle   1   1 $suff   0",
      "{$this->migrationPrefix}_comment_article    Idle   2   2 $suff   0",
    ]);

    $this->assertTestContent();

    // Add a new migration to the mode we will re-export.
    $module_location = implode('/', [
      DRUPAL_ROOT,
      $this->getMigrateExportDestination(),
      $this->moduleName,
    ]);
    $front_page_id = "{$this->migrationPrefix}_homepage";
    $front_page_migration_location = $this->addFrontPageMigration($module_location, $front_page_id, $this->migrationGroup);
    $this->assertFileExists($front_page_migration_location);

    // Create additional test content and verify that it is added to the content
    // export.
    $this->createAdditionalTestContent();
    $this->drush('eme:export', [], [
      'update' => $this->moduleName,
    ]);
    $this->assertOutputEquals('🎉 Export finished.');

    $this->assertComment1Json(implode('/', [
      $module_location,
      JsonFiles::DATA_SUBDIR,
      'comment',
      'article',
      'comment-1.json',
    ]));
    $this->assertMedia1Json(implode('/', [
      $module_location,
      JsonFiles::DATA_SUBDIR,
      'media',
      'image',
      'media-1.json',
    ]));

    // Delete the previously imported and the additional test content.
    $this->drush('migrate:rollback', [], [
      'tag' => $this->migrationGroup,
    ]);
    $this->deleteTestContent();
    $this->resetAll();

    // Uninstall and reinstall modules.
    $this->resetContentRelatedModules();

    $this->createTestEntityTypes();

    $this->drush('cache:rebuild');
    $this->assertFileExists($front_page_migration_location);

    $this->drush('migrate:status', [], [
      'tag' => $this->migrationGroup,
      'fields' => 'id,status,total,imported,unprocessed',
    ]);
    $this->assertDrushOutputHasAllLines([
      "{$front_page_id}                            Idle   1   0   1",
      "{$this->migrationPrefix}_menu_link_content  Idle   2   0   2",
      "{$this->migrationPrefix}_user               Idle   3   0   3",
      // The thumbnail of the document media '3' should also been discovered.
      "{$this->migrationPrefix}_file               Idle   3   0   3",
      "{$this->migrationPrefix}_media_document     Idle   1   0   1",
      "{$this->migrationPrefix}_media_image        Idle   1   0   1",
      "{$this->migrationPrefix}_node_article       Idle   2   0   2",
      "{$this->migrationPrefix}_comment_article    Idle   3   0   3",
    ]);
    $this->assertNotEquals('/node/2', $this->config('system.site')->get('page.front'));

    // Let's import the updated test export.
    $this->drush('migrate:import', ['--execute-dependencies'], [
      'tag' => $this->migrationGroup,
    ]);

    $this->drush('migrate:status', [], [
      'tag' => $this->migrationGroup,
      'fields' => 'id,status,total,imported,unprocessed',
    ]);
    $this->assertDrushOutputHasAllLines([
      "{$front_page_id}                            Idle   1   1 $suff   0",
      "{$this->migrationPrefix}_menu_link_content  Idle   2   2 $suff   0",
      "{$this->migrationPrefix}_user               Idle   3   3 $suff   0",
      "{$this->migrationPrefix}_file               Idle   3   3 $suff   0",
      "{$this->migrationPrefix}_media_document     Idle   1   1 $suff   0",
      "{$this->migrationPrefix}_media_image        Idle   1   1 $suff   0",
      "{$this->migrationPrefix}_node_article       Idle   2   2 $suff   0",
      "{$this->migrationPrefix}_comment_article    Idle   3   3 $suff   0",
    ]);

    $this->rebuildAll();
    $this->assertTestContent(TRUE);
    $this->assertEquals('/node/2', $this->config('system.site')->get('page.front'));
  }

}
