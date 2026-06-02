<?php declare(strict_types=1);

use Brain\Monkey\Functions;
use Fern\Core\Services\HTTP\Request;

/**
 * Stubs every WordPress function the Request constructor touches so a real
 * instance can be built from the current superglobals. Predicate-specific
 * functions are left to the individual tests to stub.
 *
 * @param array<string, mixed> $overrides
 */
function stubRequestBoot(array $overrides = []): void {
  Functions\when('getallheaders')->justReturn($overrides['headers'] ?? []);
  Functions\when('get_home_url')->justReturn($overrides['home_url'] ?? 'https://example.test');
  Functions\when('untrailingslashit')->alias(static fn (string $value): string => rtrim($value, '/'));
  Functions\when('get_queried_object')->justReturn($overrides['queried_object'] ?? null);
  Functions\when('get_queried_object_id')->justReturn($overrides['queried_object_id'] ?? 0);
  Functions\when('get_the_ID')->justReturn($overrides['the_ID'] ?? false);
}

/**
 * Builds a fresh Request after stubbing its boot dependencies.
 *
 * @param array<string, mixed> $overrides
 */
function makeRequest(array $overrides = []): Request {
  stubRequestBoot($overrides);

  return Request::getCurrent();
}

/**
 * Request whose raw-input seam returns a controlled string, so the body-parsing
 * branches that read php://input can be exercised.
 */
final class InputControlledRequest extends Request {
  public static string|false $input = '';

  public static function build(string|false $input): self {
    self::$input = $input;
    Request::flushInstances();

    return self::getInstance();
  }

  protected function readInput(): string|false {
    return self::$input;
  }
}

describe('HTTP method resolution', function (): void {
  it('defaults to GET and reports the matching predicate', function (): void {
    $req = makeRequest();

    expect($req->getMethod())->toBe('GET')
      ->and($req->isGet())->toBeTrue()
      ->and($req->isPost())->toBeFalse()
      ->and($req->isPut())->toBeFalse()
      ->and($req->isDelete())->toBeFalse();
  });

  it('uppercases the incoming method', function (string $raw, string $expected): void {
    $_SERVER['REQUEST_METHOD'] = $raw;
    $req = makeRequest();

    expect($req->getMethod())->toBe($expected);
  })->with([
    'post' => ['post', 'POST'],
    'put' => ['put', 'PUT'],
    'delete' => ['delete', 'DELETE'],
  ]);

  it('resolves each method predicate', function (): void {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    expect(makeRequest()->isPost())->toBeTrue();

    Request::flushInstances();
    $_SERVER['REQUEST_METHOD'] = 'PUT';
    expect(makeRequest()->isPut())->toBeTrue();

    Request::flushInstances();
    $_SERVER['REQUEST_METHOD'] = 'DELETE';
    expect(makeRequest()->isDelete())->toBeTrue();
  });
});

describe('content type detection', function (): void {
  it('exposes an empty content type when none is set', function (): void {
    expect(makeRequest()->getContentType())->toBe('');
  });

  it('exposes the raw content type header', function (string $contentType): void {
    $_SERVER['CONTENT_TYPE'] = $contentType;

    expect(makeRequest()->getContentType())->toBe($contentType);
  })->with([
    'application/json',
    'multipart/form-data; boundary=xyz',
    'application/x-www-form-urlencoded',
    'text/plain',
  ]);
});

