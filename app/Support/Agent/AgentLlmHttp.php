<?php

namespace App\Support\Agent;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class AgentLlmHttp
{
    public static function client(): PendingRequest
    {
        $request = Http::withToken((string) config('agent.llm.api_key'))
            ->timeout((int) config('agent.llm.timeout', 30));

        if (! config('agent.llm.verify_ssl', true)) {
            $request = $request->withOptions(['verify' => false]);
        }

        return $request;
    }
}
