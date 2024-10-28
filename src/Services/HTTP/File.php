<?php declare(strict_types=1);

namespace Fern\Core\Services\HTTP;

use Fern\Core\Errors\FileHandlingError;
use Fern\Core\Fern;
use Fern\Core\Wordpress\Filters;
use Fern\Services\HTTP\FileConstants;
use InvalidArgumentException;

/**
 * Class File
 *
 * Represents a file uploaded to the server.
 */
class File {
  public readonly string $id;             // The field id.

  public readonly string $name;           // The original name of the file

  public readonly string $fileName;       // The name of the file without extension

  public readonly string $fileExtension;  // The file extension

  public readonly string $type;           // The MIME type of the file

  public readonly string $tmp_name;       // The temporary name of the file

  public readonly int $error;             // The error code associated with the file upload

  public readonly int $size;              // The size of the file in bytes

  private string|null $url;       // The URL of the uploaded file

  private string $fullPath;       // The full path to the file

  /**
   * Constructs a File object.
   *
   * @param string $name     Original name of the file.
   * @param string $fullPath Full path to the file.
   * @param string $type     MIME type of the file.
   * @param string $tmp_name Temporary name of the file.
   * @param int    $error    Error code associated with the file upload.
   * @param int    $size     Size of the file in bytes.
   */
  public function __construct(string $id, string $name, string $fullPath, string $type, string $tmp_name, int $error, int $size) {
    $this->id = $id;
    $this->name = $name;
    $this->fullPath = $fullPath;
    $this->type = $type;
    $this->tmp_name = $tmp_name;
    $this->error = $error;
    $this->size = $size;
    $this->url = null;

    $pathInfo = pathinfo($name);
    $this->fileName = $pathInfo['filename'];
    $this->fileExtension = isset($pathInfo['extension']) ? $pathInfo['extension'] : '';
  }

  /**
   *  Get all files from the current request
   *
   * @return array<int, self>
   */
  public static function getAllFromCurrentRequest(): array {
    if (empty($_FILES)) {
      return [];
    }

    $files = [];
    $index = 0;

    foreach ($_FILES as $key => $data) {
      if (is_array($data['name'])) {
        // Flatten the array of files from handleMultipleFiles
        $multipleFiles = self::handleMultipleFiles($key, $data);

        foreach ($multipleFiles as $file) {
          $files[$index++] = $file;
        }
        continue;
      }

      $files[$index++] = new self(
          $key,
          $data['name'],
          $data['full_path'],
          $data['type'],
          $data['tmp_name'],
          $data['error'],
          $data['size'],
      );
    }

    return $files;
  }

  /**
   * Retrieves the list of file extensions that are not allowed for upload.
   *
   * @return array<string> The list of disallowed file extensions.
   */
  public static function getNotAllowedFileExtensions() {
    /**
     * Filter the list of disallowed file extensions.
     *
     * @filter fern:core:file:disallowed_upload_extensions
     *
     * @return array<string>
     */
    return Filters::apply('fern:core:file:disallowed_upload_extensions', FileConstants::DISALLOWED_FILE_EXTENSIONS);
  }

  /**
   * Checks if the file extension of the uploaded file is allowed.
   *
   * @return bool Whether the file extension is allowed or not.
   */
  public function isFileExtensionAllowed() {
    $extensions = self::getNotAllowedFileExtensions();

    return !in_array($this->fileExtension, array_map('strtolower', $extensions), true);
  }

  /**
   * @return string The file ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * @return string The original name of the file.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * @return string The full path to the file.
   */
  public function getFullPath(): string {
    return $this->fullPath;
  }

  /**
   * @return string The MIME type of the file.
   */
  public function getType(): string {
    return $this->type;
  }

  /**
   * @return string The temporary name of the file.
   */
  public function getTmpName(): string {
    return $this->tmp_name;
  }

  /**
   * @return int The error code associated with the file upload.
   */
  public function getError(): int {
    return $this->error;
  }

  /**
   * @return int The size of the file in bytes.
   */
  public function getSize(): int {
    return $this->size;
  }

