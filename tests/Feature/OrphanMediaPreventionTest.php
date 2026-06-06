<?php

use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventProduct;
use App\Models\EventProductCategory;
use App\Models\Form;
use App\Models\Hotel;
use App\Models\LinkPage;
use App\Models\LinkPageBanner;
use App\Models\LinkPageItem;
use App\Models\Project;
use App\Models\ProjectBanner;
use App\Models\PromotionPost;
use App\Models\RoomType;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

/**
 * Attach a fake image to a model and return its relative disk path.
 */
function attachMediaPath($model): string
{
    $media = $model->addMedia(UploadedFile::fake()->image('photo.jpg', 120, 120))
        ->toMediaCollection('test_media');

    return $media->getPathRelativeToRoot();
}

it('force-deleting an event removes media of its products, categories and documents', function () {
    $event = Event::factory()->create();
    $category = EventProductCategory::factory()->create(['event_id' => $event->id]);
    $product = EventProduct::factory()->create(['event_id' => $event->id, 'category_id' => $category->id]);
    $document = EventDocument::factory()->create(['event_id' => $event->id]);

    $paths = [attachMediaPath($product), attachMediaPath($category), attachMediaPath($document)];
    foreach ($paths as $path) {
        expect(Storage::disk('public')->exists($path))->toBeTrue();
    }

    $event->forceDelete();

    foreach ($paths as $path) {
        expect(Storage::disk('public')->exists($path))->toBeFalse();
    }
});

it('force-deleting a brand removes media down the brand-event → promotion-post chain', function () {
    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create(['brand_id' => $brand->id]);
    $post = PromotionPost::factory()->create(['brand_event_id' => $brandEvent->id]);

    $brandEventPath = attachMediaPath($brandEvent);
    $postPath = attachMediaPath($post);

    $brand->forceDelete();

    expect(Storage::disk('public')->exists($brandEventPath))->toBeFalse();
    expect(Storage::disk('public')->exists($postPath))->toBeFalse();
});

it('force-deleting an event also clears its brand-events promotion-post media', function () {
    $event = Event::factory()->create();
    $brandEvent = BrandEvent::factory()->create(['event_id' => $event->id]);
    $post = PromotionPost::factory()->create(['brand_event_id' => $brandEvent->id]);

    $postPath = attachMediaPath($post);

    $event->forceDelete();

    expect(Storage::disk('public')->exists($postPath))->toBeFalse();
});

it('force-deleting a project removes its banners media', function () {
    $project = Project::factory()->create();
    $banner = ProjectBanner::factory()->create(['project_id' => $project->id]);
    $path = attachMediaPath($banner);

    $project->forceDelete();

    expect(Storage::disk('public')->exists($path))->toBeFalse();
});

it('force-deleting a hotel removes its room types media', function () {
    $hotel = Hotel::factory()->create();
    $roomType = RoomType::factory()->create(['hotel_id' => $hotel->id]);
    $path = attachMediaPath($roomType);

    $hotel->forceDelete();

    expect(Storage::disk('public')->exists($path))->toBeFalse();
});

it('force-deleting a link page removes its items and banners media', function () {
    $linkPage = LinkPage::factory()->create();
    $item = LinkPageItem::factory()->create(['link_page_id' => $linkPage->id]);
    $banner = LinkPageBanner::factory()->create(['link_page_id' => $linkPage->id]);

    $itemPath = attachMediaPath($item);
    $bannerPath = attachMediaPath($banner);

    $linkPage->forceDelete();

    expect(Storage::disk('public')->exists($itemPath))->toBeFalse();
    expect(Storage::disk('public')->exists($bannerPath))->toBeFalse();
});

it('soft-deleting an event preserves its children media (restorable)', function () {
    $event = Event::factory()->create();
    $product = EventProduct::factory()->create(['event_id' => $event->id]);
    $path = attachMediaPath($product);

    $event->delete(); // soft delete

    expect(Storage::disk('public')->exists($path))->toBeTrue();
    expect(EventProduct::query()->whereKey($product->id)->exists())->toBeTrue();
});

it('permanently deleting a user removes media of tasks and forms they created', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');
    $this->actingAs($admin, 'sanctum');

    $victim = User::factory()->create(['email_verified_at' => now()]);
    $task = Task::factory()->create(['created_by' => $victim->id]);
    $form = Form::factory()->create(['created_by' => $victim->id, 'user_id' => $victim->id]);
    $taskPath = attachMediaPath($task);
    $formPath = attachMediaPath($form);

    $victim->delete(); // soft-delete first (forceDestroy operates on trashed users)

    $this->deleteJson("/api/users/trash/{$victim->id}")->assertSuccessful();

    expect(Storage::disk('public')->exists($taskPath))->toBeFalse();
    expect(Storage::disk('public')->exists($formPath))->toBeFalse();
    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    $this->assertDatabaseMissing('forms', ['id' => $form->id]);
});
