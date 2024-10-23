<?php

declare(strict_types=1);

namespace Fern\Core\Services\HTTP;

use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Actions\Action;
use Fern\Core\Utils\JSON;

/**
 * @phpstan-type HttpMethod 'GET'|'POST'|'PUT'|'DELETE'|'PATCH'|'HEAD'|'OPTIONS'
 * @phpstan-type HttpHeader string
 * @phpstan-type HttpHeaders array<string, HttpHeader>
 * @phpstan-type QueryParams array<string, string|array<string>>
 * @phpstan-type UploadedFile array{
 *   name: string,
 *   type: string,
 *   tmp_name: string,
 *   error: int,
 *   size: int
 * }
 * @phpstan-type UploadedFiles array<string, UploadedFile|array<UploadedFile>>
 * @phpstan-type Cookie string
 * @phpstan-type Cookies array<string, Cookie>
 * @phpstan-type RequestBody array<string, mixed>|string
 */
class Request extends Singleton {
  /**
   * @var int The ID of the current request (post, page, or term ID).
   */
  private int $id;

  /**
   * @var RequestBody The parsed body of the request.
   */
  private mixed $body;

  /**
   * @var string The content type of the request.
   */
  private string $contentType;

  /**
   * @var HttpHeaders The headers of the request.
   */
  private array $headers;

  /**
   * @var HttpMethod The HTTP method of the request (GET, POST, etc.).
   */
  private string $method;

  /**
   * @var string The requested URI.
   */
  private string $requestedUri;

  /**
   * @var string The user agent string of the request.
   */
  private string $userAgent;

  /**
   * @var UploadedFiles|null The uploaded files in the request.
   */
  private ?array $files;

  /**
   * @var QueryParams The query parameters of the request.
   */
  private array $query;

  /**
   * @var string The full URL of the request.
   */
  private string $url;

  /**
   * @var Cookies The cookies of the request.
   */
  private array $cookies;

  public function __construct() {
    $this->id = $this->getCurrentId();
    $this->contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    unset($headers['Cookie']);
    $this->headers = $headers;
    $this->files = null;
    $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $this->requestedUri = $_SERVER['REQUEST_URI'] ?? '';
    $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $this->cookies = $_COOKIE;
    $this->url = untrailingslashit(get_home_url()) . $this->requestedUri;
    $this->query = $this->parseUrlParams();

    $this->parseBody();
  }

  /**
   * Checks if the current request is an action request.
   */
  public function isAction(): bool {
    return isset($this->headers['X-Fern-Action']);
  }

  /**
   * Gets the uploaded files.
   *
   * @return UploadedFiles|null
   */
  public function getFiles(): ?array {
    return $this->files;
  }

  /**
   * Gets the current request TRUE ID
   */
  public function getCurrentId(): int {
    $queriedObject = get_queried_object();
    $id = get_the_ID();

    if (!is_null($queriedObject)) {
      $id = $queriedObject->ID ?? false;
    }

    if (!$id) {
      if (!is_null($queriedObject)) {
        $termId = $queriedObject->term_id ?? null;

        if (!is_null($termId)) {
          return apply_filters('fern:core:http:request:queried_object', $termId);
        }
      }

      return -1;
    }

    return (int) $id;
  }

  /**
   * Gets the action from the request.
   */
  public function getAction(): Action {
    return Action::getCurrent();
  }

  /**
   * Checks if the current request is a REST request,
   */
  public function isREST(): bool {
    return defined('REST_REQUEST') && REST_REQUEST;
  }

  /**
   * Checks if the current request is a CLI request,
   */
  public function isCLI(): bool {
    return defined('WP_CLI') && constant('WP_CLI');
  }

  /**
   * Checks if the current request is a auto save request,
   */
  public function isAutoSave(): bool {
    return defined('DOING_AUTOSAVE') && DOING_AUTOSAVE;
  }

  /**
   * Checks if the current request is a CRON request, (Wordpress CRON only)
   */
  public function isCRON(): bool {
    return wp_doing_cron();
  }

  /**
   * Checks if the current request is a XMLRPC request
   */
  public function isXMLRPC(): bool {
    return defined('XMLRPC_REQUEST') && XMLRPC_REQUEST;
  }

