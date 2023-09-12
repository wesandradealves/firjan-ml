<?php

namespace Drupal\Tests\eme\Traits;

use Drupal\comment\Entity\Comment;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\eme\Eme;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\Tests\file\Functional\FileFieldCreationTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Trait for EME's functional tests.
 */
trait EmeTestTrait {

  use CommentTestTrait;
  use FileFieldCreationTrait;
  use MediaTypeCreationTrait;
  use MediaFieldCreationTrait;
  use TestFileCreationTrait;
  use ContentTypeCreationTrait;
  use UserCreationTrait;
  use NodeCreationTrait;

  /**
   * Data of test users, keyed by the expected user ID.
   *
   * @var array[]
   */
  protected $testUserData = [
    // User "10" is the owner of node "2", node "3" and comment "2".
    10 => ['uid' => 10],
    // User "20" is the owner of node "4" and comment "1".
    20 => ['uid' => 20],
    // User "30" is the owner of node "1" and comment "3".
    30 => ['uid' => 30],
  ];

  /**
   * Data of initially created test nodes, keyed by the expected node ID.
   *
   * @var array[]
   */
  protected $testNodeData = [
    // Node "1" is a simple page and it does not have comments.
    1 => [
      'nid' => 1,
      'uuid' => '4997f53d-62d0-4d0d-88fa-3e4ef1800282',
      'vid' => 1,
      'langcode' => 'en',
      'type' => 'page',
      'title' => 'Page 1',
      'revision_timestamp' => 1600000000,
      'revision_log' => 'Log for page 1',
      'status' => 1,
      'uid' => 30,
      'created' => 1600000000,
      'changed' => 1600000000,
      'promote' => 0,
      'sticky' => 0,
      'default_langcode' => 1,
      'revision_default' => 1,
      'revision_uid' => 30,
      'revision_translation_affected' => 1,
      'body' => [
        [
          'value' => 'Test body page 1',
          'summary' => 'Test body page 1',
          'format' => 'plain_text',
        ],
      ],
    ],
    // Node "2" is an article with two comments.
    2 => [
      'nid' => 2,
      'uuid' => 'a47faf2f-71b4-4e2a-a50c-9d6ec80a6300',
      'vid' => 2,
      'langcode' => 'en',
      'type' => 'article',
      'title' => 'Article 2',
      'revision_timestamp' => 1600001000,
      'revision_log' => 'Log for article 2',
      'status' => 1,
      'uid' => 10,
      'created' => 1600001000,
      'changed' => 1600001000,
      'promote' => 1,
      'sticky' => 1,
      'default_langcode' => 1,
      'revision_default' => 1,
      'revision_uid' => 10,
      'revision_translation_affected' => 1,
      'body' => [
        [
          'value' => 'Test body article 2',
          'summary' => 'Test body article 2',
          'format' => 'plain_text',
        ],
      ],
      'media' => [['target_id' => 1]],
    ],
  ];

  /**
   * Data of additionally created test nodes, keyed by the expected node ID.
   *
   * @var array[]
   */
  protected $testAdditionalNodeData = [
    // Node "3" is an article owned by user "30". This node is created as
    // additional content, and it will have a comment with ID "3" owned by
    // user "30".
    3 => [
      'nid' => 3,
      'uuid' => 'ab103d00-b2e9-4bdd-be43-312a5fa806b2',
      'vid' => 3,
      'langcode' => 'en',
      'type' => 'article',
      'title' => 'Article 3',
      'revision_timestamp' => 1600002000,
      'revision_log' => 'Log for article 3',
      'status' => 0,
      'uid' => 30,
      'created' => 1600002000,
      'changed' => 1600002000,
      'promote' => 1,
      'sticky' => 0,
      'default_langcode' => 1,
      'revision_default' => 1,
      'revision_uid' => 10,
      'revision_translation_affected' => 1,
      'body' => [
        [
          'value' => 'Test body article 3',
          'summary' => 'Test body article 3',
          'format' => 'plain_text',
        ],
      ],
      'media' => [['target_id' => 3]],
    ],
  ];

