<?php
/**
 * OpenBonnet — database initialiser.
 * Usage:  php database/init.php        (creates openbonnet.sqlite + demo data)
 *         php database/init.php --fresh  (deletes any existing DB first)
 *
 * All demo accounts use the password:  password123
 */

declare(strict_types=1);

$dbFile = __DIR__ . '/openbonnet.sqlite';

if (in_array('--fresh', $argv ?? [], true) && file_exists($dbFile)) {
    unlink($dbFile);
    echo "Removed existing database.\n";
}

if (file_exists($dbFile)) {
    exit("Database already exists at database/openbonnet.sqlite.\nRun with --fresh to rebuild.\n");
}

$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');

// --- schema ---------------------------------------------------------------
$pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
echo "Schema created.\n";

// --- helpers --------------------------------------------------------------
$hash = password_hash('password123', PASSWORD_DEFAULT);

function insert(PDO $pdo, string $table, array $row): int
{
    $cols = implode(',', array_keys($row));
    $ph   = implode(',', array_fill(0, count($row), '?'));
    $pdo->prepare("INSERT INTO $table ($cols) VALUES ($ph)")->execute(array_values($row));
    return (int)$pdo->lastInsertId();
}

// --- users ----------------------------------------------------------------
$aoife  = insert($pdo, 'users', ['name'=>'Aoife Byrne',   'email'=>'aoife@example.com',  'password_hash'=>$hash, 'role'=>'private',  'phone'=>'085 123 4567', 'county'=>'Dublin']);
$conor  = insert($pdo, 'users', ['name'=>'Conor Walsh',   'email'=>'conor@example.com',  'password_hash'=>$hash, 'role'=>'private',  'phone'=>'086 234 5678', 'county'=>'Cork']);
$niamh  = insert($pdo, 'users', ['name'=>'Niamh Kelly',   'email'=>'niamh@example.com',  'password_hash'=>$hash, 'role'=>'private',  'phone'=>'087 345 6789', 'county'=>'Galway']);
$daraU  = insert($pdo, 'users', ['name'=>'Dara Nolan',    'email'=>'dara@example.com',   'password_hash'=>$hash, 'role'=>'mechanic', 'phone'=>'083 456 7890', 'county'=>'Dublin']);
$sineadU= insert($pdo, 'users', ['name'=>'Sinéad O\'Meara','email'=>'sinead@example.com','password_hash'=>$hash, 'role'=>'mechanic', 'phone'=>'089 567 8901', 'county'=>'Kildare']);
$dealer = insert($pdo, 'users', ['name'=>'Lakeside Motors','email'=>'dealer@example.com','password_hash'=>$hash, 'role'=>'dealer',   'phone'=>'01 555 0100',  'county'=>'Meath']);
$dealer2= insert($pdo, 'users', ['name'=>'Westgate Autos', 'email'=>'dealer2@example.com','password_hash'=>$hash,'role'=>'dealer',   'phone'=>'091 555 0200', 'county'=>'Galway']);
echo "Users seeded.\n";