  /**
   * @return string|null The URL of the uploaded file.
   */
  public function getUrl(): string|null {
    return $this->url;
  }

  /**
   * @return string The name of the file without extension.
   */
  public function getFileName(): string {
    return $this->fileName;
  }

  /**
   * @return string The file extension.
   */
  public function getFileExtension(): string {
    return $this->fileExtension;
  }

  /**
   * Set the full path to the file.
   *
   * @param string $fullPath Full path to the file.
   */
  public function setFullPath(string $fullPath): void {
    $this->fullPath = $fullPath;
  }

  /**
   * Set the URL of the uploaded file.
   *
   * @param string $url URL of the uploaded file.
   */
  public function setUrl(string $url): void {
    $this->url = $url;
  }

  /**
   * Delete the file from the server.
   */
  public function delete(): void {
    if (file_exists($this->fullPath)) {
      @unlink($this->fullPath);
    }

    if (file_exists($this->tmp_name)) {
      @unlink($this->tmp_name);
    }
  }

  /**
   * Uploads the file to the server using WordPress functions.
   *
   * @param ?string $path The path to upload the file to. Must be within the WordPress uploads directory.
   *
   * @throws FileHandlingError
   */
  public function upload(?string $path = null): void {
    if (!$this->canUpload()) {
      throw new FileHandlingError('File cannot be uploaded.');
    }

    if ($path !== null) {
      $path = ltrim($path, '/\\');
      $uploadsDir = wp_upload_dir()['basedir'];
      $fullPath = realpath($uploadsDir . '/' . $path);
      $uploadsRealPath = realpath($uploadsDir);

      if ($fullPath === false || $uploadsRealPath === false || !str_starts_with($fullPath, $uploadsRealPath)) {
        throw new FileHandlingError('Invalid upload path. Path must be within the WordPress uploads directory.');
      }
    }

    $uploadData = [
      'name' => $this->getName(),
      'type' => $this->getType(),
      'tmp_name' => $this->getTmpName(),
      'error' => $this->getError(),
      'size' => $this->getSize(),
    ];

    if (!function_exists('wp_handle_upload')) {
      $this->loadWordPressDependencies();
    }

    if ($path !== null) {
      $uploadDirFilter = function ($uploads) use ($path) {
        $path = trim($path, '/');
        $uploads['path'] = $uploads['basedir'] . '/' . $path;
        $uploads['url'] = $uploads['baseurl'] . '/' . $path;
        $uploads['subdir'] = '/' . $path;

        return $uploads;
      };

      Filters::on('upload_dir', $uploadDirFilter, 50, 1);
    }

    try {
      Filters::on('upload_dir', [$this, 'validateUploadDir'], 20);
      $upload = wp_handle_upload($uploadData, [
        'test_form' => false,
        'action' => 'local',
        'unique_filename_callback' => [$this, 'makeFilenameUnique'],
      ]);

      if ($upload && isset($upload['error'])) {
        throw new FileHandlingError('File upload failed : ' . $upload['error']);
      }

      $this->setFullPath($upload['file']);
      $this->setUrl($upload['url']);
    } finally {
      if ($path !== null) {
        remove_filter('upload_dir', $uploadDirFilter);
      }
      remove_filter('upload_dir', [$this, 'validateUploadDir'], 20);

      // Delete the temporary file if it still exists
      if (file_exists($this->tmp_name)) {
        @unlink($this->tmp_name);
      }
    }
  }

  /**
   * Validate the upload directory.
   *
   * @param array<string, mixed> $uploads The upload directory data.
   *
   * @return array<string, mixed> The validated upload directory data.
   *
   * @throws FileHandlingError
   */
  public function validateUploadDir(array $uploads): array {
    if (!wp_mkdir_p($uploads['path'])) {
      throw new FileHandlingError('Failed to create upload directory');
    }

    if (!wp_is_writable($uploads['path'])) {
      throw new FileHandlingError('Upload directory is not writable');
    }

    return $uploads;
  }

