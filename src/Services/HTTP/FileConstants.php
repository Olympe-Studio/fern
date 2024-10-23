<?php declare(strict_types=1);

namespace Fern\Services\HTTP;

class FileConstants {
  /**
   * List of allowed MIME types.
   *
   * @var array<string>
   */
  public const ALLOWED_MIME_TYPES = [
    // Images
    'image/jpeg',                 // .jpg, .jpeg
    'image/png',                  // .png
    'image/gif',                  // .gif
    'image/webp',                 // .webp
    'image/svg+xml',              // .svg
    'image/bmp',                  // .bmp
    'image/tiff',                 // .tiff, .tif
    'image/avif',                 // .avif

    // Documents
    'application/pdf',            // .pdf
    'application/msword',         // .doc
    'application/vnd.ms-excel',   // .xls
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',    // .docx
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',          // .xlsx
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',  // .pptx
    'application/vnd.ms-powerpoint',  // .ppt
    'text/plain',                 // .txt
    'text/csv',                   // .csv
    'text/rtf',                   // .rtf

    // Archives
    'application/zip',            // .zip
    'application/x-rar-compressed', // .rar
    'application/x-7z-compressed',  // .7z
    'application/x-tar',          // .tar
    'application/gzip',           // .gz

    // Audio
    'audio/mpeg',                 // .mp3
    'audio/wav',                  // .wav
    'audio/midi',                 // .midi
    'audio/ogg',                  // .ogg
    'audio/x-m4a',                // .m4a
    'audio/aac',                  // .aac

    // Video
    'video/mp4',                  // .mp4
    'video/mpeg',                 // .mpeg
    'video/quicktime',            // .mov
    'video/webm',                 // .webm
    'video/x-msvideo',            // .avi
    'video/x-ms-wmv',             // .wmv
    'video/3gpp',                 // .3gp

    // Fonts
    'font/ttf',                   // .ttf
    'font/otf',                   // .otf
    'font/woff',                  // .woff
    'font/woff2',                 // .woff2
  ];

  /**
   * List of disallowed file extensions.
   *
   * @var array<string>
   */
  public const DISALLOWED_FILE_EXTENSIONS = [
    'exe',
    'bat',
    'cmd',
    'scr',
    'pif',
    'com',
    'js',
    'jsp',
    'php',
    'asp',
    'sh',
    'dll',
    'lnk',
    'sys',
    'vb',
    'vbs',
    'prisma',
    'cgi',
    'py',
    'sql',
    'asp',
    'htaccess',
    'htpasswd',
    'ini',
    'jar',
    'swf',
  ];
}
