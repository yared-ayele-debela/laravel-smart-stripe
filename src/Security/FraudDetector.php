<?php

namespace Yared\SmartStripe\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FraudDetector
{
    public function passes(array $context): bool
    {
        if (!config('stripe-smart.fraud_detection.enabled', true)) {
            return true;
        }

        $checks = [
            $this->checkPaymentsPerIp($context),
            $this->checkPaymentsPerUser($context),
            $this->checkRapidAttempts($context),
            $this->checkSuspiciousCountry($context),
        ];

        return !in_array(false, $checks);
    }

    protected function checkPaymentsPerIp(array $context): bool
    {
        $ip = $context['ip'] ?? null;
        if (!$ip) return true;

        $max = config('stripe-smart.fraud_detection.max_payments_per_ip_per_hour', 10);
        $key = "stripe_fraud_ip_{$ip}";

        $count = (int) Cache::get($key, 0);
        if ($count >= $max) {
            return false;
        }

        Cache::put($key, $count + 1, now()->addHour());
        return true;
    }

    protected function checkPaymentsPerUser(array $context): bool
    {
        $userId = $context['user_id'] ?? null;
        if (!$userId) return true;

        $max = config('stripe-smart.fraud_detection.max_payments_per_user_per_hour', 5);
        $key = "stripe_fraud_user_{$userId}";

        $count = (int) Cache::get($key, 0);
        if ($count >= $max) {
            return false;
        }

        Cache::put($key, $count + 1, now()->addHour());
        return true;
    }

    protected function checkRapidAttempts(array $context): bool
    {
        $ip = $context['ip'] ?? null;
        if (!$ip) return true;

        $window = config('stripe-smart.fraud_detection.rapid_payment_window_seconds', 60);
        $maxAttempts = config('stripe-smart.fraud_detection.max_rapid_attempts', 3);
        $key = "stripe_rapid_{$ip}";

        $attempts = Cache::get($key, []);
        $now = time();
        $attempts = array_filter($attempts, fn($t) => $now - $t < $window);
        $attempts[] = $now;

        if (count($attempts) > $maxAttempts) {
            return false;
        }

        Cache::put($key, $attempts, now()->addSeconds($window + 10));
        return true;
    }

    protected function checkSuspiciousCountry(array $context): bool
    {
        $countries = config('stripe-smart.fraud_detection.suspicious_countries', []);
        if (empty($countries)) return true;

        $country = $this->getCountryFromContext($context);
        return !in_array(strtoupper($country), array_map('strtoupper', $countries));
    }

    protected function getCountryFromContext(array $context): string
    {
        if (isset($context['country'])) {
            return $context['country'];
        }

        $ip = $context['ip'] ?? null;
        if (!$ip || in_array($ip, ['127.0.0.1', '::1'])) {
            return 'Local';
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(2)
                ->get("http://ip-api.com/json/{$ip}?fields=country");
            $data = $response->json();
            return $data['country'] ?? 'Unknown';
        } catch (\Throwable) {
            return 'Unknown';
        }
    }
}