describe('body parsing', function (): void {
  it('uses an empty array when there is no content type', function (): void {
    expect(makeRequest()->getBody())->toBe([]);
  });

  it('reads $_POST and $_FILES for multipart form-data', function (): void {
    $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=xyz';
    $_POST = ['name' => 'Jane', 'email' => 'jane@x.test'];
    $_FILES = [
      'avatar' => [
        'name' => 'a.png',
        'type' => 'image/png',
        'tmp_name' => '/tmp/php123',
        'error' => 0,
        'size' => 10,
      ],
    ];

    $req = makeRequest();

    expect($req->getBody())->toBe(['name' => 'Jane', 'email' => 'jane@x.test'])
      ->and($req->getBodyParam('name'))->toBe('Jane')
      ->and($req->getBodyParam('missing'))->toBeNull()
      ->and($req->getFiles())->toBe($_FILES);
  });

  it('leaves files null when not a multipart request', function (): void {
    expect(makeRequest()->getFiles())->toBeNull();
  });

  it('uses an empty array when php://input is empty', function (string $contentType): void {
    $_SERVER['CONTENT_TYPE'] = $contentType;

    expect(makeRequest()->getBody())->toBe([]);
  })->with([
    'json' => ['application/json'],
    'urlencoded' => ['application/x-www-form-urlencoded'],
    'unknown' => ['text/plain'],
  ]);

  it('parses a JSON body when php://input is present', function (): void {
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    stubRequestBoot();

    expect(InputControlledRequest::build('{"name":"Jane","age":30}')->getBody())
      ->toBe(['name' => 'Jane', 'age' => 30]);
  });

  it('falls back to an empty array when the JSON body is not an object', function (): void {
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    stubRequestBoot();

    expect(InputControlledRequest::build('42')->getBody())->toBe([]);
  });

  it('parses an urlencoded body when php://input is present', function (): void {
    $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
    stubRequestBoot();

    expect(InputControlledRequest::build('a=1&b=two')->getBody())
      ->toBe(['a' => '1', 'b' => 'two']);
  });

  it('uses an empty array for an unknown content type with a present body', function (): void {
    $_SERVER['CONTENT_TYPE'] = 'text/plain';
    stubRequestBoot();

    expect(InputControlledRequest::build('raw text')->getBody())->toBe([]);
  });
});

describe('url and query parameters', function (): void {
  it('reads query params from $_GET', function (): void {
    $_GET = ['page' => '2', 'q' => 'shoes'];

    $req = makeRequest();

    expect($req->getUrlParams())->toBe(['page' => '2', 'q' => 'shoes'])
      ->and($req->getQueryString())->toBe(['page' => '2', 'q' => 'shoes'])
      ->and($req->getUrlParam('page'))->toBe('2')
      ->and($req->getUrlParam('missing'))->toBeNull()
      ->and($req->hasUrlParam('q'))->toBeTrue()
      ->and($req->hasUrlParam('missing'))->toBeFalse()
      ->and($req->hasNotUrlParam('missing'))->toBeTrue()
      ->and($req->hasNotUrlParam('q'))->toBeFalse();
  });

  it('adds and removes url params fluently', function (): void {
    $req = makeRequest();

    expect($req->addUrlParam('a', 1))->toBeInstanceOf(Request::class)
      ->and($req->getUrlParam('a'))->toBe(1)
      ->and($req->removeUrlParam('a'))->toBeInstanceOf(Request::class)
      ->and($req->hasUrlParam('a'))->toBeFalse();
  });

  it('builds the full url from home url and request uri', function (): void {
    $_SERVER['REQUEST_URI'] = '/blog/post';

    $req = makeRequest(['home_url' => 'https://example.test/']);

    expect($req->getUrl())->toBe('https://example.test/blog/post')
      ->and($req->getUri())->toBe('/blog/post');
  });
});

describe('headers', function (): void {
  it('exposes headers but strips the cookie header', function (): void {
    $req = makeRequest(['headers' => [
      'Cookie' => 'session=secret',
      'X-Custom' => 'value',
    ]]);

    expect($req->getHeaders())->toBe(['X-Custom' => 'value'])
      ->and($req->hasHeader('X-Custom'))->toBeTrue()
      ->and($req->hasHeader('Cookie'))->toBeFalse()
      ->and($req->getHeader('X-Custom'))->toBe('value')
      ->and($req->getHeader('missing'))->toBeNull();
  });

  it('detects an action request via the X-Fern-Action header', function (): void {
    expect(makeRequest(['headers' => ['X-Fern-Action' => 'doThing']])->isAction())->toBeTrue();

    Request::flushInstances();

    expect(makeRequest(['headers' => []])->isAction())->toBeFalse();
  });
});

describe('cookies', function (): void {
  it('reads cookies from $_COOKIE', function (): void {
    $_COOKIE = ['theme' => 'dark'];

    $req = makeRequest();

    expect($req->getCookies())->toBe(['theme' => 'dark'])
      ->and($req->getCookie('theme'))->toBe('dark')
      ->and($req->getCookie('missing'))->toBeNull()
      ->and($req->hasCookie('theme'))->toBeTrue()
      ->and($req->hasCookie('missing'))->toBeFalse();
  });
});

