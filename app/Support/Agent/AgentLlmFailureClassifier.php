<?php

namespace App\Support\Agent;

class AgentLlmFailureClassifier
{
    public const QUOTA_EXCEEDED = 'quota_exceeded';

    public const RATE_LIMIT = 'rate_limit';

    public const CONTEXT_LENGTH = 'context_length';

    public const TIMEOUT = 'timeout';

    public const AUTH_ERROR = 'auth_error';

    public const SERVICE_UNAVAILABLE = 'service_unavailable';

    public const CONNECTION_ERROR = 'connection_error';

    public const UNKNOWN = 'unknown';

    public static function fromHttp(int $status, string $body = ''): string
    {
        $lower = mb_strtolower($body);

        if ($status === 401 || $status === 403 || str_contains($lower, 'invalid_api_key') || str_contains($lower, 'incorrect api key')) {
            return self::AUTH_ERROR;
        }

        if ($status === 429 || str_contains($lower, 'rate_limit') || str_contains($lower, 'rate limit')) {
            if (str_contains($lower, 'quota') || str_contains($lower, 'insufficient_quota') || str_contains($lower, 'billing')) {
                return self::QUOTA_EXCEEDED;
            }

            return self::RATE_LIMIT;
        }

        if ($status === 402 || str_contains($lower, 'insufficient_quota') || str_contains($lower, 'exceeded your current quota')) {
            return self::QUOTA_EXCEEDED;
        }

        if (
            str_contains($lower, 'context_length')
            || str_contains($lower, 'maximum context length')
            || str_contains($lower, 'token limit')
            || str_contains($lower, 'too many tokens')
        ) {
            return self::CONTEXT_LENGTH;
        }

        if ($status === 503 || $status === 502 || $status === 504) {
            return self::SERVICE_UNAVAILABLE;
        }

        if ($status >= 500) {
            return self::SERVICE_UNAVAILABLE;
        }

        return self::UNKNOWN;
    }

    public static function fromException(\Throwable $exception): string
    {
        $message = mb_strtolower($exception->getMessage());

        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return self::TIMEOUT;
        }

        if (
            str_contains($message, 'ssl certificate')
            || str_contains($message, 'curl error 60')
            || str_contains($message, 'unable to get local issuer certificate')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'could not resolve host')
        ) {
            return self::CONNECTION_ERROR;
        }

        if (str_contains($message, 'context_length') || str_contains($message, 'token')) {
            return self::CONTEXT_LENGTH;
        }

        return self::UNKNOWN;
    }
}
