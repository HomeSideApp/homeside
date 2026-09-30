<?php

namespace App\Actions\Economy;

use App\Data\Economy\ConfirmEconomicImportData;
use App\Data\Economy\CreateEconomicTransactionData;
use App\Enums\EconomicImportStatus;
use App\Enums\TransactionScope;
use App\Enums\TransactionType;
use App\Models\EconomicImport;
use App\Models\EconomicTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Confirms an import, applying the user's edits and creating the economic
 * transaction from the merged payload.
 */
final class ConfirmEconomicImport
{
    /**
     * Create a new import confirmation action instance.
     *
     * @param  CreateEconomicTransaction  $createEconomicTransaction  The action used to persist the reviewed transaction.
     * @return void This constructor does not return a value.
     */
    public function __construct(
        private readonly CreateEconomicTransaction $createEconomicTransaction,
    ) {}

    /**
     * Merge the reviewed data into an import and create its economic transaction.
     *
     * @param  EconomicImport  $import  The import that is ready for confirmation.
     * @param  ConfirmEconomicImportData  $data  The user-reviewed values that override extracted data.
     * @param  User  $user  The user confirming and owning the resulting transaction.
     * @return EconomicTransaction The confirmed transaction with its related economic data loaded.
     */
    public function execute(
        EconomicImport $import,
        ConfirmEconomicImportData $data,
        User $user,
    ): EconomicTransaction {
        return DB::transaction(function () use ($import, $data, $user) {
            if ($import->status !== EconomicImportStatus::ReadyForReview) {
                abort(409, __('app.validation.import_not_ready'));
            }

            $payload = $import->extracted_payload ?? [];
            if ($import->review_payload) {
                $payload = array_merge($payload, array_filter($import->review_payload));
            }

            if ($data->has('title')) {
                $payload['title'] = $data->title;
            }
            if ($data->has('amount') && $data->amount_minor !== null) {
                $payload['amount'] = number_format($data->amount_minor / 100, 2, '.', '');
            }
            if ($data->has('currency')) {
                $payload['currency'] = $data->currency;
            }
            if ($data->has('account_id')) {
                $payload['account_id'] = $data->account_id;
            }
            if ($data->has('place')) {
                $payload['place'] = $data->place;
            }
            if ($data->has('occurred_at')) {
                $payload['occurred_at'] = $data->occurred_at?->toISOString();
            }
            if ($data->has('items')) {
                $payload['items'] = $data->items ?? [];
            }
            if ($data->has('taxes')) {
                $payload['taxes'] = $data->taxes ?? [];
            }
            if ($data->has('participants')) {
                $payload['participants'] = $data->participants ?? [];
            }

            if (empty($payload['title']) || empty($payload['amount'])) {
                throw ValidationException::withMessages([
                    'import' => __('app.validation.import_requires_title_amount'),
                ]);
            }

            $payload['type'] = ($data->type ?? TransactionType::Expense)->value;
            $payload['scope'] = $import->household_id !== null
                ? ($data->scope ?? TransactionScope::Personal)->value
                : TransactionScope::Personal->value;
            $payload['source_document_id'] = $import->document_id;
            $payload['items'] = array_map(function (array $item): array {
                $item['quantity'] ??= 1;

                if (! isset($item['subtotal'], $item['subtotal_minor'])) {
                    if (isset($item['total_minor'])) {
                        $item['subtotal_minor'] = $item['total_minor'];
                    } else {
                        $item['subtotal'] = $item['total'];
                    }
                }

                return $item;
            }, $payload['items'] ?? []);

            $transactionData = CreateEconomicTransactionData::fromArray($payload);
            $transaction = $this->createEconomicTransaction->execute(
                $transactionData,
                $import->household,
                $user,
            );

            $import->update([
                'status' => EconomicImportStatus::Confirmed,
                'confirmed_at' => now(),
                'review_payload' => $data->toArray(),
            ]);

            return $transaction->load(['items', 'taxes', 'participants.householdMember.user', 'sourceDocument']);
        });
    }
}
