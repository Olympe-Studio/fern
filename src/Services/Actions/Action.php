<?php

declare(strict_types=1);

namespace Fern\Core\Services\Actions;

use Fern\Core\Services\HTTP\Request;

class Action {
  private static ?Action $current = null;

  private string|null $name;

  /** @var array<string, mixed> */
  private array $args;

  public function __construct(Request $req) {
    $body = $req->getBody();
    $this->init($req, $body);
  }

  /**
   * Gets the current action instance.
   */
  public static function getCurrent(): Action {
    if (is_null(self::$current)) {
      self::$current = new Action(Request::getCurrent());
    }

    return self::$current;
  }

  /**
   * Checks if the Action failed at being resolved from the request.
   *
   * @return bool True if the request failed. False if the action is well parsed.
   */
  public function isBadRequest(): bool {
    return is_null($this->name);
  }

  /**
   * Gets the raw arguments as an array of key-value pairs.
   *
   * @return array<string, mixed> The raw arguments.
   */
  public function getRawArgs(): array {
    return $this->args;
  }

  /**
   * Gets an argument from the Action request.
   *
   * @param string $argument The argument key.
   * @param mixed $default The default value.
   *
   * @return mixed The argument value
   */
  public function get(string $argument, mixed $default = null): mixed {
    return $this->args[$argument] ?? $default;
  }

  /**
   * Adds an argument to the Action.
   *
   * @param string $argumentName  The argument key.
   * @param mixed  $argumentValue The argument value.
   *
   * @return Action The current action instance.
   */
  public function add(string $argumentName, mixed $argumentValue): Action {
    $this->args[$argumentName] = $argumentValue;

    return $this;
  }

  /**
   * Updates a value of an argument.
   *
   * @param string $argumentName  The argument key.
   * @param mixed  $argumentValue The argument new value.
   *
   * @return Action The current action instance.
   */
  public function update(string $argumentName, mixed $argumentValue): Action {
    $this->args[$argumentName] = $argumentValue;

    return $this;
  }

  /**
   * Removes an argument from the Action.
   *
   * @param string $argumentName The argument key.
   *
   * @return Action The current action instance.
   */
  public function remove(string $argumentName): Action {
    unset($this->args[$argumentName]);

    return $this;
  }

  /**
   * merge new arguments to the action arguments.
   *
   * @param array<string, mixed> $data The new arguments to merge.
   *
   * @return Action The current action instance.
   */
  public function merge(array $data): Action {
    $this->args = [
      ...$this->args,
      ...$data,
    ];

    return $this;
  }

  /**
   * Checks if an argument exists in the action.
   *
   * @param string $key The argument name,
   *
   * @return bool True if the argument exists in the action.
   */
  public function has(string $key): bool {
    return isset($this->args[$key]);
  }

  /**
   * Checks if an argument exists in the action.
   *
   * @param string $key The argument name,
   *
   * @return bool True if the argument exists in the action.
   */
  public function hasNot(string $key): bool {
    return !$this->has($key);
  }

  /**
   * Gets the called action name.
   *
   * @return string|null The action name.
   */
  public function getName(): string|null {
    return $this->name;
  }

  /**
   * Sets the called action name.
   *
   * @return string The new action name.
   * @return Action The current action instance.
   */
  public function setName(string $name): Action {
    $this->name = $name;

    return $this;
  }

  /**
   * Initializes the action
   *
   * @param Request              $req  The request instance.
   * @param array<string, mixed> $body The request body.
   */
  private function init(Request $req, array $body): void {
    $this->name = $body['action'] ?? null;
    $this->args = $this->parseArgs($req, $body);
  }

  /**
   * Parse the action arguments.
   *
   * @param Request              $req  The request instance.
   * @param array<string, mixed> $body The request body.
   *
   * @return array<string, mixed>
   */
  private function parseArgs(Request $req, array $body): array {
    if (isset($body['args']) && $req->getContentType() !== 'form-data') {
      return $body['args'];
    }

    if ($req->getContentType() === 'form-data') {
      $shadowClone = $body;
      unset($shadowClone['action']);

      return $shadowClone;
    }

    return [];
  }
}
