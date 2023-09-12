<?php

namespace Drupal\config_normalizer;

/**
 * Defines an interface for config item normalizers.
 *
 * @api This is the main API of this module.
 */
interface ConfigNormalizerInterface {

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
   *
   * @return array
   *   Normalized configuration array.
   */
  public function normalize($name, array $data);

}
