<?php

declare(strict_types=1);

namespace Drupal\eme;

/**
 * Helpers of Entity Migrate Export.
 *
 * @internal
 */
final class Eme {

  /**
   * The lock ID.
   *
   * @const string
   */
  const LOCK_NAME = 'eme';

  /**
   * The extension of the temporary archive.
   *
   * @const string
   */
  const ARCHIVE_EXTENSION = 'tar.gz';

  /**
   * Eme's config name.
   *
   * @const string
   */
  const CONFIG_NAME = 'eme.settings';

  /**
   * The default ID.
   *
   * @const string
   */
  const ID = 'eme';

  /**
   * Migration plugin definitions directory.
   *
   * @const string
   */
  const MIGRATION_DIR = 'migrations';

  /**
   * Returns internal ID of the generated module.
   *
   * @return string
   *   The internal ID of the generated module.
   */
  public static function getDefaultId(): string {
    $stored_id = \Drupal::config(self::CONFIG_NAME)->get('eme_id');
    return !empty($stored_id)
      ? $stored_id
      : self::ID;
  }

  /**
   * Returns the machine name of the generated module.
   *
   * @param string|null $id
   *   The custom EME base ID, if any.
   *
   * @return string
   *   The machine name of the generated module.
   */
  public static function getModuleName(string $id = NULL): string {
    return implode('_', [
      $id ?? self::getDefaultId(),
      'content',
    ]);
  }

  /**
   * Returns the human name of the generated module.
   *
   * @param string|null $id
   *   The custom EME base ID, if any.
   *
   * @return string
   *   The human name of the generated module.
   */
  public static function getModuleHumanName(string $id = NULL): string {
    return implode(' ', [
      preg_replace('/_+/', ' ', ucfirst($id ?? self::getDefaultId())),
      'Content Entity Migration',
    ]);
  }

  /**
   * Returns the list of entity types to exclude.
   *
   * @return string[]
   *   The list of entity types to exclude.
   */
  public static function getExcludedTypes(): array {
    return \Drupal::config(self::CONFIG_NAME)->get('ignored_entity_types') ?? [];
  }

  /**
   * Returns the name of the temporary archive.
   *
   * @param string|null $module_name
   *   The machine name of the generated module.
   *
   * @return string
   *   The name of the temporary archive.
   */
  public static function getArchiveName(string $module_name = NULL): string {
    return implode('.', [
      $module_name ?? self::ID,
      self::ARCHIVE_EXTENSION,
    ]);
  }

}