  /**
   * Data of initially created comments, keyed by comment ID.
   *
   * @var array[]
   */
  protected $testCommentData = [
    // Comment 1 (on node 2), owned by user "20".
    1 => [
      'cid' => 1,
      'pid' => NULL,
      'uid' => 20,
      'entity_id' => 2,
      'entity_type' => 'node',
      'field_name' => 'comments',
      'comment_type' => 'article',
      'subject' => 'Comment 1 subject',
      'thread' => '00/',
      'comment_body' => [
        'value' => 'Comment 1 body',
        'format' => 'plain_text',
      ],
    ],
    // Comment 2 (on node 2, reply to comment 1), owned by user "10".
    2 => [
      'cid' => 2,
      'pid' => 1,
      'uid' => 10,
      'entity_id' => 2,
      'entity_type' => 'node',
      'field_name' => 'comments',
      'comment_type' => 'article',
      'subject' => 'Reply',
      'thread' => '00.00/',
      'comment_body' => [
        'value' => 'Comment 2 body',
        'format' => 'plain_text',
      ],
    ],
  ];

  /**
   * Data of additionally created comments, keyed by comment ID.
   *
   * @var array[]
   */
  protected $testAdditionalCommentData = [
    // Comment 3 (on node 2), owned by user "30". This comment is created as
    // additional content.
    3 => [
      'cid' => 3,
      'pid' => NULL,
      'uid' => 30,
      'entity_id' => 3,
      'entity_type' => 'node',
      'field_name' => 'comments',
      'comment_type' => 'article',
      'subject' => 'Comment 3 subject',
      'thread' => '00/',
      'comment_body' => [
        'value' => 'Comment 3 body',
        'format' => 'plain_text',
      ],
    ],
  ];

  /**
   * Data of initially created media, keyed by media ID.
   *
   * @var array[]
   */
  protected $testMediaData = [
    1 => [
      'mid' => 1,
      'uuid' => 'bdb68e13-5fd5-4356-bb1e-12053c32c7eb',
      'vid' => 1,
      'langcode' => 'en',
      'bundle' => 'image',
      'status' => 1,
      'uid' => 10,
      'created' => 1600001000,
      'changed' => 1600001000,
      'name' => 'Test image media',
      'field_media_image' => [['target_id' => 1]],
    ],
  ];

  /**
   * Data of initially created media, keyed by media ID.
   *
   * @var array[]
   */
  protected $testAdditionalMediaData = [
    3 => [
      'mid' => 3,
      'name' => 'Test document media',
      'bundle' => 'document',
      'field_media_file' => [['target_id' => 3]],
    ],
  ];

  /**
   * Test nodes, keyed by node ID.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected $testNodes;

  /**
   * Test users, keyed by user ID.
   *
   * @var \Drupal\user\UserInterface[]
   */
  protected $testUsers;

  /**
   * Test comments, keyed by comment ID.
   *
   * @var \Drupal\comment\CommentInterface[]
   */
  protected $testComments;

  /**
   * Test files, keyed by file ID.
   *
   * @var \Drupal\file\FileInterface[]
   */
  protected $testFiles;

  /**
   * Menu link content entities, keyed by menu link content ID.
   *
   * @var \Drupal\menu_link_content\MenuLinkContentInterface[]
   */
  protected $menuLinkContents;

  /**
   * Media entities, keyed by media ID.
   *
   * @var \Drupal\media\MediaInterface[]
   */
  protected $testMedia;

