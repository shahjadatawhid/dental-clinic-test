<?php

/*
 * Helper for tests/Concurrency: takes <count> numbers from <series> in this
 * separate PHP process, holding each lock for a random moment, and prints them.
 *
 *   php tests/Support/sequence-worker.php receipt_voucher 25
 */

use App\Enums\SequenceSeries;
use App\Services\NumberSequenceService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$series = SequenceSeries::from($argv[1]);
$count = (int) $argv[2];
$service = $app->make(NumberSequenceService::class);

try {
    for ($i = 0; $i < $count; $i++) {
        echo DB::transaction(function () use ($service, $series) {
            $number = $service->next($series);
            usleep(random_int(0, 5000)); // keep the lock a little, like a real save would

            return $number;
        }), PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage().PHP_EOL);
    exit(1);
}