describe('server value and user agent accessors', function (): void {
  it('reads the user agent', function (): void {
    $_SERVER['HTTP_USER_AGENT'] = 'TestAgent/1.0';

    expect(makeRequest()->getUserAgent())->toBe('TestAgent/1.0');
  });

  it('returns server values via get', function (): void {
    $_SERVER['HTTP_X_THING'] = 'thing';

    $req = makeRequest();

    expect($req->get('HTTP_X_THING'))->toBe('thing')
      ->and($req->get('HTTP_MISSING_KEY'))->toBeNull();

    unset($_SERVER['HTTP_X_THING']);
  });

  it('extracts the country from the accept-language header', function (): void {
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr-FR,fr;q=0.9';

    expect(makeRequest()->getCountryFrom())->toBe('FR');
  });

  it('returns null when the accept-language header has no country', function (): void {
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en;q=0.9';

    expect(makeRequest()->getCountryFrom())->toBeNull();
  });
});

describe('current id resolution', function (): void {
  it('uses get_the_ID and applies the filter', function (): void {
    Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value): mixed => $value);

    $req = makeRequest(['the_ID' => 42]);

    expect($req->getId())->toBe(42);
  });

  it('lets the filter override the resolved id', function (): void {
    Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value): int => 999);

    $req = makeRequest(['the_ID' => 42]);

    expect($req->getId())->toBe(999);
  });

  it('falls back to the queried object ID when get_the_ID is unavailable', function (): void {
    Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value): mixed => $value);

    $req = makeRequest([
      'the_ID' => false,
      'queried_object' => new WP_Post(['ID' => 7]),
    ]);

    expect($req->getId())->toBe(7);
  });

  it('returns -1 when nothing resolves an id', function (): void {
    Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value): mixed => $value);

    $req = makeRequest(['the_ID' => 0, 'queried_object' => null]);

    expect($req->getId())->toBe(-1);
  });

  it('uses get_queried_object_id for term requests', function (): void {
    Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value): mixed => $value);

    $req = makeRequest([
      'queried_object' => new WP_Term(['term_id' => 5, 'taxonomy' => 'category']),
      'queried_object_id' => 5,
    ]);

    expect($req->getId())->toBe(5);
  });

  it('exposes getCurrentId directly', function (): void {
    Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value): mixed => $value);

    $req = makeRequest(['the_ID' => 13]);

    expect($req->getCurrentId())->toBe(13);
  });
});

describe('queried object dependent predicates', function (): void {
  it('detects term requests', function (): void {
    $term = makeRequest(['queried_object' => new WP_Term(['term_id' => 3])]);
    expect($term->isTerm())->toBeTrue();

    Request::flushInstances();
    $post = makeRequest(['queried_object' => new WP_Post(['ID' => 1])]);
    expect($post->isTerm())->toBeFalse();

    Request::flushInstances();
    expect(makeRequest(['queried_object' => null])->isTerm())->toBeFalse();
  });

  it('resolves the taxonomy of a term request and caches it', function (): void {
    $req = makeRequest([
      'queried_object' => new WP_Term(['term_id' => 3, 'taxonomy' => 'product_cat']),
    ]);

    expect($req->getTaxonomy())->toBe('product_cat')
      ->and($req->getTaxonomy())->toBe('product_cat');
  });

  it('returns null taxonomy for non-term requests', function (): void {
    $req = makeRequest(['queried_object' => new WP_Post(['ID' => 1])]);

    expect($req->getTaxonomy())->toBeNull();
  });
});

