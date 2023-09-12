<?php

namespace Drupal\Tests\blocache\Unit;

use Drupal\blocache\BlocacheMetadata;
use Drupal\block\BlockInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\Tests\block\Traits\BlockCreationTrait;

/**
 * Tests for BlocacheMetadata class.
 *
 * @group blocache
 */
class BlocacheMetadataUnitTest extends UnitTestCase {

  use BlockCreationTrait;

  /**
   * The blocache.metadata service.
   *
   * @var \Drupal\blocache\BlocacheMetadata
   */
  protected $blocacheMetadata;

  /**
   * The block entity.
   *
   * @var \Drupal\block\BlockInterface
   */
  protected $block;

  /**
   * Cache metadata for the tests.
   *
   * @var array
   */
  protected $metadata;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->blocacheMetadata = new BlocacheMetadata();

    $plugin = $this->getMockBuilder('Drupal\Core\Block\BlockBase')
      ->disableOriginalConstructor()
      ->getMock();
    $plugin->expects($this->any())
      ->method('getMachineNameSuggestion')
      ->will($this->returnValue($this->randomMachineName(8)));

    $block = $this->getMockBuilder('Drupal\block\Entity\Block')
      ->disableOriginalConstructor()
      ->getMock();
    $block->expects($this->any())
      ->method('getPlugin')
      ->will($this->returnValue($plugin));

    $this->block = $block;
    $this->blocacheMetadata->setBlock($this->block);

    $this->metadata = [
      BlocacheMetadata::METADATA_MAX_AGE => 600,
      BlocacheMetadata::METADATA_CONTEXTS => [
        'user',
        'language',
      ],
      BlocacheMetadata::METADATA_TAGS => [
        'user:1',
        'language:en',
      ],
    ];
  }

  /**
   * @covers Drupal\blocache\BlocacheMetadata::setBlock
   */
  public function testSetBlock() {
    $this->assertInstanceOf(BlockInterface::class, $this->blocacheMetadata->getBlock());
  }

  /**
   * @covers Drupal\blocache\BlocacheMetadata::setOverrides
   */
  public function testSetOverrides() {
    $max_age = $this->metadata[BlocacheMetadata::METADATA_MAX_AGE];
    $contexts = $this->metadata[BlocacheMetadata::METADATA_CONTEXTS];
    $tags = $this->metadata[BlocacheMetadata::METADATA_TAGS];

    $this->assertEquals(TRUE, $this->blocacheMetadata->setOverrides($max_age, $contexts, $tags));
    $this->assertEquals(TRUE, $this->blocacheMetadata->isOverridden());
    $this->assertEquals($this->metadata, $this->blocacheMetadata->getOverrides());
  }

  /**
   * @covers Drupal\blocache\BlocacheMetadata::unsetOverrides
   */
  public function testUnsetOverrides() {
    $max_age = $this->metadata[BlocacheMetadata::METADATA_MAX_AGE];
    $contexts = $this->metadata[BlocacheMetadata::METADATA_CONTEXTS];
    $tags = $this->metadata[BlocacheMetadata::METADATA_TAGS];

    $this->assertEquals(TRUE, $this->blocacheMetadata->setOverrides($max_age, $contexts, $tags));
    $this->assertEquals(TRUE, $this->blocacheMetadata->unsetOverrides());
    $this->assertEquals([], $this->blocacheMetadata->getOverrides());
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    unset($this->blocacheMetadata);
    unset($this->block);
  }

}
