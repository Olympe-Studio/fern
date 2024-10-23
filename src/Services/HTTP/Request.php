<?php

declare(strict_types=1);

namespace Fern\Core\Services\HTTP;

use Fern\Core\Factory\Singleton;
use Fern\Core\Services\Actions\Action;
use Fern\Core\Utils\JSON;

class Request extends Singleton {
  /**
   * @var int|null The ID of the current request (post, page, or term ID).
   */
  private ?int $id;

  /**
   * @var mixed The parsed body of the request.
   */
  private $body;

  /**
   * @var string The content type of the request.
   */
  private string $contentType;

  /**
   * @var array The headers of the request.
   */
  private $headers;

  /**
   * @var string The HTTP method of the request (GET, POST, etc.).
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
   * @var array The uploaded files in the request.
   */
  private $files;

  /**
   * @var array The query parameters of the request.
   */
  private $query;

  /**
   * @var string The full URL of the request.
   */
  private string $url;

  public function __construct() {
    $this->id = $this->getCurrentId();
    $this->body = '';
    $this->contentType = '';
    $this->parseBody();
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    unset($headers["Cookie"]);
    $this->headers = $headers;
    $this->files = $_FILES;
    $this->method = $_SERVER["REQUEST_METHOD"];
    $this->requestedUri = $_SERVER['REQUEST_URI'];
    $this->userAgent = $_SERVER['HTTP_USER_AGENT'];
    $this->url = untrailingslashit(get_home_url()) . $this->requestedUri;
    $this->query = $this->parseUrlParams();
  }

  /**
   * Checks if the current request is an action request.
   *
   * @return bool
   */
  public function isAction(): bool {
    return isset($this->headers['X-Fern-Action']);
  }

  /**
   * Sets the files
   *
   * @return void
   */
  public function setFiles($files): void {
    $this->files = $files;
  }

  /**
   * Gets the current request TRUE ID
   *
   * @return int|null
   */
  public function getCurrentId() {
    $queriedObject = get_queried_object();
    $id = get_the_ID();

    if (!is_null($queriedObject) && is_object($queriedObject)) {
      $id = $queriedObject->ID ?? false;
    }
    $termId = null;

    if (!$id) {
      if (!is_null($queriedObject)) {
        $termId = $queriedObject->term_id ?? null;
        $termId = is_null($termId) ? null :  apply_filters('fern:core:http:request:queried_object', $termId);
      }
    }

    if (!$id) {
      $id = -1;
    }

    return (int) is_null($termId) ? $id : $termId;
  }

  /**
   * Gets the action from the request.
   *
   * @return Action
   */
  public function getAction(): Action {
    return Action::getCurrent();
  }

  /**
   * Checks if the current request is a REST request,
   *
   * @return bool;
   */
  public function isREST(): bool {
    return defined('REST_REQUEST') && REST_REQUEST;
  }

  /**
   * Checks if the current request is a CLI request,
   *
   * @return bool;
   */
  public function isCLI(): bool {
    return defined('WP_CLI') && constant('WP_CLI');
  }

  /**
   * Checks if the current request is a auto save request,
   *
   * @return bool;
   */
  public function isAutoSave(): bool {
    return defined('DOING_AUTOSAVE') && DOING_AUTOSAVE;
  }

  /**
   * Checks if the current request is a CRON request, (Wordpress CRON only)
   *
   * @return bool;
   */
  public function isCRON(): bool {
    return wp_doing_cron();
  }

  /**
   * Checks if the current request is a XMLRPC request
   *
   * @return bool;
   */
  public function isXMLRPC(): bool {
    return defined('XMLRPC_REQUEST') && XMLRPC_REQUEST;
  }

  /**
   * Retrieve the value associated with the given key from the request data.
   *
   * @param string $key The key to retrieve the value for
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
   *
   * @return string|null
   */
  public function getCountryFrom(): ?string {
    $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
    preg_match('/^[a-z]{2}-([A-Z]{2})/', $acceptLanguage, $matches);
    return $matches[1] ?? null;
  }

