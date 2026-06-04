<?php declare(strict_types=1);

use Fern\Core\Errors\ActionException;
use Fern\Core\Errors\ActionNotFoundException;
use Fern\Core\Errors\AttributeValidationException;
use Fern\Core\Errors\ControllerRegistration;
use Fern\Core\Errors\FernConfigurationExceptions;
use Fern\Core\Errors\FernMailerException;
use Fern\Core\Errors\FileHandlingError;
use Fern\Core\Errors\ReplyParsingError;
use Fern\Core\Errors\RouterException;
use Fern\Core\Errors\SchedulerParsingError;
use Fern\Core\Errors\ViewsExceptions;

dataset('errors', [
  ActionException::class,
  ActionNotFoundException::class,
  AttributeValidationException::class,
  ControllerRegistration::class,
  FernConfigurationExceptions::class,
  FernMailerException::class,
  FileHandlingError::class,
  ReplyParsingError::class,
  RouterException::class,
  SchedulerParsingError::class,
  ViewsExceptions::class,
]);

it('is a throwable exception', function (string $class): void {
  $error = new $class('something went wrong');

  expect($error)
    ->toBeInstanceOf(Throwable::class)
    ->toBeInstanceOf(Exception::class)
    ->and($error->getMessage())->toBe('something went wrong');
})->with('errors');

it('can be thrown and caught', function (string $class): void {
  expect(function () use ($class): void {
    throw new $class('boom');
  })->toThrow($class, 'boom');
})->with('errors');

it('preserves a previous exception in the chain', function (string $class): void {
  $previous = new RuntimeException('root cause');
  $error = new $class('wrapper', 42, $previous);

  expect($error->getPrevious())->toBe($previous)
    ->and($error->getCode())->toBe(42);
})->with('errors');
