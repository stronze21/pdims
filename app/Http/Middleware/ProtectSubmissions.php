<?php

namespace App\Http\Middleware;

use App\Services\SubmissionLedger;
use Closure;
use Illuminate\Http\Request;

class ProtectSubmissions
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethodSafe()) return $next($request);
        if ($request->is('livewire/upload-file')) return $next($request);

        // Authentication has its own token/session lifecycle; never replay credentials.
        if ($request->is('login', 'logout', 'register', 'forgot-password', 'reset-password', 'two-factor-challenge', 'api/login', 'api/logout')
            || preg_match('~/(?:login|logout|register|password|otp|verify)(?:/|$)~', $request->path())) {
            return $next($request);
        }

        $payload = $request->except(['_token', '_submission_key']);
        $originalPayload = $payload;
        $key = $request->header('Idempotency-Key', $request->input('_submission_key'));
        if ($request->is('livewire/*')) {
            // Livewire 2 generates a transport-only ID for every update; it is not
            // part of the user's operation and must not turn a retry into a new write.
            foreach ($payload['updates'] ?? [] as $index => $update) {
                if (is_array($update) && isset($update['type'])) unset($payload['updates'][$index]['payload']['id']);
            }
            // Two clicks can be bundled into ONE transport request. A request ledger
            // alone cannot prevent both calls from running inside that request.
            if (isset($payload['components']) && is_array($payload['components'])) {
                foreach ($payload['components'] as &$component) {
                    $component['calls'] = $this->unique($component['calls'] ?? []);
                }
                unset($component);
                $request->merge(['components' => $payload['components']]);
            } elseif (isset($payload['fingerprint'], $payload['updates'])) {
                $originalUpdates = $request->input('updates');
                $transportUpdates = [];
                $seen = [];
                foreach ($payload['updates'] as $index => $update) {
                    $signature = json_encode($update, JSON_THROW_ON_ERROR);
                    if (!isset($seen[$signature])) $transportUpdates[] = $originalUpdates[$index];
                    $seen[$signature] = true;
                }
                $payload['updates'] = $this->unique($payload['updates']);
                $request->merge(['updates' => $transportUpdates]);
            }
            // A retry carries the same signed snapshot. Every response rotates its nonce,
            // so an intentional subsequent action gets a fresh identity, even without UI changes.
            if (isset($payload['components'])) {
                $identity = array_map(function ($component) {
                    $snapshot = json_decode($component['snapshot'] ?? '{}', true);
                    return [$snapshot['checksum'] ?? $component['snapshot'] ?? '', $component['calls'] ?? []];
                }, $payload['components']);
            } else {
                $actions = array_values(array_filter($payload['updates'] ?? [], function ($update) {
                    return !in_array($update['type'] ?? '', ['syncInput', 'syncInputDeferred'], true);
                }));
                if (!$actions) $actions = array_map(function ($update) {
                    return [$update['type'] ?? '', $update['payload']['name'] ?? ''];
                }, $payload['updates'] ?? []);
                $identity = [$payload['fingerprint']['id'] ?? '', $payload['serverMemo']['checksum'] ?? '', $actions];
            }
            // Property edits are the fingerprint, not a new operation identity. Changing
            // data under an already-submitted snapshot must conflict instead of writing twice.
            $key = hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
        }
        if (!is_string($key) || !preg_match('/^[A-Za-z0-9._:-]{16,200}$/D', $key)) {
            return response()->json(['message' => 'A valid Idempotency-Key header or submission key is required. Refresh the form and try again.'], 428);
        }
        $files = [];
        foreach (\Illuminate\Support\Arr::dot($request->allFiles()) as $name => $file) {
            $files[$name] = hash_file('sha256', $file->getRealPath());
        }
        $user = $request->user();
        $identity = $user ? get_class($user) . ':' . $user->getAuthIdentifier()
            : ($request->hasSession() ? $request->session()->getId() : hash('sha256', (string) $request->bearerToken()));
        $scope = hash('sha256', config('app.key')) . ':' . $identity . ':' . $request->method() . ':' . $request->path();
        $fingerprint = hash('sha256', json_encode([$payload, $files], JSON_THROW_ON_ERROR));

        $response = app(SubmissionLedger::class)->run($scope, $key, $fingerprint, function () use ($next, $request) {
            $response = $next($request);
            if ($request->is('livewire/*') && $request->has('fingerprint')) {
                $data = json_decode($response->getContent(), true);
                if (isset($data['effects']['returns'])) {
                    foreach ($request->input('updates', []) as $update) {
                        $id = $update['payload']['id'] ?? null;
                        if ($id !== null && array_key_exists($id, $data['effects']['returns'])) {
                            unset($update['payload']['id']);
                            $data['effects']['submissionReturns'][hash('sha256', json_encode($update))] = $data['effects']['returns'][$id];
                        }
                    }
                    $response->setContent(json_encode($data, JSON_THROW_ON_ERROR));
                }
            }
            return $response;
        });
        // Transport IDs differ on Livewire 2 retries. Bundled duplicate calls also
        // still have individual client promises: resolve each using the one execution.
        if ($request->is('livewire/*') && $response->getStatusCode() === 200) {
            $data = json_decode($response->getContent(), true);
            if (isset($data['effects']['submissionReturns'])) {
                $returns = [];
                foreach ($originalPayload['updates'] ?? [] as $update) {
                    $id = $update['payload']['id'] ?? null;
                    unset($update['payload']['id']);
                    $signature = hash('sha256', json_encode($update));
                    if ($id !== null && array_key_exists($signature, $data['effects']['submissionReturns'])) $returns[$id] = $data['effects']['submissionReturns'][$signature];
                }
                $data['effects']['returns'] = $returns;
                unset($data['effects']['submissionReturns']);
            }
            foreach ($originalPayload['components'] ?? [] as $index => $component) {
                if (!isset($data['components'][$index]['effects']['returns'])) continue;
                $unique = $this->unique($component['calls'] ?? []);
                $returns = $data['components'][$index]['effects']['returns'];
                $data['components'][$index]['effects']['returns'] = array_map(function ($call) use ($unique, $returns) {
                    return $returns[array_search($call, $unique, true)] ?? null;
                }, $component['calls'] ?? []);
            }
            if (is_array($data)) $response->setContent(json_encode($data, JSON_THROW_ON_ERROR));
        }
        return $response;
    }

    private function unique(array $calls)
    {
        $seen = [];
        foreach ($calls as $call) $seen[json_encode($call, JSON_THROW_ON_ERROR)] = $call;
        return array_values($seen);
    }
}
