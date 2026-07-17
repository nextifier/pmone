<?php

use App\Models\Attendee;
use App\Models\Brand;
use App\Models\Contact;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Project;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\TicketOrder;
use App\Models\TicketWaitlistEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('normalizes reservation guest fields on create', function () {
    $reservation = Reservation::factory()->create([
        'guest_name' => 'JOHN EDWARD BENNETT',
        'guest_email' => 'JEBENNETT@HERSHEYS.COM',
        'guest_phone' => '0812 3456 7890',
        'guest_company' => '  The Hershey   Company ',
    ]);

    expect($reservation->guest_name)->toBe('John Edward Bennett')
        ->and($reservation->guest_email)->toBe('jebennett@hersheys.com')
        ->and($reservation->guest_phone)->toBe('+6281234567890')
        ->and($reservation->guest_company)->toBe('The Hershey Company');
});

it('normalizes reservation guest fields on update but preserves mixed-case names', function () {
    $reservation = Reservation::factory()->create();

    $reservation->update([
        'guest_name' => 'Alistair McDonald',
        'guest_email' => 'Alistair.McDonald@Example.COM',
    ]);

    expect($reservation->refresh()->guest_name)->toBe('Alistair McDonald')
        ->and($reservation->guest_email)->toBe('alistair.mcdonald@example.com');
});

it('normalizes reservation item guest names', function () {
    $item = ReservationItem::factory()->create(['guest_name' => 'yuana purnama sari']);

    expect($item->guest_name)->toBe('Yuana Purnama Sari');
});

it('normalizes ticket order buyer fields', function () {
    $order = TicketOrder::factory()->create([
        'buyer_name' => 'ISAAC YIP HAO EE',
        'buyer_email' => 'HAOEE1234@GMAIL.COM',
        'buyer_phone' => '081234567890',
    ]);

    expect($order->buyer_name)->toBe('Isaac Yip Hao Ee')
        ->and($order->buyer_email)->toBe('haoee1234@gmail.com')
        ->and($order->buyer_phone)->toBe('+6281234567890');
});

it('normalizes attendee fields', function () {
    $attendee = Attendee::factory()->create([
        'name' => 'ani setiyoningrum',
        'email' => 'ANI.SETIYONINGRUM@GMAIL.COM',
        'phone' => '0812-1111-2222',
    ]);

    expect($attendee->name)->toBe('Ani Setiyoningrum')
        ->and($attendee->email)->toBe('ani.setiyoningrum@gmail.com')
        ->and($attendee->phone)->toBe('+6281211112222');
});

it('normalizes ticket waitlist entries', function () {
    $entry = TicketWaitlistEntry::factory()->create([
        'name' => 'CHRIS VINCENT',
        'email' => 'Chris.Vincent@WorldCocoa.ORG',
        'phone' => '081298765432',
    ]);

    expect($entry->name)->toBe('Chris Vincent')
        ->and($entry->email)->toBe('chris.vincent@worldcocoa.org')
        ->and($entry->phone)->toBe('+6281298765432');
});

it('normalizes user name, email, and phone', function () {
    $user = User::factory()->create([
        'name' => 'masahide wada',
        'email' => 'Masahide.Wada@GLICO.com',
        'phone' => '0811 222 333',
    ]);

    expect($user->name)->toBe('Masahide Wada')
        ->and($user->email)->toBe('masahide.wada@glico.com')
        ->and($user->phone)->toBe('+62811222333');
});

it('preserves intentional mixed-case user names', function () {
    $user = User::factory()->create(['name' => 'Erwan de Saint Mars']);

    expect($user->name)->toBe('Erwan de Saint Mars');
});

it('normalizes contact email and phone lists', function () {
    $contact = Contact::factory()->create([
        'name' => 'TAKANORI YUI',
        'emails' => ['Takanori.Yui@GLICO.com', '  '],
        'phones' => ['0812 3456 7890'],
        'company_name' => 'PT GLOBAL  NIAGA',
    ]);

    expect($contact->name)->toBe('Takanori Yui')
        ->and($contact->emails)->toBe(['takanori.yui@glico.com'])
        ->and($contact->phones)->toBe(['+6281234567890'])
        ->and($contact->company_name)->toBe('PT GLOBAL NIAGA');
});

it('normalizes hotel contact details but never the hotel name', function () {
    $hotel = Hotel::factory()->create([
        'name' => 'THE 101 Yogyakarta Tugu Hotel',
        'contact_email' => 'RESERVATION@The101.com',
        'contact_phone' => '0274 123456',
    ]);

    expect($hotel->name)->toBe('THE 101 Yogyakarta Tugu Hotel')
        ->and($hotel->contact_email)->toBe('reservation@the101.com')
        ->and($hotel->contact_phone)->toBe('+62274123456');
});

it('normalizes brand company contact details but never casing of the company name', function () {
    $brand = Brand::factory()->create([
        'company_name' => '  HERSHEYS   INDONESIA ',
        'company_email' => 'Info@Hersheys.COM',
    ]);

    expect($brand->company_name)->toBe('HERSHEYS INDONESIA')
        ->and($brand->company_email)->toBe('info@hersheys.com');
});

it('normalizes project email and labeled phone entries', function () {
    $project = Project::factory()->create([
        'email' => 'Hello@Askindo.ORG',
        'phone' => [['label' => 'WhatsApp Sales', 'number' => '0812 3456 7890']],
    ]);

    expect($project->email)->toBe('hello@askindo.org')
        ->and($project->phone)->toBe([['label' => 'WhatsApp Sales', 'number' => '+6281234567890']]);
});

it('normalizes guest speaker names', function () {
    $guest = Guest::factory()->create(['name' => 'ALBERTHINOES PAKENDEN']);

    expect($guest->name)->toBe('Alberthinoes Pakenden');
});
