<?php

namespace App\Enums;

/**
 * Every numbered document in the system. Defaults are used the first time a
 * series is needed; after that the number_sequences row is the source of truth.
 * Prefix/format decisions are open question Q1/Q2 in docs/03-business-rules.md.
 */
enum SequenceSeries: string
{
    case Patient = 'patient';
    case Invoice = 'invoice';
    case ReceiptVoucher = 'receipt_voucher';
    case PaymentVoucher = 'payment_voucher';
    case LabOrder = 'lab_order';
    case Purchase = 'purchase';

    /** @return array{prefix: string, format: string, padding: int, reset_yearly: bool} */
    public function defaults(): array
    {
        return match ($this) {
            // Clinic's own style (Q1, confirmed 2026-11-22): FD-MM-YY-NNNNNN, MM-YY = registration
            // month/year, counter never restarts. Old 4-digit IDs (FD-01-23-0208) stay as legacy_code.
            self::Patient => ['prefix' => 'FD', 'format' => '{prefix}-{mm}-{yy}-{number}', 'padding' => 6, 'reset_yearly' => false],
            self::Invoice => ['prefix' => 'INV', 'format' => '{prefix}-{year}-{number}', 'padding' => 6, 'reset_yearly' => false],
            self::ReceiptVoucher => ['prefix' => 'RV', 'format' => '{prefix}-{year}-{number}', 'padding' => 6, 'reset_yearly' => false],
            self::PaymentVoucher => ['prefix' => 'PV', 'format' => '{prefix}-{year}-{number}', 'padding' => 6, 'reset_yearly' => false],
            self::LabOrder => ['prefix' => 'LAB', 'format' => '{prefix}-{year}-{number}', 'padding' => 5, 'reset_yearly' => false],
            self::Purchase => ['prefix' => 'PO', 'format' => '{prefix}-{year}-{number}', 'padding' => 5, 'reset_yearly' => false],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Patient => __('Patient code'),
            self::Invoice => __('Invoice'),
            self::ReceiptVoucher => __('Receipt voucher (money received)'),
            self::PaymentVoucher => __('Payment voucher (money paid out)'),
            self::LabOrder => __('Lab order'),
            self::Purchase => __('Purchase order'),
        };
    }
}