describe('post type resolution', function (): void {
  it('reads the post type for singular requests', function (): void {
    Functions\when('is_singular')->justReturn(true);
    Functions\when('get_post_type')->justReturn('product');

    $req = makeRequest();

    expect($req->getPostType())->toBe('product')
      ->and($req->getPostType())->toBe('product');
  });

  it('returns null when get_post_type is false on a singular request', function (): void {
    Functions\when('is_singular')->justReturn(true);
    Functions\when('get_post_type')->justReturn(false);

    expect(makeRequest()->getPostType())->toBeNull();
  });

  it('reads the post type for a post type archive', function (): void {
    Functions\when('is_singular')->justReturn(false);
    Functions\when('is_post_type_archive')->justReturn(true);
    Functions\when('get_query_var')->justReturn('product');

    expect(makeRequest()->getPostType())->toBe('product');
  });

  it('returns null when the archive query var is empty', function (): void {
    Functions\when('is_singular')->justReturn(false);
    Functions\when('is_post_type_archive')->justReturn(true);
    Functions\when('get_query_var')->justReturn('');

    expect(makeRequest()->getPostType())->toBeNull();
  });

  it('returns page for a page request', function (): void {
    Functions\when('is_singular')->justReturn(false);
    Functions\when('is_post_type_archive')->justReturn(false);
    Functions\when('is_page')->justReturn(true);

    expect(makeRequest()->getPostType())->toBe('page');
  });

  it('returns null when no post context matches', function (): void {
    Functions\when('is_singular')->justReturn(false);
    Functions\when('is_post_type_archive')->justReturn(false);
    Functions\when('is_page')->justReturn(false);

    expect(makeRequest()->getPostType())->toBeNull();
  });
});

describe('wordpress conditional predicates', function (): void {
  it('delegates simple boolean predicates', function (string $method, string $wpFn): void {
    Functions\when($wpFn)->justReturn(true);
    expect(makeRequest()->{$method}())->toBeTrue();

    Request::flushInstances();
    Functions\when($wpFn)->justReturn(false);
    expect(makeRequest()->{$method}())->toBeFalse();
  })->with([
    'isPage' => ['isPage', 'is_page'],
    'isFeed' => ['isFeed', 'is_feed'],
    'isAjax' => ['isAjax', 'wp_doing_ajax'],
    'isAttachment' => ['isAttachment', 'is_attachment'],
    'isPagination' => ['isPagination', 'is_paged'],
    'isTag' => ['isTag', 'is_tag'],
    'isTax' => ['isTax', 'is_tax'],
    'isPostTypeArchive' => ['isPostTypeArchive', 'is_post_type_archive'],
    'isDate' => ['isDate', 'is_date'],
    'isCategory' => ['isCategory', 'is_category'],
    'isAdmin' => ['isAdmin', 'is_admin'],
    'isSearch' => ['isSearch', 'is_search'],
    'isAuthor' => ['isAuthor', 'is_author'],
    'isBlog' => ['isBlog', 'is_home'],
    'isCRON' => ['isCRON', 'wp_doing_cron'],
    'is404' => ['is404', 'is_404'],
  ]);

  it('reports the home page when either home or front page matches', function (): void {
    Functions\when('is_home')->justReturn(false);
    Functions\when('is_front_page')->justReturn(true);
    expect(makeRequest()->isHome())->toBeTrue();

    Request::flushInstances();
    Functions\when('is_home')->justReturn(false);
    Functions\when('is_front_page')->justReturn(false);
    expect(makeRequest()->isHome())->toBeFalse();
  });

  it('reflects the 404 state in getCode', function (): void {
    Functions\when('is_404')->justReturn(true);
    expect(makeRequest()->getCode())->toBe(404);

    Request::flushInstances();
    Functions\when('is_404')->justReturn(false);
    expect(makeRequest()->getCode())->toBe(200);
  });
});

describe('constant backed predicates', function (): void {
  it('reports REST, CLI, autosave and xmlrpc as false when constants are undefined', function (): void {
    $req = makeRequest();

    expect($req->isREST())->toBeFalse()
      ->and($req->isCLI())->toBeFalse()
      ->and($req->isAutoSave())->toBeFalse()
      ->and($req->isXMLRPC())->toBeFalse();
  });
});

describe('side request detection', function (): void {
  it('is a side request when ajax is active', function (): void {
    Functions\when('wp_doing_ajax')->justReturn(true);
    Functions\when('wp_doing_cron')->justReturn(false);

    expect(makeRequest()->isSideRequest())->toBeTrue();
  });

  it('is a side request when cron is active', function (): void {
    Functions\when('wp_doing_ajax')->justReturn(false);
    Functions\when('wp_doing_cron')->justReturn(true);

    expect(makeRequest()->isSideRequest())->toBeTrue();
  });

  it('is not a side request for a plain front-end request', function (): void {
    Functions\when('wp_doing_ajax')->justReturn(false);
    Functions\when('wp_doing_cron')->justReturn(false);

    expect(makeRequest()->isSideRequest())->toBeFalse();
  });
});

