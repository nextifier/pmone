<?php

use App\Models\AppliedAdjustment;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventDocumentSubmission;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Project;
use App\Models\PromotionPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.sheets.api_token' => 'test-sheets-token']);
    $this->token = 'test-sheets-token';
});

it('rejects requests without a valid token', function () {
    $this->getJson('/api/sheets/brands?token=wrong')->assertStatus(401);
    $this->getJson('/api/sheets/brand-events?token=wrong')->assertStatus(401);
    $this->getJson('/api/sheets/operational-documents?token=wrong')->assertStatus(401);
});

it('renders brand custom fields as typed columns, not a json blob', function () {
    $project = Project::factory()->create();
    $field = CustomField::factory()->brand($project)
        ->type(CustomField::TYPE_SELECT)
        ->create([
            'label' => ['en' => 'Business Concept'],
            'key' => 'business_concept',
            'options' => [
                ['value' => 'fnb', 'label' => 'Food & Beverage'],
                ['value' => 'retail', 'label' => 'Retail'],
            ],
        ]);

    $brand = Brand::factory()->create(['custom_fields' => ['business_concept' => 'fnb']]);
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => Event::factory()->create(['project_id' => $project->id])->id,
    ]);

    $response = $this->getJson("/api/sheets/brands?token={$this->token}")->assertSuccessful();

    $headings = $response->json('headings');
    expect($headings)->not->toContain('Custom Fields');
    expect($headings)->toContain('Business Concept');
    expect($headings)->toContain('Profile Image URL');

    $col = array_search('Business Concept', $headings, true);
    expect($response->json("rows.0.{$col}"))->toBe('Food & Beverage');
});

it('maps same-label brand fields from different projects to one column', function () {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();

    // Same label, different storage keys across two projects.
    CustomField::factory()->brand($projectA)->type(CustomField::TYPE_TEXT)->create([
        'label' => ['en' => 'Buyer Target'],
        'key' => 'buyer_target',
    ]);
    CustomField::factory()->brand($projectB)->type(CustomField::TYPE_TEXT)->create([
        'label' => ['en' => 'Buyer Target'],
        'key' => 'buyer_target_2',
    ]);

    $brandA = Brand::factory()->create(['custom_fields' => ['buyer_target' => 'Investors']]);
    BrandEvent::factory()->create([
        'brand_id' => $brandA->id,
        'event_id' => Event::factory()->create(['project_id' => $projectA->id])->id,
    ]);

    $brandB = Brand::factory()->create(['custom_fields' => ['buyer_target_2' => 'Distributors']]);
    BrandEvent::factory()->create([
        'brand_id' => $brandB->id,
        'event_id' => Event::factory()->create(['project_id' => $projectB->id])->id,
    ]);

    $response = $this->getJson("/api/sheets/brands?token={$this->token}")->assertSuccessful();
    $headings = $response->json('headings');

    // Only one "Buyer Target" column.
    expect(collect($headings)->filter(fn ($h) => $h === 'Buyer Target')->count())->toBe(1);

    // Each brand's value is read from whichever key holds it (coalesced).
    $col = array_search('Buyer Target', $headings, true);
    $rows = collect($response->json('rows'));
    $nameCol = array_search('Name', $headings, true);
    $rowA = $rows->first(fn ($r) => $r[$nameCol] === $brandA->name);
    $rowB = $rows->first(fn ($r) => $r[$nameCol] === $brandB->name);
    expect($rowA[$col])->toBe('Investors');
    expect($rowB[$col])->toBe('Distributors');
});

it('uses the canonical lowest-id definition for same-label select options', function () {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();

    // Older field (lower id) holds the real option label for value "inv".
    CustomField::factory()->brand($projectA)->type(CustomField::TYPE_SELECT)->create([
        'label' => ['en' => 'Buyer Target'],
        'key' => 'buyer_target',
        'options' => [['value' => 'inv', 'label' => 'Investors']],
    ]);
    // Newer duplicate maps the same value to a junk label.
    CustomField::factory()->brand($projectB)->type(CustomField::TYPE_SELECT)->create([
        'label' => ['en' => 'Buyer Target'],
        'key' => 'buyer_target_dup',
        'options' => [['value' => 'inv', 'label' => 'JUNK']],
    ]);

    $brand = Brand::factory()->create(['custom_fields' => ['buyer_target' => 'inv']]);
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => Event::factory()->create(['project_id' => $projectA->id])->id,
    ]);

    $response = $this->getJson("/api/sheets/brands?token={$this->token}")->assertSuccessful();
    $headings = $response->json('headings');
    $col = array_search('Buyer Target', $headings, true);
    $nameCol = array_search('Name', $headings, true);
    $row = collect($response->json('rows'))->first(fn ($r) => $r[$nameCol] === $brand->name);

    expect($row[$col])->toBe('Investors');
});

