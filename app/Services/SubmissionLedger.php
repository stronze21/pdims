<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/** Durable, fail-closed reservations. Never automatically retry an uncertain write. */
class SubmissionLedger
{
    public function run($scope, $key, $fingerprint, callable $operation)
    {
        $id = hash('sha256', $scope . "\0" . $key);
        $table = DB::connection('hospital')->table('pharm_submission_receipts');
        try {
            $table->insert([
                'id' => $id, 'fingerprint' => $fingerprint, 'state' => 'pending',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Only handle an existing reservation; connection/schema errors must fail closed.
            $receipt = $table->where('id', $id)->first();
            if (!$receipt) throw $e;
            if (!hash_equals($receipt->fingerprint, $fingerprint)) {
                return response()->json(['message' => 'This submission key was already used with different data.'], 409);
            }
            if ($receipt->state !== 'completed') {
                return response()->json(['message' => 'This submission is in progress or needs review. Refresh before continuing; do not resubmit an uncertain transaction.'], 409);
            }
            $saved = json_decode(Crypt::decryptString($receipt->response), true, 512, JSON_THROW_ON_ERROR);
            return new Response($saved['body'], $saved['status'], array_merge($saved['headers'], ['Idempotency-Replayed' => 'true']));
        }

        try {
            $response = $operation();
            // Store only replay-relevant headers, never cookies or authentication headers.
            $headers = [];
            foreach (['Content-Type', 'Location', 'X-Livewire-Redirect'] as $header) {
                if ($response->headers->has($header)) $headers[$header] = $response->headers->get($header);
            }
            $saved = Crypt::encryptString(json_encode([
                'body' => $response->getContent(), 'status' => $response->getStatusCode(), 'headers' => $headers,
            ], JSON_THROW_ON_ERROR));
            $table->where('id', $id)->update(['state' => 'completed', 'response' => $saved, 'updated_at' => now()]);
            return $response;
        } catch (\Throwable $e) {
            // The handler may have written through another database or dispatched a job.
            // Keep the reservation even on failure: deleting it could execute those writes twice.
            $table->where('id', $id)->update(['state' => 'uncertain', 'updated_at' => now()]);
            throw $e;
        }
    }
}
