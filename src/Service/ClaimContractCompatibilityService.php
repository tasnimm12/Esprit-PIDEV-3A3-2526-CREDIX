<?php

namespace App\Service;

class ClaimContractCompatibilityService
{
    private const TYPE_KEYWORDS = [
        'AUTO' => [
            'auto', 'car', 'vehicle', 'voiture', 'accident', 'collision', 'crash',
            'bumper', 'hood', 'windshield', 'moto', 'motorcycle', 'truck', 'garage',
            'road', 'route', 'driving', 'driver',
        ],
        'SANTE' => [
            'sante', 'health', 'medical', 'doctor', 'hospital', 'clinic', 'injury',
            'maladie', 'illness', 'treatment', 'surgery', 'medication', 'consultation',
            'patient', 'care', 'blessure',
        ],
        'HABITATION' => [
            'home', 'house', 'apartment', 'habitation', 'maison', 'roof', 'kitchen',
            'fire', 'flood', 'water leak', 'storm', 'window', 'burglary', 'theft',
            'property damage', 'residence',
        ],
        'VOYAGE' => [
            'travel', 'voyage', 'trip', 'flight', 'airport', 'hotel', 'luggage',
            'baggage', 'reservation', 'visa', 'abroad', 'journey',
        ],
        'VIE' => [
            'life insurance', 'vie', 'death', 'deces', 'beneficiary', 'inheritance',
            'funeral', 'survivor',
        ],
        'SCOLAIRE' => [
            'school', 'scolaire', 'student', 'classroom', 'teacher', 'campus',
            'education', 'university', 'college',
        ],
        'RESPONSABILITE_CIVILE' => [
            'liability', 'responsabilite', 'third party', 'tiers', 'caused damage',
            'damage to others', 'civil', 'neighbor', 'client damage',
        ],
        'PROFESSIONNELLE' => [
            'professional', 'business', 'office', 'company', 'workplace', 'employee',
            'commercial', 'client project', 'enterprise',
        ],
    ];

    public function evaluate(?string $description, ?string $contractType): array
    {
        $normalizedContractType = strtoupper(trim((string) $contractType));
        if ($normalizedContractType === '') {
            return [
                'is_compatible' => true,
                'detected_type' => null,
                'confidence' => 0,
                'message' => null,
            ];
        }

        $detected = $this->detectClaimType($description);
        if ($detected === null) {
            return [
                'is_compatible' => true,
                'detected_type' => null,
                'confidence' => 0,
                'message' => null,
            ];
        }

        if ($detected['type'] === $normalizedContractType) {
            return [
                'is_compatible' => true,
                'detected_type' => $detected['type'],
                'confidence' => $detected['score'],
                'message' => null,
            ];
        }

        return [
            'is_compatible' => false,
            'detected_type' => $detected['type'],
            'confidence' => $detected['score'],
            'message' => sprintf(
                'Claim rejected: this contract covers %s, but your description looks like a %s claim.',
                $this->humanizeType($normalizedContractType),
                $this->humanizeType($detected['type'])
            ),
        ];
    }

    public function detectClaimTypeFromDescription(?string $description): ?string
    {
        $detected = $this->detectClaimType($description);

        return $detected['type'] ?? null;
    }

    private function detectClaimType(?string $description): ?array
    {
        $normalizedDescription = $this->normalize($description);
        if ($normalizedDescription === '') {
            return null;
        }

        $scores = [];
        foreach (self::TYPE_KEYWORDS as $type => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                $normalizedKeyword = $this->normalize($keyword);
                if ($normalizedKeyword !== '' && str_contains($normalizedDescription, $normalizedKeyword)) {
                    $score++;
                }
            }

            if ($score > 0) {
                $scores[$type] = $score;
            }
        }

        if ($scores === []) {
            return null;
        }

        arsort($scores);
        $topType = array_key_first($scores);
        $topScore = $scores[$topType];
        $secondScore = array_values($scores)[1] ?? 0;

        if ($topScore < 2 || $topScore === $secondScore) {
            return null;
        }

        return [
            'type' => $topType,
            'score' => $topScore,
        ];
    }

    private function normalize(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) {
            $value = $ascii;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function humanizeType(string $type): string
    {
        return match ($type) {
            'AUTO' => 'Auto',
            'SANTE' => 'Health',
            'HABITATION' => 'Home',
            'VOYAGE' => 'Travel',
            'VIE' => 'Life',
            'SCOLAIRE' => 'School',
            'RESPONSABILITE_CIVILE' => 'Liability',
            'PROFESSIONNELLE' => 'Professional',
            default => ucfirst(strtolower(str_replace('_', ' ', $type))),
        };
    }
}
