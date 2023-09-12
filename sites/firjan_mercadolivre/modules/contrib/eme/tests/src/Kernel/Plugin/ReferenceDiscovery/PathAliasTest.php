<?php

namespace Drupal\Tests\eme\Kernel\Plugin\ReferenceDiscovery;

use Drupal\eme\Plugin\Eme\ReferenceDiscovery\PathAlias;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\path_alias\Entity\PathAlias as PathAliasEntity;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the path alias reference discovery plugin.
 *
 * @coversDefaultClass \Drupal\eme\Plugin\Eme\ReferenceDiscovery\PathAlias
 * @group eme
 */
class PathAliasTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'path_alias',
    'user',
  ];

  /**
   * The test entity.
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('entity_test');

    // We need an anonymous user.
    // @see https://drupal.org/i/3056234
    $uid = $this->createUser([], '', FALSE, ['uid' => 0])->save();

    // The test entity.
    $this->entity = EntityTest::create([
      'id' => 1,
      'uid' => $uid,
    ]);
    $this->entity->save();

    // Create an another test entity.
    EntityTest::create([
      'id' => 2,
      'uid' => $uid,
    ])->save();
  }

  /**
   * Tests that path aliases related to an entity get discovered.
   *
   * @covers ::fetchReferences
   * @dataProvider providerTestFetchReferences
   */
  public function testFetchReferences(array $source_aliases, array $expected_aliases): void {
    foreach ($source_aliases as $data) {
      PathAliasEntity::create($data)->save();
    }

    $plugin = new PathAlias(
      [],
      'path_alias',
      [],
      $this->container->get('entity_type.manager')
    );

    $actual_aliases = array_map(function (PathAliasEntity $path_alias) {
      return [
        'alias' => $path_alias->getAlias(),
        'path' => $path_alias->getPath(),
      ];
    }, $plugin->fetchReferences($this->entity));

    $this->assertEquals($expected_aliases, array_values($actual_aliases));
  }

  /**
   * Data provider for testFetchReferences.
   *
   * @return array
   *   The test cases.
   */
  public function providerTestFetchReferences(): array {
    return [
      'No aliases' => [
        'Source aliases' => [],
        'Expected aliases' => [],
      ],
      'Path aliases for test entity' => [
        'Source aliases' => [
          ['path' => '/entity_test/1', 'alias' => '/an-alias'],
          ['path' => '/entity_test/1', 'alias' => '/an/another/alias'],
        ],
        'Expected aliases' => [
          ['path' => '/entity_test/1', 'alias' => '/an-alias'],
          ['path' => '/entity_test/1', 'alias' => '/an/another/alias'],
        ],
      ],
      'Unrelated aliases for other test entity' => [
        'Source aliases' => [
          ['path' => '/entity_test/2', 'alias' => '/an-unrelated-alias'],
          ['path' => '/user/1', 'alias' => '/root-user'],
        ],
        'Expected aliases' => [],
      ],
      'Aliases for the test entity and for other entities' => [
        'Source aliases' => [
          ['path' => '/entity_test/2', 'alias' => '/an-unrelated-alias'],
          ['path' => '/entity_test/1', 'alias' => '/an-alias'],
          ['path' => '/user/1', 'alias' => '/root-user'],
          ['path' => '/user/login', 'alias' => '/not-an-entity-alias'],
          ['path' => '/entity_test/1', 'alias' => '/an/another/alias'],
        ],
        'Expected aliases' => [
          ['path' => '/entity_test/1', 'alias' => '/an-alias'],
          ['path' => '/entity_test/1', 'alias' => '/an/another/alias'],
        ],
      ],
    ];
  }

}
