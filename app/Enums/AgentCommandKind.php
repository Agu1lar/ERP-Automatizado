<?php

namespace App\Enums;

enum AgentCommandKind: string
{
    case Read = 'read';
    case Write = 'write';
}
