<?php

namespace Drupal\Tests\eme\Traits;

/**
 * Test setup trait for EME tests.
 */
trait EmeTestSetupTrait {

  /**
   * A path where the exported module should be saved.
   *
   * @var string
   */
  protected $exportDestination = 'modules/content_migrations';

  /**
   * The machine name for the generated module.
   *
   * @var string
   */
  protected $moduleName;

  /**
   * The human name for the generated module.
   *
   * @var string
   */
  protected $moduleHumanName;

  /**
   * The migration plugin ID prefix of the generated migration plugins.
   *
   * @var string
   */
  protected $migrationPrefix;

  /**
   * The migration group of the generated migration plugins.
   *
   * @var string
   */
  protected $migrationGroup;

  /**
   * Returns the destination for the exported module.
   *
   * @return string
   *   The destination where the exported module should be saved.
   */
  protected function getMigrateExportDestination(): string {
    return $this->siteDirectory . '/' . $this->exportDestination;
  }

  /**
   * Sets up variables used for generating content exports.
   */
  protected function setupExportVars() {
    $prop_base = strtolower($this->randomMachineName(6));
    $this->moduleName = "{$prop_base}_module";
    $this->moduleHumanName = "Entity export {$prop_base}";
    $this->migrationPrefix = "id_{$prop_base}";
    $this->migrationGroup = "group_{$prop_base}";
    $this->assertDirectoryIsWritable($this->siteDirectory);
  }

}
