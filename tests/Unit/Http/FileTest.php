<?php declare(strict_types=1);

use Brain\Monkey\Functions;
use Fern\Core\Errors\FileHandlingError;
use Fern\Core\Services\HTTP\File;
use Fern\Core\Services\HTTP\FileConstants;

it('exposes its constants via the correct autoloadable namespace', function (): void {
  expect(class_exists(FileConstants::class))->toBeTrue()
    ->and(FileConstants::class)->toBe('Fern\Core\Services\HTTP\FileConstants');
});

function makeTmpFile(string $contents = 'hello world'): string {
  $path = tempnam(sys_get_temp_dir(), 'fern-file-test-');
  file_put_contents($path, $contents);

  return $path;
}

function makeTextFile(string $name = 'doc.txt', int $error = UPLOAD_ERR_OK): File {
  $tmp = makeTmpFile('plain text content');

  return new File($name, $name, $tmp, 'text/plain', $tmp, $error, 18);
}

afterEach(function (): void {
  foreach (glob(sys_get_temp_dir() . '/fern-file-test-*') ?: [] as $leftover) {
    if (is_file($leftover)) {
      @unlink($leftover);
    }
  }
});

describe('construction and name parsing', function (): void {
  it('splits the file name into base name and extension', function (): void {
    $file = new File('avatar', 'photo.JPG', '/tmp/x', 'image/jpeg', '/tmp/x', 0, 1024);

    expect($file->getId())->toBe('avatar')
      ->and($file->getName())->toBe('photo.JPG')
      ->and($file->getFileName())->toBe('photo')
      ->and($file->getFileExtension())->toBe('JPG')
      ->and($file->getType())->toBe('image/jpeg')
      ->and($file->getTmpName())->toBe('/tmp/x')
      ->and($file->getError())->toBe(0)
      ->and($file->getSize())->toBe(1024)
      ->and($file->getFullPath())->toBe('/tmp/x')
      ->and($file->getUrl())->toBeNull();
  });

  it('leaves the extension empty when the name has no dot', function (): void {
    $file = new File('id', 'README', '/tmp/x', 'text/plain', '/tmp/x', 0, 1);

    expect($file->getFileName())->toBe('README')
      ->and($file->getFileExtension())->toBe('');
  });
});