  /**
   * Make the filename unique by adding a suffix if necessary.
   *
   * @param string $dir  The directory to upload the file to.
   * @param string $name The name of the file.
   *
   * @return string The unique filename.
   */
  public function makeFilenameUnique(string $dir, string $name): string {
    return wp_unique_filename($dir, $name);
  }

  /**
   * Parses the File object to an array suitable for uploading.
   *
   * @return array<string, mixed> The File object as an associative array.
   */
  public function toArray(): array {
    return [
      'id' => $this->id,
      'name' => $this->name,
      'type' => $this->type,
      'size' => $this->size,
      'url' => $this->url,
    ];
  }

  /**
   * Handle multiple file uploads for a single input
   *
   * @param string               $key  The input key
   * @param array<string, mixed> $data The input data
   *
   * @return array<int, self>
   *
   * @throws FileHandlingError
   */
  private static function handleMultipleFiles(string $key, array $data): array {
    $files = [];
    $errors = [];
    $fileCount = count($data['name']);

    for ($i = 0; $i < $fileCount; $i++) {
      try {
        $files[] = new self(
            $key . '_' . $i,
            $data['name'][$i],
            $data['full_path'][$i],
            $data['type'][$i],
            $data['tmp_name'][$i],
            $data['error'][$i],
            $data['size'][$i],
        );
      } catch (InvalidArgumentException $e) {
        $errors[] = "File {$data['name'][$i]}: {$e->getMessage()}";
      }
    }

    if (!empty($errors)) {
      throw new FileHandlingError(
          "Failed to process multiple files:\n" . implode("\n", $errors),
      );
    }

    return $files;
  }

  /**
   * Validate the MIME type of the file.
   *
   * @return bool Whether the MIME type is allowed or not.
   */
  private function validateMimeType(): bool {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    if (!$finfo) {
      throw new FileHandlingError('Failed to initialize fileinfo');
    }

    try {
      $actualMime = finfo_file($finfo, $this->tmp_name);
      /**
       * Filter the list of allowed MIME types.
       *
       * @filter fern:core:file:allowed_mime_types
       *
       * @return array<string>
       */
      $allowedMimeTypes = Filters::apply('fern:core:file:allowed_mime_types', FileConstants::ALLOWED_MIME_TYPES);

      return in_array($actualMime, $allowedMimeTypes, true);
    } finally {
      finfo_close($finfo);
    }
  }

  /**
   * Get the error message for the file upload.
   *
   * @return string The error message.
   */
  private function getErrorMessage(): string {
    return match ($this->error) {
      UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive',
      UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive',
      UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
      UPLOAD_ERR_NO_FILE => 'No file was uploaded',
      UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
      UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
      UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
      default => 'Unknown upload error'
    };
  }

  /**
   * Check if the file can be uploaded.
   *
   * @return bool Whether the file can be uploaded or not.
   *
   * @throws FileHandlingError
   */
  private function canUpload(): bool {
    if (!$this->isFileExtensionAllowed()) {
      throw new FileHandlingError('File type not allowed. Received : ' . $this->getFileExtension());
    }

    if ($this->error !== UPLOAD_ERR_OK) {
      throw new FileHandlingError($this->getErrorMessage());
    }

    if (!$this->validateMimeType()) {
      throw new FileHandlingError('File type not allowed. Received : ' . $this->getType());
    }

    return true;
  }

  /**
   * Load the WordPress dependencies.
   *
   * @throws FileHandlingError
   */
  private function loadWordPressDependencies(): void {
    $root = trailingslashit(Fern::getRoot());
    $paths = [
      $root . 'public/wp/wp-admin/includes/file.php',
      $root . 'public/wp/wp-admin/includes/image.php',
      $root . 'public/wp/wp-admin/includes/media.php',
    ];

    foreach ($paths as $path) {
      if (!is_file($path) || !is_readable($path)) {
        throw new FileHandlingError('Cannot resolve Wordpress upload handling files.');
      }

      require_once $path;
    }
  }
}
