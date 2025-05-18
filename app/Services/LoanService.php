<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use InvalidArgumentException;
use Illuminate\Support\Carbon;
use App\Models\User;

class LoanService
{
    /**
     * Create a Loan
     *
     * @param  User  $user
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  int  $terms
     * @param  string  $processedAt
     *
     * @return Loan
     */
    public function createLoan(User $user, int $amount, string $currencyCode, int $terms, string $processedAt): Loan
    {
        if (!in_array($currencyCode, [Loan::CURRENCY_SGD, Loan::CURRENCY_VND])) {
            throw new InvalidArgumentException("Invalid currency code: {$currencyCode}");
        }

        $loan = Loan::create([
            'user_id' => $userId,
            'amount' => $amount,
            'outstanding_amount' => $amount,
            'terms' => $terms,
            'currency_code' => $currencyCode,
            'processed_at' => $processedAt,
            'status' => Loan::STATUS_DUE,
            'created_at' => $processedAt,
        ]);

        $monthlyAmount = $amount / $terms;
        for ($i = 1; $i <= $terms; $i++) {
            ScheduledRepayment::create([
                'loan_id' => $loan->id,
                'amount' => $monthlyAmount,
                'due_date' => $processedAt->copy()->addMonths($i),
                'status' => ScheduledRepayment::STATUS_DUE,
            ]);
        }

        return $loan;
    }

    /**
     * Repay Scheduled Repayments for a Loan
     *
     * @param  Loan  $loan
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  string  $receivedAt
     *
     * @return ReceivedRepayment
     */
    public function repayLoan(Loan $loan, int $amount, string $currencyCode, string $receivedAt): ReceivedRepayment
    {
        if ($currencyCode !== $loan->currency_code) {
            throw new InvalidArgumentException("Currency code {$currencyCode} does not match loan currency {$loan->currency_code}");
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException("Amount must be positive");
        }

        try {
            $parsedReceivedAt = Carbon::parse($receivedAt);
        } catch (\Exception $e) {
            throw new InvalidArgumentException("Invalid receivedAt date format: {$receivedAt}");
        }

        $pendingRepayments = $loan->scheduledRepayments()
            ->whereIn('status', [ScheduledRepayment::STATUS_DUE, ScheduledRepayment::STATUS_PARTIAL])
            ->orderBy('due_date')
            ->get();

        if ($pendingRepayments->isEmpty()) {
            throw new InvalidArgumentException("No pending repayments to apply payment to");
        }

        $repayment = $pendingRepayments->first();
        $totalPaidForLoan = ReceivedRepayment::where('loan_id', $loan->id)->sum('amount');
        $remainingRepaymentAmount = $repayment->amount - ($totalPaidForLoan % $repayment->amount);

        if ($remainingRepaymentAmount <= 0) {
            $remainingRepaymentAmount = $repayment->amount;
        }

        $paymentAmount = min($amount, $remainingRepaymentAmount);

        $receivedRepayment = ReceivedRepayment::create([
            'loan_id' => $loan->id,
            'amount' => $paymentAmount,
            'received_at' => $parsedReceivedAt,
        ]);

        $loan->decrement('outstanding_amount', $paymentAmount);

        $totalPaidForRepayment = ReceivedRepayment::where('loan_id', $loan->id)->sum('amount');
        if ($totalPaidForRepayment >= $repayment->amount) {
            $repayment->update(['status' => ScheduledRepayment::STATUS_REPAID]);
        } elseif ($totalPaidForRepayment > 0) {
            $repayment->update(['status' => ScheduledRepayment::STATUS_PARTIAL]);
        }

        if ($loan->outstanding_amount <= 0) {
            $loan->update(['status' => Loan::STATUS_REPAID]);
        }

        return $receivedRepayment;
    }

    public function getLoanStatus(int $loanId): string
    {
        $loan = Loan::findOrFail($loanId);
        return $loan->status;
    }
}
