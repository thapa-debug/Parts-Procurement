<?php

namespace App\Console\Commands;

use App\Actions\ReconcileStripePaymentAction;
use App\Enums\PaymentReconciliation;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Models\PartRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled backstop for missed Stripe webhooks (Stripe stops retrying a
 * webhook after ~3 days): asks Stripe about every awaiting_payment request
 * that still has an open Stripe payment, and confirms the ones Stripe says
 * succeeded -- through the same single path as the webhook
 * (ReconcileStripePaymentAction -> ConfirmStripePaymentAction). Never
 * cancels or charges anything. --dry-run reports without confirming.
 */
class ReconcileStripePayments extends Command
{
    protected $signature = 'payments:reconcile {--dry-run : Report what Stripe says without confirming anything}';

    protected $description = 'Confirm awaiting-payment requests whose Stripe payment actually succeeded (missed-webhook backstop)';

    public function handle(ReconcileStripePaymentAction $reconcile): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $tally = [];

        PartRequest::query()
            ->where('status', RequestStatus::AwaitingPayment)
            ->whereHas('payments', fn ($query) => $query
                ->where('gateway', 'stripe')
                ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Failed])
                ->whereNotNull('gateway_reference'))
            ->orderBy('id')
            ->each(function (PartRequest $partRequest) use ($reconcile, $dryRun, &$tally) {
                $outcome = $dryRun ? $reconcile->inspect($partRequest) : $reconcile->execute($partRequest);
                $tally[$outcome->value] = ($tally[$outcome->value] ?? 0) + 1;

                $this->line("{$partRequest->request_code}: {$outcome->value}");
            });

        $this->newLine();
        $this->info(($dryRun ? '[dry run] ' : '').'Checked '.array_sum($tally).' awaiting-payment request(s).');
        foreach ($tally as $outcome => $count) {
            $this->line("  {$outcome}: {$count}");
        }

        Log::channel('payments')->info('payments:reconcile finished', ['dry_run' => $dryRun, 'outcomes' => $tally]);

        return ($tally[PaymentReconciliation::AmountMismatch->value] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
