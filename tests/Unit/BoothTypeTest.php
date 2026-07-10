<?php

use App\Enums\BoothType;

it('exposes a label for every case', function () {
    foreach (BoothType::cases() as $case) {
        expect($case->label())->not->toBeEmpty();
    }
});

it('resolves a booth type from its label', function (string $input, BoothType $expected) {
    expect(BoothType::tryFromLabel($input))->toBe($expected);
})->with([
    ['Artist Alley', BoothType::ArtistAlley],
    ['artist alley table', BoothType::ArtistAlleyTable],
    ['Food and Beverage', BoothType::FoodAndBeverage],
    ['Toys Hunting Ground', BoothType::ToysHuntingGround],
    ['Portfolio Review', BoothType::PortfolioReview],
    ['  Pavilion  ', BoothType::Pavilion],
    ['Table & Chair Only', BoothType::TableChairOnly],
    ['Alley', BoothType::Alley],
]);

it('resolves a booth type from its enum value', function () {
    expect(BoothType::tryFromLabel('community_booth'))->toBe(BoothType::CommunityBooth);
});

it('resolves shell scheme shorthands used in import files', function (string $input, BoothType $expected) {
    expect(BoothType::tryFromLabel($input))->toBe($expected);
})->with([
    ['Raw', BoothType::RawSpace],
    ['Shell Scheme', BoothType::StandardShellScheme],
    ['Enhanced', BoothType::EnhancedShellScheme],
]);

it('returns null for blank or unknown values', function (?string $input) {
    expect(BoothType::tryFromLabel($input))->toBeNull();
})->with([null, '', '   ', 'Rooftop Garden']);
