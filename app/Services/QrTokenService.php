<?php

namespace App\Services;

use App\Models\Event;
use App\Models\SystemSetting;
use Illuminate\Support\Str;

class QrTokenService
{
    /**
     * Generate a secure, signed, dynamic QR token valid for 20 seconds.
     *
     * @param Event $event
     * @return array
     */
    public function generateToken(Event $event): array
    {
        $durationSeconds = (int) SystemSetting::get('qr_expiration_seconds', '60');
        $now = time();
        $expiresAt = $now + $durationSeconds;
        $nonce = Str::random(16);

        $secret = config('app.key', 'BSIS-Attendance-Secret-Key');
        $dataToSign = "{$event->id}:{$now}:{$expiresAt}:{$nonce}";
        $signature = hash_hmac('sha256', $dataToSign, $secret);

        $payload = [
            'event_id' => $event->id,
            'timestamp' => $now,
            'expires_at' => $expiresAt,
            'nonce' => $nonce,
            'sig' => $signature,
        ];

        $qrToken = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');

        return [
            'qr_token' => $qrToken,
            'expires_in_seconds' => $durationSeconds,
            'expires_at' => date('c', $expiresAt),
        ];
    }

    /**
     * Decrypt, verify signature, and validate dynamic QR token.
     *
     * @param string $qrToken
     * @param int|null $referenceTimestamp Optional reference timestamp for offline-synced scans
     * @return array ['is_valid' => bool, 'event_id' => int|null, 'error' => string|null, 'payload' => array|null]
     */
    public function validateToken(string $qrToken, ?int $referenceTimestamp = null): array
    {
        try {
            $base64 = strtr($qrToken, '-_', '+/');
            $json = base64_decode($base64);

            if (!$json) {
                return ['is_valid' => false, 'error' => 'Malformed QR token format.', 'event_id' => null];
            }

            $payload = json_decode($json, true);
            if (!is_array($payload) || !isset($payload['event_id'], $payload['timestamp'], $payload['expires_at'], $payload['nonce'], $payload['sig'])) {
                return ['is_valid' => false, 'error' => 'Invalid QR token structure.', 'event_id' => null];
            }

            $secret = config('app.key', 'BSIS-Attendance-Secret-Key');
            $dataToSign = "{$payload['event_id']}:{$payload['timestamp']}:{$payload['expires_at']}:{$payload['nonce']}";
            $expectedSig = hash_hmac('sha256', $dataToSign, $secret);

            if (!hash_equals($expectedSig, $payload['sig'])) {
                return ['is_valid' => false, 'error' => 'QR token signature verification failed (tampered QR).', 'event_id' => $payload['event_id']];
            }

            $checkTime = $referenceTimestamp ?? time();
            $gracePeriodSeconds = 5; // 5-second tolerance for cellular network latency & slight clock drift
            if ($checkTime > ($payload['expires_at'] + $gracePeriodSeconds)) {
                $secondsExpired = $checkTime - $payload['expires_at'];
                return [
                    'is_valid' => false,
                    'error' => "QR token was scanned {$secondsExpired} seconds after expiration.",
                    'event_id' => $payload['event_id'],
                ];
            }

            return [
                'is_valid' => true,
                'event_id' => (int) $payload['event_id'],
                'payload' => $payload,
                'error' => null,
            ];
        } catch (\Exception $e) {
            return ['is_valid' => false, 'error' => 'Failed to parse QR token: ' . $e->getMessage(), 'event_id' => null];
        }
    }
}