it('drops the doc and custom-field json columns from the brand-events sheet', function () {
    $project = Project::factory()->create();
    $event = Event::factory()->create(['project_id' => $project->id]);
    $brand = Brand::factory()->create();
    BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $event->id,
        'custom_fields' => ['cluster' => 'A'],
    ]);
    EventDocument::factory()->create([
        'event_id' => $event->id,
        'title' => 'Booth Layout',
        'document_type' => 'file_upload',
        'booth_types' => null,
    ]);

    $headings = $this->getJson("/api/sheets/brand-events?token={$this->token}")
        ->assertSuccessful()
        ->json('headings');

    foreach ($headings as $heading) {
        expect($heading)->not->toContain('Custom Fields');
        expect(str_starts_with($heading, 'Doc: '))->toBeFalse();
    }
    expect($headings)->toContain('Profile Image URL');
    expect($headings)->toContain('Cluster'); // untyped brandEvent field surfaced
});

it('adds event, country, source and adjustment reason to the orders sheet', function () {
    $project = Project::factory()->create();
    $event = Event::factory()->create(['project_id' => $project->id, 'title' => 'Expo 2026']);
    $brand = Brand::factory()->create([
        'company_name' => 'PT Contoh',
        'address' => ['country' => 'Indonesia', 'province' => '', 'city' => '', 'street' => ''],
    ]);
    $brandEvent = BrandEvent::factory()->create(['brand_id' => $brand->id, 'event_id' => $event->id]);
    $order = Order::factory()->create([
        'brand_event_id' => $brandEvent->id,
        'source' => 'staff',
        'submitted_at' => now(),
    ]);
    AppliedAdjustment::create([
        'adjustable_type' => Order::class,
        'adjustable_id' => $order->id,
        'kind' => 'discount',
        'label' => 'Loyalty discount',
        'value_type' => 'fixed_amount',
        'value' => 100,
        'base_amount' => 1000,
        'amount' => 100,
        'rule_snapshot' => ['reason' => 'Repeat exhibitor'],
        'applied_by' => User::factory()->create()->id,
    ]);

    $response = $this->getJson("/api/sheets/orders?token={$this->token}")->assertSuccessful();
    $headings = $response->json('headings');

    expect($headings)->toContain('Event ID');
    expect($headings)->toContain('Event Title');
    expect($headings)->toContain('Country');
    expect($headings)->toContain('Source');
    expect($headings)->toContain('Order Adjustment Reason');

    $reasonCol = array_search('Order Adjustment Reason', $headings, true);
    $countryCol = array_search('Country', $headings, true);
    $sourceCol = array_search('Source', $headings, true);
    expect($response->json("rows.0.{$reasonCol}"))->toBe('Repeat exhibitor');
    expect($response->json("rows.0.{$countryCol}"))->toBe('Indonesia');
    expect($response->json("rows.0.{$sourceCol}"))->toBe('Staff');
});

