<?php
/**
 * Safe Trade — formatting, flash messages, and small UI partials.
 */

declare(strict_types=1);

/** HTML-escape. */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function price_eur(int|float|null $n): string
{
    return $n === null ? '—' : '€' . number_format((float)$n, 0, '.', ',');
}

function km(int|string|null $n): string
{
    return $n === null || $n === '' ? '—' : number_format((int)$n, 0, '.', ',') . ' km';
}

function date_ireland(?string $sqlDate, string $format = 'j M Y'): string
{
    if (!$sqlDate) {
        return '—';
    }
    try {
        $dt = new DateTimeImmutable($sqlDate, new DateTimeZone('UTC'));
        return $dt->setTimezone(new DateTimeZone('Europe/Dublin'))->format($format);
    } catch (Exception) {
        return '—';
    }
}

function date_short(?string $sqlDate): string
{
    return date_ireland($sqlDate, 'j M Y');
}

function date_time_ireland(?string $sqlDate): string
{
    return date_ireland($sqlDate, 'D j M, H:i');
}

/** Convert an Ireland-local datetime-local value to a UTC SQL timestamp. */
function local_datetime_to_utc(string $value): ?string
{
    if ($value === '') {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $value, new DateTimeZone('Europe/Dublin'));
    if (!$dt) {
        return null;
    }
    return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function valid_county(string $county): bool
{
    return $county === '' || in_array($county, COUNTIES, true);
}

function normalize_vin(string $vin): string
{
    return strtoupper(trim($vin));
}

function valid_vin(string $vin): bool
{
    return $vin === '' || preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin) === 1;
}

function listing_allowed_transitions(string $from): array
{
    return match ($from) {
        'draft'        => ['live', 'archived'],
        'live'         => ['sale_agreed', 'sold', 'archived'],
        'sale_agreed'  => ['live', 'sold', 'archived'],
        'sold'         => ['archived'],
        'archived'     => ['live'],
        default        => [],
    };
}

function listing_transition_allowed(string $from, string $to): bool
{
    return in_array($to, listing_allowed_transitions($from), true);
}

function inspection_transition_allowed(string $from, string $to, string $actor): bool
{
    $allowed = [
        'mechanic' => [
            'requested' => ['confirmed', 'cancelled'],
            'confirmed' => ['completed', 'cancelled'],
        ],
        'buyer' => [
            'requested' => ['cancelled'],
            'confirmed' => ['cancelled'],
        ],
    ];
    return in_array($to, $allowed[$actor][$from] ?? [], true);
}

const COUNTIES = ['Carlow','Cavan','Clare','Cork','Donegal','Dublin','Galway','Kerry','Kildare',
    'Kilkenny','Laois','Leitrim','Limerick','Longford','Louth','Mayo','Meath','Monaghan',
    'Offaly','Roscommon','Sligo','Tipperary','Waterford','Westmeath','Wexford','Wicklow'];

/**
 * Makes and models currently on sale, as ['Toyota' => ['Corolla Hybrid', ...], ...].
 * Feeds the make/model search selects (the model list narrows to the chosen make in JS).
 */
function live_make_models(): array
{
    $rows = db_all(
        "SELECT DISTINCT make, model FROM listings WHERE status = 'live' ORDER BY make, model"
    );
    $map = [];
    foreach ($rows as $r) {
        $map[$r['make']][] = $r['model'];
    }
    return $map;
}

/* ---------- The documents ----------
 * Six core document categories drive the completeness score.
 * 'other' docs are shown but don't count toward the score.
 */
const CORE_DOCS = [
    'nct_cert'        => 'NCT certificate',
    'service_history' => 'Service history',
    'vrc'             => 'Registration cert (VRC)',
    'timing_belt'     => 'Timing belt / chain / battery report',
    'finance_check'   => 'Finance clearance',
    'crash_report'    => 'History & accident report',
];

/** All documents for a listing. */
function listing_docs(int $listingId): array
{
    return db_all('SELECT * FROM documents WHERE listing_id = ? ORDER BY uploaded_at', [$listingId]);
}

/**
 * Documents summary.
 * "declared" means the seller created the record; "present" means a file is uploaded.
 * Only uploaded core documents contribute to the completeness score.
 */
