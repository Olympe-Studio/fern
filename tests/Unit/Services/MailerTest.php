<?php declare(strict_types=1);

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Fern\Core\Config;
use Fern\Core\Errors\FernMailerException;
use Fern\Core\Fern;
use Fern\Core\Services\Mailer\Mailer;

if (!class_exists('PHPMailer\\PHPMailer\\SMTP')) {
  eval('namespace PHPMailer\PHPMailer; class SMTP { const DEBUG_OFF = 0; const DEBUG_SERVER = 2; }');
}

if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
  eval('namespace PHPMailer\PHPMailer; class PHPMailer {
    public bool $SMTPAutoTLS = true;
    public bool $SMTPAuth = false;
    public int $SMTPDebug = 0;
    public string $SMTPSecure = "";
    public string $Debugoutput = "";
    public string $Host = "";
    public int $Port = 0;
    public string $Username = "";
    public string $Password = "";
    public bool $isSMTPCalled = false;
    public function isSMTP(): void { $this->isSMTPCalled = true; }
  }');
}

function validMailerConfig(): array {
  return [
    'from_name' => 'Acme',
    'from_address' => 'no-reply@acme.test',
    'host' => 'smtp.acme.test',
    'port' => 587,
    'username' => 'mailer',
    'password' => 'secret',
    'encryption' => 'tls',
  ];
}

function setFernDev(bool $value): void {
  (new ReflectionProperty(Fern::class, 'isDev'))->setValue(null, $value);
}

describe('getConfig', function (): void {
  it('reads the mailer config from Config', function (): void {
    Config::getInstance()->setConfig(['mailer' => ['host' => 'x']]);

    expect(Mailer::getInstance()->getConfig())->toBe(['host' => 'x']);
  });

  it('defaults to an empty array when the config is not an array', function (): void {
    Config::getInstance()->setConfig(['mailer' => 'nope']);

    expect(Mailer::getInstance()->getConfig())->toBe([]);
  });
});

describe('validateConfig', function (): void {
  it('returns true for a complete valid configuration', function (): void {
    Config::getInstance()->setConfig(['mailer' => validMailerConfig()]);

    expect(Mailer::getInstance()->validateConfig())->toBeTrue();
  });

  it('throws when a required key is missing or empty', function (string $key): void {
    $config = validMailerConfig();
    unset($config[$key]);
    Config::getInstance()->setConfig(['mailer' => $config]);

    expect(fn (): bool => Mailer::getInstance()->validateConfig())
      ->toThrow(FernMailerException::class, "missing or empty '{$key}'");
  })->with(['from_name', 'from_address', 'host', 'port', 'username', 'password']);

  it('throws when from_address is not a valid email', function (): void {
    $config = validMailerConfig();
    $config['from_address'] = 'not-an-email';
    Config::getInstance()->setConfig(['mailer' => $config]);

    expect(fn (): bool => Mailer::getInstance()->validateConfig())
      ->toThrow(FernMailerException::class, "'from_address' is not a valid email");
  });

  it('throws when the port is not numeric', function (): void {
    $config = validMailerConfig();
    $config['port'] = 'abc';
    Config::getInstance()->setConfig(['mailer' => $config]);

    expect(fn (): bool => Mailer::getInstance()->validateConfig())
      ->toThrow(FernMailerException::class, "'port' is not numeric");
  });
});

describe('boot', function (): void {
  it('returns early without hooks when the config is empty', function (): void {
    Config::getInstance()->setConfig([]);

    Actions\expectAdded('phpmailer_init')->never();
    Filters\expectAdded('wp_mail_from')->never();
    Filters\expectAdded('wp_mail_from_name')->never();

    Mailer::boot();
  });

  it('throws via validateConfig when the config is present but invalid', function (): void {
    Config::getInstance()->setConfig(['mailer' => ['from_name' => 'Acme']]);

    expect(static function (): void {
      Mailer::boot();
    })->toThrow(FernMailerException::class);
  });

  it('registers the phpmailer_init action and the wp_mail_from filters', function (): void {
    Config::getInstance()->setConfig(['mailer' => validMailerConfig()]);

    Actions\expectAdded('phpmailer_init')->once();
    Filters\expectAdded('wp_mail_from')->once();
    Filters\expectAdded('wp_mail_from_name')->once();

    Mailer::boot();
  });

  it('configures the PHPMailer instance with the SMTP settings in production', function (): void {
    setFernDev(false);
    Config::getInstance()->setConfig(['mailer' => validMailerConfig()]);

    $captured = null;
    Actions\expectAdded('phpmailer_init')->once()->whenHappen(static function ($callback) use (&$captured): void {
      $captured = $callback;
    });
    Filters\expectAdded('wp_mail_from')->once();
    Filters\expectAdded('wp_mail_from_name')->once();

    Mailer::boot();

    $mailer = new PHPMailer\PHPMailer\PHPMailer();
    $result = $captured($mailer);

    expect($mailer->isSMTPCalled)->toBeTrue()
      ->and($mailer->SMTPAutoTLS)->toBeFalse()
      ->and($mailer->SMTPAuth)->toBeTrue()
      ->and($mailer->SMTPDebug)->toBe(PHPMailer\PHPMailer\SMTP::DEBUG_OFF)
      ->and($mailer->SMTPSecure)->toBe('tls')
      ->and($mailer->Debugoutput)->toBe('error_log')
      ->and($mailer->Host)->toBe('smtp.acme.test')
      ->and($mailer->Port)->toBe(587)
      ->and($mailer->Username)->toBe('mailer')
      ->and($mailer->Password)->toBe('secret')
      ->and($result)->toBe($mailer);
  });

  it('enables verbose SMTP debugging in development mode', function (): void {
    setFernDev(true);
    Config::getInstance()->setConfig(['mailer' => validMailerConfig()]);

    $captured = null;
    Actions\expectAdded('phpmailer_init')->once()->whenHappen(static function ($callback) use (&$captured): void {
      $captured = $callback;
    });
    Filters\expectAdded('wp_mail_from')->once();
    Filters\expectAdded('wp_mail_from_name')->once();

    Mailer::boot();

    $mailer = new PHPMailer\PHPMailer\PHPMailer();
    $captured($mailer);

    expect($mailer->SMTPDebug)->toBe(PHPMailer\PHPMailer\SMTP::DEBUG_SERVER);
  });

});
