<?php

namespace Drupal\config_normalizer;

/**
 * Defines an interface for config item normalizers.
 *
 * @deprecated in config_normalizer:2.0.0-alpha1 and is removed from config_normalizer:2.0.0. Use ConfigNormalizerInterface instead.
 * @see https://www.drupal.org/project/config_normalizer/issues/3230426
 */
interface ConfigItemNormalizerInterface extends ConfigNormalizerInterface {

  /**
   * Normalizes config for comparison.
   *
   * Normalization can help ensure that config from different storages can be
   * compared meaningfully.
   *
   * @param string $name
   *   The name of a configuration object to normalize.
   * @param array $data
   *   Configuration array to normalize.
   * @param array $context
   *   (optional, deprecated) This parameter will be removed in 2.0.0.
   *
   * @return array
   *   Normalized configuration array.
   */
  public function normalize($name, array $data, array $context = []);

}