it('reports a per-item discount on its own line and marks the first line of each order', function () {
    $project = Project::factory()->create();
    $event = Event::factory()->create(['project_id' => $project->id]);
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => Brand::factory()->create()->id,
        'event_id' => $event->id,
    ]);
    $order = Order::factory()->create([
        'brand_event_id' => $brandEvent->id,
        'subtotal' => 3000000,
        'discount_amount' => 200000,
        'submitted_at' => now(),
    ]);

    $discounted = OrderItem::create([
        'order_id' => $order->id,
        'product_name' => 'Booth Lighting',
        'unit_price' => 2000000,
        'quantity' => 1,
        'total_price' => 2000000,
    ]);
    $untouched = OrderItem::create([
        'order_id' => $order->id,
        'product_name' => 'Extra Chair',
        'unit_price' => 1000000,
        'quantity' => 1,
        'total_price' => 1000000,
    ]);

    $staff = User::factory()->create();

    AppliedAdjustment::create([
        'adjustable_type' => Order::class,
        'adjustable_id' => $order->id,
        'order_item_id' => $discounted->id,
        'kind' => 'discount',
        'label' => 'Lighting promo',
        'value_type' => 'fixed_amount',
        'value' => 200000,
        'base_amount' => 2000000,
        'amount' => 200000,
        'applied_by' => $staff->id,
    ]);

    // A voided one on the same item must not show: its amount is kept as an
    // audit record, not as a live discount.
    AppliedAdjustment::create([
        'adjustable_type' => Order::class,
        'adjustable_id' => $order->id,
        'order_item_id' => $discounted->id,
        'kind' => 'discount',
        'label' => 'Cancelled promo',
        'value_type' => 'fixed_amount',
        'value' => 500000,
        'base_amount' => 2000000,
        'amount' => 500000,
        'applied_by' => $staff->id,
        'voided_at' => now(),
        'void_reason' => 'admin_voided',
    ]);

    $response = $this->getJson("/api/sheets/orders?token={$this->token}")->assertSuccessful();
    $headings = $response->json('headings');
    $rows = $response->json('rows');

    expect($rows)->toHaveCount(2);
    expect($rows[0])->toHaveCount(count($headings));

    $col = fn (string $heading) => array_search($heading, $headings, true);

    $byProduct = collect($rows)->keyBy(fn ($row) => $row[$col('Product Name')]);

    // JSON collapses a float with no fraction back to an int, so compare numerically.
    $lit = $byProduct['Booth Lighting'];
    expect((float) $lit[$col('Item Discount')])->toBe(200000.0)
        ->and((float) $lit[$col('Item Penalty')])->toBe(0.0)
        ->and((float) $lit[$col('Item Net Total')])->toBe(1800000.0)
        ->and($lit[$col('Item Adjustment Note')])->toBe('Lighting promo');

    $chair = $byProduct['Extra Chair'];
    expect((float) $chair[$col('Item Discount')])->toBe(0.0)
        ->and((float) $chair[$col('Item Net Total')])->toBe(1000000.0)
        ->and($chair[$col('Item Adjustment Note')])->toBe('-');

    // The order-level total repeats on both rows, so exactly one row is flagged
    // as the one to sum.
    expect(collect($rows)->pluck($col('Is First Line'))->filter()->count())->toBe(1);
    expect(collect($rows)->pluck($col('Line #'))->all())->toBe([1, 2]);
    expect(collect($rows)->pluck($col('Order Discount Amount'))->map(fn ($v) => (float) $v)->unique()->all())->toBe([200000.0]);

    // Item-scoped adjustments are reported on their line, not repeated in the
    // order-level reason column.
    expect(collect($rows)->pluck($col('Order Adjustment Reason'))->unique()->all())->toBe(['-']);
});

it('returns orders across every event, unscoped', function () {
    $eventA = Event::factory()->create(['title' => 'Event A']);
    $eventB = Event::factory()->create(['title' => 'Event B']);

    $brandEventA = BrandEvent::factory()->create(['event_id' => $eventA->id]);
    $brandEventB = BrandEvent::factory()->create(['event_id' => $eventB->id]);

    $orderA = Order::factory()->create(['brand_event_id' => $brandEventA->id, 'submitted_at' => now()]);
    $orderB = Order::factory()->create(['brand_event_id' => $brandEventB->id, 'submitted_at' => now()]);

    $response = $this->getJson("/api/sheets/orders?token={$this->token}")->assertSuccessful();

    $headings = $response->json('headings');
    $orderNumberCol = array_search('Order Number', $headings, true);
    $eventTitleCol = array_search('Event Title', $headings, true);

    $orderNumbers = collect($response->json('rows'))->pluck($orderNumberCol);
    expect($orderNumbers)->toContain($orderA->order_number)
        ->toContain($orderB->order_number);

    $eventTitles = collect($response->json('rows'))->pluck($eventTitleCol)->unique()->values();
    expect($eventTitles)->toContain('Event A')->toContain('Event B');

    expect($response->json('title'))->toBe('Orders');
});

