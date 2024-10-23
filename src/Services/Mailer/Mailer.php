<?php declare(strict_types=1);

namespace Fern\Core\Services\Mailer;

use Fern\Core\Config;
use Fern\Core\Errors\FernMailerException;
use Fern\Core\Factory\Singleton;
use Fern\Core\Fern;
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
    $this->config = Config::get('mailer');
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
      if (!isset($this->config[$key]) || empty($this->config[$key])) {
        throw new FernMailerException("Mailer configuration is invalid: missing or empty '{$key}'");
      }
    }

    if (!filter_var($this->config['from_address'], FILTER_VALIDATE_EMAIL)) {
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

    if ($config === null || !is_array($config)) {
      // Means the user don't want to configure mailer with Fern.
      return;
    }

    $instance->validateConfig();

    Events::addHandlers('phpmailer_init', function (PHPMailer $mailer) use ($config) {
      $mailer->isSMTP();
      $mailer->SMTPAutoTLS = false;
      $mailer->SMTPAuth = !empty($config['username']) && !empty($config['password']);
      $mailer->SMTPDebug = Fern::isDev() ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;
      $mailer->SMTPSecure = $config['encryption'];
      $mailer->Debugoutput = 'error_log';
      $mailer->Host = $config['host'];
      $mailer->Port = $config['port'];
      $mailer->Username = $config['username'];
      $mailer->Password = $config['password'];

      return $mailer;
    });

    Filters::add('wp_mail_from', function () use ($config) {
      return $config['from_address'];
    });

    Filters::add('wp_mail_from_name', function () use ($config) {
      return $config['from_name'];
    });
  }
}