  /**
   * Parse the incomming request body and sets its content type.
   *
   * @return void
   */
  private function parseBody(): void {
    if (!isset($_SERVER["CONTENT_TYPE"])) {
      return;
    }

    // handles FormData javascript Objects.
    if (isset($_SERVER["CONTENT_TYPE"]) && strpos($_SERVER["CONTENT_TYPE"], 'multipart/form-data') !== false) {
      $this->setFiles($_FILES);
      $body = [
        ...$_POST,
        ...$this->files
      ];

      $this->setContentType($_SERVER["CONTENT_TYPE"]);
      $this->setBody($body);
      return;
    }

    $this->setContentType($_SERVER["CONTENT_TYPE"]);
    $body = file_get_contents('php://input');

    // If body is indeed valid JSON.
    if (JSON::validate($body)) {
      $json = JSON::decode($body, true);
      $this->setBody($json);
      return;
    }

    // It is HTML
    if (stripos($body, '<!DOCTYPE html>') !== false) {
      $this->setBody($body);
      return;
    }

    // it is XML
    libxml_use_internal_errors(true);
    $xml = @simplexml_load_string($body);
    if ($xml) {
      $this->setBody($body);
    }
  }

  /**
   * Add a URL parameter to the initial requests.
   *
   * @param string $name  The parameter name.
   * @param mixed  $value The parameter value.
   *
   * @return Request  The current Request instance.
   */
  public function addUrlParam(string $name, mixed $value): Request {
    $this->query[$name] = $value;
    return $this;
  }

  /**
   * Remove a URL parameter from the initial requests.
   *
   * @param string $name  The parameter name.
   *
   * @return Request  The current Request instance.
   */
  public function removeUrlParam(string $name): Request {
    unset($this->query[$name]);
    return $this;
  }

  /**
   * Check if a URL parameter is set.
   *
   * @param string $name  The parameter name.
   *
   * @return bool  True if the param is set, false otherwise.
   */
  public function hasUrlParam(string $name): bool {
    return isset($this->query[$name]);
  }

  /**
   * Check if a URL parameter is **NOT** set.
   *
   * @param string $name  The parameter name.
   *
   * @return bool  True if the param is **NOT** set, false otherwise.
   */
  public function hasNotUrlParam(string $name): bool {
    return !$this->hasUrlParam($name);
  }

  /**
   * Sets the request Url Parameters
   *
   * @return array|null  An array of parsed URL parameters or null.
   */
  private function parseUrlParams(): ?array {
    return $_GET;
  }

  /**
   * Sets the request content type to `html`, `xml`, `json` or `form-data`
   *
   * @param string $type `html`, `xml`, `json` or `form-data`. Any other type will be ignored.
   *
   * @return Request The current request instance.
   */
  public function setContentType(string $type): Request {
    $this->contentType = $type;
    return $this;
  }

  /**
   * Sets the request body.
   *
   * @param string|array $body  The **parsed** body value.
   *
   * @return Request The current request instance.
   */
  public function setBody($body): Request {
    $this->body = $body;
    return $this;
  }

  /**
   * Retrieve the current Request object.
   *
   * @return Request  The current request.
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
   * @return int|null  The wordpress page ID or NULL if the requests is handled by the Heracles Router in heracles/routes.php.
   */
  public function getId(): ?int {
    return $this->id;
  }

  /**
   * Gets the request payload.
   *
   * @return mixed  The parsed payload.
   */
  public function getBody(): mixed {
    return $this->body;
  }

  /**
   * Gets a body parameter.
   *
   * @param string $key  The parameter name.
   *
   * @return mixed|null  The parameter value or null if the parameter is not set.
   */
  public function getBodyParam(string $key) {
    return $this->body[$key] ?? null;
  }

  /**
   * Gets the request method.
   *
   * @return string  The method of the incomming request.
   */
  public function getMethod(): string {
    return $this->method;
  }

  /**
   * Checks if the current requets is a 404
   *
   * @return bool
   */
  public function is404(): bool {
    return is_404();
  }

  /**
   * Force the current Request to be a 404.
   *
   * @return never
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
   *
   * @return string.
   */
  public function getContentType() {
    return $this->contentType;
  }

  /**
   * Gets the request requested Uri
   *
   * @return string.
   */
  public function getUri(): string {
    return $this->requestedUri;
  }

  /**
   * Check if the request has a GET method.
   *
   * @return bool  True if the request has a GET method.
   */
  public function isGet(): bool {
    return $this->getMethod() === 'GET';
  }

  /**
   * Check if the request has a POST method.
   *
   * @return bool  True if the request has a POST method.
   */
  public function isPost(): bool {
    return $this->getMethod() === 'POST';
  }

  /**
   * Check if the request has a PUT method.
   *
   * @return bool  True if the request has a PUT method.
   */
  public function isPut(): bool {
    return $this->getMethod() === 'PUT';
  }