describe('getAllFromCurrentRequest', function (): void {
  it('returns an empty array when no files were uploaded', function (): void {
    $_FILES = [];

    expect(File::getAllFromCurrentRequest())->toBe([]);
  });

  it('parses a single uploaded file from the superglobal', function (): void {
    $_FILES = [
      'avatar' => [
        'name' => 'me.png',
        'full_path' => 'me.png',
        'type' => 'image/png',
        'tmp_name' => '/tmp/php123',
        'error' => 0,
        'size' => 2048,
      ],
    ];

    $files = File::getAllFromCurrentRequest();

    expect($files)->toHaveCount(1)
      ->and($files[0]->getId())->toBe('avatar')
      ->and($files[0]->getName())->toBe('me.png')
      ->and($files[0]->getType())->toBe('image/png')
      ->and($files[0]->getTmpName())->toBe('/tmp/php123')
      ->and($files[0]->getSize())->toBe(2048);
  });

  it('defaults missing optional keys to safe empty values', function (): void {
    $_FILES = [
      'doc' => [
        'name' => 'a.pdf',
      ],
    ];

    $files = File::getAllFromCurrentRequest();

    expect($files)->toHaveCount(1)
      ->and($files[0]->getType())->toBe('')
      ->and($files[0]->getTmpName())->toBe('')
      ->and($files[0]->getError())->toBe(0)
      ->and($files[0]->getSize())->toBe(0)
      ->and($files[0]->getFullPath())->toBe('');
  });

  it('skips entries that are not arrays', function (): void {
    $_FILES = [
      'broken' => 'not-an-array',
      'avatar' => [
        'name' => 'me.png',
        'type' => 'image/png',
        'tmp_name' => '/tmp/php123',
        'error' => 0,
        'size' => 10,
      ],
    ];

    $files = File::getAllFromCurrentRequest();

    expect($files)->toHaveCount(1)
      ->and($files[0]->getId())->toBe('avatar');
  });

  it('flattens a multi-file input into individual File objects', function (): void {
    $_FILES = [
      'gallery' => [
        'name' => ['one.jpg', 'two.png'],
        'full_path' => ['one.jpg', 'two.png'],
        'type' => ['image/jpeg', 'image/png'],
        'tmp_name' => ['/tmp/a', '/tmp/b'],
        'error' => [0, 0],
        'size' => [100, 200],
      ],
    ];

    $files = File::getAllFromCurrentRequest();

    expect($files)->toHaveCount(2)
      ->and($files[0]->getId())->toBe('gallery_0')
      ->and($files[0]->getName())->toBe('one.jpg')
      ->and($files[0]->getSize())->toBe(100)
      ->and($files[1]->getId())->toBe('gallery_1')
      ->and($files[1]->getName())->toBe('two.png')
      ->and($files[1]->getSize())->toBe(200);
  });

  it('combines single and multiple inputs while keeping sequential indexes', function (): void {
    $_FILES = [
      'single' => [
        'name' => 'solo.txt',
        'type' => 'text/plain',
        'tmp_name' => '/tmp/solo',
        'error' => 0,
        'size' => 5,
      ],
      'gallery' => [
        'name' => ['one.jpg', 'two.png'],
        'type' => ['image/jpeg', 'image/png'],
        'tmp_name' => ['/tmp/a', '/tmp/b'],
        'error' => [0, 0],
        'size' => [100, 200],
      ],
    ];

    $files = File::getAllFromCurrentRequest();

    expect($files)->toHaveCount(3)
      ->and($files[0]->getId())->toBe('single')
      ->and($files[1]->getId())->toBe('gallery_0')
      ->and($files[2]->getId())->toBe('gallery_1');
  });
});

describe('allowed extensions filter', function (): void {
  it('returns the default disallowed extension list', function (): void {
    Functions\when('apply_filters')->alias(fn (string $hook, mixed $value): mixed => $value);

    expect(File::getNotAllowedFileExtensions())->toContain('exe', 'php', 'sh');
  });

  it('falls back to an empty list when the filter returns a non-array', function (): void {
    Functions\when('apply_filters')->justReturn('not-an-array');

    expect(File::getNotAllowedFileExtensions())->toBe([]);
  });

  it('honours a custom disallowed list returned by the filter', function (): void {
    Functions\when('apply_filters')->justReturn(['custom']);

    $allowed = new File('id', 'safe.png', '/tmp/x', 'image/png', '/tmp/x', 0, 1);
    $blocked = new File('id', 'bad.custom', '/tmp/x', 'application/x', '/tmp/x', 0, 1);

    expect($allowed->isFileExtensionAllowed())->toBeTrue()
      ->and($blocked->isFileExtensionAllowed())->toBeFalse();
  });

  it('only lowercases the disallowed list, so an upper-cased extension slips through', function (): void {
    Functions\when('apply_filters')->alias(fn (string $hook, mixed $value): mixed => $value);

    $lower = new File('id', 'evil.php', '/tmp/x', 'text/plain', '/tmp/x', 0, 1);
    $upper = new File('id', 'evil.PHP', '/tmp/x', 'text/plain', '/tmp/x', 0, 1);

    expect($lower->isFileExtensionAllowed())->toBeFalse()
      ->and($upper->isFileExtensionAllowed())->toBeTrue();
  });
});

describe('mutators and serialisation', function (): void {
  it('updates the full path and url', function (): void {
    $file = new File('id', 'a.png', '/tmp/x', 'image/png', '/tmp/x', 0, 1);

    $file->setFullPath('/uploads/a.png');
    $file->setUrl('https://site.test/a.png');

    expect($file->getFullPath())->toBe('/uploads/a.png')
      ->and($file->getUrl())->toBe('https://site.test/a.png');
  });

  it('exposes a public array representation', function (): void {
    $file = new File('avatar', 'a.png', '/tmp/x', 'image/png', '/tmp/x', 0, 99);
    $file->setUrl('https://site.test/a.png');

    expect($file->toArray())->toBe([
      'id' => 'avatar',
      'name' => 'a.png',
      'type' => 'image/png',
      'size' => 99,
      'url' => 'https://site.test/a.png',
    ]);
  });
});

