<?php

namespace Drupal\Tests\blocache\Functional;

use Drupal\Core\Language\LanguageInterface;

/**
 * Blocache browser's tests.
 *
 * @group blocache
 */
class BlocacheBrowserTest extends BlocacheBrowserTestBase {

  /**
   * Tests access to metadata configuration fields.
   */
  public function testBlocacheSettingsAccess() {
    // Access the add block page for the default theme.
    $block_name = 'system_powered_by_block';
    $default_theme = $this->config('system.theme')->get('default');
    $this->drupalGet('admin/structure/block/add/' . $block_name . '/' . $default_theme);
    $this->assertField('edit-blocache-overridden');
  }

  /**
   * Tests storage of cache metadata.
   */
  public function testStorageMetadata() {
    // Access the add block page for the default theme.
    $block_name = 'system_powered_by_block';
    $default_theme = $this->config('system.theme')->get('default');
    $this->drupalGet('admin/structure/block/add/' . $block_name . '/' . $default_theme);

    // Configures the cache metadata and saves the block.
    $edit = [
      'id' => strtolower($this->randomMachineName(8)),
      'region' => 'sidebar_first',
      'settings[label]' => $this->randomMachineName(8),
      'blocache[overridden]' => 1,
      'blocache[tabs][max-age][value]' => 600,
      'blocache[tabs][contexts][value][user.roles]' => 1,
      'blocache[tabs][contexts][value][user.roles__arg]' => 'administrator',
      'blocache[tabs][contexts][value][languages]' => 1,
      'blocache[tabs][contexts][value][languages__arg]' => LanguageInterface::TYPE_URL,
    ];
    $this->drupalPostForm(NULL, $edit, t('Save block'));

    // Access the block form again and check if the values have been saved.
    $this->assertText('The block configuration has been saved.', 'Block was saved');
    $this->clickLink('Configure');
    $this->assertFieldChecked('edit-blocache-overridden');
    $this->assertFieldByName('blocache[tabs][max-age][value]', 600);
    $this->assertFieldByName('blocache[tabs][contexts][value][user.roles]', 1);
    $this->assertFieldByName('blocache[tabs][contexts][value][user.roles__arg]', 'administrator');
    $this->assertFieldByName('blocache[tabs][contexts][value][languages]', 1);
    $this->assertFieldByName('blocache[tabs][contexts][value][languages__arg]', LanguageInterface::TYPE_URL);
  }

}
