<?php

use App\Models\ProjectPaymentGateway;

/**
 * Build an unsaved gateway. isConfigured() reads nothing but the provider and
 * the secret, so there is no need to touch the database here.
 */
function gateway(string $provider, string $secret): ProjectPaymentGateway
{
    $gateway = new ProjectPaymentGateway;
    $gateway->provider = $provider;
    $gateway->secret_key = $secret;

    return $gateway;
}

// The regression this whole guard was rewritten for.
test('it accepts a real key whose random run happens to contain a marker', function (string $secret) {
    expect(gateway('xendit', $secret)->isConfigured())->toBeTrue();
})->with([
    'xxx mid-run' => ['xnd_development_a9xxxQ2mZ7pLr4TvB8NcW1eK3jH6sD5g'],
    'xxx at the end' => ['xnd_development_a9bQ2mZ7pLr4TvB8NcW1eK3jH6sDxxx'],
    'fake mid-run' => ['xnd_development_a9bfakeQ2mZ7pLr4TvB8NcW1eK3jH6s'],
    'sample mid-run' => ['xnd_developmentsampleQ2mZ7pLr4TvB8NcW1eK3jH'],
    'replace mid-run' => ['xnd_developmentreplaceQ2mZ7pLr4TvB8NcW1eK3j'],
]);

test('it still rejects a marker standing on its own between separators', function (string $secret) {
    expect(gateway('xendit', $secret)->isConfigured())->toBeFalse();
})->with([
    'underscored dummy' => ['xnd_dummy_key_for_local_development_only_1234'],
    'underscored placeholder' => ['xnd_placeholder_value_until_finance_pastes_it'],
    'underscored changeme' => ['xnd_changeme_before_going_live_1234567890abcd'],
    'underscored fake' => ['xnd_fake_credentials_for_the_staging_box_9876'],
    'multi-word marker' => ['xnd_test_key_for_local_development_1234567890'],
    'dotted separator' => ['xnd_a.sample.value.that.is.long.enough.to.pass'],
]);

// Masked and redacted keys, the shape people paste out of a dashboard or a
// doc. The old substring rule caught these for the wrong reason; the run check
// catches them for the right one.
test('it rejects a secret carrying a long run of one character', function (string $provider, string $secret) {
    expect(gateway($provider, $secret)->isConfigured())->toBeFalse();
})->with([
    'xendit all x' => ['xendit', 'xnd_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'],
    'xendit all a' => ['xendit', 'xnd_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
    'midtrans masked upper' => ['midtrans', 'SB-Mid-server-XXXXXXXXXXXXXXXXXXXX'],
    'midtrans masked lower' => ['midtrans', 'SB-Mid-server-xxxxxxxxxxxxxxxxxxxx'],
    'midtrans masked digits' => ['midtrans', 'SB-Mid-server-1111111111111111111111'],
]);

test('it rejects a secret drawn from barely any alphabet', function () {
    expect(gateway('custom', 'abcabcabcabcabcabcabc')->isConfigured())->toBeFalse();
});

// The variety floor started at eight distinct characters, which rejected real
// 20-character hex secrets once in 142 - six times worse than the substring bug
// it replaced. Hex is a normal alphabet for a generic provider's secret.
test('it accepts a genuine hex secret from a generic provider', function (string $secret) {
    expect(gateway('custom', $secret)->isConfigured())->toBeTrue();
})->with([
    '20 hex chars, 8 distinct' => ['a85a4782426888525d58'],
    '24 hex chars, 8 distinct' => ['250b2059b58a85bdab0abadd'],
]);

test('it keeps the provider format rules', function (string $provider, string $secret, bool $expected) {
    expect(gateway($provider, $secret)->isConfigured())->toBe($expected);
})->with([
    'xendit needs the prefix' => ['xendit', 'nope_a9bQ2mZ7pLr4TvB8NcW1eK3jH6sD5g', false],
    'xendit needs 30 chars' => ['xendit', 'xnd_short', false],
    'xendit valid' => ['xendit', 'xnd_a9bQ2mZ7pLr4TvB8NcW1eK3jH6sD5gY', true],
    'midtrans needs a server key' => ['midtrans', 'SB-Mid-client-a9bQ2mZ7pLr4TvB8Nc', false],
    'midtrans valid sandbox' => ['midtrans', 'SB-Mid-server-a9bQ2mZ7pLr4TvB8Nc', true],
    'midtrans valid live' => ['midtrans', 'Mid-server-a9bQ2mZ7pLr4TvB8NcW1eK', true],
    'generic needs 20 chars' => ['custom', 'tooshort', false],
    'generic valid' => ['custom', 'a9bQ2mZ7pLr4TvB8NcW1eK3jH6sD5g', true],
    'empty is never configured' => ['xendit', '', false],
]);

test('a marker inside a random run does not survive across many generated keys', function () {
    // The old str_contains rule failed roughly one key in 858. Walk a large
    // batch of realistically-shaped keys and assert none of them is rejected.
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $rejected = [];

    // Seeded so a failure is reproducible; 5000 keys is ~6x the rate at which
    // the old rule tripped, so a regression would show up here rather than in
    // production.
    mt_srand(20260817);

    for ($i = 0; $i < 5000; $i++) {
        $body = '';
        for ($j = 0; $j < 40; $j++) {
            $body .= $alphabet[mt_rand(0, 61)];
        }

        $secret = 'xnd_'.$body;

        if (! gateway('xendit', $secret)->isConfigured()) {
            $rejected[] = $secret;
        }
    }

    expect($rejected)->toBe([]);
});