  /**
   * Retrieve the value associated with the given key from the request data.
   *
   * @param string $key The key to retrieve the value for
   *
   * @return mixed|null The value associated with the key, or null if the key is not found
   */
  public function get(string $key) {
    if (isset($_SERVER[$key])) {
      return $_SERVER[$key];
    }

    return null;
  }

  /**
   * Checks the country from which the request have been made.
   */
  public function getCountryFrom(): ?string {
    $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
    preg_match('/^[a-z]{2}-([A-Z]{2})/', $acceptLanguage, $matches);

    return $matches[1] ?? null;
  }

  /**
   * Add a URL parameter to the initial requests.
   *
   * @param string $name  The parameter name.
   * @param mixed  $value The parameter value.
   *
   * @return Request The current Request instance.
   */
  public function addUrlParam(string $name, mixed $value): Request {
    $this->query[$name] = $value;

    return $this;
  }

  /**
   * Remove a URL parameter from the initial requests.
   *
   * @param string $name The parameter name.
   *
   * @return Request The current Request instance.
   */
  public function removeUrlParam(string $name): Request {
    unset($this->query[$name]);

    return $this;
  }

  /**
   * Check if a URL parameter is set.
   *
   * @param string $name The parameter name.
   *
   * @return bool True if the param is set, false otherwise.
   */
  public function hasUrlParam(string $name): bool {
    return isset($this->query[$name]);
  }

  /**
   * Check if a URL parameter is **NOT** set.
   *
   * @param string $name The parameter name.
   *
   * @return bool True if the param is **NOT** set, false otherwise.
   */
  public function hasNotUrlParam(string $name): bool {
    return !$this->hasUrlParam($name);
  }

  /**
   * Retrieve the current Request object.
   *
   * @return Request The current request.
   */
  public static function getCurrent(): Request {
    $req = self::getInstance();

    return $req;
  }

  /**
   * Gets the expected response code for this request
   */
  public function getCode(): int {
    return is_404() ? 404 : 200;
  }

  /**
   * Gets the page ID associated with the request.
   *
   * @return int|null The wordpress page ID or NULL if the requests is handled by the Heracles Router in heracles/routes.php.
   */
  public function getId(): ?int {
    return $this->id;
  }

  /**
   * Gets the request payload.
   *
   * @return mixed The parsed payload.
   */
  public function getBody(): mixed {
    return $this->body;
  }

  /**
   * Gets a body parameter.
   *
   * @param string $key The parameter name.
   *
   * @return mixed|null The parameter value or null if the parameter is not set.
   */
  public function getBodyParam(string $key) {
    return $this->body[$key] ?? null;
  }

  /**
   * Gets the request method.
   *
   * @return string The method of the incomming request.
   */
  public function getMethod(): string {
    return $this->method;
  }

  /**
   * Checks if the current requets is a 404
   */
  public function is404(): bool {
    return is_404();
  }

  /**
   * Force the current Request to be a 404.
   */
  public function set404(): never {
    global $wp_query;
    $wp_query->query = $wp_query->queried_object = $wp_query->queried_object_id = null;
    $wp_query->set_404();
    status_header(404);
    header('Location:' . trailingslashit(get_home_url()) . '404-not-found');
    nocache_headers();
    exit;
  }

  /**
   * Gets the request content Type.
   */
  public function getContentType(): string {
    return $this->contentType;
  }

  /**
   * Gets the request requested Uri
   */
  public function getUri(): string {
    return $this->requestedUri;
  }

  /**
   * Check if the request has a GET method.
   *
   * @return bool True if the request has a GET method.
   */
  public function isGet(): bool {
    return $this->getMethod() === 'GET';
  }

  /**
   * Check if the request has a POST method.
   *
   * @return bool True if the request has a POST method.
   */
  public function isPost(): bool {
    return $this->getMethod() === 'POST';
  }

  /**
   * Check if the request has a PUT method.
   *
   * @return bool True if the request has a PUT method.
   */
  public function isPut(): bool {
    return $this->getMethod() === 'PUT';
  }

  /**
   * Check if the request has a DELETE method.
   *
   * @return bool True if the request has a DELETE method.
   */
  public function isDelete(): bool {
    return $this->getMethod() === 'DELETE';
  }