  /**
   * Creates the entity types (node type, etc) for the tests.
   */
  protected function createTestEntityTypes() {
    // Setup a basic node.
    $this->createContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);
    // Setup an article node type and add comment type.
    $this->createContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $this->addDefaultCommentField('node', 'article', 'comments', CommentItemInterface::OPEN, 'article');
    $this->createMediaType('image', ['id' => 'image']);
    $this->createMediaType('file', ['id' => 'document']);
    $this->createMediaField('media', 'node', 'article');
  }

  /**
   * Creates the default test content.
   */
  protected function createDefaultTestContent() {
    // Setup users.
    foreach ($this->testUserData as $uid => $userData) {
      $this->testUsers[$uid] = $this->createUser(['access content'], NULL, FALSE, $userData);
    }

    // Create initial comments.
    foreach ($this->testCommentData as $cid => $commentData) {
      $comment = Comment::create($commentData);
      $comment->save();
      $this->testComments[$cid] = Comment::load($comment->id());
    }

    // Create initial test files.
    foreach ($this->getTestFiles('image') as $image) {
      $extension = pathinfo($image->uri, PATHINFO_EXTENSION);
      if ($extension === 'png') {
        $png_image = $image;
      }
      elseif ($extension === 'jpg') {
        $jpg_image = $image;
      }
    }
    $this->testFiles[1] = File::create([
      'fid' => 1,
      'uid' => 10,
      'status' => 1,
      'created' => 1600000000,
      'changed' => 1600000000,
    ] + (array) $png_image);
    $this->testFiles[1]->save();
    $this->testFiles[2] = File::create([
      'fid' => 2,
      'uid' => 20,
      'status' => 1,
      'created' => 1600001000,
      'changed' => 1600001000,
    ] + (array) $jpg_image);
    $this->testFiles[2]->save();

    // Create initial media.
    foreach ($this->testMediaData as $mid => $mediaData) {
      $media = Media::create($mediaData);
      $media->save();
      $this->testMedia[$mid] = Media::load($media->id());
    }

    // Create initial nodes.
    foreach ($this->testNodeData as $nid => $nodeData) {
      $this->testNodes[$nid] = $this->createNode($nodeData);
    }

    $node2link = MenuLinkContent::create([
      'id' => 2,
      'title' => 'Node 2 menu link',
      'menu_name' => 'main',
      'link' => [['uri' => 'entity:node/' . $this->testNodes[2]->id()]],
    ]);
    $node2link->save();
    $this->menuLinkContents[2] = MenuLinkContent::load($node2link->id());

    // Reload node 2.
    $this->testNodes[2] = Node::load($this->testNodes[2]->id());
  }

  /**
   * Creates additional test content.
   */
  protected function createAdditionalTestContent() {
    // User "30" shouldn't been exported (so neither imported), so the test has
    // to re-create it.
    $this->assertNull(User::load(30));
    $this->testUsers[30] = $this->createUser(['access content'], NULL, FALSE, $this->testUserData[30]);

    $this->testFiles[3] = File::create([
      'fid' => 3,
      'uid' => 30,
      'status' => 1,
      'created' => 1600002000,
      'changed' => 1600002000,
    ] + (array) $this->getTestFiles('text')[0]);
    $this->testFiles[3]->save();

    // Create additional media.
    foreach ($this->testAdditionalMediaData as $mid => $mediaData) {
      $media = Media::create($mediaData);
      $media->save();
      $this->testMedia[$mid] = Media::load($media->id());
    }

    foreach ($this->testAdditionalNodeData as $nid => $nodeData) {
      $this->testNodes[$nid] = $this->createNode($nodeData);
    }

    foreach ($this->testAdditionalCommentData as $cid => $commentData) {
      $comment = Comment::create($commentData);
      $comment->save();
      $this->testComments[$cid] = Comment::load($comment->id());
    }

    $node3link = MenuLinkContent::create([
      'id' => 3,
      'title' => 'Node 3 menu link',
      'menu_name' => 'main',
      'link' => [['uri' => 'entity:node/' . $this->testNodes[3]->id()]],
    ]);
    $node3link->save();
    $this->menuLinkContents[3] = MenuLinkContent::load($node3link->id());

    // Reload node 2.
    $this->testNodes[3] = Node::load($this->testNodes[3]->id());
  }

  /**
   * Deletes the test content.
   */
  protected function deleteTestContent() {
    foreach ($this->testComments as $testComment) {
      $testComment->delete();
      $this->assertNull(Comment::load($testComment->id()));
    }
    $this->assertEmpty(Comment::loadMultiple());

    foreach ($this->testNodes as $testNode) {
      $testNode->delete();
      $this->assertNull(Node::load($testNode->id()));
    }
    $this->assertEmpty(Node::loadMultiple());

    foreach ($this->testMedia as $testMedia) {
      $testMedia->delete();
      $this->assertNull(Media::load($testMedia->id()));
    }
    $this->assertEmpty(Media::loadMultiple());

    foreach ($this->testUsers as $testUser) {
      $testUser->delete();
      $this->assertNull(User::load($testUser->id()));
    }
    $this->assertCount(2, User::loadMultiple());

    $file_system = $this->container->get('file_system');
    assert($file_system instanceof FileSystemInterface);
    foreach (File::loadMultiple() as $file) {
      $file->delete();
      $file_system->delete($file->getFileUri());
      $this->assertNull(File::load($file->id()));
      $this->assertFalse(file_exists($file->getFileUri()));
    }
    $this->assertEquals([], array_keys(File::loadMultiple()));

    foreach ($this->menuLinkContents as $menu_link_content) {
      $menu_link_content->delete();
      $this->assertNull(MenuLinkContent::load($menu_link_content->id()));
    }
    $this->assertEmpty(MenuLinkContent::loadMultiple());

    $this->resetAll();
  }

  /**
   * Resets (uninstalls and reinstalls) content related modules.
   */
  protected function resetContentRelatedModules() {
    $this->resetAll();
    $module_installer = $this->container->get('module_installer');
    assert($module_installer instanceof ModuleInstallerInterface);
    $module_installer->uninstall(['node', 'media']);
    $this->resetAll();
    $module_installer = $this->container->get('module_installer');
    assert($module_installer instanceof ModuleInstallerInterface);
    $module_installer->uninstall(['comment', 'file', 'image']);
    $this->resetAll();
    $module_installer->install(['node', 'comment', 'file', 'image', 'media']);
  }

  /**
   * Verifies that the exported content was successfully imported.
   */
  public function assertTestContent(bool $with_additionals = FALSE) {
    $this->assertEquals(
      static::getComparableUserProperties($this->testUsers[10]),
      User::load(10)->toArray()
    );
    $this->assertEquals(
      static::getComparableUserProperties($this->testUsers[20]),
      User::load(20)->toArray()
    );

    $this->assertEquals(
      $this->testComments[1]->toArray(),
      Comment::load(1)->toArray()
    );
    $this->assertEquals(
      $this->testComments[2]->toArray(),
      Comment::load(2)->toArray()
    );

    $this->assertEmpty(Node::load(1));
    $this->assertEquals(
      $this->testNodes[2]->toArray(),
      Node::load(2)->toArray()
    );

    $this->assertEquals(
      $this->testFiles[1]->toArray(),
      File::load(1)->toArray()
    );
    $this->assertTrue(file_exists($this->testFiles[1]->getFileUri()));
    $this->assertEmpty(File::load(2));

    $this->assertEquals(
      $this->menuLinkContents[2]->toArray(),
      MenuLinkContent::load(2)->toArray()
    );

    $this->assertEquals(
      static::getComparableMediaProperties($this->testMedia[1]),
      static::getComparableMediaProperties(Media::load(1))
    );

    if ($with_additionals) {
      $this->assertEquals(
        static::getComparableUserProperties($this->testUsers[30]),
        User::load(30)->toArray()
      );

      $this->assertEquals(
        $this->testComments[3]->toArray(),
        Comment::load(3)->toArray()
      );

      $this->assertEquals(
        $this->testFiles[3]->toArray(),
        File::load(3)->toArray()
      );
      $this->assertTrue(file_exists($this->testFiles[3]->getFileUri()));

      $this->assertEquals(
        $this->testNodes[3]->toArray(),
        Node::load(3)->toArray()
      );

      $this->assertEquals(
        $this->menuLinkContents[3]->toArray(),
        MenuLinkContent::load(3)->toArray()
      );

      $this->assertEquals(
        static::getComparableMediaProperties($this->testMedia[3]),
        static::getComparableMediaProperties(Media::load(3))
      );
    }
    else {
      $this->assertEmpty(User::load(30));
    }
  }

  /**
   * Verifies that the exported comment 1 json source is pretty printed.
   */
  public function assertComment1Json($comment_1_json_path) {
    $expected_file_content = <<<EOF
[
    {
        "cid": 1,
        "uuid": "{$this->testComments[1]->uuid()}",
        "langcode": "en",
        "comment_type": "article",
        "status": "0",
        "uid": 20,
        "pid": null,
        "entity_id": 2,
        "subject": "Comment 1 subject",
        "name": null,
        "mail": null,
        "homepage": null,
        "hostname": null,
        "created": {$this->testComments[1]->getCreatedTime()},
        "changed": {$this->testComments[1]->getChangedTime()},
        "thread": "00/",
        "entity_type": "node",
        "field_name": "comments",
        "default_langcode": "1",
        "comment_body": [
            {
                "value": "Comment 1 body",
                "format": "plain_text"
            }
        ]
    }
]

EOF;

    $this->assertEquals($expected_file_content, file_get_contents($comment_1_json_path));
  }

  /**
   * Verifies that the media 1 source data does not contain "revision_created".
   */
  public function assertMedia1Json($media_1_json_path) {
    $media_1_source_data = json_decode(file_get_contents($media_1_json_path), TRUE);
    $this->assertEquals(1, $media_1_source_data[0]['mid']);
    $this->assertArrayNotHasKey('revision_created', $media_1_source_data[0]);
  }

  /**
   * Removes the "existing" computed property from user pass.
   */
  protected static function getComparableUserProperties(UserInterface $user): array {
    // The "existing" property of the user pass shouldn't be compared.
    $user_array = $user->toArray();
    unset($user_array['pass'][0]['existing']);
    return $user_array;
  }

  /**
   * Removes the "revision_created" computed property from media.
   */
  protected static function getComparableMediaProperties(MediaInterface $media): array {
    // For new revisions, the "revision_created" property is overwritten by
    // media.
    $media_array = $media->toArray();
    unset($media_array['revision_created']);
    return $media_array;
  }

  /**
   * Adds a front page config migration to the specified module.
   *
   * @return string|null
   *   The path of the file, or NULL on failure.
   */
  protected function addFrontPageMigration(string $module_path, string $migration_id, string $migration_group, string $site_front_page = '/node/2') {
    $front_page_migration = [
      'label' => 'Homepage',
      'migration_group' => $migration_group,
      'migration_tags' => [
        'Drupal ' . substr(\Drupal::VERSION, 0, 1),
        'Configuration',
        $migration_group,
      ],
      'id' => $migration_id,
      'source' => [
        'plugin' => 'embedded_data',
        'data_rows' => [['homepage' => $site_front_page]],
        'ids' => ['homepage' => ['type' => 'string']],
      ],
      'process' => ['page/front' => 'homepage'],
      'destination' => [
        'plugin' => 'config',
        'config_name' => 'system.site',
      ],
    ];

    $file_path = implode('/', [
      $module_path,
      Eme::MIGRATION_DIR,
      "$migration_id.info.yml",
    ]);

    return file_put_contents($file_path, Yaml::encode($front_page_migration))
      ? $file_path
      : NULL;
  }

}
