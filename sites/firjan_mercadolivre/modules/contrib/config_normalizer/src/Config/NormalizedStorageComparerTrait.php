<?php

namespace Drupal\config_normalizer\Config;

use Drupal\config_normalizer\ConfigNormalizerInterface;
use Drupal\config_normalizer\Plugin\ConfigNormalizerManager;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;

/**
 * Using this trait will add a ::createStorageComparer() method to the class.
 *
 * If the class is capable of injecting services from the container, it should
 * inject the 'config_normalizer.normalizer' service and call setNormalizer()
 */
trait NormalizedStorageComparerTrait {

  /**
   * The config normalizer service.
   *
   * @var \Drupal\config_normalizer\ConfigNormalizerInterface
   */
  protected $configNormalizer;

  /**
   * Creates and returns a storage comparer.
   *
   * @param \Drupal\Core\Config\StorageInterface $source_storage
   *   The source storage.
   * @param \Drupal\Core\Config\StorageInterface $target_storage
   *   The target storage.
   * @param string $mode
   *   (optional, deprecated) The normalization mode.
   *
   * @return \Drupal\Core\Config\StorageComparer
   *   A storage comparer.
   */
  protected function createStorageComparer(StorageInterface $source_storage, StorageInterface $target_storage, $mode = NULL) {
    // Set up a storage comparer using normalized storages.
    $storage_comparer = new StorageComparer(
      new NormalizedReadOnlyStorage($source_storage, $this->getNormalizer()),
      new NormalizedReadOnlyStorage($target_storage, $this->getNormalizer())
    );

    return $storage_comparer;
  }

  /**
   * Gets the normalizer service.
   *
   * @return \Drupal\config_normalizer\ConfigNormalizerInterface
   *   The configuration normalizer.
   */
  protected function getNormalizer() {
    if (!$this->configNormalizer) {
      $this->configNormalizer = \Drupal::service('config_normalizer.normalizer');
    }
    return $this->configNormalizer;
  }

  /**
   * Sets the normalizer manager service to use.
   *
   * @param \Drupal\config_normalizer\ConfigNormalizerInterface $normalizer
   *   The normalizer service.
   *
   * @return $this
   *
   * @deprecated in config_normalizer:2.0.0-alpha1 and is removed from config_normalizer:2.0.0. No replacement.
   * @see https://www.drupal.org/project/config_normalizer/issues/3230398
   */
  public function setNormalizer(ConfigNormalizerInterface $normalizer) {
    $this->configNormalizer = $normalizer;
    return $this;
  }

  /**
   * Gets the configuration manager service.
   *
   * @return \Drupal\Core\Config\ConfigManagerInterface
   *   The configuration manager.
   *
   * @deprecated in config_normalizer:2.0.0-alpha1 and is removed from config_normalizer:2.0.0. No replacement.
   * @see https://www.drupal.org/project/config_normalizer/issues/3230398
   */
  protected function getConfigManager() {
    return \Drupal::service('config.manager');
  }

  /**
   * Sets the configuration manager service to use.
   *
   * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
   *   The configuration manager service.
   *
   * @return $this
   *
   * @deprecated in config_normalizer:2.0.0-alpha1 and is removed from config_normalizer:2.0.0. No replacement.
   * @see https://www.drupal.org/project/config_normalizer/issues/3230398
   */
  public function setConfigManager(ConfigManagerInterface $config_manager) {
    return $this;
  }

  /**
   * Gets the normalizer manager service.
   *
   * @return \Drupal\config_normalizer\Plugin\ConfigNormalizerManager
   *   The normalizer manager.
   *
   * @deprecated in config_normalizer:2.0.0-alpha1 and is removed from config_normalizer:2.0.0. No replacement.
   * @see https://www.drupal.org/project/config_normalizer/issues/3230398
   */
  protected function getNormalizerManager() {
    return \Drupal::service('plugin.manager.config_normalizer');
  }

  /**
   * Sets the normalizer manager service to use.
   *
   * @param \Drupal\config_normalizer\Plugin\ConfigNormalizerManager $normalizer_manager
   *   The normalizer manager service.
   *
   * @return $this
   *
   * @deprecated in config_normalizer:2.0.0-alpha1 and is removed from config_normalizer:2.0.0. No replacement.
   * @see https://www.drupal.org/project/config_normalizer/issues/3230398
   */
  public function setNormalizerManager(ConfigNormalizerManager $normalizer_manager) {
    return $this;
  }

}
