<?php

namespace Drupal\Tests\eme\Unit\Plugin\ReferenceDiscovery;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\eme\Plugin\Eme\ReferenceDiscovery\EntityReference;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * Tests the entity reference reference discovery plugin.
 *
 * @coversDefaultClass \Drupal\eme\Plugin\Eme\ReferenceDiscovery\EntityReference
 * @group eme
 */
class EntityReferenceTest extends UnitTestCase {

  /**
   * Tests that entities referred by fields get discovered.
   *
   * @covers ::fetchReferences
   * @dataProvider providerTestFetchReferences
   */
  public function testFetchReferences(array $fields, int $expected_number_of_entities) {
    $regular_field = $this->prophesize(FieldItemListInterface::class);
    $content_ref_field = $this->prophesize(EntityReferenceFieldItemListInterface::class);
    $config_ref_field = $this->prophesize(EntityReferenceFieldItemListInterface::class);
    $referenced_entity = $this->prophesize(ContentEntityInterface::class);
    $referenced_config = $this->prophesize(ConfigEntityInterface::class);

    $content_ref_field->referencedEntities()->willReturn([$referenced_entity->reveal()]);
    $config_ref_field->referencedEntities()->willReturn([$referenced_config->reveal()]);

    $field_map = [
      'neutral' => $regular_field,
      'config' => $config_ref_field,
      'content' => $content_ref_field,
    ];
    $getfield_return = array_map(function (string $type) use ($field_map) {
      return $field_map[$type] ?? NULL;
    }, $fields);

    $host_entity = $this->prophesize(ContentEntityInterface::class);
    $host_entity->getFields(Argument::any())->willReturn(array_filter($getfield_return));

    $plugin = new EntityReference([], 'entity_reference', []);
    $referenced_entities = $plugin->fetchReferences($host_entity->reveal());

    assert(Inspector::assertAllObjects($referenced_entities, ContentEntityInterface::class));

    $this->assertCount($expected_number_of_entities, $referenced_entities);
  }

  /**
   * Data provider for testFetchReferences.
   *
   * @return array
   *   The test cases.
   */
  public function providerTestFetchReferences(): array {
    return [
      'No fields' => [
        'Fields' => [],
        'Expected number of referenced content entities' => 0,
      ],
      'Unrelated fields' => [
        'Fields' => [
          'field_unrelated1' => 'neutral',
          'field_config_reference' => 'config',
          'field_unrelated2' => 'neutral',
        ],
        'Expected number of referenced content entities' => 0,
      ],
      'Fields with content references' => [
        'Fields' => [
          'field_unrelated1' => 'neutral',
          'field_content_ref_1' => 'content',
          'field_config_reference' => 'config',
          'field_unrelated2' => 'neutral',
          'field_content_ref_2' => 'content',
        ],
        'Expected number of referenced content entities' => 2,
      ],
    ];
  }

}
