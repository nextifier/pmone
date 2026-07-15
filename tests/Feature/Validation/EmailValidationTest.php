<?php

use App\Rules\AllowedEmailDomain;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Email;

it('rejects reserved and disposable domains', function (string $email) {
    $validator = Validator::make(['email' => $email], ['email' => [new AllowedEmailDomain]]);

    expect($validator->fails())->toBeTrue();
})->with([
    'reserved example.com' => 'user@example.com',
    'reserved subdomain' => 'user@mail.example.org',
    'reserved .test tld' => 'user@anything.test',
    'reserved .invalid tld' => 'user@domain.invalid',
    'filler test.com' => 'user@test.com',
    'disposable mailinator' => 'user@mailinator.com',
    'disposable yopmail' => 'user@yopmail.com',
]);

it('accepts ordinary real-world domains', function (string $email) {
    $validator = Validator::make(['email' => $email], ['email' => [new AllowedEmailDomain]]);

    expect($validator->passes())->toBeTrue();
})->with([
    'gmail' => 'user@gmail.com',
    'company domain' => 'user@panoramamedia.co.id',
    // "example" only counts as a domain or tld, not as a fragment of one.
    'domain containing example' => 'user@examplestore.com',
]);

it('leaves format failures to the email rule itself', function () {
    $validator = Validator::make(['email' => 'not-an-email'], ['email' => [new AllowedEmailDomain]]);

    expect($validator->passes())->toBeTrue();
});

it('keeps the shared email default lenient outside production', function () {
    // Factories generate @example.com addresses and CI runs offline, so the
    // MX check and the domain blocklist must only bind in production.
    $validator = Validator::make(['email' => 'user@example.com'], ['email' => Email::default()]);

    expect($validator->passes())->toBeTrue();
});

it('rejects fake domains under the production email rule composition', function () {
    // The production branch of Email::defaults(), minus the network-dependent
    // MX lookup so the assertion stays deterministic offline.
    $rule = Rule::email()->rfcCompliant(strict: true)->preventSpoofing()->rules([new AllowedEmailDomain]);

    $validator = Validator::make(['email' => 'nsme@example.com'], ['email' => $rule]);

    expect($validator->fails())->toBeTrue();
});
