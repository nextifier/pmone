<?php

namespace App\Enums;

enum BoothType: string
{
    case RawSpace = 'raw_space';
    case StandardShellScheme = 'standard_shell_scheme';
    case EnhancedShellScheme = 'enhanced_shell_scheme';
    case TableChairOnly = 'table_chair_only';
    case Alley = 'alley';
    case FoodAndBeverage = 'food_and_beverage';
    case ArtistAlley = 'artist_alley';
    case ArtistAlleyTable = 'artist_alley_table';
    case GuestBooth = 'guest_booth';
    case CosplayZone = 'cosplay_zone';
    case ToysHuntingGround = 'toys_hunting_ground';
    case PortfolioReview = 'portfolio_review';
    case CommunityBooth = 'community_booth';
    case Pavilion = 'pavilion';

    public function label(): string
    {
        return match ($this) {
            self::RawSpace => 'Raw Space',
            self::StandardShellScheme => 'Standard Shell Scheme',
            self::EnhancedShellScheme => 'Enhanced Shell Scheme',
            self::TableChairOnly => 'Table & Chair Only',
            self::Alley => 'Alley',
            self::FoodAndBeverage => 'Food and Beverage',
            self::ArtistAlley => 'Artist Alley',
            self::ArtistAlleyTable => 'Artist Alley Table',
            self::GuestBooth => 'Guest Booth',
            self::CosplayZone => 'Cosplay Zone',
            self::ToysHuntingGround => 'Toys Hunting Ground',
            self::PortfolioReview => 'Portfolio Review',
            self::CommunityBooth => 'Community Booth',
            self::Pavilion => 'Pavilion',
        };
    }

    /**
     * Resolve a booth type from a human-written value (import files, spreadsheets).
     * Matches the enum value, the label, or the shell scheme shorthands.
     */
    public static function tryFromLabel(?string $value): ?self
    {
        if (blank($value)) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        foreach (self::cases() as $case) {
            if ($normalized === $case->value || $normalized === strtolower($case->label())) {
                return $case;
            }
        }

        return match (true) {
            str_contains($normalized, 'raw') => self::RawSpace,
            str_contains($normalized, 'enhanced') => self::EnhancedShellScheme,
            str_contains($normalized, 'standard'), str_contains($normalized, 'shell') => self::StandardShellScheme,
            default => null,
        };
    }
}