describe('delete', function (): void {
  it('unlinks both the destination and temporary files when present', function (): void {
    $dest = makeTmpFile('dest');
    $tmp = makeTmpFile('tmp');

    $file = new File('id', 'a.txt', $dest, 'text/plain', $tmp, 0, 4);
    $file->delete();

    expect(file_exists($dest))->toBeFalse()
      ->and(file_exists($tmp))->toBeFalse();
  });

  it('is a no-op when neither file exists', function (): void {
    $file = new File('id', 'a.txt', '/tmp/does-not-exist-x', 'text/plain', '/tmp/does-not-exist-y', 0, 4);

    $file->delete();

    expect(true)->toBeTrue();
  });
});

describe('makeFilenameUnique', function (): void {
  it('delegates to wp_unique_filename', function (): void {
    Functions\expect('wp_unique_filename')
      ->once()
      ->with('/uploads', 'a.png')
      ->andReturn('a-1.png');

    $file = new File('id', 'a.png', '/tmp/x', 'image/png', '/tmp/x', 0, 1);

    expect($file->makeFilenameUnique('/uploads', 'a.png'))->toBe('a-1.png');
  });
});

describe('validateUploadDir', function (): void {
  it('returns the uploads array when the directory is created and writable', function (): void {
    Functions\expect('wp_mkdir_p')->once()->with('/uploads/sub')->andReturn(true);
    Functions\expect('wp_is_writable')->once()->with('/uploads/sub')->andReturn(true);

    $file = new File('id', 'a.png', '/tmp/x', 'image/png', '/tmp/x', 0, 1);
    $uploads = ['path' => '/uploads/sub'];

    expect($file->validateUploadDir($uploads))->toBe($uploads);
  });

  it('throws when the upload directory cannot be created', function (): void {
    Functions\when('wp_mkdir_p')->justReturn(false);

    $file = new File('id', 'a.png', '/tmp/x', 'image/png', '/tmp/x', 0, 1);

    expect(fn (): array => $file->validateUploadDir(['path' => '/uploads/sub']))
      ->toThrow(FileHandlingError::class, 'Failed to create upload directory');
  });

  it('throws when the upload directory is not writable', function (): void {
    Functions\when('wp_mkdir_p')->justReturn(true);
    Functions\when('wp_is_writable')->justReturn(false);

    $file = new File('id', 'a.png', '/tmp/x', 'image/png', '/tmp/x', 0, 1);

    expect(fn (): array => $file->validateUploadDir(['path' => '/uploads/sub']))
      ->toThrow(FileHandlingError::class, 'Upload directory is not writable');
  });
});

describe('upload validation (canUpload branches)', function (): void {
  beforeEach(function (): void {
    Functions\when('apply_filters')->alias(fn (string $hook, mixed $value): mixed => $value);
  });

  it('rejects a disallowed file extension before touching WordPress', function (): void {
    $file = makeTextFile('payload.php');

    expect(fn (): null => $file->upload())
      ->toThrow(FileHandlingError::class, 'File type not allowed. Received : php');
  });

  it('rejects a file carrying an upload error code', function (): void {
    $file = makeTextFile('doc.txt', UPLOAD_ERR_INI_SIZE);

    expect(fn (): null => $file->upload())
      ->toThrow(FileHandlingError::class, 'exceeds the upload_max_filesize');
  });

  it('maps each upload error code to its message', function (int $code, string $fragment): void {
    $file = makeTextFile('doc.txt', $code);

    expect(fn (): null => $file->upload())
      ->toThrow(FileHandlingError::class, $fragment);
  })->with([
    'form size' => [UPLOAD_ERR_FORM_SIZE, 'MAX_FILE_SIZE directive'],
    'partial' => [UPLOAD_ERR_PARTIAL, 'only partially uploaded'],
    'no file' => [UPLOAD_ERR_NO_FILE, 'No file was uploaded'],
    'no tmp dir' => [UPLOAD_ERR_NO_TMP_DIR, 'Missing a temporary folder'],
    'cant write' => [UPLOAD_ERR_CANT_WRITE, 'Failed to write file to disk'],
    'extension' => [UPLOAD_ERR_EXTENSION, 'stopped by extension'],
  ]);

  it('rejects a file whose real MIME type is not allowed', function (): void {
    $tmp = makeTmpFile("\x00\x01\x02binary-not-allowed");
    $file = new File('id', 'thing.bin', $tmp, 'application/octet-stream', $tmp, UPLOAD_ERR_OK, 10);

    expect(fn (): null => $file->upload())
      ->toThrow(FileHandlingError::class, 'File type not allowed. Received : application/octet-stream');
  });
});