it('lists a row per applicable document with file history', function () {
    Storage::fake('public');

    $project = Project::factory()->create();
    $event = Event::factory()->create(['project_id' => $project->id]);
    $brand = Brand::factory()->create();
    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => $brand->id,
        'event_id' => $event->id,
        'booth_number' => 'A-01',
    ]);

    $document = EventDocument::factory()->create([
        'event_id' => $event->id,
        'title' => 'Insurance',
        'document_type' => 'file_upload',
        'booth_types' => null,
    ]);

    $submission = EventDocumentSubmission::factory()->create([
        'event_document_id' => $document->id,
        'event_id' => $event->id,
        'booth_identifier' => 'A-01',
        'document_version' => $document->content_version ?? 1,
    ]);

    // Two versions: v1 superseded, v2 current.
    $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
    $submission->addMedia(UploadedFile::fake()->createWithContent('v1.pdf', $pdf))
        ->withCustomProperties(['version' => 1, 'superseded_at' => now()->subDay()->toIso8601String(), 'uploaded_by_name' => 'Jane'])
        ->toMediaCollection('submission_file');
    $submission->addMedia(UploadedFile::fake()->createWithContent('v2.pdf', $pdf))
        ->withCustomProperties(['version' => 2, 'uploaded_by_name' => 'Jane'])
        ->toMediaCollection('submission_file');

    $response = $this->getJson("/api/sheets/operational-documents?token={$this->token}")->assertSuccessful();
    $headings = $response->json('headings');

    expect($headings)->toContain('File History');
    expect($headings)->toContain('Document Kind');

    $rows = $response->json('rows');
    expect($rows)->toHaveCount(1);

    $historyCol = array_search('File History', $headings, true);
    $versionsCol = array_search('File Versions Count', $headings, true);
    expect($rows[0][$versionsCol])->toBe(2);
    expect($rows[0][$historyCol])->toContain('v2 v2');
    expect($rows[0][$historyCol])->toContain('v1 v1');
    expect($rows[0][$historyCol])->toContain('(current)');
    expect($rows[0][$historyCol])->toContain('(superseded)');
});

it('links the brand-events sheet to a downloadable promotion image bundle', function () {
    Storage::fake('public');

    $event = Event::factory()->create(['project_id' => Project::factory()->create()->id]);

    $withoutImages = BrandEvent::factory()->create([
        'brand_id' => Brand::factory()->create(['name' => 'No Images'])->id,
        'event_id' => $event->id,
    ]);
    $withOne = BrandEvent::factory()->create([
        'brand_id' => Brand::factory()->create(['name' => 'One Image'])->id,
        'event_id' => $event->id,
    ]);
    $withMany = BrandEvent::factory()->create([
        'brand_id' => Brand::factory()->create(['name' => 'Many Images'])->id,
        'event_id' => $event->id,
    ]);

    PromotionPost::factory()->create(['brand_event_id' => $withOne->id])
        ->addMedia(UploadedFile::fake()->image('poster.jpg'))->toMediaCollection('post_image');

    // Two posts, so the bundle has to gather media across posts, not just within one.
    PromotionPost::factory()->create(['brand_event_id' => $withMany->id])
        ->addMedia(UploadedFile::fake()->image('first.jpg'))->toMediaCollection('post_image');
    PromotionPost::factory()->create(['brand_event_id' => $withMany->id])
        ->addMedia(UploadedFile::fake()->image('second.jpg'))->toMediaCollection('post_image');

    $response = $this->getJson("/api/sheets/brand-events?token={$this->token}")->assertSuccessful();
    $headings = $response->json('headings');
    $rows = collect($response->json('rows'));

    $withOne->load('promotionPosts.media');

    expect($headings)->toContain('Promotion Post Image Link');

    $nameCol = array_search('Brand Name', $headings, true);
    $linkCol = array_search('Promotion Post Image Link', $headings, true);
    $byBrand = $rows->keyBy(fn ($row) => $row[$nameCol]);

    expect($byBrand['No Images'][$linkCol])->toBe('-');

    // A lone image points at the original file, not at this app - nothing to zip,
    // so no reason to make the spreadsheet round-trip through a rate-limited
    // endpoint. Only the multi-image case needs the download route.
    $lone = $withOne->promotionPosts->first()->getFirstMedia('post_image');
    expect($byBrand['One Image'][$linkCol])->toBe($lone->getUrl());
    expect($byBrand['One Image'][$linkCol])->not->toContain('promotion-images');
    expect($byBrand['One Image'][$linkCol])->not->toContain('conversions');

    expect($byBrand['Many Images'][$linkCol])->toContain("/brand-events/{$withMany->id}/promotion-images");
    expect($byBrand['Many Images'][$linkCol])->toContain("token={$this->token}");

    // The endpoint still serves a single image on its own, for anyone who reaches
    // it directly, and zips the rest.
    $single = $this->get("/api/sheets/brand-events/{$withOne->id}/promotion-images?token={$this->token}")
        ->assertSuccessful();
    expect($single->headers->get('content-type'))->toStartWith('image/');

    $bundle = $this->get("/api/sheets/brand-events/{$withMany->id}/promotion-images?token={$this->token}")
        ->assertSuccessful();
    expect($bundle->headers->get('content-type'))->toContain('zip');
    expect($bundle->headers->get('content-disposition'))->toContain('.zip');

    $this->get("/api/sheets/brand-events/{$withoutImages->id}/promotion-images?token={$this->token}")
        ->assertNotFound();
    $this->get("/api/sheets/brand-events/{$withOne->id}/promotion-images?token=wrong")
        ->assertStatus(401);
});

