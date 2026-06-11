<?php

namespace App\Agent\Document;

class AgentDocumentAnalyzeResult
{
    /**
     * @param  array{reply: string, extracted: array<string, mixed>, proposed_actions: list<array<string, mixed>>}|null  $plan
     */
    public function __construct(
        public readonly ?array $plan,
        public readonly bool $attempted,
        public readonly ?string $failureReason = null,
    ) {}

    public static function skipped(): self
    {
        return new self(null, false);
    }

    /** @param  array{reply: string, extracted: array<string, mixed>, proposed_actions: list<array<string, mixed>>}  $plan */
    public static function success(array $plan): self
    {
        return new self($plan, true);
    }

    public static function failure(?string $reason): self
    {
        return new self(null, true, $reason);
    }

    public function shouldNotifyFallback(): bool
    {
        return $this->attempted && $this->plan === null;
    }
}
