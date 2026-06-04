<?php

declare(strict_types=1);

namespace Fern\Core\Services\Mail;

use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Views\Views;
use Fern\Core\Utils\Types;
use Fern\Core\Wordpress\Filters;

use function wp_mail;

/**
 * Simple mail helper built on top of WordPress wp_mail.
 *
 * It renders the email body with the Views service and exposes a small
 * hook surface for applications to adjust payload before delivery.
 */
final class Mail extends Singleton {
  /**
   * Send an email rendered from a view template.
   *
   * @param string|array<int,string>    $to          Recipient address(es).
   * @param string                      $subject     Email subject.
   * @param string                      $view        Template name understood by Views.
   * @param array<string,mixed>         $data        Data passed to the template.
   * @param array<int,string>           $headers     Optional extra headers.
   * @param array<int,string>           $attachments Optional attachments.
   */
  public static function send($to, string $subject, string $view, array $data = [], array $headers = [], array $attachments = []): bool {
    $body     = self::renderBody($view, $data);
    $headers  = self::prepareHeaders($headers);

    /** @var array<string,mixed> $payload */
    $payload = Filters::apply('fern:core:mail:payload', [
      'to'          => $to,
      'subject'     => $subject,
      'message'     => $body,
      'headers'     => $headers,
      'attachments' => $attachments,
    ]);

    /** @var string|array<int, string> $to */
    $to = $payload['to'] ?? $to;
    /** @var string|array<int, string> $payloadHeaders */
    $payloadHeaders = $payload['headers'] ?? $headers;
    /** @var string|array<int, string> $payloadAttachments */
    $payloadAttachments = $payload['attachments'] ?? $attachments;

    $sent = wp_mail(
      $to,
      Types::getSafeString($payload['subject'] ?? $subject),
      Types::getSafeString($payload['message'] ?? $body),
      $payloadHeaders,
      $payloadAttachments,
    );

    return $sent;
  }

  /**
   * Render the email body through the Views service and apply a filter.
   *
   * @param array<string,mixed> $data
   */
  private static function renderBody(string $view, array $data): string {
    $body = Views::render($view, $data);

    return Types::getSafeString(Filters::apply('fern:core:mail:body', $body, $view, $data));
  }

  /**
   * Ensure the headers array contains a proper HTML content-type.
   *
   * @param array<int,string> $headers
   * @return array<int,string>
   */
  private static function prepareHeaders(array $headers): array {
    $hasType = false;

    foreach ($headers as $header) {
      if (stripos($header, 'content-type:') === 0) {
        $hasType = true;
        break;
      }
    }

    if (!$hasType) {
      $headers[] = 'Content-Type: text/html; charset=UTF-8';
    }

    $filtered = Filters::apply('fern:core:mail:headers', $headers);
    /** @var array<int, string> $headers */
    $headers = is_array($filtered) ? $filtered : $headers;

    return $headers;
  }
}
