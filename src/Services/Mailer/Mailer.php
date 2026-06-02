<?php

declare(strict_types=1);

namespace Fern\Core\Services\Mailer;

use Fern\Core\Config;
use Fern\Core\Errors\FernMailerException;
use Fern\Core\Factory\Singleton;
use Fern\Core\Fern;
use Fern\Core\Utils\Types;
use Fern\Core\Wordpress\Events;
use Fern\Core\Wordpress\Filters;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class Mailer extends Singleton {
  /**
   * @var array<string, mixed>
   */
  private array $config;

  public function __construct() {
    parent::__construct();

    $config = Config::get('mailer');
    $config = is_array($config) ? $config : [];
    /** @var array<string, mixed> $config */
    $this->config = $config;
  }

  /**
   * Get the mailer configuration
   *
   * @return array<string, mixed>
   */
  public function getConfig(): array {
    return $this->config;
  }

  /**
   * Validate the mailer configuration
   *
   * @throws FernMailerException
   */
  public function validateConfig(): bool {
    $requiredKeys = ['from_name', 'from_address', 'host', 'port', 'username', 'password'];

    foreach ($requiredKeys as $key) {
      $value = $this->config[$key] ?? null;

      if ($value === null || $value === '' || $value === 0) {
        throw new FernMailerException("Mailer configuration is invalid: missing or empty '{$key}'");
      }
    }

    if (filter_var($this->config['from_address'], FILTER_VALIDATE_EMAIL) === false) {
      throw new FernMailerException("Mailer configuration is invalid: 'from_address' is not a valid email");
    }

    if (!is_numeric($this->config['port'])) {
      throw new FernMailerException("Mailer configuration is invalid: 'port' is not numeric");
    }

    return true;
  }

  /**
   * Boot the mailer
   *
   * @throws FernMailerException
   */
  public static function boot(): void {
    $instance = self::getInstance();
    $config = $instance->getConfig();

    if ($config === []) {
      // Means the user don't want to configure mailer with Fern.
      return;
    }

    $instance->validateConfig();

    Events::on('phpmailer_init', function (PHPMailer $mailer) use ($config) {
      $mailer->isSMTP();
      $mailer->SMTPAutoTLS = false;
      $mailer->SMTPAuth = Types::getSafeString($config['username'] ?? '') !== '' && Types::getSafeString($config['password'] ?? '') !== '';
      $mailer->SMTPDebug = Fern::isDev() ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;
      $mailer->SMTPSecure = Types::getSafeString($config['encryption'] ?? '');
      $mailer->Debugoutput = 'error_log';
      $mailer->Host = Types::getSafeString($config['host'] ?? '');
      $mailer->Port = Types::getSafeInt($config['port'] ?? 0);
      $mailer->Username = Types::getSafeString($config['username'] ?? '');
      $mailer->Password = Types::getSafeString($config['password'] ?? '');

      return $mailer;
    });

    Filters::on('wp_mail_from', fn() => $config['from_address']);
    Filters::on('wp_mail_from_name', fn() => $config['from_name']);
  }
}
