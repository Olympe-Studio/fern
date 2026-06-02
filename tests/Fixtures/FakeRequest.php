<?php declare(strict_types=1);

namespace Tests\Fixtures;

use Fern\Core\Services\HTTP\Request;

/**
 * A minimal Request double that only exposes the body and content type, without
 * running the real constructor (which parses superglobals). Used where code
 * depends on the concrete Request type but only reads these two values.
 */
class FakeRequest extends Request {
  /**
   * @param array<string, mixed> $body
   */
  public function __construct(
    private array $body = [],
    private string $contentType = 'application/json',
  ) {}

  public function getBody(): mixed {
    return $this->body;
  }

  public function getContentType(): string {
    return $this->contentType;
  }
}
