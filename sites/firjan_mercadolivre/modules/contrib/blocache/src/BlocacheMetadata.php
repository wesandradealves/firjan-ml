<?php

namespace Drupal\blocache;

use Drupal\block\BlockInterface;

/**
 * Class BlocacheMetadata.
 */
class BlocacheMetadata {

  /**
   * The module name related to third-party settings.
   *
   * @var string
   */
  const MODULE = 'blocache';

  /**
   * The third-party setting name with the cache metadata overwrite information.
   *
   * @var string
   */
  const OVERRIDEN = 'overridden';

  /**
   * The third-party setting name of cache metadata "max-age".
   *
   * @var string
   */
  const METADATA_MAX_AGE = 'max-age';

  /**
   * The third-party setting name of cache metadata "contexts".
   *
   * @var string
   */
  const METADATA_CONTEXTS = 'contexts';

  /**
   * The third-party setting name of cache metadata "tags".
   *
   * @var string
   */
  const METADATA_TAGS = 'tags';

  /**
   * The block entity.
   *
   * @var \Drupal\block\BlockInterface
   */
  protected $block = NULL;

  /**
   * Whether the cache metadata has been overwritten.
   *
   * @var bool
   */
  protected $overridden = FALSE;

  /**
   * The defaults cache metadata.
   *
   * @var array
   */
  protected $defaults = [];

  /**
   * The overwritten cache metadata.
   *
   * @var array
   */
  protected $overrides = [];

  /**
   * Gets the cache metadata defined for the block.
   *
   * @return array
   *   If the user defined custom metadata, these values will be returned;
   *   otherwise, the values defined by Drupal or other modules will be
   *   returned.
   *
   *   In both cases an array is returned with the following keys:
   *     - max-age
   *     - contexts
   *     - tags
   */
  public function getMetadata() {
    if ($this->isOverridden()) {
      return $this->getOverrides();
    }

    return $this->getDefaults();
  }

  /**
   * Gets the default cache metadata for the block.
   *
   * @return array
   *   If the block entity has not been defined, it returns an empty
   *   array; otherwise, it returns an array with the following keys:
   *     - max-age
   *     - contexts
   *     - tags
   */
  public function getDefaults() {
    return $this->defaults;
  }

  /**
   * Gets the block cache metadata setted by the user.
   *
   * @return array
   *   If the block entity has not been defined, it returns an empty
   *   array; otherwise, it returns an array with the following keys:
   *     - max-age
   *     - contexts
   *     - tags
   */
  public function getOverrides() {
    return $this->overrides;
  }

  /**
   * Gets the block entity.
   *
   * @return \Drupal\block\BlockInterface|null
   *   Return the block entity or NULL.
   */
  public function getBlock() {
    return $this->block;
  }

  /**
   * Set the default cache metadata.
   */
  public function setBlock(BlockInterface $block) {
    $this->block = $block;

    $this->defaults = [
      self::METADATA_MAX_AGE => $this->block->getCacheMaxAge(),
      self::METADATA_CONTEXTS => $this->block->getCacheContexts(),
      self::METADATA_TAGS => $this->block->getCacheTags(),
    ];

    $this->overridden = $this->block->getThirdPartySetting(self::MODULE, self::OVERRIDEN);
    $this->overrides = $this->block->getThirdPartySetting(self::MODULE, 'metadata');
  }

  /**
   * Set the overwritten cache metadata.
   *
   * @return bool
   *   Returns TRUE if the cache metadata has been setted;
   *   FALSE, otherwise.
   */
  public function setOverrides(int $max_age, array $contexts, array $tags) {
    if (!$this->isBlock()) {
      return FALSE;
    }

    $metadata = [
      self::METADATA_MAX_AGE => $max_age,
      self::METADATA_CONTEXTS => array_filter($contexts),
      self::METADATA_TAGS => array_filter($tags),
    ];

    $this->overridden = TRUE;
    $this->overrides = $metadata;
    $this->block->setThirdPartySetting(self::MODULE, self::OVERRIDEN, TRUE);
    $this->block->setThirdPartySetting(self::MODULE, 'metadata', $metadata);

    return TRUE;
  }

  /**
   * Unset the overwritten cache metadata.
   *
   * @return bool
   *   Returns TRUE if the overwritten cache metadata has been deleted;
   *   FALSE, otherwise.
   */
  public function unsetOverrides() {
    if (!$this->isBlock()) {
      return FALSE;
    }

    $this->overridden = FALSE;
    $this->overrides = [];
    $this->block->unsetThirdPartySetting(self::MODULE, self::OVERRIDEN);
    $this->block->unsetThirdPartySetting(self::MODULE, 'metadata');

    return TRUE;
  }

  /**
   * Checks whether block cache metadata has been overridden.
   *
   * @return bool
   *   Returns TRUE if the block cache metadata has been overridden;
   *   FALSE, otherwise.
   */
  public function isOverridden() {
    if (!$this->isBlock()) {
      return FALSE;
    }

    return $this->overridden;
  }

  /**
   * Checks whether block entity has been setted.
   *
   * @return bool
   *   Returns TRUE if the block entity has been setted;
   *   FALSE, otherwise.
   */
  private function isBlock() {
    return $this->block instanceof BlockInterface;
  }

}
