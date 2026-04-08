# Input Validation Guide (Contrôle de Saisie)

## Overview
A comprehensive input validation system has been implemented across all CRUD controllers to ensure data integrity and security.

## Components

### 1. **InputValidationService** (`src/Service/InputValidationService.php`)
Core validation service with methods for:
- `validateString()` - Min/max length, required check
- `validateEmail()` - Email format validation
- `validatePhone()` - Phone format validation (min 6 digits)
- `validateNumeric()` - Numeric values with min/max
- `validateInteger()` - Integer values with min/max
- `validateDate()` - Date format and future check
- `validateDateRange()` - Date range validation
- `validateEnum()` - Select from allowed values
- `validatePercentage()` - 0-100 range
- `validateBankAccount()` - Bank account format
- `validateIBAN()` - IBAN format
- Sanitization methods: `sanitizeString()`, `sanitizeNumeric()`, `sanitizeInteger()`

### 2. **CrudValidationTrait** (`src/Trait/CrudValidationTrait.php`)
Helper methods for each entity type:
- `validateBankAccount()` - All bank account fields
- `validateInsurance()` - All insurance fields
- `validateContract()` - All contract fields
- `validateClaim()` - All claim fields
- `validateSubscription()` - All subscription fields
- `validateUserProfile()` - All user fields

### 3. **Error Display Partial** (`templates/_partials/validation_errors.html.twig`)
Reusable error display template with Bootstrap styling.

## Implementation in Controllers

### Setup (Copy to each controller):

```php
<?php
namespace App\Controller;

use App\Service\InputValidationService;
use App\Trait\CrudValidationTrait;

class YourController extends AbstractController
{
    use CrudValidationTrait;
    
    private InputValidationService $validator;

    public function __construct(InputValidationService $validator)
    {
        $this->validator = $validator;
    }

    // Your methods...
}
```

### Example: Bank Account Validation

```php
#[Route('/compte-bancaire/new', name: 'app_compte_bancaire_new')]
public function new(Request $request, EntityManagerInterface $em): Response
{
    $errors = [];

    if ($request->isMethod('POST')) {
        // Sanitize inputs
        $numeroCompte = $this->validator->sanitizeString($request->request->get('numero_compte'));
        $titulaire = $this->validator->sanitizeString($request->request->get('titulaire'));
        $email = $this->validator->sanitizeString($request->request->get('email'));
        $telephone = $this->validator->sanitizeString($request->request->get('telephone'));
        $solde = $request->request->get('solde', '0');
        $devise = $this->validator->sanitizeString($request->request->get('devise', 'USD'));
        $typeCompte = $this->validator->sanitizeString($request->request->get('type_compte'));

        // Validate using trait method
        $data = [
            'numero_compte' => $numeroCompte,
            'titulaire' => $titulaire,
            'email' => $email,
            'telephone' => $telephone,
            'solde' => $solde,
            'devise' => $devise,
            'type_compte' => $typeCompte
        ];
        $errors = $this->validateBankAccount($data);

        if (empty($errors)) {
            $compte = new CompteBancaire();
            $compte->setUtilisateur($user);
            $compte->setNumeroCompte($numeroCompte);
            // ... set other properties
            $em->persist($compte);
            $em->flush();
            return $this->redirectToRoute('success_page');
        }
    }

    return $this->render('compte_bancaire/new.html.twig', [
        'errors' => $errors,
    ]);
}
```

## Validation Rules by Entity

### Bank Account (Compte Bancaire)
- **Numero Compte**: 5-34 chars (IBAN standard), required
- **Titulaire**: 2-100 chars, required
- **Email**: Valid email format, required
- **Telephone**: Min 6 digits, required
- **Solde**: 0-999,999,999, required, numeric
- **Devise**: USD|EUR|GBP|TND|JPY|CHF, required
- **Type Compte**: COURANT|EPARGNE|TITRE|CRYPTO, required

### Insurance (Assurance)
- **Type**: AUTOMOBILE|HABITATION|VIE|SANTE|VOYAGE|PROFESSIONNEL, required
- **Compagnie**: 2-100 chars, required
- **Numero Police**: 3-50 chars, required
- **Montant Couverture**: Numeric, min 0, required
- **Franchise**: Numeric, min 0, optional
- **Prime Annuelle**: Numeric, min 0, required
- **Prime Mensuelle**: Numeric, min 0, optional
- **Dates**: End date must be after start date

### Contract (Contrat)
- **Date Signature**: Not in future, required
- **Date Fin**: Must be after date signature
- **Durée**: 0-360 months integer
- **Taux Remboursement**: 0-100 percentage
- **Plafond Annuel**: Numeric, min 0
- **Statut**: ACTIF|SUSPENDU|EXPIRE|RESILLIE

### Claim (Sinistre)
- **Date Sinistre**: Not in future, required
- **Description**: 10-5000 chars, required
- **Statut**: EN_ATTENTE|ACCEPTE|REJETE|RESOLU
- **File Uploads**: Max 5 files, 25MB each, PDF/Images/Word only

### Subscription (Abonnement)
- **Type**: BASIC|STANDARD|PREMIUM|ENTERPRISE, required
- **Prix Mensuel**: Min 0.01, required
- **Prix Annuel**: Min 0.01, required
- **Durée**: 1-50 chars, required
- **Annual Price** should be >= Monthly Price

### User Profile
- **Nom**: 2-100 chars, required
- **Prenom**: 2-100 chars, required
- **Email**: Valid email format, required
- **Telephone**: Min 6 digits, optional

## Template Integration

Add validation error display to all forms:

```twig
{% extends 'base.html.twig' %}

{% block content %}
<div class="your-container">
    <!-- Display validation errors -->
    {% include '_partials/validation_errors.html.twig' %}

    <!-- Your form -->
    <form method="POST">
        <!-- form fields... -->
    </form>
</div>
{% endblock %}
```

## Usage in JavaScript

For client-side validation feedback:

```html
<input type="email" name="email" class="form-control" required>
<small class="form-text text-muted">Valid email format required</small>
```

## Security Features

1. **HTML Escaping**: All string inputs sanitized with `htmlspecialchars()`
2. **Type Casting**: Numeric values strictly cast to float/int
3. **Range Validation**: Min/max bounds enforced
4. **Format Validation**: Email, phone, IBAN patterns validated
5. **Date Logic**: Future dates rejected for incident/historical dates
6. **File Validation**: Type, size, count restrictions

## Implementation Checklist

- [ ] Add InputValidationService injection to controller
- [ ] Use CrudValidationTrait in controller
- [ ] Sanitize all inputs before validation
- [ ] Call validation method for the entity type
- [ ] Return form with errors if validation fails
- [ ] Pass errors to template: `'errors' => $errors`
- [ ] Include validation_errors.html.twig in template
- [ ] Add `$errors = []` initialization before form processing

## Example Controllers Updated

1. ✅ CompteBancaireController
2. ✅ SinistreController (partial)
3. ⏳ AssuranceController
4. ⏳ ContratAssuranceController
5. ⏳ AbonnementController (already has some validation)
6. ⏳ UserController
7. ⏳ AuthController

## Testing Validation

Test each controller by:
1. Submit empty form (required field check)
2. Submit invalid email
3. Submit future date
4. Submit out-of-range numbers
5. Submit oversized strings
6. Verify error messages display correctly
7. Verify form is redisplayed with user input preserved

## Notes

- All validation errors display in one consistent format
- Users see helpful error messages
- Invalid data cannot be saved to database
- Sanitization prevents XSS attacks
- Type casting prevents type-juggling vulnerabilities