describe('upload success flow', function (): void {
  beforeEach(function (): void {
    Functions\when('apply_filters')->alias(fn (string $hook, mixed $value): mixed => $value);
    Functions\when('add_filter')->justReturn(true);
    Functions\when('remove_filter')->justReturn(true);
  });

  it('moves the file via wp_handle_upload and records the result', function (): void {
    $tmp = makeTmpFile('plain text content');
    $file = new File('doc', 'note.txt', $tmp, 'text/plain', $tmp, UPLOAD_ERR_OK, 18);

    Functions\expect('wp_handle_upload')
      ->once()
      ->andReturn([
        'file' => '/var/www/uploads/note.txt',
        'url' => 'https://site.test/uploads/note.txt',
        'type' => 'text/plain',
      ]);

    $file->upload();

    expect($file->getFullPath())->toBe('/var/www/uploads/note.txt')
      ->and($file->getUrl())->toBe('https://site.test/uploads/note.txt')
      ->and(file_exists($tmp))->toBeFalse();
  });

  it('throws when wp_handle_upload reports an error', function (): void {
    $tmp = makeTmpFile('plain text content');
    $file = new File('doc', 'note.txt', $tmp, 'text/plain', $tmp, UPLOAD_ERR_OK, 18);

    Functions\when('wp_handle_upload')->justReturn(['error' => 'disk full']);

    expect(fn (): null => $file->upload())
      ->toThrow(FileHandlingError::class, 'File upload failed : disk full');
  });

  it('validates the target path stays inside the uploads directory', function (): void {
    $tmp = makeTmpFile('plain text content');
    $file = new File('doc', 'note.txt', $tmp, 'text/plain', $tmp, UPLOAD_ERR_OK, 18);

    Functions\when('wp_upload_dir')->justReturn(['basedir' => '/etc']);

    expect(fn (): null => $file->upload('../../escape'))
      ->toThrow(FileHandlingError::class, 'Invalid upload path');
  });

  it('accepts a path within the uploads directory and applies the dir filter', function (): void {
    $uploadsDir = sys_get_temp_dir() . '/fern-file-test-uploads-' . uniqid('', true);
    mkdir($uploadsDir . '/sub', 0777, true);

    $tmp = makeTmpFile('plain text content');
    $file = new File('doc', 'note.txt', $tmp, 'text/plain', $tmp, UPLOAD_ERR_OK, 18);

    Functions\when('wp_upload_dir')->justReturn(['basedir' => $uploadsDir]);
    Functions\expect('wp_handle_upload')
      ->once()
      ->andReturn(['file' => $uploadsDir . '/sub/note.txt', 'url' => 'https://site.test/sub/note.txt']);

    $file->upload('sub');

    expect($file->getFullPath())->toBe($uploadsDir . '/sub/note.txt');

    if (file_exists($uploadsDir . '/sub/note.txt')) {
      unlink($uploadsDir . '/sub/note.txt');
    }
    @rmdir($uploadsDir . '/sub');
    @rmdir($uploadsDir);
  });
});
