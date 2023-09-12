<?php

namespace Drupal\config_normalizer;

/**
 * Class responsible for performing configuration normalization.
 *
 * @deprecated in config_normalizer:2.0.0-alpha2 and is removed from config_normalizer:2.0.0. Use ConfigNormalizer instead.
 * @see https://www.drupal.org/project/config_normalizer/issues/3230426
 */
class ConfigItemNormalizer extends ConfigNormalizer implements ConfigItemNormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function normalize($name, array $data, array $context = []) {
    return parent::normalize($name, $data);
  }

}
