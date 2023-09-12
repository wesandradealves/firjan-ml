<?php

namespace Drupal\config_normalizer\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Config normalizer plugins.
 *
 * @deprecated in config_normalizer:2.0.0-alpha1 and is removed from config_normalizer:2.0.0. No replacement.
 * @see https://www.drupal.org/project/config_normalizer/issues/3230398
 */
interface ConfigNormalizerInterface extends PluginInspectionInterface {

  /**
   * Normalizes config for comparison.
   *
   * Normalization can help ensure that config from different storages can be
   * compared meaningfully.
   *
   * @param string $name
   *   The name of a configuration object to normalize.
   * @param array &$data
   *   Configuration array to normalize.
   * @param array $context
   *   An array of key-value pairs to pass additional context when needed.
   */
  public function normalize($name, array &$data, array $context);

}
