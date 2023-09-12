<?php

namespace Drupal\Tests\eme\Traits;

use Drush\Drush;
use Drush\TestTraits\DrushTestTrait;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Drush specific assertions.
 */
trait EmeTestDrushAssertionsTrait {

  use DrushTestTrait;

  /**
   * Whether the actual Drush version is an old one without migrate commands.
   *
   * @return bool
   *   Whether the actual Drush version is an old one without migrate commands.
   */
  public static function isOldDrushVersion(): bool {
    return version_compare(Drush::getVersion(), '10.4.0', 'lt');
  }

  /**
   * Checks that migrate status output has all the lines.
   *
   * @param string|string[] $expected_lines
   *   The expected lines in drush output.
   */
  protected function assertDrushOutputHasAllLines($expected_lines) {
    $actual_output = $this->getSimplifiedOutput();

    try {
      foreach ((array) $expected_lines as $expected_line) {
        $expected_line_pieces = array_filter(
          explode(' ', $expected_line),
          function ($word) {
            return (string) $word !== '';
          }
        );
        foreach ($expected_line_pieces as $key => $text) {
          $expected_line_pieces[$key] = preg_quote((string) $text, '/');
        }
        $pattern = '/\b' . implode('\s+', $expected_line_pieces) . '\b/';
        $this->assertEquals(1, preg_match($pattern, $actual_output));
      }
    }
    catch (ExpectationFailedException $e) {
      $this->assertEquals(implode("\n", $expected_lines), $this->getOutput());
    }
  }

}