// --- listings -------------------------------------------------------------
$corolla = insert($pdo, 'listings', [
    'user_id'=>$aoife, 'make'=>'Toyota', 'model'=>'Corolla Hybrid', 'year'=>2019,
    'reg'=>'191-D-21744', 'vin'=>'SB1KE3JE10E123456', 'mileage_km'=>82000, 'price_eur'=>21950,
    'fuel'=>'Hybrid', 'transmission'=>'Automatic', 'colour'=>'Silver', 'county'=>'Dublin',
    'description'=>"One owner from new, full Toyota main-dealer service history. NCT passed last month with no advisories. Hybrid battery health report included in the dossier. Selling as we've gone down to one car.",
]);
$bmw = insert($pdo, 'listings', [
    'user_id'=>$conor, 'make'=>'BMW', 'model'=>'320d M Sport', 'year'=>2016,
    'reg'=>'161-C-8812', 'vin'=>'WBA8C5102GK654321', 'mileage_km'=>148000, 'price_eur'=>15500,
    'fuel'=>'Diesel', 'transmission'=>'Manual', 'colour'=>'Estoril Blue', 'county'=>'Cork',
    'description'=>"Timing chain done at 130k with receipts (in the dossier). Two keys, new tyres front and back. A genuinely minded car — happy for any inspection.",
]);
$golf = insert($pdo, 'listings', [
    'user_id'=>$niamh, 'make'=>'Volkswagen', 'model'=>'Golf 1.6 TDI', 'year'=>2018,
    'reg'=>'182-G-4471', 'vin'=>'WVWZZZAUZJP765432', 'mileage_km'=>112000, 'price_eur'=>16750,
    'fuel'=>'Diesel', 'transmission'=>'Manual', 'colour'=>'Tungsten Grey', 'county'=>'Galway',
    'description'=>"Comfortline spec, adaptive cruise, App-Connect. Serviced every 15k — most receipts uploaded, chasing the last two from the garage.",
]);
$focus = insert($pdo, 'listings', [
    'user_id'=>$conor, 'make'=>'Ford', 'model'=>'Focus Zetec', 'year'=>2015,
    'reg'=>'151-C-30265', 'vin'=>null, 'mileage_km'=>176000, 'price_eur'=>7900,
    'fuel'=>'Petrol', 'transmission'=>'Manual', 'colour'=>'Race Red', 'county'=>'Cork',
    'description'=>"Honest starter car. High miles but motorway ones. NCT to 03/27. A few stone chips, priced accordingly.",
]);
$tucson = insert($pdo, 'listings', [
    'user_id'=>$aoife, 'make'=>'Hyundai', 'model'=>'Tucson Executive', 'year'=>2020,
    'reg'=>'201-D-15098', 'vin'=>'TMAJ3815ALJ112233', 'mileage_km'=>64000, 'price_eur'=>26400,
    'fuel'=>'Diesel', 'transmission'=>'Automatic', 'colour'=>'Phantom Black', 'county'=>'Dublin',
    'description'=>"Balance of manufacturer warranty until Nov 2027. Full dossier being uploaded this week.",
]);
$octavia = insert($pdo, 'listings', [
    'user_id'=>$niamh, 'make'=>'Skoda', 'model'=>'Octavia Ambition', 'year'=>2017,
    'reg'=>'171-G-9930', 'vin'=>'TMBJG7NE0H0334455', 'mileage_km'=>131000, 'price_eur'=>12250,
    'fuel'=>'Diesel', 'transmission'=>'Manual', 'colour'=>'Moon White', 'county'=>'Galway',
    'description'=>"Huge boot, cheap tax. Finance cleared — cert in the dossier. Open to dealer bids via auction.",
]);
echo "Listings seeded.\n";

// --- documents ------------------------------------------------------------
// The Corolla is the "gold standard" dossier: all six core categories, most verified.
$docs = [
    // corolla — complete
    [$corolla,'nct_cert','NCT certificate to 07/2027','Passed, zero advisories.',1],
    [$corolla,'service_history','Toyota main dealer service book + invoices','Every service on schedule since new.',1],
    [$corolla,'vrc','Vehicle Registration Cert (logbook)','In my name since 2019.',1],
    [$corolla,'timing_belt','Hybrid battery health report','94% state of health, tested June 2026.',1],
    [$corolla,'finance_check','Finance clearance letter','Cleared 2023, letter from lender attached.',0],
    [$corolla,'crash_report','No-accident declaration + history check','Clean history report attached.',0],
    // bmw — strong but partial
    [$bmw,'service_history','Service invoices 2018–2026','Mix of main dealer and independent specialist.',1],
    [$bmw,'timing_belt','Timing chain replacement invoice','Done at 130,411 km, genuine parts.',1],
    [$bmw,'nct_cert','NCT certificate to 01/2027',null,0],
    // golf — partial
    [$golf,'nct_cert','NCT certificate to 09/2026',null,0],
    [$golf,'service_history','Service receipts (most)','Chasing two missing invoices from the garage.',0],
    // octavia — partial
    [$octavia,'finance_check','Finance clearance certificate','HP settled in full, 2025.',1],
    [$octavia,'vrc','Vehicle Registration Cert (logbook)',null,0],
];
foreach ($docs as [$lid,$type,$title,$note,$ver]) {
    insert($pdo, 'documents', ['listing_id'=>$lid,'doc_type'=>$type,'title'=>$title,'note'=>$note,'verified'=>$ver]);
}
echo "Documents seeded.\n";

// --- mechanics ------------------------------------------------------------
$dara = insert($pdo, 'mechanic_profiles', [
    'user_id'=>$daraU, 'headline'=>'German marques — BMW, Audi, VW diesels',
    'bio'=>"Fifteen years on German cars, ten of them in a BMW main dealer. I do pre-purchase inspections evenings and weekends: compression and leak-down where warranted, full OBD scan, and I put everything in writing. If a timing chain is rattling, I'll hear it before you buy it.",
    'base_county'=>'Dublin', 'callout_fee_eur'=>85, 'years_experience'=>15, 'verified'=>1,
]);
$sinead = insert($pdo, 'mechanic_profiles', [
    'user_id'=>$sineadU, 'headline'=>'Toyota & Lexus hybrid specialist',
    'bio'=>"Hybrid systems are my thing — battery state-of-health testing, inverter checks, the lot. I'll tell you in plain English whether that Prius or Corolla battery has years left or is living on borrowed time. Weekend viewings across Kildare and west Dublin.",
    'base_county'=>'Kildare', 'callout_fee_eur'=>70, 'years_experience'=>9, 'verified'=>1,
]);
foreach ([
    [$dara,'BMW','N47/B47 timing chain wear, EGR + swirl flaps'],
    [$dara,'Audi','DSG mechatronic health, oil consumption checks'],
    [$dara,'Volkswagen','1.6/2.0 TDI DPF and injector condition'],
    [$sinead,'Toyota','Hybrid battery state-of-health testing'],
    [$sinead,'Lexus','Hybrid drivetrain and inverter diagnostics'],
] as [$mid,$make,$focus]) {
    insert($pdo, 'mechanic_specialties', ['mechanic_id'=>$mid,'make'=>$make,'focus'=>$focus]);
}
echo "Mechanics seeded.\n";