  /**
   * Gets the request User Agent.
   *
   * @return string The user agent data.
   */
  public function getUserAgent(): string {
    return $this->userAgent;
  }

  /**
   * Gets the request headers.
   *
   * @return HttpHeaders An array with all headers.
   */
  public function getHeaders(): array {
    return $this->headers;
  }

  /**
   * Gets a specific header value.
   *
   * @param string $header The desired header key.
   *
   * @return mixed|null The provided header value or null.
   */
  public function getHeader(string $header) {
    return $this->hasHeader($header)
      ? $this->headers[$header]
      : null;
  }

  /**
   * Checks if the current requests is a hidden request
   *
   * return bool
   */
  public function isSideRequest(): bool {
    return $this->isAjax() || $this->isRest() || $this->isCron() || $this->isCLI();
  }

  /**
   * Checks if a header is set.
   *
   * @param string $header The desired header key.
   *
   * @return bool True if the header is set, false otherwise.
   */
  public function hasHeader(string $header): bool {
    return isset($this->headers[$header]);
  }

  /**
   * Gets the URL parameters as an array.
   *
   * @return QueryParams The array of parameters in the URL or null.
   */
  public function getUrlParams(): ?array {
    return $this->query;
  }

  /**
   * Gets the requested URL.
   *
   * @return string The full URL.
   */
  public function getUrl(): string {
    return $this->url;
  }

  /**
   * Gets a specific URL parameter.
   *
   * @param string $key The param name.
   *
   * @return mixed The param value.
   */
  public function getUrlParam(string $key): mixed {
    return $this->query[$key] ?? null;
  }

  /**
   * Gets the Query String (the URL parameters)
   *
   * @return QueryParams An array of parsed querystring or null.
   */
  public function getQueryString(): ?array {
    return $this->query;
  }

  /**
   * Gets every cookies of the incomming request.
   *
   * @return Cookies An array of cookies.
   */
  public function getCookies(): array {
    return $this->cookies;
  }

  /**
   * Gets a specific cookie of the incomming request.
   *
   * @param string $cookie The cookie name.
   *
   * @return string|null The cookie value or null if it doesn't exists.
   */
  public function getCookie(string $cookie): ?string {
    return $this->cookies[$cookie] ?? null;
  }

  /**
   * Checks if a cookie is set.
   *
   * @param string $cookie The cookie name.
   *
   * @return bool True if the cookie is set, false otherwise.
   */
  public function hasCookie(string $cookie): bool {
    return isset($this->cookies[$cookie]);
  }

  /**
   * Checks if the current request is for the home page.
   *
   * @return bool True if the current request is for the home page, false otherwise.
   */
  public function isHome(): bool {
    return is_home() || is_front_page();
  }

  /**
   * Checks if the current request is for an author page.
   *
   * @return bool True if the current request is for an author page, false otherwise.
   */
  public function isAuthor(): bool {
    return is_author();
  }

  /**
   * Gets the current taxonomy if the request is for a term page.
   *
   * @return string|null The current taxonomy slug, or null if not a term page.
   */
  public function getTaxonomy(): ?string {
    if ($this->isTerm()) {
      $queriedObject = get_queried_object();

      return $queriedObject->taxonomy ?? null;
    }

    return null;
  }

  /**
   * Gets the current post type.
   *
   * @return string|null The current post type, or null if not in a post context.
   */
  public function getPostType(): ?string {
    if (is_singular()) {
      return get_post_type() ?: null;
    }

    if (is_post_type_archive()) {
      return get_query_var('post_type') ?: null;
    }

    return null;
  }