function documents(array $docs): array
{
    $slots = [];
    foreach (CORE_DOCS as $type => $label) {
        $slots[$type] = [
            'label' => $label,
            'declared' => false,
            'present' => false,
            'verified' => false,
        ];
    }
    foreach ($docs as $d) {
        if (!isset($slots[$d['doc_type']])) {
            continue;
        }
        $slots[$d['doc_type']]['declared'] = true;
        if (!empty($d['file_path'])) {
            $slots[$d['doc_type']]['present'] = true;
            if (!empty($d['verified'])) {
                $slots[$d['doc_type']]['verified'] = true;
            }
        }
    }

    $declared = count(array_filter($slots, fn($s) => $s['declared']));
    $present  = count(array_filter($slots, fn($s) => $s['present']));
    $total    = count($slots);

    return [
        'score'    => (int) round($present / $total * 100),
        'declared' => $declared,
        'present'  => $present,
        'total'    => $total,
        'slots'    => $slots,
    ];
}

function listing_can_publish(int $listingId): bool
{
    $types = array_keys(CORE_DOCS);
    $placeholders = implode(',', array_fill(0, count($types), '?'));
    $row = db_row(
        "SELECT COUNT(DISTINCT doc_type) AS n
           FROM documents
          WHERE listing_id = ?
            AND file_path IS NOT NULL
            AND file_path != ''
            AND doc_type IN ($placeholders)",
        array_merge([$listingId], $types)
    );
    return (int)($row['n'] ?? 0) >= 1;
}

/** Segmented documents meter (one segment per core category). */
function documents_meter(array $documents, bool $labels = false): string
{
    $h = '<div class="meter" role="img" aria-label="Uploaded documents ' . $documents['present'] . ' of ' . $documents['total'] . '">';
    foreach ($documents['slots'] as $slot) {
        if ($slot['verified']) {
            $cls = 'seg on verified';
            $tip = $slot['label'] . ' — verified';
        } elseif ($slot['present']) {
            $cls = 'seg on';
            $tip = $slot['label'] . ' — uploaded';
        } elseif ($slot['declared']) {
            $cls = 'seg declared';
            $tip = $slot['label'] . ' — declared, file not uploaded';
        } else {
            $cls = 'seg';
            $tip = $slot['label'] . ' — missing';
        }
        $h .= '<span class="' . $cls . '" title="' . e($tip) . '"></span>';
    }
    $h .= '</div>';
    if ($labels) {
        $h .= '<p class="meter-caption">' . $documents['present'] . ' of ' . $documents['total']
            . ' core files uploaded';
        if ($documents['declared'] > $documents['present']) {
            $h .= ' · ' . ($documents['declared'] - $documents['present']) . ' declared only';
        }
        $h .= ' · <span class="stamp-ink">✓</span> = verified</p>';
    }
    return $h;
}

/* ---------- UI partials ---------- */

/** Irish reg plate chip with the EU blue band. The site's signature element. */
function reg_plate(?string $reg): string
{
    if (!$reg) {
        return '<span class="plate plate-empty"><span class="plate-band">IRL</span><span class="plate-no">UNREG</span></span>';
    }
    return '<span class="plate"><span class="plate-band">IRL</span><span class="plate-no">' . e($reg) . '</span></span>';
}

/** Deterministic gradient block used instead of photos in the dev build. */
function car_thumb(array $l, string $size = ''): string
{
    $palettes = [
        ['#2E4057', '#4F6D7A'], ['#3D5A45', '#6B8F71'], ['#5C4D7D', '#8A7AA8'],
        ['#7A4E48', '#A8766E'], ['#44607A', '#7A97AD'], ['#6B5E3F', '#9C8B62'],
    ];
    [$a, $b] = $palettes[crc32($l['make'] . $l['model']) % count($palettes)];
    return '<div class="car-thumb ' . $size . '" style="background:linear-gradient(135deg,' . $a . ',' . $b . ')">'
         . '<span>' . e(strtoupper($l['make'])) . '</span>'
         . '<small>' . e((string)$l['year']) . '</small></div>';
}

/** ★★★★☆-style rating. */
function stars(float $avg): string
{
    $full = (int) round($avg);
    return '<span class="stars" aria-label="' . number_format($avg, 1) . ' out of 5">'
        . str_repeat('★', $full) . str_repeat('☆', 5 - $full) . '</span>';
}

function status_badge(string $status): string
{
    $map = ['live'=>'Live','draft'=>'Draft','sale_agreed'=>'Sale agreed','sold'=>'Sold','archived'=>'Archived'];
    return '<span class="badge badge-' . e($status) . '">' . e($map[$status] ?? $status) . '</span>';
}

/* ---------- Flash messages ---------- */

function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

function render_flashes(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $h = '<div class="flashes">';
    foreach ($_SESSION['flash'] as $f) {
        $h .= '<div class="flash flash-' . e($f['type']) . '">' . e($f['msg']) . '</div>';
    }
    $h .= '</div>';
    unset($_SESSION['flash']);
    return $h;
}

/** Redirect helper. */
function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}
