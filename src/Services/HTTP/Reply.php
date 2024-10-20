<?php

declare(strict_types=1);

namespace Fern\Core\Services\HTTP;

use Fern\Core\Errors\ReplyParsingError;
use Fern\Core\Fern;
use Fern\Core\Wordpress\Events;
use Fern\Core\Wordpress\Filters;

class Reply {
  private string $contentType;
  private $body;
  private $headers;
  private $trailers;
  private int $status;
  private bool $hijacked;

  public function __construct($status = 200, $body = '', $contentType = null, $headers = []) {
    $this->contentType = $contentType ?? 'text/html';

    if (is_array($body) && $contentType === null) {
      $this->contentType = $contentType ?? 'application/json';
    } elseif ($contentType === null && is_string($body)) {
      $this->contentType = 'text/html';
    }

    $this->headers = $headers;
    $this->trailers = [];
    $this->body = $body;
    $this->status = $status;
    $this->hijacked = false;
  }

  /**
   * Redirect the request.
   *
   * @param string $to  The new location (URL) to redirect the request to.
   *
   * @return never|void
   */
  public function redirect(string $to) {
    if ($this->hijacked) {
      return;
    }

    $this->applyHeaders();
    wp_safe_redirect($to, $this->status);
    exit;
  }

  /**
   * Gets the reply body.
   *
   * @return mixed  The body content.
   */
  public function getBody(): mixed {
    return $this->body;
  }

  /**
   * Sets the reply body.
   *
   * @param mixed  $body  The body content.
   *
   * @return Reply  The current Reply instance.
   */
  public function setBody($body): Reply {
    $this->body = $body;
    return $this;
  }

  /**
   * Sets the reply status code.
   *
   * @param int $code  The status code to set.
   *
   * @return Reply  The current Reply instance.
   */
  public function code(int $code): Reply {
    $this->status = $code;
    return $this;
  }

  /**
   * Alias of Reply::code()?.
   *
   * @param int $code  The status code to set.
   *
   * @return Reply  The current Reply instance.
   */
  public function statusCode(int $code): Reply {
    return $this->code($code);
  }

  /**
   * Sets the Reply Content Type
   *
   * @param int $type  The HTTP content type. (Default `text/html`).
   *
   * @return Reply  The current Reply instance.
   */
  public function contentType($type): Reply {
    $this->contentType = $type;
    return $this;
  }

  /**
   * Gets the Reply Content Type
   *
   * @return string  The current Reply content type setting.
   */
  public function getContentType(): string {
    return $this->contentType;
  }

  /**
   * Halt the execution of the normal request lifecycle and prevent the Reply to be sent.
   *
   * @return Reply  The current Reply instance.
   */
  public function hijack(): Reply {
    $this->hijacked = true;
    return $this;
  }

  /**
   * Reset the Hijacking.
   *
   * @return Reply  The current Reply instance.
   */
  public function resetHijack(): Reply {
    $this->hijacked = false;
    return $this;
  }

  /**
   * Sets the Reply Content Type
   *
   * @param int $type  The HTTP content type. (Default `text/html`).
   *
   * @return Reply  The current Reply instance.
   */
  public function type($type): Reply {
    return $this->contentType($type);
  }

  /**
   * Sets the Reply status code.
   *
   * @param int $code  The HTTP status code. (Default 200).
   *
   * @return Reply  The current Reply instance.
   */
  public function status(int $code): Reply {
    return $this->code($code);
  }

  /**
   * Gets the list of headers.
   *
   * @return array  An array of headers.
   */
  public function getHeaders(): array {
    return $this->headers;
  }

  /**
   * Gets a specific header value.
   *
   * @param string $key  The header name.
   *
   * @return mixed|null  The header value or null.
   */
  public function getHeader(string $key): mixed {
    return $this->headers[$key] ?? null;
  }

  /**
   * Sets a header.
   *
   * @param string $key    The header name.
   * @param mixed  $value  The header value.
   *
   * @return Reply  The current Reply instance.
   */
  public function setHeader(string $key, mixed $value): Reply {
    $this->headers[$key] = $value;
    return $this;
  }

  /**
   * Remove a header if it exists.
   *
   * @param string $key    The header name.
   *
   * @return Reply  The current Reply instance.
   */
  public function removeHeader(string $key): Reply {
    if ($this->hasHeader($key)) {
      unset($this->headers[$key]);
    }

    return $this;
  }

  /**
   * Remove every headers.
   *
   * @return Reply  The current Reply instance.
   */
  public function resetHeader(): Reply {
    $this->headers = [];
    return $this;
  }

  /**
   * Check if a specific header exists.
   *
   * @param string $key  The header name.
   *
   * @return bool  True if it exists, false otherwise.
   */
  public function hasHeader(string $key): bool {
    return isset($this->headers[$key]);
  }

  /**
   * Add a Trailer in the Reply.
   *
   * (If there is trailers, the `Transfer-Encoding: chunked` header will be added.)
   * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Trailer
   *
   * @param string $headerName  The name of the header for the Trailer to point to.
   *
   * @return Reply  The current Reply.
   */
  public function addTrailer(string $headerName, mixed $value): Reply {
    $this->trailers[$headerName] = $value;
    return $this;
  }