  /**
   * Convert the request to an array
   *
   * @return array<string, mixed>
   */
  public function toArray(): array {
    return [
      'id' => $this->id,
      'body' => $this->body,
      'contentType' => $this->contentType,
      'headers' => $this->headers,
      'code' => $this->getCode(),
      'method' => $this->method,
      'requestedUri' => $this->requestedUri,
      'userAgent' => $this->userAgent,
      'cookies' => $this->getCookies(),
      'query' => $this->query,
      'isREST' => $this->isREST(),
      'isCLI' => $this->isCLI(),
      'isAjax' => $this->isAjax(),
      'isTerm' => $this->isTerm(),
      'isPage' => $this->isPage(),
      'isPagination' => $this->isPagination(),
      'isAdmin' => $this->isAdmin(),
      'isSearch' => $this->isSearch(),
      'isArchive' => $this->isArchive(),
      'isPost' => $this->isPost(),
      'isAutoSave' => $this->isAutoSave(),
      'isHome' => $this->isHome(),
      'isAction' => $this->isAction(),
      'isFeed' => $this->isFeed(),
      'isAuthor' => $this->isAuthor(),
      'isAttachment' => $this->isAttachment(),
      'isCategory' => $this->isCategory(),
      'isTag' => $this->isTag(),
      'isTax' => $this->isTax(),
      'isDate' => $this->isDate(),
      'isPostTypeArchive' => $this->isPostTypeArchive(),
      'taxonomy' => $this->getTaxonomy(),
      'postType' => $this->getPostType(),
    ];
  }

  /**
   * Determine if the current request is a term.
   *
   * @return bool
   */
  public function isTerm() {
    $queriedObject = get_queried_object();

    $termId = null;

    if (!is_null($queriedObject)) {
      $termId = $queriedObject->term_id ?? null;
    }

    return !is_null($termId);
  }

  /**
   * Determine if the current request is a page.
   *
   * @return bool
   */
  public function isPage() {
    return is_page();
  }

  /**
   * Checks if the current request is for a feed.
   */
  public function isFeed(): bool {
    return is_feed();
  }

  /**
   * Determine if the current request is an AJAX request.
   */
  public function isAjax(): bool {
    return wp_doing_ajax();
  }

  /**
   * Determine if the current request is an attachment.
   */
  public function isAttachment(): bool {
    return is_attachment();
  }

  /**
   * Determine if the current request is a pagination.
   */
  public function isPagination(): bool {
    return is_paged();
  }

  /**
   * Determine if the current request is a tag.
   */
  public function isTag(): bool {
    return is_tag();
  }

  /**
   * Check if the current request is for a custom taxonomy archive.
   */
  public function isTax(): bool {
    return is_tax();
  }

  /**
   * Check if the current request is for a post type archive.
   */
  public function isPostTypeArchive(): bool {
    return is_post_type_archive();
  }

  /**
   * Check if the current request is for a date-based archive.
   */
  public function isDate(): bool {
    return is_date();
  }

  /**
   * Check if the current request is for a category archive.
   */
  public function isCategory(): bool {
    return is_category();
  }

  /**
   * Determine if the current request is an admin request.
   */
  public function isAdmin(): bool {
    return is_admin();
  }

  /**
   * Determine if the current request is a search request.
   */
  public function isSearch(): bool {
    return is_search();
  }

  /**
   * Check if the current request is for any type of archive page.
   */
  public function isArchive(): bool {
    return $this->isCategory()
      || $this->isTag()
      || $this->isAuthor()
      || $this->isDate()
      || $this->isTax()
      || $this->isPostTypeArchive();
  }

  /**
   * Sets the files
   *
   * @param UploadedFiles $files The files array.
   */
  private function setFiles(array $files): void {
    $this->files = $files;
  }

  /**
   * Parse the incomming request body and sets its content type.
   */
  private function parseBody(): void {
    if (empty($this->contentType)) {
      $this->body = '';

      return;
    }

    // handles FormData javascript Objects.
    if (strpos($this->contentType, 'multipart/form-data') !== false) {
      $this->setFiles($_FILES);
      $this->setBody($_POST);

      return;
    }

    $input = file_get_contents('php://input');

    if (!$input) {
      return;
    }

    if (strpos($this->contentType, 'application/json') !== false) {
      $this->body = JSON::decode($input, true) ?: $input;

      return;
    }

    if (strpos($this->contentType, 'application/x-www-form-urlencoded') !== false) {
      /** @phpstan-ignore-next-line */
      parse_str($input, $this->body);

      return;
    }

    $this->body = $input;
  }

  /**
   * Sets the request Url Parameters
   *
   * @return QueryParams The array of parsed URL parameters.
   */
  private function parseUrlParams(): array {
    return $_GET;
  }

  /**
   * Sets the request body.
   *
   * @param RequestBody $body The **parsed** body value.
   *
   * @return Request The current request instance.
   */
  private function setBody($body): Request {
    $this->body = $body;

    return $this;
  }
}
