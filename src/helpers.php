<?php
/**
 * OpenBonnet — formatting, flash messages, and small UI partials.
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

function km(?int $n): string
{
    return $n === null ? '—' : number_format($n, 0, '.', ',') . ' km';
}

function date_short(?string $sqlDate): string
{
    return $sqlDate ? date('j M Y', strtotime($sqlDate)) : '—';
}

const COUNTIES = ['Carlow','Cavan','Clare','Cork','Donegal','Dublin','Galway','Kerry','Kildare',
    'Kilkenny','Laois','Leitrim','Limerick','Longford','Louth','Mayo','Meath','Monaghan',
    'Offaly','Roscommon','Sligo','Tipperary','Waterford','Westmeath','Wexford','Wicklow'];

/* ---------- The dossier ----------
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
 * Dossier summary: which core categories are present/verified, plus a 0–100 score.
 * Returns ['score'=>int, 'present'=>int, 'total'=>int, 'slots'=>[type=>['label','present','verified']]]
 */
function dossier(array $docs): array
{
    $slots = [];
    foreach (CORE_DOCS as $type => $label) {
        $slots[$type] = ['label' => $label, 'present' => false, 'verified' => false];
    }
    foreach ($docs as $d) {
        if (isset($slots[$d['doc_type']])) {
            $slots[$d['doc_type']]['present'] = true;
            if ($d['verified']) {
                $slots[$d['doc_type']]['verified'] = true;
            }
        }
    }
    $present = count(array_filter($slots, fn($s) => $s['present']));
    $total   = count($slots);
    return [
        'score'   => (int) round($present / $total * 100),
        'present' => $present,
        'total'   => $total,
        'slots'   => $slots,
    ];
}

/** Segmented dossier meter (one segment per core category). */
function dossier_meter(array $dossier, bool $labels = false): string
{
    $h = '<div class="meter" role="img" aria-label="Dossier ' . $dossier['present'] . ' of ' . $dossier['total'] . ' documents">';
    foreach ($dossier['slots'] as $slot) {
        $cls = $slot['present'] ? ($slot['verified'] ? 'seg on verified' : 'seg on') : 'seg';
        $tip = $slot['label'] . ($slot['verified'] ? ' — verified' : ($slot['present'] ? '' : ' — missing'));
        $h  .= '<span class="' . $cls . '" title="' . e($tip) . '"></span>';
    }
    $h .= '</div>';
    if ($labels) {
        $h .= '<p class="meter-caption">' . $dossier['present'] . ' of ' . $dossier['total']
            . ' core documents · <span class="stamp-ink">✓</span> = verified</p>';
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