  /**
   * Remove a Trailer from the Reply
   * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Trailer
   *
   * @param string $headerName  The name of the header for the Trailer to point to.
   *
   * @return Reply  The current Reply.
   */
  public function removeTrailer(string $headerName): Reply {
    unset($this->trailers[$headerName]);
    return $this;
  }

  /**
   * Gets every registered Trailers for the current reply.
   *
   * @return array   An array of Trailers value.
   */
  public function getTrailers(): array {
    return $this->trailers;
  }

  /**
   * Checks if the provided header is declared as being a trailer.
   *
   * @return bool  True if it is a trailer, false otherwise.
   */
  public function hasTrailer($name): bool {
    return isset($this->trailers[$name]);
  }

  /**
   * Remove every trailers
   *
   * @return Reply  The current Reply.
   */
  public function resetTrailers(): Reply {
    $this->trailers = [];
    return $this;
  }
  /**
   * Apply the headers of the current Reply.
   *
   * @return void
   */
  private function applyHeaders(): void {
    // Apply regular headers first
    foreach ($this->headers as $name => $value) {
      header("{$name}: {$value}");
    }

    // Handle chunked transfer encoding if trailers are present
    if (!empty($this->trailers)) {
      $this->removeHeader('Transfer-Encoding');
      header('Transfer-Encoding: chunked');

      // Content-Encoding should not be set to 'chunked'
      // Remove this line: $this->removeHeader('Content-Encoding');
      // Remove this line: header('Content-Encoding: chunked');

      // Declare trailers
      foreach ($this->trailers as $name => $value) {
        header("Trailer: {$name}");
      }
    }
  }

  /**
   * Apply the Trailers of the current Reply.
   *
   * @return void
   */
  private function applyTrailers(): void {
    if (!empty($this->trailers)) {
      // Ensure we're using chunked transfer encoding
      if (!$this->hasHeader('Transfer-Encoding') || $this->getHeader('Transfer-Encoding') !== 'chunked') {
        throw new \RuntimeException('Trailers can only be sent with chunked Transfer-Encoding');
      }

      // Send the last chunk to indicate the end of the body
      echo "0\r\n";

      // Send trailers
      foreach ($this->trailers as $name => $value) {
        echo "{$name}: {$value}\r\n";
      }

      // End of trailers
      echo "\r\n";
      flush();
    }
  }

  /**
   * Sends the reply.
   *
   * @param mixed $data Optional data to send
   * @return never|void
   */
  public function send($data = null) {
    if ($this->hijacked) {
      return;
    }

    if ($data !== null) {
      $this->body = $data;
    }

    if ($this->body === '' && $this->contentType === 'application/json') {
      $this->body = [];
    }


    $this->applyHeaders();

    if (!headers_sent()) {
      header('Content-Type: ' . $this->contentType . '; charset=' . get_option('blog_charset'));
      http_response_code($this->status);
    }

    $body = $this->getBody();

    // Handle JSON content
    if ($this->contentType === 'application/json') {
      if (!is_array($body)) {
        throw new ReplyParsingError('You cannot send a reply marked as `application/json` with a non-array formatted body. Your body is of type: `' . gettype($body) . '`.');
      }

      /**
       * Filters the body of the reply before it is sent.
       *
       * @param mixed $body  The body content.
       * @param Reply $reply The current Reply instance.
       *
       * @return mixed  The filtered body content.
       */
      $body = Filters::apply('fern:core:reply:will_be_send', $body, $this);
      $content = wp_json_encode($body);
    } else {

      /**
       * Filters the body of the reply before it is sent.
       *
       * @param mixed $body  The body content.
       * @param Reply $reply The current Reply instance.
       *
       * @return mixed  The filtered body content.
       */
      $content = Filters::apply('fern:core:reply:will_be_send', $body, $this);
    }


    $isChunked = $this->hasHeader('Transfer-Encoding') && $this->getHeader('Transfer-Encoding') === 'chunked';

    if (Fern::isDev()) {
      wp_head();
      echo '<!-- wp_head is only available in dev mode -->';
    }

    if ($isChunked) {
      // Send content in chunks
      $chunkSize = 4096;
      for ($i = 0; $i < strlen($content); $i += $chunkSize) {
        $chunk = substr($content, $i, $chunkSize);
        echo dechex(strlen($chunk)) . "\r\n" . $chunk . "\r\n";
        flush();
      }
    } else {
      echo $content;
    }

    if (Fern::isDev()) {
      wp_footer();
      echo '<!-- wp_footer is only available in dev mode -->';
    }

    // Apply trailers if using chunked transfer
    if ($isChunked) {
      $this->applyTrailers();
    }

    /**
     * Fires when the reply has been sent, just before exiting.
     *
     * @param Reply $reply  The current Reply instance.
     */
    Events::trigger('fern:core:reply:has_been_sent', $this);
    Events::trigger('qm/stop', 'fern:resolve_routes');
    exit;
  }
}