it('carries promotion post captions next to the post count', function () {
    $event = Event::factory()->create(['project_id' => Project::factory()->create()->id]);

    $withCaptions = BrandEvent::factory()->create([
        'brand_id' => Brand::factory()->create(['name' => 'Two Captions'])->id,
        'event_id' => $event->id,
    ]);
    $blankCaption = BrandEvent::factory()->create([
        'brand_id' => Brand::factory()->create(['name' => 'Blank Caption'])->id,
        'event_id' => $event->id,
    ]);
    $noPosts = BrandEvent::factory()->create([
        'brand_id' => Brand::factory()->create(['name' => 'No Posts'])->id,
        'event_id' => $event->id,
    ]);

    PromotionPost::factory()->create(['brand_event_id' => $withCaptions->id, 'caption' => 'First caption']);
    PromotionPost::factory()->create(['brand_event_id' => $withCaptions->id, 'caption' => 'Second caption']);
    PromotionPost::factory()->create(['brand_event_id' => $blankCaption->id, 'caption' => null]);

    $response = $this->getJson("/api/sheets/brand-events?token={$this->token}")->assertSuccessful();
    $headings = $response->json('headings');
    $rows = collect($response->json('rows'));

    $countCol = array_search('Promotion Posts Count', $headings, true);
    $captionCol = array_search('Caption Promotion Post', $headings, true);

    // The column sits between the count and the image link, which is where the
    // spreadsheet the organisers work in expects it.
    expect($captionCol)->toBe($countCol + 1);
    expect($headings[$captionCol + 1])->toBe('Promotion Post Image Link');

    $nameCol = array_search('Brand Name', $headings, true);
    $byBrand = $rows->keyBy(fn ($row) => $row[$nameCol]);

    expect($byBrand['Two Captions'][$captionCol])->toBe("First caption\nSecond caption");
    expect($byBrand['Blank Caption'][$captionCol])->toBe('-');
    expect($byBrand['No Posts'][$captionCol])->toBe('-');
});

/**
 * A brand with the given links attached, label => url.
 *
 * @param  array<string, string>  $links
 */
function brandWithLinks(string $name, array $links): Brand
{
    $brand = Brand::factory()->create(['name' => $name]);

    foreach (array_values($links) as $order => $url) {
        $brand->links()->create([
            'label' => array_keys($links)[$order],
            'url' => $url,
            'order' => $order,
        ]);
    }

    return $brand;
}

it('pins the seven predefined link columns even when the data has none of them', function () {
    $event = Event::factory()->create(['project_id' => Project::factory()->create()->id]);
    BrandEvent::factory()->create([
        'brand_id' => brandWithLinks('Instagram Only', ['Instagram' => 'https://instagram.com/only'])->id,
        'event_id' => $event->id,
    ]);

    foreach (['brands', 'brand-events'] as $sheet) {
        $headings = $this->getJson("/api/sheets/{$sheet}?token={$this->token}")
            ->assertSuccessful()
            ->json('headings');

        $countCol = array_search($sheet === 'brands' ? 'Links Count' : 'Brand Links Count', $headings, true);

        expect(array_slice($headings, $countCol + 1, 7))
            ->toBe(['Website', 'Instagram', 'Facebook', 'X', 'TikTok', 'LinkedIn', 'YouTube'])
            ->and(array_slice($headings, $countCol + 8, 7))
            ->toBe([
                'Website Click', 'Instagram Click', 'Facebook Click',
                'X Click', 'TikTok Click', 'LinkedIn Click', 'YouTube Click',
            ]);
    }
});