  /**
   * Check if the request has a DELETE method.
   *
   * @return bool  True if the request has a DELETE method.
   */
  public function isDelete(): bool {
    return $this->getMethod() === 'DELETE';
  }

  /**
   * Gets the request User Agent.
   *
   * @return string  The user agent data.
   */
  public function getUserAgent(): string {
    return $this->userAgent;
  }

  /**
   * Gets the request headers.
   *
   * @return array  An array with all headers.
   */
  public function getHeaders(): array {
    return $this->headers;
  }

  /**
   * Gets a specific header value.
   *
   * @param string $header  The desired header key.
   *
   * @return mixed|null  The provided header value or null.
   */
  public function getHeader(string $header) {
    return $this->hasHeader($header)
      ? $this->headers[$header]
      : null;
  }

  /**
   * Sets a header value.
   *
   * @param string $name   The header name.
   * @param mixed  $value  The header value.
   *
   * @return Request  The current request Instance.
   */
  public function setHeader($name, $value): Request {
    $this->headers[$name] = $value;
    return $this;
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
   * @param string $header  The desired header key.
   *
   * @return bool  True if the header is set, false otherwise.
   */
  public function hasHeader(string $header): bool {
    return isset($this->headers[$header]);
  }

  /**
   * Gets the URL parameters as an array.
   *
   * @return array|null  The array of parameters in the URL or null.
   */
  public function getUrlParams(): ?array {
    return $this->query;
  }

  /**
   * Gets the requested URL.
   *
   * @return string  The full URL.
   */
  public function getUrl(): string {
    return $this->url;
  }

  /**
   * Gets a specific URL parameter.
   *
   * @param string $key  The param name.
   *
   * @return mixed  The param value.
   */
  public function getUrlParam(string $key): mixed {
    return $this->query[$key] ?? null;
  }

  /**
   * Gets the Query String (the URL parameters)
   *
   * @return array|null  An array of parsed querystring or null.
   */
  public function getQueryString(): ?array {
    return $this->query;
  }

  /**
   * Gets every cookies of the incomming request.
   *
   * @return array  An array of cookies.
   */
  public function getCookies() {
    return $_COOKIE;
  }

  /**
   * Gets a specific cookie of the incomming request.
   *
   * @param string $cookie  The cookie name.
   *
   * @return string|null  The cookie value or null if it doesn't exists.
   */
  public function getCookie(string $cookie): ?string {
    return $this->getCookies()[$cookie] ?? null;
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
      return get_post_type();
    }

    if (is_post_type_archive()) {
      return get_query_var('post_type');
    }

    return null;
  }

  /**
   * Convert the request to an array
   *
   * @return array
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
   *
   * @return bool
   */
  public function isFeed(): bool {
    return is_feed();
  }

  /**
   * Determine if the current request is an AJAX request.
   *
   * @return bool
   */
  public function isAjax(): bool {
    return wp_doing_ajax();
  }

  /**
   * Determine if the current request is an attachment.
   *
   * @return bool
   */
  public function isAttachment(): bool {
    return is_attachment();
  }

  /**
   * Determine if the current request is a pagination.
   *
   * @return bool
   */
  public function isPagination(): bool {
    return is_paged();
  }

  /**
   * Determine if the current request is a tag.
   *
   * @return bool
   */
  public function isTag(): bool {
    return is_tag();
  }

  /**
   * Check if the current request is for a custom taxonomy archive.
   *
   * @return bool
   */
  public function isTax(): bool {
    return is_tax();
  }

  /**
   * Check if the current request is for a post type archive.
   *
   * @return bool
   */
  public function isPostTypeArchive(): bool {
    return is_post_type_archive();
  }


  /**
   * Check if the current request is for a date-based archive.
   *
   * @return bool
   */
  public function isDate(): bool {
    return is_date();
  }

  /**
   * Check if the current request is for a category archive.
   *
   * @return bool
   */
  public function isCategory(): bool {
    return is_category();
  }

  /**
   * Determine if the current request is an admin request.
   *
   * @return bool
   */
  public function isAdmin() {
    return is_admin();
  }

  /**
   * Determine if the current request is a search request.
   *
   * @return bool
   */
  public function isSearch() {
    return is_search();
  }

  /**
   * Check if the current request is for any type of archive page.
   *
   * @return bool
   */
  public function isArchive(): bool {
    return $this->isCategory()
      || $this->isTag()
      || $this->isAuthor()
      || $this->isDate()
      || $this->isTax()
      || $this->isPostTypeArchive();
  }
}
