<?php
namespace Aws;

use Aws\Api\Service;
use Aws\Api\Validator;
use Aws\Credentials\CredentialsInterface;
use GuzzleHttp\Promise;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\LazyOpenStream;
use Psr\Http\Message\RequestInterface;

final class Middleware
{
    /**
     * Middleware used to allow a command parameter (e.g., "SourceFile") to
     * be used to specify the source of data for an upload operation.
     *
     * @param Service $api
     * @param string  $bodyParameter
     * @param string  $sourceParameter
     *
     * @return callable
     */
    public static function sourceFile(
        Service $api,
        $bodyParameter = 'Body',
        $sourceParameter = 'SourceFile'
    ) {
        return function (callable $handler) use (
            $api,
            $bodyParameter,
            $sourceParameter
        ) {
            return function (
                CommandInterface $command,
                RequestInterface $request = null)
            use (
                $handler,
                $api,
                $bodyParameter,
                $sourceParameter
            ) {
                $operation = $api->getOperation($command->getName());
                $source = $command[$sourceParameter];

                if ($source !== null
                    && $operation->getInput()->hasMember($bodyParameter)
                ) {
                    $command[$bodyParameter] = new LazyOpenStream($source, 'r');
                    unset($command[$sourceParameter]);
                }

                return $handler($command, $request);
            };
        };
    }

    /**
     * Adds a middleware that uses client-side validation.
     *
     * @param Service $api API being accessed.
     *
     * @return callable
     */
    public static function validation(Service $api)
    {
        $validator = new Validator();
        return function (callable $handler) use ($api, $validator) {
            return function (
                CommandInterface $command,
                RequestInterface $request = null
            ) use ($api, $validator, $handler) {
                $operation = $api->getOperation($command->getName());
                $validator->validate(
                    $command->getName(),
                    $operation->getInput(),
                    $command->toArray()
                );
                return $handler($command, $request);
            };
        };
    }

    /**
     * Builds an HTTP request for a command.
     *
     * @param callable $serializer Function used to serialize a request for a
     *                             command.
     * @return callable
     */
    public static function requestBuilder(callable $serializer)
    {
        return function (callable $handler) use ($serializer) {
            return function (CommandInterface $command) use ($serializer, $handler) {
                return $handler($command, $serializer($command));
            };
        };
    }

    /**
     * Creates a middleware that signs requests for a command.
     *
     * @param CredentialsInterface $creds             Credentials to sign with.
     * @param callable             $signatureFunction Function that accepts a
     *                                                Command object and returns
     *                                                a SignatureInterface.
     * @return callable
     */
    public static function signer(CredentialsInterface $creds, callable $signatureFunction)
    {
        return function (callable $handler) use ($signatureFunction, $creds) {
            return function (
                CommandInterface $command,
                RequestInterface $request
            ) use ($handler, $signatureFunction, $creds) {
                $signer = $signatureFunction($command);
                return $handler($command, $signer->signRequest($request, $creds));
            };
        };
    }

    /**
     * Creates a middleware that invokes a callback at a given step.
     *
     * The tap callback accepts a CommandInterface and RequestInterface as
     * arguments but is not expected to return a new value or proxy to
     * downstream middleware. It's simply a way to "tap" into the handler chain
     * to debug or get an intermediate value.
     *
     * @param callable $fn Tap function
     *
     * @return callable
     */
    public static function tap(callable $fn)
    {
        return function (callable $handler) use ($fn) {
            return function (
                CommandInterface $command,
                RequestInterface $request = null
            ) use ($handler, $fn) {
                $fn($command, $request);
                return $handler($command, $request);
            };
        };
    }

    /**
     * Middleware wrapper function that retries requests based on the boolean
     * result of invoking the provided "decider" function.
     *
     * If no delay function is provided, a simple implementation of exponential
     * backoff will be utilized.
     *
     * @param callable $decider Function that accepts the number of retries,
     *                          a request, [result], and [exception] and
     *                          returns true if the command is to be retried.
     * @param callable $delay   Function that accepts the number of retries and
     *                          returns the number of milliseconds to delay.
     *
     * @return callable
     */
    public static function retry(callable $decider = null, callable $delay = null)
    {
        $decider = $decider ?: RetryMiddleware::createDefaultDecider();
        $delay = $delay ?: [RetryMiddleware::class, 'exponentialDelay'];

        return function (callable $handler) use ($decider, $delay) {
            return new RetryMiddleware($decider, $delay, $handler);
        };
    }

    /**
     * Middleware wrapper function that adds a Content-Type header to requests.
     * This is only done when the Content-Type has not already been set, and the
     * request body's URI is available. It then checks the file extension of the
     * URI to determine the mime-type.
     *
     * @param array $operations Operations that Content-Type should be added to.
     *
     * @return callable
     */
    public static function contentType(array $operations)
    {
        return function (callable $handler) use ($operations) {
            return function (
                CommandInterface $command,
                RequestInterface $request = null
            ) use ($handler, $operations) {
                if (!$request->hasHeader('Content-Type')
                    && in_array($command->getName(), $operations, true)
                    && ($uri = $request->getBody()->getMetadata('uri'))
                ) {
                    $request = $request->withHeader(
                        'Content-Type',
                        Psr7\mimetype_from_filename($uri) ?: 'application/octet-stream'
                    );
                }

                return $handler($command, $request);
            };
        };
    }

    /**
     * Tracks command and request history using a history container.
     *
     * This is useful for testing.
     *
     * @param History $history History container to store entries.
     *
     * @return callable
     */
    public static function history(History $history)
    {
        return function (callable $handler) use ($history) {
            return function (
                CommandInterface $command,
                RequestInterface $request = null
            ) use ($handler, $history) {
                $ticket = $history->start($command, $request);
                return $handler($command, $request)
                    ->then(
                        function ($result) use ($history, $ticket) {
                            $history->finish($ticket, $result);
                            return $result;
                        },
                        function ($reason) use ($history, $ticket) {
                            $history->finish($ticket, $reason);
                            return Promise\rejection_for($reason);
                        }
                    );
            };
        };
    }

    /**
     * Creates a middleware that applies a map function to requests as they
     * pass through the middleware.
     *
     * @param callable $f Map function that accepts a RequestInterface and
     *                    returns a RequestInterface.
     *
     * @return callable
     */
    public static function mapRequest(callable $f)
    {
        return function (callable $handler) use ($f) {
            return function (
                CommandInterface $command,
                RequestInterface $request = null
            ) use ($handler, $f) {
                return $handler($command, $f($request));
            };
        };
    }

    /**
     * Creates a middleware that applies a map function to commands as they
     * pass through the middleware.
     *
     * @param callable $f Map function that accepts a command and returns a
     *                    command.
     *
     * @return callable
     */
    public static function mapCommand(callable $f)
    {
        return function (callable $handler) use ($f) {
            return function (
                CommandInterface $command,
                RequestInterface $request = null
            ) use ($handler, $f) {
                return $handler($f($command), $request);
            };
        };
    }

    /**
     * Creates a middleware that applies a map function to results.
     *
     * @param callable $f Map function that accepts an Aws\ResultInterface and
     *                    returns an Aws\ResultInterface.
     *
     * @return callable
     */
    public static function mapResult(callable $f)
    {
        return function (callable $handler) use ($f) {
            return function (
                CommandInterface $command,
                RequestInterface $request = null
            ) use ($handler, $f) {
                return $handler($command, $request)->then($f);
            };
        };
    }
}