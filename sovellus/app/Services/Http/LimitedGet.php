<?php

namespace App\Services\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class LimitedGet
{
    public function __construct(private readonly int $maxBytes = 2_000_000) {}

    public function get(string $url, array $query = [], ?string $token = null): Response
    {
        return $this->send(function () use ($url, $query, $token) {
            $request = Http::connectTimeout(5)
                ->timeout(15)
                ->accept('application/json, application/xml, text/xml');

            if ($token !== null) {
                $request = $request->withToken($token);
            }

            return $request->get($url, $query);
        });
    }

    public function getUnfollowed(string $url): Response
    {
        return $this->send(function () use ($url) {
            return Http::connectTimeout(5)
                ->timeout(15)
                ->withOptions(['allow_redirects' => false])
                ->accept('text/calendar, text/plain')
                ->get($url);
        });
    }

    public function postForm(string $url, array $fields): Response
    {
        return $this->send(function () use ($url, $fields) {
            return Http::connectTimeout(5)
                ->timeout(15)
                ->asForm()
                ->acceptJson()
                ->post($url, $fields);
        });
    }

    private function send(callable $attemptRequest): Response
    {
        $attempt = 0;
        $delayMs = 400;

        while (true) {
            $attempt++;

            try {
                $response = $attemptRequest();
            } catch (ConnectionException $exception) {
                if ($attempt >= 3) {
                    throw new SourceException('Yhteys aikakatkaistiin.', previous: $exception);
                }

                $this->pauseMs($delayMs);
                $delayMs *= 2;

                continue;
            }

            if (! $response instanceof Response) {
                throw new SourceException('Yhteys epäonnistui.');
            }

            if ($response->status() === 429 && $attempt < 3) {
                $retryAfter = (int) $response->header('Retry-After');
                $this->pauseMs(($retryAfter > 0 ? min($retryAfter, 30) : 1) * 1000);

                continue;
            }

            if (in_array($response->status(), [401, 403, 404], true)) {
                return $response;
            }

            if ($response->serverError() && $attempt < 3) {
                $this->pauseMs($delayMs);
                $delayMs *= 2;

                continue;
            }

            if (strlen($response->body()) > $this->maxBytes) {
                throw new SourceException('Vastaus oli liian suuri.');
            }

            return $response;
        }
    }

    private function pauseMs(int $milliseconds): void
    {
        if ($milliseconds > 0 && ! app()->runningUnitTests()) {
            usleep($milliseconds * 1000);
        }
    }
}
