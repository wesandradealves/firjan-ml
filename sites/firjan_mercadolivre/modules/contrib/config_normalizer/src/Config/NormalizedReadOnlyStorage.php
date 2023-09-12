<?php

namespace Drupal\config_normalizer\Config;

use Drupal\config_normalizer\ConfigNormalizerInterface;
use Drupal\Core\Config\ReadOnlyStorage;
use Drupal\Core\Config\StorageInterface;

/**
 * Defines the normalized read only storage.
 */
class NormalizedReadOnlyStorage extends ReadOnlyStorage implements NormalizedReadOnlyStorageInterface {

  /**
   * The config item normalizer.
   *
   * @var \Drupal\config_normalizer\ConfigNormalizerInterface
   */
  protected $normalizer;

  /**
   * Create a NormalizedReadOnlyStorage decorating another storage.
   *
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The decorated storage.
   * @param \Drupal\config_normalizer\ConfigNormalizerInterface|mixed $normalizer
   *   The normalization manager. In 2.0.0 we will add a typehint.
   * @param array $context
   *   (optional, deprecated) This parameter will be removed in 2.0.0.
   */
  public function __construct(StorageInterface $storage, $normalizer, array $context = []) {
    parent::__construct($storage);
    if (!$normalizer instanceof ConfigNormalizerInterface) {
      $normalizer = \Drupal::service('config_normalizer.normalizer');
    }
    $this->normalizer = $normalizer;
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function setContext(array $context = []) {
  }

  /**
   * {@inheritdoc}
   */
  public function read($name) {
    $data = parent::read($name);

    $data = $this->normalize($name, $data);

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function readMultiple(array $names) {
    $list = parent::readMultiple($names);

    foreach ($list as $name => &$data) {
      $data = $this->normalize($name, $data);
    }

    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public function createCollection($collection) {
    return new static(
      $this->storage->createCollection($collection),
      $this->normalizer
    );
  }

  /**
   * Normalizes configuration data.
   *
   * @param string $name
   *   The name of a configuration object to load.
   * @param array|bool $data
   *   The configuration data to normalize.
   *
   * @return array|bool
   *   The configuration data stored for the configuration object name. If no
   *   configuration data exists for the given name, FALSE is returned.
   */
  protected function normalize($name, $data) {
    if (!is_bool($data)) {
      $data = $this->normalizer->normalize($name, $data);
    }

    return $data;
  }

}
