<?php

namespace App\Agent\Chat;

class AgentLlmInterpretResult
{
    /**
     * @param  array{command?: string, input?: array<string, mixed>, reply?: string}|null  $parsed
     */
    public function __construct(
        public readonly ?array $parsed,
        public readonly bool $attempted,
        public readonly bool $succeeded,
        public readonly ?string $failureReason = null,
    ) {}

    public static function skipped(): self
    {
        return new self(null, false, false);
    }

    /** @param  array{command?: string, input?: array<string, mixed>, reply?: string}  $parsed */
    public static function success(array $parsed): self
    {
        return new self($parsed, true, true);
    }

    public static function failure(?string $reason): self
    {
        return new self(null, true, false, $reason);
    }

    public function shouldNotifyFallback(): bool
    {
        return $this->attempted && ! $this->succeeded;
    }
}
