<?php

namespace App\Enums;

enum CustomFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Textarea = 'textarea';
    case Date = 'date';
    case Boolean = 'boolean';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Texto curto',
            self::Number => 'Número',
            self::Textarea => 'Texto longo',
            self::Date => 'Data',
            self::Boolean => 'Sim/Não',
        };
    }
}
