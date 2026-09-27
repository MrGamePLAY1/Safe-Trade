<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/helpers.php';

$failures = 0;

function check(bool $condition, string $message): void
{
    global $failures;
    if ($condition) {
        echo "PASS: {$message}\n";
        return;
    }
    $failures++;
    fwrite(STDERR, "FAIL: {$message}\n");
}

$declaredOnly = [[
    'doc_type' => 'nct_cert',
    'file_path' => null,
    'verified' => 0,
]];
$summary = documents($declaredOnly);
check($summary['declared'] === 1, 'declared document is tracked');
check($summary['present'] === 0, 'declared-only document does not count as uploaded');

$uploaded = [[
    'doc_type' => 'nct_cert',
    'file_path' => 'abc.pdf',
    'verified' => 0,
]];
$summary = documents($uploaded);
check($summary['present'] === 1, 'uploaded document counts toward completeness');
check($summary['score'] === 17, 'one of six uploaded core documents scores 17 percent');

check(inspection_transition_allowed('requested', 'confirmed', 'mechanic'), 'mechanic can confirm requested inspection');
check(!inspection_transition_allowed('requested', 'completed', 'mechanic'), 'mechanic cannot skip directly to completed');
check(inspection_transition_allowed('confirmed', 'completed', 'mechanic'), 'mechanic can complete confirmed inspection');
check(inspection_transition_allowed('confirmed', 'cancelled', 'buyer'), 'buyer can cancel confirmed inspection');
check(!inspection_transition_allowed('completed', 'cancelled', 'buyer'), 'completed inspection cannot be cancelled');

check(listing_transition_allowed('draft', 'live'), 'draft listing can be published');
check(!listing_transition_allowed('draft', 'sold'), 'draft listing cannot jump straight to sold');
check(listing_transition_allowed('sale_agreed', 'live'), 'sale agreed listing can return to live');
check(!listing_transition_allowed('sold', 'live'), 'sold listing cannot return directly to live');

check(valid_vin(''), 'VIN remains optional');
check(valid_vin('WVWZZZAUZJP765432'), 'valid 17 character VIN is accepted');
check(!valid_vin('INVALIDVIN'), 'malformed VIN is rejected');

exit($failures === 0 ? 0 : 1);
