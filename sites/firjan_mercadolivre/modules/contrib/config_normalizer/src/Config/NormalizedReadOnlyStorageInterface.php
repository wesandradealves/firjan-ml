<?php

namespace Drupal\config_normalizer\Config;

use Drupal\Core\Config\StorageInterface;

/**
 * Interface for a normalized read only storage.
 *
 * This is like any other StorageInterface, except it does not allow writing
 * and normalizes data on read.
 */
interface NormalizedReadOnlyStorageInterface extends StorageInterface {

  /**
   * Mode in which the storage is being prepared for comparison.
   *
   * This mode is typically used on both storages that are passed to a
   * class implementing \Drupal\Core\Config\StorageComparerInterface.
   *
   * @deprecated in config_normalizer:2.0.0-alpha1 and is removed from config_normalizer:2.0.0. No replacement.
   * @see https://www.drupal.org/project/config_normalizer/issues/3230398
   */
  const NORMALIZATION_MODE_COMPARE = 'compare';

  /**
   * Mode in which the storage is being prepared for providing configuration.
   *
   * Because configuration is to be provided in a context where it may be later
   * written, only write-appropriate normalization should be done. For example,
   * data should not be sorted since sorting will leave data in a state that
   * may not be appropriate for writing.
   *
   * @deprecated in config_normalizer:2.0.0-alpha1 and is removed from config_normalizer:2.0.0. No replacement.
   * @see https://www.drupal.org/project/config_normalizer/issues/3230398
   */
  const NORMALIZATION_MODE_PROVIDE = 'provide';

  /**
   * The default normalization mode.
   *
   * @deprecated in config_normalizer:2.0.0-alpha1 and is removed from config_normalizer:2.0.0. No replacement.
   * @see https://www.drupal.org/project/config_normalizer/issues/3230398
   */
  const DEFAULT_NORMALIZATION_MODE = self::NORMALIZATION_MODE_COMPARE;

  /**
   * The default context for normalization.
   *
   * Context is an array with the following key-valu pairs:
   * - normalization_mode: The mode used for normalization.
   * - reference_storage_service: a storage that the configuration is normalized
   *   against. When this is the site's active configuration storage,
   *   config.storage, normalization should replicate any changes made at
   *   config install time.
   *
   * @deprecated in config_normalizer:2.0.0-alpha1 and is removed from config_normalizer:2.0.0. No replacement.
   * @see https://www.drupal.org/project/config_normalizer/issues/3230398
   */
  const DEFAULT_CONTEXT = [
    'normalization_mode' => self::DEFAULT_NORMALIZATION_MODE,
    'reference_storage_service' => NULL,
  ];

  /**
   * Gets the context to be used for normalization.
   *
   * @return array
   *   An array of key-value pairs to pass additional context when needed.
   *
   * @deprecated in config_normalizer:2.0.0-alpha1 and is removed from config_normalizer:2.0.0. No replacement.
   * @see https://www.drupal.org/project/config_normalizer/issues/3230398
   */
  public function getContext();

  /**
   * Sets the context to be used for normalization.
   *
   * If not given, values are defaulted to those in ::DEFAULT_CONTEXT.
   *
   * @param array $context
   *   (optional) Unused.
   *
   * @deprecated in config_normalizer:2.0.0-alpha1 and is removed from config_normalizer:2.0.0. No replacement.
   * @see https://www.drupal.org/project/config_normalizer/issues/3230398
   */
  public function setContext(array $context = []);

}
