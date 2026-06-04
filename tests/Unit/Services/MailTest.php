<?php declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Fern\Core\Config;
use Fern\Core\Services\Mail\Mail;
use Fern\Core\Services\Views\Views;
use Tests\Fixtures\FakeRenderingEngine;

function resetMailViewsEngine(FakeRenderingEngine $engine): void {
  (new ReflectionProperty(Views::class, 'engine'))->setValue(null, $engine);
}

beforeEach(function (): void {
  $this->engine = new FakeRenderingEngine('<p>body</p>');
  Config::getInstance()->setConfig(['rendering_engine' => $this->engine]);
  resetMailViewsEngine($this->engine);
});

afterEach(function (): void {
  (new ReflectionProperty(Views::class, 'engine'))->setValue(null, null);
});

describe('send', function (): void {
  it('renders the view, appends an HTML content-type header and calls wp_mail', function (): void {
    Functions\expect('wp_mail')
      ->once()
      ->with(
        'to@acme.test',
        'Hello',
        '<p>body</p>',
        ['Content-Type: text/html; charset=UTF-8'],
        [],
      )
      ->andReturn(true);

    $sent = Mail::send('to@acme.test', 'Hello', 'emails/welcome', ['name' => 'A']);

    expect($sent)->toBeTrue()
      ->and($this->engine->lastTemplate)->toBe('emails/welcome')
      ->and($this->engine->lastData)->toHaveKey('name', 'A');
  });

  it('returns false when wp_mail reports a delivery failure', function (): void {
    Functions\expect('wp_mail')->once()->andReturn(false);

    expect(Mail::send('to@acme.test', 'Hi', 'emails/welcome'))->toBeFalse();
  });

  it('does not add a second content-type header when one is already present', function (): void {
    Functions\expect('wp_mail')
      ->once()
      ->with(
        'to@acme.test',
        'Hi',
        '<p>body</p>',
        ['content-type: text/plain'],
        [],
      )
      ->andReturn(true);

    Mail::send('to@acme.test', 'Hi', 'emails/welcome', [], ['content-type: text/plain']);
  });

  it('forwards multiple recipients and attachments to wp_mail', function (): void {
    Functions\expect('wp_mail')
      ->once()
      ->with(
        ['a@acme.test', 'b@acme.test'],
        'Bulk',
        '<p>body</p>',
        Mockery::type('array'),
        ['/tmp/file.pdf'],
      )
      ->andReturn(true);

    Mail::send(['a@acme.test', 'b@acme.test'], 'Bulk', 'emails/welcome', [], [], ['/tmp/file.pdf']);
  });

  it('applies the body filter to the rendered output', function (): void {
    Filters\expectApplied('fern:core:mail:body')
      ->once()
      ->andReturn('<p>filtered body</p>');

    Functions\expect('wp_mail')
      ->once()
      ->with('to@acme.test', 'Hi', '<p>filtered body</p>', Mockery::type('array'), [])
      ->andReturn(true);

    Mail::send('to@acme.test', 'Hi', 'emails/welcome');
  });

  it('applies the headers filter to the prepared headers', function (): void {
    Filters\expectApplied('fern:core:mail:headers')
      ->once()
      ->andReturn(['X-Custom: 1']);

    Functions\expect('wp_mail')
      ->once()
      ->with('to@acme.test', 'Hi', '<p>body</p>', ['X-Custom: 1'], [])
      ->andReturn(true);

    Mail::send('to@acme.test', 'Hi', 'emails/welcome');
  });

  it('keeps the original headers when the headers filter returns a non-array', function (): void {
    Filters\expectApplied('fern:core:mail:headers')
      ->once()
      ->andReturn('not-an-array');

    Functions\expect('wp_mail')
      ->once()
      ->with('to@acme.test', 'Hi', '<p>body</p>', ['Content-Type: text/html; charset=UTF-8'], [])
      ->andReturn(true);

    Mail::send('to@acme.test', 'Hi', 'emails/welcome');
  });

  it('lets the payload filter override the recipient, subject, headers and attachments', function (): void {
    Filters\expectApplied('fern:core:mail:payload')
      ->once()
      ->andReturn([
        'to' => 'override@acme.test',
        'subject' => 'Overridden',
        'message' => '<p>overridden</p>',
        'headers' => ['X-Payload: 1'],
        'attachments' => ['/tmp/p.pdf'],
      ]);

    Functions\expect('wp_mail')
      ->once()
      ->with('override@acme.test', 'Overridden', '<p>overridden</p>', ['X-Payload: 1'], ['/tmp/p.pdf'])
      ->andReturn(true);

    Mail::send('to@acme.test', 'Original', 'emails/welcome');
  });
});
