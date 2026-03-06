<?php

namespace App\Enums;

enum BoothType: string
{
    case RawSpace = 'raw_space';
    case StandardShellScheme = 'standard_shell_scheme';
    case EnhancedShellScheme = 'enhanced_shell_scheme';
    case TableChairOnly = 'table_chair_only';

    public function label(): string
    {
        return match ($this) {
            self::RawSpace => 'Raw Space',
            self::StandardShellScheme => 'Standard Shell Scheme',
            self::EnhancedShellScheme => 'Enhanced Shell Scheme',
            self::TableChairOnly => 'Table & Chair Only',
        };
    }
}
