<?php

declare(strict_types=1);

namespace Drupal\eme\Component;

use Drupal\Core\Archiver\ArchiveTar;
use Drupal\eme\Eme;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A temporary export object represents the export module being generated.
 *
 * @internal
 */
final class TemporaryExport {

  /**
   * The location of the export module.
   *
   * @var string
   */
  protected $location;

  /**
   * The temporary directory.
   *
   * @var string
   */
  protected $tempDir;

  /**
   * The symfony file system utility.
   *
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  protected $filesystem;

  /**
   * Constuts the TemporaryExport instance.
   *
   * @param string $temporary_dir
   *   The temporary directory.
   */
  public function __construct(string $temporary_dir) {
    $this->tempDir = $temporary_dir;
    $this->location = $temporary_dir . DIRECTORY_SEPARATOR . Eme::ID;
  }

  /**
   * Returns the symfony file system utility.
   *
   * @return \Symfony\Component\Filesystem\Filesystem
   *   The symfony file system utility.
   */
  protected function filesystem() {
    if (!($this->filesystem instanceof Filesystem)) {
      $this->filesystem = new Filesystem();
    }
    return $this->filesystem;
  }

  /**
   * Prepares the export object.
   *
   * Removes the previous file assets (by removing the root directory) and the
   * generated TAR archive.
   */
  public function reset(): void {
    if (file_exists($this->location)) {
      $this->filesystem()->remove($this->location);
    }
    $archive_location = implode('/', [
      $this->tempDir,
      Eme::getArchiveName(),
    ]);
    if (file_exists($archive_location)) {
      $this->filesystem()->remove($archive_location);
    }
  }

  /**
   * Returns the content of the given file.
   *
   * @param string $resource
   *   The path of the file, relative to the export module root.
   *
   * @return string|null
   *   The content of the given file in string if it exists, or NULL if the file
   *   cannot be found.
   */
  public function getFileContent(string $resource): ?string {
    $resource_path = implode(DIRECTORY_SEPARATOR, [
      $this->location,
      $resource,
    ]);

    if (!file_exists($resource_path) || !is_file($resource_path)) {
      return NULL;
    }

    $content = file_get_contents($resource_path);

    return $content === FALSE
      ? NULL
      : $content;
  }

  /**
   * Creates or overrides a file in the temporary export with the given content.
   *
   * @param string $filename
   *   A string which contains the full filename path that will be associated
   *   with the given file content.
   * @param string $content
   *   The content of the file added in the export.
   *
   * @return bool
   *   TRUE on success, FALSE on error.
   */
  public function addFileWithContent(string $filename, string $content): bool {
    $destination = implode(DIRECTORY_SEPARATOR, [
      $this->location,
      $filename,
    ]);
    $this->filesystem()->mkdir(dirname($destination));
    $return = file_put_contents($destination, $content);
    $this->filesystem()->chmod($destination, 0600);

    return $return === FALSE ? FALSE : TRUE;
  }

  /**
   * Copies the given files into the temporary export module.
   *
   * @param string[] $filelist
   *   An array of filenames and/or directory names to copy into the export
   *   module.
   * @param string $add_dir
   *   A string which contains a path to be added to the beginning of the real
   *   path of each element in the list.
   * @param string $remove_dir
   *   A substring to be removed from the beginning of the real path of each
   *   element in the list.
   */
  public function addFiles(array $filelist, string $add_dir = '', string $remove_dir = ''): bool {
    $remove_dir = substr($remove_dir, -1) !== DIRECTORY_SEPARATOR
      ? $remove_dir . DIRECTORY_SEPARATOR
      : $remove_dir;

    foreach ($filelist as $filepath) {
      $destination_raw = $filepath;

      if (substr($destination_raw, 0, strlen($remove_dir)) === $remove_dir) {
        $destination_raw = substr($destination_raw, strlen($remove_dir));
      }

      if (!empty($add_dir)) {
        $destination_raw = substr($add_dir, -1) === DIRECTORY_SEPARATOR
          ? $add_dir . $destination_raw
          : $add_dir . DIRECTORY_SEPARATOR . $destination_raw;
      }

      $destination = implode(DIRECTORY_SEPARATOR, [
        $this->location,
        $destination_raw,
      ]);
      try {
        $this->filesystem()->copy($filepath, $destination, TRUE);
      }
      catch (FileNotFoundException $e) {
        return FALSE;
      }
      catch (IOException $e) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Moves the export to the given destination.
   *
   * @param string $destination
   *   The destination path.
   *
   * @return bool
   *   Whether the operation was successful or not.
   */
  public function move(string $destination): bool {
    try {
      $this->filesystem()->mirror($this->location, $destination, NULL, [
        'override' => TRUE,
      ]);
    }
    catch (IOException $e) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Creates a tar archive from the temporary export module.
   *
   * @return bool
   *   Whether the archive was successfully created.
   */
  public function createTemporaryArchive(): bool {
    $archive_location = implode('/', [
      $this->tempDir,
      Eme::getArchiveName(),
    ]);
    if (file_exists($archive_location)) {
      $this->filesystem()->remove($archive_location);
    }
    $archive = new ArchiveTar($archive_location);
    return $archive->addModify([$this->location], '', $this->location);
  }

}
