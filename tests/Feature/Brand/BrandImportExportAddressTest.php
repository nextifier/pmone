<?php

use App\Exports\BrandEventsExport;
use App\Exports\BrandsExport;
use App\Exports\BrandsTemplateExport;
use App\Imports\BrandsImport;
use App\Models\Brand;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The phone-formatting column letters are protected implementation detail, but
 * they must stay aligned with headings() or exported phone numbers get mangled.
 */
function phoneColumnsOf(object $export): array
{
    $method = new ReflectionMethod($export, 'phoneColumns');
    $method->setAccessible(true);

    return $method->invoke($export);
}

test('importing builds the address object from the four location columns', function () {
    (new BrandsImport)->model([
        'name' => 'Imported Brand',
        'country' => ' Indonesia ',
        'province' => ' DKI Jakarta ',
        'city' => ' Jakarta Selatan ',
        'street_address' => ' Jl. Sudirman No. 1 ',
    ]);

    $brand = Brand::where('name', 'Imported Brand')->firstOrFail();

    expect($brand->address)->toBe([
        'country' => 'Indonesia',
        'province' => 'DKI Jakarta',
        'city' => 'Jakarta Selatan',
        'street' => 'Jl. Sudirman No. 1',
    ]);
});

test('importing a row without any location column leaves the address null', function () {
    (new BrandsImport)->model(['name' => 'No Address Brand']);

    expect(Brand::where('name', 'No Address Brand')->firstOrFail()->address)->toBeNull();
});

test('brands export splits the address into four columns', function () {
    $headings = (new BrandsExport)->headings();

    expect($headings)->not->toContain('Company Address');
    expect($headings)->toContain('Country', 'Province', 'City', 'Street Address');

    $brand = Brand::factory()->create([
        'address' => [
            'street' => 'Jl. Sudirman No. 1',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'country' => 'Indonesia',
        ],
    ]);
    $brand->load(['media', 'users', 'brandEvents', 'tags']);

    $row = (new BrandsExport)->map($brand);
    $addressIndex = array_search('Country', $headings, true);

    expect($row)->toHaveCount(count($headings));
    expect(array_slice($row, $addressIndex, 4))
        ->toBe(['Indonesia', 'DKI Jakarta', 'Jakarta Selatan', 'Jl. Sudirman No. 1']);
});

test('brands export renders a missing address as dashes', function () {
    $brand = Brand::factory()->create(['address' => null]);
    $brand->load(['media', 'users', 'brandEvents', 'tags']);

    $headings = (new BrandsExport)->headings();
    $row = (new BrandsExport)->map($brand);
    $addressIndex = array_search('Country', $headings, true);

    expect(array_slice($row, $addressIndex, 4))->toBe(['-', '-', '-', '-']);
});

test('the brands template ships the four location columns with matching sample data', function () {
    $template = new BrandsTemplateExport;
    $headings = $template->headings();

    expect($headings)->not->toContain('Company Address');
    expect($headings)->toContain('Country', 'Province', 'City', 'Street Address');

    foreach ($template->array() as $row) {
        expect($row)->toHaveCount(count($headings));
    }

    // Company Phone sits before the address, so it stays in column D.
    expect($headings[3])->toBe('Company Phone');
    expect(phoneColumnsOf($template))->toBe(['D']);
});

test('brands export keeps the phone column in place after the address split', function () {
    $export = new BrandsExport;

    expect($export->headings()[5])->toBe('Company Phone');
    expect(phoneColumnsOf($export))->toBe(['F']);
});

test('brand events export shifts the phone column after the address split', function () {
    $event = Event::factory()->create();
    $export = new BrandEventsExport($event->id);

    // The address sits before the phone here, so Company Phone moves F -> I.
    expect($export->headings()[8])->toBe('Company Phone');
    expect(phoneColumnsOf($export))->toBe(['I']);
});
