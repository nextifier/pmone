<?php

namespace App\Console\Commands\Reservation;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Services\Xendit\XenditService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reservations:backfill-payment-channel
        {--limit=100 : Maximum number of reservations to process in this run}
        {--dry-run : Show what would be updated without writing changes}')]
#[Description('Backfill payment_channel for paid reservations missing the field by fetching Xendit invoice details')]
class BackfillPaymentChannel extends Command
{
    public function handle(): int
    {
        $query = Reservation::query()
            ->whereIn('status', [ReservationStatus::Paid, ReservationStatus::VoucherSent])
            ->whereNotNull('xendit_invoice_id')
            ->whereNull('payment_channel')
            ->whereNotNull('payment_gateway_id')
            ->with('paymentGateway');

        $total = (clone $query)->count();
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Found {$total} reservation(s) missing payment_channel. Processing up to {$limit}.");

        $processed = 0;
        $updated = 0;
        $failed = 0;

        $query->limit($limit)->each(function (Reservation $reservation) use (&$processed, &$updated, &$failed, $dryRun) {
            $processed++;

            if (! $reservation->paymentGateway) {
                $this->warn("[{$reservation->reservation_number}] missing paymentGateway relation, skipping.");
                $failed++;

                return;
            }

            $detail = XenditService::forGateway($reservation->paymentGateway)
                ->fetchInvoiceDetail($reservation->xendit_invoice_id);

            if (! $detail) {
                $this->warn("[{$reservation->reservation_number}] Xendit fetch failed.");
                $failed++;

                return;
            }

            $channel = $detail['payment_channel'] ?? $detail['bank_code'] ?? null;
            $destination = $detail['payment_destination'] ?? null;
            $paymentId = $detail['payment_id'] ?? null;

            if (! $channel) {
                $this->line("[{$reservation->reservation_number}] Xendit returned no channel; skipping.");

                return;
            }

            $update = ['payment_channel' => $channel];
            if ($destination && empty($reservation->payment_destination)) {
                $update['payment_destination'] = $destination;
            }
            if ($paymentId && empty($reservation->xendit_payment_id)) {
                $update['xendit_payment_id'] = $paymentId;
            }

            if ($dryRun) {
                $this->info("[{$reservation->reservation_number}] would set: ".json_encode($update));

                return;
            }

            $reservation->forceFill($update)->saveQuietly();
            $updated++;
            $this->info("[{$reservation->reservation_number}] set payment_channel = {$channel}");
        });

        $this->newLine();
        $this->info("Processed: {$processed} | Updated: {$updated} | Failed/Skipped: {$failed}".($dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}