// --- inspections + reviews -------------------------------------------------
$insp1 = insert($pdo, 'inspections', [
    'mechanic_id'=>$dara,'buyer_id'=>$niamh,'listing_id'=>$bmw,
    'scheduled_for'=>date('Y-m-d H:i', strtotime('-20 days')),'status'=>'completed',
    'message'=>'Viewing Saturday morning if you can make Cork?',
]);
$insp2 = insert($pdo, 'inspections', [
    'mechanic_id'=>$sinead,'buyer_id'=>$conor,'listing_id'=>$corolla,
    'scheduled_for'=>date('Y-m-d H:i', strtotime('-9 days')),'status'=>'completed',
    'message'=>null,
]);
insert($pdo, 'inspections', [
    'mechanic_id'=>$dara,'buyer_id'=>$aoife,'listing_id'=>$golf,
    'scheduled_for'=>date('Y-m-d H:i', strtotime('+3 days')),'status'=>'requested',
    'message'=>'Golf in Galway — could you travel, or recommend someone local?',
]);

foreach ([
    [$dara,$niamh,$insp1,'BMW','320d',5,'Caught early timing chain rattle on a different 320d I nearly bought the week before — saved me thousands. On this one he confirmed the chain work was genuine from the invoices and the sound.','Worth every cent of the callout. Report was two pages, photos included.'],
    [$dara,$conor,null,'Audi','A4 2.0 TDI',5,'Spotted a weeping injector seal and a lazy glow plug the seller didn\'t know about.','Used the report to knock €600 off. Straight talker.'],
    [$dara,$aoife,null,'Volkswagen','Passat',4,'DPF was on its way out — he showed me the soot load reading on his scanner.','Walked away from that car. Only reason for 4 stars is he was 20 minutes late.'],
    [$sinead,$conor,$insp2,'Toyota','Corolla Hybrid',5,'Ran a full battery state-of-health test — 94%, printed the report on the spot.','That report is literally in the seller\'s dossier now. Brilliant.'],
    [$sinead,$niamh,null,'Toyota','Prius',5,'Told me the battery had 2 years left at best. She was right — it was already throwing a soft code.','Honest even though it cost her a repeat job. Booking her for the next one.'],
] as [$mid,$rev,$iid,$mk,$mdl,$rating,$found,$comment]) {
    insert($pdo, 'mechanic_reviews', [
        'mechanic_id'=>$mid,'reviewer_id'=>$rev,'inspection_id'=>$iid,
        'car_make'=>$mk,'car_model'=>$mdl,'rating'=>$rating,
        'found_issues'=>$found,'comment'=>$comment,
    ]);
}
echo "Inspections + reviews seeded.\n";

// --- auction ---------------------------------------------------------------
$auc = insert($pdo, 'auctions', [
    'listing_id'=>$octavia, 'reserve_eur'=>11000,
    'ends_at'=>date('Y-m-d H:i:s', strtotime('+3 days 4 hours')), 'status'=>'open',
]);
insert($pdo, 'bids', ['auction_id'=>$auc,'dealer_id'=>$dealer, 'amount_eur'=>10200]);
insert($pdo, 'bids', ['auction_id'=>$auc,'dealer_id'=>$dealer2,'amount_eur'=>10650]);
insert($pdo, 'bids', ['auction_id'=>$auc,'dealer_id'=>$dealer, 'amount_eur'=>11100]);
echo "Auction seeded.\n";

echo "\nDone. Demo accounts (password for all: password123)\n";
echo "  Private seller : aoife@example.com\n";
echo "  Private seller : conor@example.com\n";
echo "  Private seller : niamh@example.com\n";
echo "  Mechanic       : dara@example.com\n";
echo "  Mechanic       : sinead@example.com\n";
echo "  Dealer         : dealer@example.com\n";
echo "  Dealer         : dealer2@example.com\n";
