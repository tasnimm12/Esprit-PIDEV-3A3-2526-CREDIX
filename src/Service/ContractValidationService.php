<?php

namespace App\Service;

use App\Entity\ContratAssurance;
use App\Entity\Utilisateur;
use App\Entity\Assurance;
use App\Entity\CompteBancaire;
use Doctrine\ORM\EntityManagerInterface;

class ContractValidationService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Validate contract creation data
     */
    public function validateContractCreation(array $data, ?Utilisateur $currentUser = null, bool $isAdmin = false): array
    {
        $errors = [];

        // Validate insurance selection
        if (empty($data['assurance_id'])) {
            $errors[] = 'Please select an insurance';
        } else {
            $assurance = $this->entityManager->getRepository(Assurance::class)->find((int)$data['assurance_id']);
            if (!$assurance) {
                $errors[] = 'Insurance policy not found';
            }
        }

        // Validate bank account selection
        if (!$isAdmin) {
            // For non-admin users, bank account is required
            if (empty($data['compte_bancaire_id'])) {
                $errors[] = 'Please select a bank account';
            } else {
                $compteBancaire = $this->entityManager->getRepository(CompteBancaire::class)->find((int)$data['compte_bancaire_id']);
                if (!$compteBancaire) {
                    $errors[] = 'Bank account not found';
                } elseif (!$compteBancaire->getActif()) {
                    $errors[] = 'Selected bank account is not active';
                }
            }
        } else if (isset($data['compte_bancaire_id']) && !empty($data['compte_bancaire_id'])) {
            // For admin, bank account is optional but if provided must be valid
            $compteBancaire = $this->entityManager->getRepository(CompteBancaire::class)->find((int)$data['compte_bancaire_id']);
            if (!$compteBancaire) {
                $errors[] = 'Selected bank account not found';
            } elseif (!$compteBancaire->getActif()) {
                $errors[] = 'Selected bank account is not active';
            }
        }

        // Validate signing date
        if (empty($data['date_signature'])) {
            $errors[] = 'Signing date is required';
        } else {
            $this->validateSigningDate($data['date_signature'], $errors);
        }

        // Validate expiration date (required)
        if (empty($data['date_fin_contrat'])) {
            $errors[] = 'Expiration date is required';
        } else {
            $this->validateExpirationDate($data['date_signature'] ?? null, $data['date_fin_contrat'], $errors);
        }

        // Validate contract duration (required)
        if (empty($data['duree_contrat'])) {
            $errors[] = 'Contract duration is required';
        } else {
            $this->validateDuration($data['duree_contrat'], $errors);
        }

        // Validate annual maximum (required)
        if (empty($data['plafond_annuel'])) {
            $errors[] = 'Annual maximum is required';
        } else {
            $this->validateAnnualMaximum($data['plafond_annuel'], $errors);
        }

        // Validate reimbursement rate (required)
        if (empty($data['taux_remboursement'])) {
            $errors[] = 'Reimbursement rate is required';
        } else {
            $this->validateReimbursementRate($data['taux_remboursement'], $errors);
        }

        // Validate waiting period (required)
        if (empty($data['delai_carence'])) {
            $errors[] = 'Waiting period is required';
        } else {
            $this->validateWaitingPeriod($data['delai_carence'], $errors);
        }

        // Validate user exists and has sufficient balance (for new contracts)
        $userId = $data['utilisateur_id'] ?? ($currentUser ? $currentUser->getId() : null);
        if (empty($userId)) {
            $errors[] = 'User is required';
        } else {
            $this->validateUserAndBalance((int)$userId, !empty($data['assurance_id']) ? (int)$data['assurance_id'] : null, $errors);
        }

        // Validate contract number (required)
        if (empty($data['numero_contrat'])) {
            $errors[] = 'Contract number is required';
        } else {
            $this->validateContractNumber($data['numero_contrat'], $errors);
        }

        return $errors;
    }

    /**
     * Validate contract edit data
     */
    public function validateContractEdit(array $data): array
    {
        $errors = [];

        // Validate status (required)
        if (empty($data['statut'])) {
            $errors[] = 'Status is required';
        } elseif (!in_array($data['statut'], ['ACTIF', 'INACTIF', 'SUSPENDU', 'RESILIE', 'ACCEPTED', 'EN_ATTENTE', 'EXPIRE'])) {
            $errors[] = 'Invalid status selected';
        }

        // Validate insurance selection (required)
        if (empty($data['assurance_id'])) {
            $errors[] = 'Please select an insurance';
        } else {
            $assurance = $this->entityManager->getRepository(Assurance::class)->find((int)$data['assurance_id']);
            if (!$assurance) {
                $errors[] = 'Insurance policy not found';
            }
        }

        // Validate bank account selection (required)
        if (empty($data['compte_bancaire_id'])) {
            $errors[] = 'Please select a bank account';
        } else {
            $compte = $this->entityManager->getRepository(CompteBancaire::class)->find((int)$data['compte_bancaire_id']);
            if (!$compte) {
                $errors[] = 'Bank account not found';
            } elseif (!$compte->getActif()) {
                $errors[] = 'Selected bank account is not active';
            }
        }

        // Validate contract number (required)
        if (empty($data['numero_contrat'])) {
            $errors[] = 'Contract number is required';
        } else {
            $this->validateContractNumber($data['numero_contrat'], $errors);
        }

        // Validate signing date (required)
        if (empty($data['date_signature'])) {
            $errors[] = 'Signing date is required';
        } else {
            $this->validateSigningDate($data['date_signature'], $errors);
        }

        // Validate expiration date (required)
        if (empty($data['date_fin_contrat'])) {
            $errors[] = 'Expiration date is required';
        } else {
            $this->validateExpirationDate($data['date_signature'] ?? null, $data['date_fin_contrat'], $errors);
        }

        // Validate duration (required)
        if (empty($data['duree_contrat'])) {
            $errors[] = 'Duration is required';
        } else {
            $this->validateDuration($data['duree_contrat'], $errors);
        }

        // Validate annual maximum (required)
        if (empty($data['plafond_annuel'])) {
            $errors[] = 'Annual maximum is required';
        } else {
            $this->validateAnnualMaximum($data['plafond_annuel'], $errors);
        }

        // Validate reimbursement rate (required)
        if (empty($data['taux_remboursement'])) {
            $errors[] = 'Reimbursement rate is required';
        } else {
            $this->validateReimbursementRate($data['taux_remboursement'], $errors);
        }

        // Validate waiting period (required)
        if (empty($data['delai_carence'])) {
            $errors[] = 'Waiting period is required';
        } else {
            $this->validateWaitingPeriod($data['delai_carence'], $errors);
        }

        return $errors;
    }

    /**
     * Validate signing date
     */
    private function validateSigningDate(?string $signDate, array &$errors): void
    {
        if (empty($signDate)) {
            return;
        }

        try {
            $date = new \DateTime($signDate);
            $today = new \DateTime();
            $today->setTime(0, 0, 0);
            
            if ($date > $today) {
                $errors[] = 'Signing date cannot be in the future';
            }
        } catch (\Exception $e) {
            $errors[] = 'Invalid signing date format';
        }
    }

    /**
     * Validate expiration date
     */
    private function validateExpirationDate(?string $signDate, string $endDate, array &$errors): void
    {
        try {
            $end = new \DateTime($endDate);
            
            if (!empty($signDate)) {
                $start = new \DateTime($signDate);
                if ($end <= $start) {
                    $errors[] = 'Expiration date must be after signing date';
                }
            }
        } catch (\Exception $e) {
            $errors[] = 'Invalid expiration date format';
        }
    }

    /**
     * Validate contract duration
     */
    private function validateDuration($duration, array &$errors): void
    {
        $duree = intval($duration);
        
        if ($duree < 0 || $duree > 360) {
            $errors[] = 'Duration must be between 0 and 360 months';
        }
        
        if ($duree !== (int)$duration) {
            $errors[] = 'Duration must be a whole number';
        }
    }

    /**
     * Validate annual maximum
     */
    private function validateAnnualMaximum($maximum, array &$errors): void
    {
        $plafond = floatval($maximum);
        
        if ($plafond < 0 || $plafond > 999999999) {
            $errors[] = 'Annual maximum must be between 0 and 999,999,999';
        }
    }

    /**
     * Validate reimbursement rate
     */
    private function validateReimbursementRate($rate, array &$errors): void
    {
        $taux = floatval($rate);
        
        if ($taux < 0 || $taux > 100) {
            $errors[] = 'Reimbursement rate must be between 0% and 100%';
        }
    }

    /**
     * Validate waiting period
     */
    private function validateWaitingPeriod($period, array &$errors): void
    {
        $delai = intval($period);
        
        if ($delai < 0 || $delai > 365) {
            $errors[] = 'Waiting period must be between 0 and 365 days';
        }
        
        if ($delai !== (int)$period) {
            $errors[] = 'Waiting period must be a whole number';
        }
    }

    /**
     * Validate contract number
     */
    private function validateContractNumber(string $numero, array &$errors): void
    {
        if (strlen($numero) < 3 || strlen($numero) > 50) {
            $errors[] = 'Contract number must be between 3 and 50 characters';
        }
    }

    /**
     * Validate user exists and has sufficient balance for insurance cost
     */
    private function validateUserAndBalance(?int $userId, ?int $assuranceId, array &$errors): void
    {
        if (empty($userId)) {
            $errors[] = 'User is required';
            return;
        }

        $utilisateur = $this->entityManager->getRepository(Utilisateur::class)->find($userId);
        if (!$utilisateur) {
            $errors[] = 'User not found';
            return;
        }

        // Check insurance exists and user has sufficient balance
        if (!empty($assuranceId)) {
            $assurance = $this->entityManager->getRepository(Assurance::class)->find($assuranceId);
            if (!$assurance) {
                $errors[] = 'Insurance policy not found';
                return;
            }

            $priceAssurance = (float)($assurance->getPrixAssurance() ?? 0);
            if ($priceAssurance > 0) {
                $bankAccounts = $utilisateur->getComptesbanCaires();
                $hasSufficientBalance = false;

                foreach ($bankAccounts as $compte) {
                    if ($compte->getActif() && (float)$compte->getSolde() >= $priceAssurance) {
                        $hasSufficientBalance = true;
                        break;
                    }
                }

                if (!$hasSufficientBalance) {
                    $errors[] = 'Insufficient balance. Insurance cost: $' . number_format($priceAssurance, 2) . '. Please ensure you have a bank account with sufficient funds.';
                }
            }
        }
    }

    /**
     * Sanitize and validate string input
     */
    public function sanitizeString(?string $input, string $fieldName = 'field', int $minLength = 0, int $maxLength = 255): ?array
    {
        if ($input === null || $input === '') {
            return null;
        }

        // Trim whitespace
        $sanitized = trim($input);

        // Check for empty after trimming
        if (empty($sanitized)) {
            return ['error' => $fieldName . ' cannot be empty'];
        }

        // Check length
        if (strlen($sanitized) < $minLength) {
            return ['error' => $fieldName . ' must be at least ' . $minLength . ' characters'];
        }

        if (strlen($sanitized) > $maxLength) {
            return ['error' => $fieldName . ' must not exceed ' . $maxLength . ' characters'];
        }

        // Escape for security (prevents XSS)
        $escaped = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');

        return ['value' => $escaped];
    }

    /**
     * Validate and sanitize numeric input
     */
    public function validateNumeric(?string $input, string $fieldName = 'field', ?float $min = null, ?float $max = null, bool $allowNegative = false): ?array
    {
        if ($input === null || $input === '') {
            return null;
        }

        // Try to convert to float
        if (!is_numeric($input)) {
            return ['error' => $fieldName . ' must be a valid number'];
        }

        $value = (float)$input;

        // Check for negative values if not allowed
        if (!$allowNegative && $value < 0) {
            return ['error' => $fieldName . ' cannot be negative'];
        }

        // Check min value
        if ($min !== null && $value < $min) {
            return ['error' => $fieldName . ' must be at least ' . $min];
        }

        // Check max value
        if ($max !== null && $value > $max) {
            return ['error' => $fieldName . ' must not exceed ' . $max];
        }

        return ['value' => $value];
    }

    /**
     * Validate integer input
     */
    public function validateInteger(?string $input, string $fieldName = 'field', ?int $min = null, ?int $max = null): ?array
    {
        if ($input === null || $input === '') {
            return null;
        }

        // Check if it's an integer
        if (!ctype_digit((string)$input) && !((string)$input[0] === '-' && ctype_digit(substr((string)$input, 1)))) {
            return ['error' => $fieldName . ' must be a whole number'];
        }

        $value = (int)$input;

        // Check min value
        if ($min !== null && $value < $min) {
            return ['error' => $fieldName . ' must be at least ' . $min];
        }

        // Check max value
        if ($max !== null && $value > $max) {
            return ['error' => $fieldName . ' must not exceed ' . $max];
        }

        return ['value' => $value];
    }

    /**
     * Validate percentage input
     */
    public function validatePercentage(?string $input, string $fieldName = 'Percentage'): ?array
    {
        if ($input === null || $input === '') {
            return null;
        }

        if (!is_numeric($input)) {
            return ['error' => $fieldName . ' must be a valid number'];
        }

        $value = (float)$input;

        if ($value < 0 || $value > 100) {
            return ['error' => $fieldName . ' must be between 0 and 100'];
        }

        return ['value' => $value];
    }

    /**
     * Validate email input
     */
    public function validateEmail(?string $input, string $fieldName = 'Email'): ?array
    {
        if ($input === null || $input === '') {
            return null;
        }

        $email = trim($input);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => $fieldName . ' is not a valid email address'];
        }

        return ['value' => htmlspecialchars($email, ENT_QUOTES, 'UTF-8')];
    }

    /**
     * Validate phone number input
     */
    public function validatePhoneNumber(?string $input, string $fieldName = 'Phone'): ?array
    {
        if ($input === null || $input === '') {
            return null;
        }

        $phone = trim($input);

        // Allow various phone formats
        if (!preg_match('/^[+]?[(]?[0-9]{1,4}[)]?[-\s\.]?[(]?[0-9]{1,4}[)]?[-\s\.]?[0-9]{1,9}$/', $phone)) {
            return ['error' => $fieldName . ' format is invalid. Use format: +XX XX XXX XXXX'];
        }

        return ['value' => htmlspecialchars($phone, ENT_QUOTES, 'UTF-8')];
    }

    /**
     * Sanitize textarea/multi-line text input
     */
    public function sanitizeTextarea(?string $input, string $fieldName = 'field', int $maxLength = 5000): ?array
    {
        if ($input === null || $input === '') {
            return null;
        }

        $sanitized = trim($input);

        if (strlen($sanitized) > $maxLength) {
            return ['error' => $fieldName . ' must not exceed ' . $maxLength . ' characters'];
        }

        // Escape but preserve line breaks
        $escaped = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');

        return ['value' => $escaped];
    }

    /**
     * Get all validation errors from request
     */
    public function validateAllFields(array $data): array
    {
        $errors = [];

        // Validate contract number
        if (!empty($data['numero_contrat'])) {
            $validation = $this->sanitizeString($data['numero_contrat'], 'Contract number', 3, 50);
            if ($validation && isset($validation['error'])) {
                $errors[] = $validation['error'];
            }
        }

        // Validate special conditions if provided
        if (!empty($data['conditions_particulieres'])) {
            $validation = $this->sanitizeTextarea($data['conditions_particulieres'], 'Special conditions', 5000);
            if ($validation && isset($validation['error'])) {
                $errors[] = $validation['error'];
            }
        }

        // Validate exclusions if provided
        if (!empty($data['exclusions'])) {
            $validation = $this->sanitizeTextarea($data['exclusions'], 'Exclusions', 5000);
            if ($validation && isset($validation['error'])) {
                $errors[] = $validation['error'];
            }
        }

        // Validate beneficiary clause if provided
        if (!empty($data['clause_beneficiaire'])) {
            $validation = $this->sanitizeTextarea($data['clause_beneficiaire'], 'Beneficiary clause', 5000);
            if ($validation && isset($validation['error'])) {
                $errors[] = $validation['error'];
            }
        }

        return $errors;
    }
}

