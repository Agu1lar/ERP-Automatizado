<?php

namespace App\Agent\Chat;

use App\Enums\CopilotMode;

class AgentChatOptions
{
    /**
     * @param  list<array{path: string, mime: string, original_name: string, extracted_text?: string|null}>  $attachments
     */
    public function __construct(
        public readonly bool $confirmed = false,
        public readonly CopilotMode $mode = CopilotMode::Ask,
        public readonly array $attachments = [],
    ) {}

    public function hasAttachments(): bool
    {
        return $this->attachments !== [];
    }
}
