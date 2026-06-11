<?php

namespace App\Support;

class FlashMessage
{
    /**
     * @param  list<array{label: string, url: string, primary?: bool}>  $actions
     */
    public static function success(string $message, array $actions = []): void
    {
        session()->flash('success', $message);

        if ($actions !== []) {
            session()->flash('success_actions', $actions);
        }
    }

    /**
     * @param  list<array{label: string, url: string, primary?: bool}>  $actions
     */
    public static function error(string $message, array $actions = []): void
    {
        session()->flash('error', $message);

        if ($actions !== []) {
            session()->flash('error_actions', $actions);
        }
    }
}
