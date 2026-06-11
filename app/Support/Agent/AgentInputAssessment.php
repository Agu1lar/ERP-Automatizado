<?php

namespace App\Support\Agent;

class AgentInputAssessment
{
    /**
     * @param  list<array{key: string, label: string, hint: string, alternatives: list<string>}>  $missing
     * @param  list<array{key: string, label: string, hint: string}>  $recommended
     */
    public function __construct(
        public readonly string $command,
        public readonly bool $complete,
        public readonly array $missing = [],
        public readonly array $recommended = [],
        public readonly string $actionLabel = '',
    ) {}

    public function isComplete(): bool
    {
        return $this->complete;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'command' => $this->command,
            'complete' => $this->complete,
            'action_label' => $this->actionLabel,
            'missing' => $this->missing,
            'recommended' => $this->recommended,
        ];
    }
}
