<?php

namespace Drupal\Tests\blocache\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Provides setup and helper methods for block module tests.
 */
abstract class BlocacheBrowserTestBase extends BrowserTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'blocache'];

  /**
   * A list of theme regions to test.
   *
   * @var array
   */
  protected $regions;

  /**
   * A test user with administrative privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The blocache.metadata service.
   *
   * @var \Drupal\blocache\BlocacheMetadata
   */
  protected $blocacheMetadata;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Use the test page as the front page.
    $this->config('system.site')->set('page.front', '/test-page')->save();

    // Create and log in an administrative user having access to the Full HTML
    // text format.
    $this->adminUser = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
      'administer block cache',
    ]);
    $this->drupalLogin($this->adminUser);

    // Define the existing regions.
    $this->regions = [
      'header',
      'sidebar_first',
      'content',
      'sidebar_second',
      'footer',
    ];

    $block_storage = $this->container->get('entity_type.manager')->getStorage('block');
    $blocks = $block_storage->loadByProperties(['theme' => $this->config('system.theme')->get('default')]);
    foreach ($blocks as $block) {
      $block->delete();
    }
  }

}
