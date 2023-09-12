<?php

namespace Drupal\Tests\eme\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Url;
use Drupal\eme\Eme;
use Drupal\eme\Plugin\Eme\Export\JsonFiles;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\eme\Traits\EmeTestDrushAssertionsTrait;
use Drupal\Tests\eme\Traits\EmeTestSetupTrait;
use Drupal\Tests\eme\Traits\EmeTestTrait;

/**
 * Tests EME UI.
 *
 * @group eme
 */
class UiTest extends WebDriverTestBase {

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
   * Test export from the UI.
   */
  public function testExportWithUi() {
    $this->setupExportVars();

    $this->createTestEntityTypes();
    $this->createDefaultTestContent();

    // Let's export the test content.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet(Url::fromRoute('eme.eme_export_form'));
    $session = $this->assertSession();

    // If EME will have more than one export plugins, this test should pick
    // the json file source export.
    if ($export_type_radios = $this->getSession()->getPage()->findField('plugin')) {
      $export_type_radios->selectOption('json_files');
    }
    // Configure export module metadata.
    $session->fieldExists('Module name')->setValue($this->moduleName);
    $session->fieldExists('Module human name')->setValue($this->moduleHumanName);
    $session->fieldExists('Migration prefix')->setValue($this->migrationPrefix);
    $session->fieldExists('Migration group')->setValue($this->migrationGroup);
    // Check 'comment'.
    $session->fieldExists('comment')->check();
    // Start export.
    $session->buttonExists('Start export')->press();

    $this->assertWaitOnBatch();

    $refresh_url = (string) Url::fromRoute('eme.eme_export_download_file')->setAbsolute()->toString();
    $meta_refresh_tag = $this->xpath('//head/meta[@http-equiv="refresh"]');
    assert($meta_refresh_tag[0] instanceof NodeElement);
    $refresh = $meta_refresh_tag[0]->getAttribute('content');
    $this->assertEquals("5;url={$refresh_url}", $refresh);

    // Leave the download form.
    $this->drupalGet('<front>');

    // Extract the archive.
    $file_system = \Drupal::service('file_system');
    assert($file_system instanceof FileSystemInterface);
    $temp_directory = $file_system->getTempDirectory();
    $archive = new ArchiveTar($temp_directory . '/' . Eme::getArchiveName());
    $archive->extract(DRUPAL_ROOT . '/' . $this->getMigrateExportDestination() . '/' . $this->moduleName);

    $this->drupalGet(Url::fromRoute('eme.collection'));
    $table_rows = $this->xpath('//form[@data-drupal-selector="eme-collection-form"]//table//tbody/tr');
    $this->assertCount(1, $table_rows);
    $only_table_row = $table_rows[0];
    $table_cells = $only_table_row->findAll('xpath', '/td');
    $this->assertStringContainsString($this->moduleName, $table_cells[0]->getText());
    $this->assertStringContainsString($this->moduleHumanName, $table_cells[0]->getText());
    $this->assertStringContainsString($this->migrationGroup, $table_cells[1]->getText());
    $this->assertStringContainsString('JSON files source', $table_cells[2]->getText());
    $this->assertStringContainsString('comment', $table_cells[3]->getText());
    $this->assertStringContainsString("{$this->migrationPrefix}_user", $table_cells[4]->getText());
    $this->assertStringContainsString("{$this->migrationPrefix}_node_article", $table_cells[4]->getText());
    $this->assertStringContainsString("{$this->migrationPrefix}_comment_article", $table_cells[4]->getText());

    $module_installer = $this->container->get('module_installer');
    assert($module_installer instanceof ModuleInstallerInterface);
    $module_installer->install([$this->moduleName]);

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

    $this->drupalGet(Url::fromRoute('system.admin_content'));
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

    $this->drupalGet(Url::fromRoute('eme.collection'));
    $table_rows = $this->xpath('//form[@data-drupal-selector="eme-collection-form"]//tr[@data-drupal-selector="edit-table-' . str_replace('_', '-', $this->moduleName) . '"]');
    $this->assertCount(1, $table_rows);
    $table_rows[0]->findButton('Reexport')->press();
    $this->assertWaitOnBatch();

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
    $this->assertFileExists($front_page_migration_location);

    // Delete the previously imported and the additional test content.
    $this->drush('migrate:rollback', [], [
      'tag' => $this->migrationGroup,
    ]);
    $this->deleteTestContent();
    $this->resetAll();

    // Uninstall and reinstall modules.
    $this->resetContentRelatedModules();

    $this->assertNotEquals('/node/2', $this->config('system.site')->get('page.front'));
    $this->createTestEntityTypes();

    // Let's import the updated test export.
    $this->drush('migrate:import', ['--execute-dependencies'], [
      'tag' => $this->migrationGroup,
    ]);

    $this->resetAll();
    $this->drupalGet(Url::fromRoute('system.admin_content'));
    $this->assertTestContent(TRUE);
    $this->assertEquals('/node/2', $this->config('system.site')->get('page.front'));
  }

  /**
   * Waits for a batch to be completed.
   *
   * @param int $timeout
   *   (Optional) Timeout in seconds, defaults to 60.
   * @param string $message
   *   (optional) A message for exception.
   *
   * @throws \RuntimeException
   *   When the batch is not completed.
   */
  public function assertWaitOnBatch($timeout = 60, $message = 'Unable to complete batch.') {
    // Wait for a time to allow page state to update after clicking.
    sleep(1);
    $condition = <<<JS
      (function() {
        return drupalSettings.path.currentPath !== 'batch';
      }());
JS;
    $result = $this->getSession()->wait($timeout * 1000, $condition);
    if (!$result) {
      throw new \RuntimeException($message);
    }
  }

}
