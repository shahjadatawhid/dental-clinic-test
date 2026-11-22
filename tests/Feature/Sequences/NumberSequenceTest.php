<?php

use App\Enums\SequenceSeries;
use App\Models\NumberSequence;
use App\Services\NumberSequenceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->numbers = app(NumberSequenceService::class);
});

it('formats each series from its defaults', function () {
    $date = Carbon::parse('2026-03-15');

    expect($this->numbers->next(SequenceSeries::Patient, $date))->toBe('FD-03-26-000001')
        ->and($this->numbers->next(SequenceSeries::ReceiptVoucher, $date))->toBe('RV-2026-000001')
        ->and($this->numbers->next(SequenceSeries::PaymentVoucher, $date))->toBe('PV-2026-000001')
        ->and($this->numbers->next(SequenceSeries::Invoice, $date))->toBe('INV-2026-000001')
        ->and($this->numbers->next(SequenceSeries::LabOrder, $date))->toBe('LAB-2026-00001');
});

it('counts each series separately and never repeats', function () {
    $rv = collect(range(1, 5))->map(fn () => $this->numbers->next(SequenceSeries::ReceiptVoucher));
    $pv = $this->numbers->next(SequenceSeries::PaymentVoucher);

    expect($rv->unique())->toHaveCount(5)
        ->and($rv->last())->toEndWith('-000005')
        ->and($pv)->toEndWith('-000001');
});

it('keeps counting across years when yearly reset is off', function () {
    $this->numbers->next(SequenceSeries::ReceiptVoucher, Carbon::parse('2026-12-31'));

    expect($this->numbers->next(SequenceSeries::ReceiptVoucher, Carbon::parse('2027-01-01')))->toBe('RV-2027-000002');
});

it('restarts every year when yearly reset is on, and back-dated documents use their own year', function () {
    $this->numbers->sequence(SequenceSeries::ReceiptVoucher)->update(['reset_yearly' => true]);

    expect($this->numbers->next(SequenceSeries::ReceiptVoucher, Carbon::parse('2026-12-30')))->toBe('RV-2026-000001')
        ->and($this->numbers->next(SequenceSeries::ReceiptVoucher, Carbon::parse('2027-01-02')))->toBe('RV-2027-000001')
        ->and($this->numbers->next(SequenceSeries::ReceiptVoucher, Carbon::parse('2026-12-31')))->toBe('RV-2026-000002')
        ->and($this->numbers->next(SequenceSeries::ReceiptVoucher, Carbon::parse('2027-01-03')))->toBe('RV-2027-000002');
});

it('releases the number when the saving transaction fails', function () {
    try {
        DB::transaction(function () {
            $this->numbers->next(SequenceSeries::ReceiptVoucher);
            throw new RuntimeException('Saving the receipt failed');
        });
    } catch (RuntimeException) {
    }

    expect($this->numbers->next(SequenceSeries::ReceiptVoucher))->toEndWith('-000001');
});

it('previews the next number without taking it', function () {
    $date = Carbon::parse('2026-11-22');
    $preview = $this->numbers->preview(SequenceSeries::Patient, $date);

    expect($preview)->toBe('FD-11-26-000001')
        ->and($this->numbers->preview(SequenceSeries::Patient, $date))->toBe('FD-11-26-000001')
        ->and($this->numbers->next(SequenceSeries::Patient, $date))->toBe($preview);
});

it('gives patient codes the registration month and year, and never restarts the counter', function () {
    expect($this->numbers->next(SequenceSeries::Patient, Carbon::parse('2026-11-22')))->toBe('FD-11-26-000001')
        ->and($this->numbers->next(SequenceSeries::Patient, Carbon::parse('2026-11-30')))->toBe('FD-11-26-000002')
        ->and($this->numbers->next(SequenceSeries::Patient, Carbon::parse('2026-12-01')))->toBe('FD-12-26-000003')
        ->and($this->numbers->next(SequenceSeries::Patient, Carbon::parse('2027-01-02')))->toBe('FD-01-27-000004');
});

it('never clashes with the clinic\'s old 4-digit patient IDs', function () {
    $new = $this->numbers->next(SequenceSeries::Patient, Carbon::parse('2023-01-10'));

    expect($new)->toBe('FD-01-23-000001')->not->toBe('FD-01-23-0001');
});

it('uses a changed prefix and format for later numbers and audits the change', function () {
    $this->numbers->next(SequenceSeries::Patient);
    $this->numbers->sequence(SequenceSeries::Patient)->update(['format' => '{prefix}-{yy}-{number}', 'padding' => 4]);

    expect($this->numbers->next(SequenceSeries::Patient, Carbon::parse('2026-05-01')))->toBe('FD-26-0002')
        ->and(Activity::where('log_name', 'number_sequences')->where('event', 'updated')->exists())->toBeTrue();
});

it('grows past the padding instead of wrapping', function () {
    $sequence = $this->numbers->sequence(SequenceSeries::LabOrder);
    $sequence->counters()->create(['period' => 0, 'last_value' => 99999]);

    expect($this->numbers->next(SequenceSeries::LabOrder, Carbon::parse('2026-01-01')))->toBe('LAB-2026-100000');
});

it('pre-creates every series and counter with the sync command, safely twice', function () {
    $this->artisan('app:sync-sequences')->assertSuccessful();
    $this->artisan('app:sync-sequences')->assertSuccessful();

    expect(NumberSequence::count())->toBe(count(SequenceSeries::cases()))
        ->and($this->numbers->next(SequenceSeries::Invoice))->toEndWith('-000001');
});
