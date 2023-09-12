<?php

namespace Drupal\Tests\eme\Kernel\Plugin\ReferenceDiscovery;

use Drupal\eme\Plugin\Eme\ReferenceDiscovery\MenuLinkContent;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent as MenuLinkContentEntity;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the menu link content reference discovery plugin.
 *
 * @coversDefaultClass \Drupal\eme\Plugin\Eme\ReferenceDiscovery\MenuLinkContent
 * @group eme
 */
class MenuLinkContentTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'link',
    'menu_link_content',
    'system',
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
    $this->installEntitySchema('menu_link_content');
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
   * Tests that menu link content related to an entity get discovered.
   *
   * @covers ::fetchReverseReferences
   * @dataProvider providerTestFetchReverseReferences
   */
  public function testFetchReverseReferences(array $link_uris, array $expected_matching_uris): void {
    foreach ($link_uris as $uri) {
      $link = MenuLinkContentEntity::create([
        'link' => ['uri' => $uri],
        'enabled' => 1,
      ]);
      $link->save();
    }

    $plugin = new MenuLinkContent(
      [],
      'menu_link_content',
      [],
      $this->container->get('entity_type.manager')
    );

    $actual_matching_menu_link_uris = array_map(function (MenuLinkContentInterface $menu_link) {
      return $menu_link->link->first()->uri;
    }, $plugin->fetchReverseReferences($this->entity));

    sort($expected_matching_uris);
    sort($actual_matching_menu_link_uris);

    $this->assertEquals($expected_matching_uris, $actual_matching_menu_link_uris);
  }

  /**
   * Data provider for testFetchReverseReferences.
   *
   * @return array
   *   The test cases.
   */
  public function providerTestFetchReverseReferences(): array {
    return [
      'No menu links' => [
        'Links' => [],
        'Expected' => [],
      ],
      'Menu links for test entity' => [
        'Links' => [
          'internal:/entity_test/1',
          'entity:entity_test/1',
          'internal:/entity_test/1',
          'route:entity.entity_test.canonical;entity_test=1',
        ],
        'Expected' => [
          'entity:entity_test/1',
          'internal:/entity_test/1',
          'internal:/entity_test/1',
          'route:entity.entity_test.canonical;entity_test=1',
        ],
      ],
      'Menu links for other test entity' => [
        'Links' => [
          'entity:entity_test/2',
          'internal:/entity_test/2',
          'base:entity_test/2',
          'route:entity.entity_test.canonical;entity_test=2',
        ],
        'Expected' => [],
      ],
      'Menu links for test entity and for other entities' => [
        'Links' => [
          'entity:entity_test/1',
          'internal:/entity_test/2',
          'base:entity_test/2',
        ],
        'Expected' => [
          'entity:entity_test/1',
        ],
      ],
    ];
  }

}