it('appends custom link labels after the predefined ones', function () {
    $event = Event::factory()->create(['project_id' => Project::factory()->create()->id]);
    BrandEvent::factory()->create([
        'brand_id' => brandWithLinks('Custom Label', [
            'Brosur Franchise' => 'https://drive.google.com/file/d/abc/view',
            'Website' => 'https://custom.example',
        ])->id,
        'event_id' => $event->id,
    ]);

    $headings = $this->getJson("/api/sheets/brand-events?token={$this->token}")
        ->assertSuccessful()
        ->json('headings');

    $youtubeCol = array_search('YouTube', $headings, true);

    expect($headings[$youtubeCol + 1])->toBe('Brosur Franchise')
        ->and($headings[array_search('YouTube Click', $headings, true) + 1])->toBe('Brosur Franchise Click');
});

it('fills a predefined link column whatever case the label was saved in', function () {
    $event = Event::factory()->create(['project_id' => Project::factory()->create()->id]);
    BrandEvent::factory()->create([
        'brand_id' => brandWithLinks('Lowercase Label', ['instagram' => 'https://instagram.com/lower'])->id,
        'event_id' => $event->id,
    ]);

    $response = $this->getJson("/api/sheets/brand-events?token={$this->token}")->assertSuccessful();
    $headings = $response->json('headings');
    $row = $response->json('rows.0');

    // One canonical column, not a second lowercase one beside it.
    expect(array_count_values($headings)['Instagram'])->toBe(1)
        ->and($row[array_search('Instagram', $headings, true)])->toBe('https://instagram.com/lower')
        ->and($row[array_search('Website', $headings, true)])->toBe('');
});

it('reports participation status on the orders and operational documents sheets', function () {
    $project = Project::factory()->create();
    $event = Event::factory()->create(['project_id' => $project->id]);

    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => Brand::factory()->create(['name' => 'Draft Brand'])->id,
        'event_id' => $event->id,
        'booth_number' => 'A-01',
        'status' => 'draft',
    ]);

    $order = Order::factory()->create(['brand_event_id' => $brandEvent->id]);
    OrderItem::create([
        'order_id' => $order->id,
        'product_name' => 'Booth Lighting',
        'unit_price' => 2000000,
        'quantity' => 1,
        'total_price' => 2000000,
    ]);

    EventDocument::factory()->create(['event_id' => $event->id, 'title' => 'Booth Design']);

    $orders = $this->getJson("/api/sheets/orders?token={$this->token}")->assertSuccessful();
    $orderHeadings = $orders->json('headings');
    $orderCol = array_search('Participation Status', $orderHeadings, true);

    expect($orderHeadings[array_search('Sales PIC', $orderHeadings, true) + 1])->toBe('Participation Status')
        ->and($orders->json('rows.0')[$orderCol])->toBe('Draft');

    $docs = $this->getJson("/api/sheets/operational-documents?token={$this->token}")->assertSuccessful();
    $docHeadings = $docs->json('headings');
    $docCol = array_search('Participation Status', $docHeadings, true);

    expect($docHeadings[array_search('Booth Type', $docHeadings, true) + 1])->toBe('Participation Status')
        ->and($docs->json('rows.0')[$docCol])->toBe('Draft');
});

it('keeps every sheet row as wide as its heading list', function () {
    $project = Project::factory()->create();
    $event = Event::factory()->create(['project_id' => $project->id]);

    $brandEvent = BrandEvent::factory()->create([
        'brand_id' => brandWithLinks('Wide Row', [
            'Website' => 'https://wide.example',
            'Brosur Franchise' => 'https://drive.google.com/file/d/abc/view',
        ])->id,
        'event_id' => $event->id,
        'booth_number' => 'A-01',
    ]);

    $order = Order::factory()->create(['brand_event_id' => $brandEvent->id]);
    OrderItem::create([
        'order_id' => $order->id,
        'product_name' => 'Booth Lighting',
        'unit_price' => 2000000,
        'quantity' => 1,
        'total_price' => 2000000,
    ]);
    EventDocument::factory()->create(['event_id' => $event->id, 'title' => 'Booth Design']);

    foreach (['brands', 'brand-events', 'orders', 'operational-documents'] as $sheet) {
        $response = $this->getJson("/api/sheets/{$sheet}?token={$this->token}")->assertSuccessful();

        expect($response->json('rows.0'))
            ->toHaveCount(count($response->json('headings')), "sheet: {$sheet}");
    }
});