describe('archive detection with caching', function (): void {
  it('is an archive when any archive predicate matches', function (): void {
    Functions\when('is_category')->justReturn(true);

    $req = makeRequest();

    expect($req->isArchive())->toBeTrue()
      ->and($req->isArchive())->toBeTrue();
  });

  it('is not an archive when no archive predicate matches', function (): void {
    foreach ([
      'is_category', 'is_tag', 'is_author', 'is_date',
      'is_home', 'is_tax', 'is_post_type_archive',
    ] as $fn) {
      Functions\when($fn)->justReturn(false);
    }

    expect(makeRequest()->isArchive())->toBeFalse();
  });
});

describe('sitemap detection', function (): void {
  it('detects a sitemap url', function (): void {
    $_SERVER['REQUEST_URI'] = '/sitemap.xml';

    expect(makeRequest(['home_url' => 'https://example.test'])->isSitemap())->toBeTrue();
  });

  it('rejects a non sitemap url', function (): void {
    $_SERVER['REQUEST_URI'] = '/blog/post';

    expect(makeRequest(['home_url' => 'https://example.test'])->isSitemap())->toBeFalse();
  });

  it('rejects a sitemap path that is not xml', function (): void {
    $_SERVER['REQUEST_URI'] = '/sitemap.html';

    expect(makeRequest(['home_url' => 'https://example.test'])->isSitemap())->toBeFalse();
  });
});

describe('getAction delegation', function (): void {
  it('returns the current action', function (): void {
    $req = makeRequest();

    expect($req->getAction())->toBeInstanceOf(\Fern\Core\Services\Actions\Action::class);
  });
});

describe('toArray', function (): void {
  it('serializes the request state', function (): void {
    Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value): mixed => $value);
    Functions\when('is_404')->justReturn(false);
    Functions\when('wp_doing_ajax')->justReturn(false);
    Functions\when('wp_doing_cron')->justReturn(false);
    Functions\when('is_page')->justReturn(true);
    Functions\when('is_paged')->justReturn(false);
    Functions\when('is_admin')->justReturn(false);
    Functions\when('is_search')->justReturn(false);
    Functions\when('is_feed')->justReturn(false);
    Functions\when('is_attachment')->justReturn(false);
    Functions\when('is_category')->justReturn(false);
    Functions\when('is_tag')->justReturn(false);
    Functions\when('is_tax')->justReturn(false);
    Functions\when('is_date')->justReturn(false);
    Functions\when('is_post_type_archive')->justReturn(false);
    Functions\when('is_author')->justReturn(false);
    Functions\when('is_home')->justReturn(false);
    Functions\when('is_front_page')->justReturn(false);
    Functions\when('is_singular')->justReturn(false);

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=z';
    $_POST = ['field' => 'value'];

    $req = makeRequest([
      'the_ID' => 21,
      'home_url' => 'https://example.test',
    ]);

    $array = $req->toArray();

    expect($array)->toBeArray()
      ->and($array['id'])->toBe(21)
      ->and($array['method'])->toBe('POST')
      ->and($array['contentType'])->toBe('multipart/form-data; boundary=z')
      ->and($array['body'])->toBe(['field' => 'value'])
      ->and($array['code'])->toBe(200)
      ->and($array['isPage'])->toBeTrue()
      ->and($array['isPost'])->toBeTrue()
      ->and($array['isAction'])->toBeFalse()
      ->and($array)->toHaveKeys([
        'id', 'body', 'contentType', 'headers', 'code', 'method',
        'requestedUri', 'userAgent', 'cookies', 'query', 'isREST',
        'isCLI', 'isAjax', 'isTerm', 'isPage', 'isPagination', 'isAdmin',
        'isSearch', 'isArchive', 'isPost', 'isAutoSave', 'isHome',
        'isAction', 'isFeed', 'isAuthor', 'isAttachment', 'isCategory',
        'isTag', 'isTax', 'isDate', 'isPostTypeArchive', 'taxonomy', 'postType',
      ]);
  });
});

describe('getCurrent / getInstance memoization', function (): void {
  it('returns the same instance on repeated calls', function (): void {
    stubRequestBoot();

    expect(Request::getCurrent())->toBe(Request::getCurrent())
      ->and(Request::getInstance())->toBe(Request::getCurrent());
  });
});
