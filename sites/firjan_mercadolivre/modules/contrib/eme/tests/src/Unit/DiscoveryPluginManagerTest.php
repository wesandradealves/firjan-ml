<?php

namespace Drupal\Tests\eme\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\eme\ReferenceDiscovery\DiscoveryPluginManager;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests EME's DiscoveryPluginManager.
 *
 * @coversDefaultClass \Drupal\eme\ReferenceDiscovery\DiscoveryPluginManager
 * @group eme
 */
class DiscoveryPluginManagerTest extends UnitTestCase {

  /**
   * Cache backend object prophecy.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $cache;

  /**
   * Module handler object prophecy.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $moduleHandler;

  /**
   * Logger prophecy.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->cache = $this->prophesize(CacheBackendInterface::class);
    $this->moduleHandler = $this->prophesize(ModuleHandlerInterface::class);
    $this->logger = $this->prophesize(LoggerInterface::class);
  }

  /**
   * Tests getting direct reference parser plugins.
   *
   * @covers ::getDirectReferenceDiscoveryPluginInstances
   * @dataProvider providerTestGetReferencePlugins
   */
  public function testGetDirectReferencePlugins(array $cached_plugins, array $expected_results) {
    $this->cache->get(DiscoveryPluginManager::CACHE_ID)->willReturn((object) [
      'data' => $cached_plugins,
    ]);
    $manager = new DiscoveryPluginManager(
      new \ArrayObject(),
      $this->cache->reveal(),
      $this->moduleHandler->reveal(),
      $this->logger->reveal()
    );
    $this->assertEquals($expected_results['direct'], $manager->getDirectReferenceDiscoveryPluginInstances());
  }

  /**
   * Tests getting reverse reference parser plugins.
   *
   * @covers ::getReverseReferenceDiscoveryPluginInstances
   * @dataProvider providerTestGetReferencePlugins
   */
  public function testGetReverseReferencePlugins(array $cached_plugins, array $expected_results) {
    $this->cache->get(DiscoveryPluginManager::CACHE_ID)->willReturn((object) [
      'data' => $cached_plugins,
    ]);
    $manager = new DiscoveryPluginManager(
      new \ArrayObject(),
      $this->cache->reveal(),
      $this->moduleHandler->reveal(),
      $this->logger->reveal()
    );
    $this->assertEquals($expected_results['reverse'], $manager->getReverseReferenceDiscoveryPluginInstances());
  }

  /**
   * Data provider for testGetReverseReferencePlugins.
   *
   * @return array
   *   The test cases.
   */
  public function providerTestGetReferencePlugins() {
    return [
      'No plugins' => [
        'Cached definitions' => [],
        'Expected results' => [
          'direct' => [],
          'reverse' => [],
        ],
      ],
      'Only direct plugins' => [
        'Cached definitions' => [
          'direct_2' => [
            'id' => 'direct_2',
            'class' => DummyDirectReferencePlugin::class,
          ],
          'direct_1' => [
            'id' => 'direct_1',
            'class' => DummyDirectReferencePlugin::class,
          ],
        ],
        'Expected results' => [
          'direct' => [
            'direct_1' => new DummyDirectReferencePlugin([], 'direct_1', [
              'id' => 'direct_1',
              'class' => DummyDirectReferencePlugin::class,
            ]),
            'direct_2' => new DummyDirectReferencePlugin([], 'direct_2', [
              'id' => 'direct_2',
              'class' => DummyDirectReferencePlugin::class,
            ]),
          ],
          'reverse' => [],
        ],
      ],
      'Direct and reverse plugins' => [
        'Cached definitions' => [
          'direct' => [
            'id' => 'direct',
            'class' => DummyDirectReferencePlugin::class,
          ],
          'reverse' => [
            'id' => 'reverse',
            'class' => DummyReverseReferencePlugin::class,
          ],
          'both' => [
            'id' => 'both',
            'class' => DummyAllReferencePlugin::class,
          ],
        ],
        'Expected results' => [
          'direct' => [
            'direct' => new DummyDirectReferencePlugin([], 'direct', [
              'id' => 'direct',
              'class' => DummyDirectReferencePlugin::class,
            ]),
            'both' => new DummyAllReferencePlugin([], 'both', [
              'id' => 'both',
              'class' => DummyAllReferencePlugin::class,
            ]),
          ],
          'reverse' => [
            'reverse' => new DummyReverseReferencePlugin([], 'reverse', [
              'id' => 'reverse',
              'class' => DummyReverseReferencePlugin::class,
            ]),
            'both' => new DummyAllReferencePlugin([], 'both', [
              'id' => 'both',
              'class' => DummyAllReferencePlugin::class,
            ]),
          ],
        ],
      ],
      'Only reverse plugins' => [
        'Cached definitions' => [
          'reverse_1' => [
            'id' => 'reverse_1',
            'class' => DummyReverseReferencePlugin::class,
          ],
          'reverse_2' => [
            'id' => 'reverse_2',
            'class' => DummyReverseReferencePlugin::class,
          ],
        ],
        'Expected results' => [
          'direct' => [],
          'reverse' => [
            'reverse_1' => new DummyReverseReferencePlugin([], 'reverse_1', [
              'id' => 'reverse_1',
              'class' => DummyReverseReferencePlugin::class,
            ]),
            'reverse_2' => new DummyReverseReferencePlugin([], 'reverse_2', [
              'id' => 'reverse_2',
              'class' => DummyReverseReferencePlugin::class,
            ]),
          ],
        ],
      ],
    ];
  }

}
