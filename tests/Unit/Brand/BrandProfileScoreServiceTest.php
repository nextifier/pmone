<?php

use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\PromotionPost;
use App\Services\Brand\BrandProfileScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * Build a brand-event whose brand has every profile field blank.
 */
function emptyBrandEvent(array $brandOverrides = []): BrandEvent
{
    $brand = Brand::factory()->create(array_merge([
        'description' => null,
        'company_name' => null,
        'company_email' => null,
        'company_phone' => null,
        'company_address' => null,
    ], $brandOverrides));

    return BrandEvent::factory()->create(['brand_id' => $brand->id]);
}

function scoreFor(BrandEvent $brandEvent): array
{
    $fresh = $brandEvent->fresh(['brand.media', 'brand.tags', 'brand.links', 'promotionPosts.media']);

    return app(BrandProfileScoreService::class)->score($fresh);
}

it('scores an empty brand as 0', function () {
    $result = scoreFor(emptyBrandEvent());

    expect($result['score'])->toBe(0);
    expect($result['is_complete'])->toBeFalse();
    expect(collect($result['breakdown'])->every(fn ($b) => $b['filled'] === false))->toBeTrue();
});

it('scores a partially filled brand by summing only earned weights', function () {
    $brandEvent = emptyBrandEvent(['description' => 'A real and visible description']);
    $brand = $brandEvent->brand;
    $brand->syncBusinessCategories(['Food & Beverage']);
    $brand->links()->create(['label' => 'Website', 'url' => 'https://example.test', 'order' => 0, 'is_active' => true]);

    $result = scoreFor($brandEvent);

    // description 14 + categories 10 + first link 6 = 30
    expect($result['score'])->toBe(30);
    expect(collect($result['breakdown'])->firstWhere('key', 'description')['filled'])->toBeTrue();
    expect(collect($result['breakdown'])->firstWhere('key', 'links')['filled'])->toBeTrue();
    expect(collect($result['breakdown'])->firstWhere('key', 'logo')['filled'])->toBeFalse();
});

it('scores a fully filled brand as 100', function () {
    Storage::fake('public');

    $brandEvent = emptyBrandEvent([
        'description' => 'Complete description',
        'company_name' => 'Acme Inc',
        'company_email' => 'hi@acme.test',
        'company_phone' => '+62 812 0000 0000',
        'company_address' => 'Jakarta, Indonesia',
    ]);
    $brand = $brandEvent->brand;
    $brand->addMedia(UploadedFile::fake()->image('logo.jpg', 400, 400))->toMediaCollection('brand_logo');
    $brand->syncBusinessCategories(['Food & Beverage']);
    $brand->links()->createMany([
        ['label' => 'Website', 'url' => 'https://acme.test', 'order' => 0, 'is_active' => true],
        ['label' => 'Instagram', 'url' => 'https://instagram.com/acme', 'order' => 1, 'is_active' => true],
    ]);

    $post = PromotionPost::factory()->create(['brand_event_id' => $brandEvent->id, 'caption' => 'Hello world']);
    $post->addMedia(UploadedFile::fake()->image('post.jpg', 600, 600))->toMediaCollection('post_image');

    $result = scoreFor($brandEvent);

    expect($result['score'])->toBe(100);
    expect($result['is_complete'])->toBeTrue();
    expect(collect($result['breakdown'])->every(fn ($b) => $b['filled'] === true))->toBeTrue();
});

it('reads promotion counts from withCount aggregates without loading the relation', function () {
    $brandEvent = emptyBrandEvent();

    $fresh = $brandEvent->fresh(['brand.media', 'brand.tags', 'brand.links']);
    $fresh->setAttribute('posts_with_image_count', 1);
    $fresh->setAttribute('posts_with_caption_count', 1);

    $result = app(BrandProfileScoreService::class)->score($fresh);

    expect($fresh->relationLoaded('promotionPosts'))->toBeFalse();
    // promotion image 14 + promotion caption 8 = 22
    expect($result['score'])->toBe(22);
    expect(collect($result['breakdown'])->firstWhere('key', 'promotion_image')['filled'])->toBeTrue();
    expect(collect($result['breakdown'])->firstWhere('key', 'promotion_caption')['filled'])->toBeTrue();
});

it('treats an html-only description as empty', function () {
    $result = scoreFor(emptyBrandEvent(['description' => '<p></p>']));

    expect(collect($result['breakdown'])->firstWhere('key', 'description')['filled'])->toBeFalse();
    expect($result['score'])->toBe(0);
});

it('awards full link credit only at two or more active links', function () {
    $brandEvent = emptyBrandEvent();
    $brand = $brandEvent->brand;
    $brand->links()->createMany([
        ['label' => 'Website', 'url' => 'https://a.test', 'order' => 0, 'is_active' => true],
        ['label' => 'Instagram', 'url' => 'https://instagram.com/a', 'order' => 1, 'is_active' => true],
    ]);

    $result = scoreFor($brandEvent);

    // two links = full 12
    expect($result['score'])->toBe(12);
});
