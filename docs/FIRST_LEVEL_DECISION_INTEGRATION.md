# First-Level Insurance Decision System - Integration Guide

## Summary

The **First-Level Insurance Decision System** is now fully implemented and integrated into your Symfony application. This system provides automated first-level claim recommendations based on damage analysis and insurance type.

**Key Components:**
- ✅ `FirstLevelDecisionService` (900+ lines) - Core decision logic for all 7 insurance types
- ✅ `FirstLevelDecisionController` (API endpoints) - REST API for decision generation
- ✅ Service Registration - Fully configured in `config/services.yaml`
- ✅ Comprehensive Documentation - Full guides and examples
- ✅ Test Suite - Verification tests for all insurance types

---

## Quick Start

### 1. Using the API (Recommended)

After a claim image is analyzed, generate a first-level decision:

```bash
POST /api/first-level-decision/analyze/{claimId}
Content-Type: application/json

{
    "insurance_type": "AUTO",
    "context": {
        "vehicle_type": "sedan",
        "third_party": false
    }
}
```

**Response:**
```json
{
    "success": true,
    "claim_id": 45,
    "decision": {
        "damage_percentage": 48,
        "estimated_cost": 4200,
        "recommended_severity": "MEDIUM",
        "confidence_score": 74,
        "recommendation": "REVIEW",
        "estimated_payout": 3700,
        "coverage_applicable": true,
        "flags_for_review": [],
        "admin_notes": "..."
    }
}
```

### 2. Using the Service in Code

```php
// In your controller
$decision = $this->decisionService->generateDecision(
    [
        'severity' => 'MEDIUM',
        'damage_percentage' => 52,
        'estimated_cost' => 5200,
        'detected_features' => ['impact_damage', 'edge_complexity'],
        'confidence_score' => 0.78,
    ],
    'AUTO',  // Insurance type
    ['vehicle_type' => 'sedan']  // Optional context
);

// Access decision data
echo $decision['recommendation'];  // REVIEW, APPROVE, or DENY
echo $decision['estimated_payout'];  // Payout after deductible
echo $decision['reasoning'];  // Human-readable explanation
```

---

## Supported Insurance Types

### 1. AUTO
Evaluates vehicle damage and collision severity.
- **Assesses:** Impact damage, structural integrity, safety risk
- **Deductible:** $500
- **Example:** Car accident claims

### 2. HOME
Evaluates property and structural damage.
- **Assesses:** Water damage, fire damage, structural integrity, habitability
- **Deductible:** $1,000
- **Example:** Storm damage, fire claims

### 3. HEALTH
Evaluates injury severity and treatment needs.
- **Assesses:** Injury type, hospitalization requirement, treatment urgency
- **Deductible:** $250
- **Example:** Medical claims

### 4. TRAVEL
Evaluates travel incident impact and disruption.
- **Assesses:** Trip cancellation vs delay, financial impact
- **Deductible:** $100
- **Example:** Cancelled trips, travel emergencies

### 5. LIABILITY
Evaluates third-party risk and exposure.
- **Assesses:** Third-party involvement, witness availability, dispute likelihood
- **Deductible:** $2,500
- **Example:** Third-party injury or property damage claims

### 6. SCHOOL
Evaluates minor student incidents.
- **Assesses:** Incident severity, school supervision
- **Deductible:** $50
- **Example:** Student accident claims

### 7. PROFESSIONAL
Evaluates business impact and loss.
- **Assesses:** Business interruption, equipment damage, revenue impact
- **Deductible:** $5,000
- **Example:** Business interruption claims

---

## Recommendation Types

The system generates one of three recommendations:

### ✅ APPROVE
- **When:** MEDIUM severity, ≥70% confidence, coverage applies
- **Action:** Can proceed to payment processing immediately
- **Example:** Standard collision claim, clear evidence, high confidence

### 🔍 REVIEW
- **When:** MAJOR severity OR low confidence OR multiple flags
- **Action:** Requires human adjuster review before approval
- **Example:** Structural damage, competing witness accounts, liability questions

### ❌ DENY
- **When:** Coverage doesn't apply OR damage below deductible
- **Action:** Claim rejected, no payment
- **Example:** Damage not covered by policy, excluded incident type

---

## Integration Points

### Controller Integration Example

In your claim processing controller:

```php
<?php
namespace App\Controller;

use App\Service\DamageDetection\FirstLevelDecisionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ClaimController extends AbstractController
{
    public function processClaim(
        int $claimId,
        FirstLevelDecisionService $decisionService
    ) {
        // ... get claim and analysis ...

        // Generate first-level decision
        $decision = $decisionService->generateDecision(
            [
                'severity' => $damageAnalysis->getSeverity(),
                'damage_percentage' => $damageAnalysis->getAffectedAreaPercentage(),
                'estimated_cost' => $damageAnalysis->getEstimatedCost(),
                'detected_features' => $this->extractFeatures($damageAnalysis),
                'confidence_score' => 0.75,
            ],
            $claim->getContrat()->getType(),  // Insurance type
            [
                'third_party' => $claim->hasThirdParty(),
                'witnesses' => $claim->getWitnessCount(),
            ]
        );

        // Store decision
        $claim->setFirstLevelRecommendation($decision['recommendation']);
        $claim->setEstimatedPayout($decision['estimated_payout']);
        $em->persist($claim);
        $em->flush();

        return $decision;
    }
}
```

### Template Integration Example (Twig)

```twig
{% if decision %}
<div class="decision-container">
    <div class="alert alert-{{ decision.recommendation|lower }}">
        <h3>First-Level Assessment</h3>
        <p><strong>Recommendation:</strong> 
            {% if decision.recommendation == 'APPROVE' %}
                <span class="badge badge-success">APPROVE - Can proceed to payment</span>
            {% elseif decision.recommendation == 'REVIEW' %}
                <span class="badge badge-warning">REVIEW - Requires adjuster review</span>
            {% else %}
                <span class="badge badge-danger">DENY - Not covered</span>
            {% endif %}
        </p>
    </div>

    <div class="details-grid">
        <div class="detail-item">
            <label>Damage Percentage:</label>
            <span>{{ decision.damage_percentage }}%</span>
        </div>
        <div class="detail-item">
            <label>Severity Level:</label>
            <span>{{ decision.recommended_severity }}</span>
        </div>
        <div class="detail-item">
            <label>Confidence:</label>
            <span>{{ decision.confidence_score }}%</span>
        </div>
        <div class="detail-item">
            <label>Estimated Payout:</label>
            <span>${{ decision.estimated_payout }}</span>
        </div>
    </div>

    {% if decision.flags_for_review and decision.flags_for_review[0] != 'None' %}
    <div class="alert alert-info">
        <strong>Items Requiring Review:</strong>
        <ul>
        {% for flag in decision.flags_for_review %}
            <li>{{ flag }}</li>
        {% endfor %}
        </ul>
    </div>
    {% endif %}

    <div class="admin-section">
        <h4>Admin Notes</h4>
        <pre>{{ decision.admin_notes }}</pre>
    </div>
</div>
{% endif %}
```

---

## Confidence Scores

The decision confidence (0-100%) indicates recommendation reliability:

| Confidence | Interpretation | Recommendation |
|-----------|-----------------|-----------------|
| 90-100% | Excellent - Clear evidence | Can be trusted |
| 75-89% | Good - Good evidence | Likely accurate |
| 60-74% | Moderate - Some uncertainty | Should be reviewed |
| <60% | Low - High uncertainty | Requires manual inspection |

---

## Key Features

✅ **Insurance-Type-Aware** - Different assessment rules for each insurance category

✅ **Coverage Applicability** - Determines if claim is covered by policy

✅ **Automatic Flagging** - Identifies claims requiring special attention

✅ **Payout Calculation** - Estimates insurance payout after deductible

✅ **Explainable Decisions** - Provides reasoning for every recommendation

✅ **Admin Notes** - Detailed assessment information for claims adjusters

✅ **Type-Specific Context** - Optional context parameters (vehicle type, witnesses, etc.)

---

## Configuration

Services are registered in `config/services.yaml`:

```yaml
services:
    App\Service\DamageDetection\FirstLevelDecisionService:
        public: true
        arguments:
            $logger: '@logger'

    App\Controller\API\FirstLevelDecisionController:
        public: true
```

---

## Important Notes

⚠️ **This is a FIRST-LEVEL RECOMMENDATION SYSTEM ONLY**

- Recommendations are **NOT** final approvals
- All decisions **MUST** be reviewed by human administrators
- Final authority rests with claims adjuster/manager
- System cannot override policy exclusions
- Fraud detection requires manual investigation
- Special circumstances need human judgment

---

## File Structure

```
src/Service/DamageDetection/
  ├── RawImageAnalysisService.php (image analysis - pure data)
  ├── RuleBasedSeverityEngine.php (severity classification)
  ├── InsuranceCostCalculator.php (cost estimation)
  ├── AnalysisValidationService.php (consistency checks)
  ├── DamageDetectionOrchestrator.php (pipeline coordination)
  └── FirstLevelDecisionService.php ← NEW (insurance-specific recommendations)

src/Controller/API/
  └── FirstLevelDecisionController.php ← NEW (REST endpoints)

config/
  └── services.yaml (updated with new service registrations)

docs/
  ├── FIRST_LEVEL_DECISION_SYSTEM.md ← Comprehensive guide
  └── FIRST_LEVEL_DECISION_INTEGRATION.md ← This file
```

---

## Next Steps

1. **Test the API**: Call `/api/first-level-decision/analyze/{claimId}` with test data
2. **Review Recommendations**: Check that recommendations match expected values
3. **Integrate into Workflow**: Add decision display to claim details page
4. **Set Up Admin Review**: Create admin interface to act on recommendations
5. **Monitor Results**: Track approval/denial rates by insurance type

---

## Support

For claims that don't fit standard patterns:
- Use the `context` parameter to provide additional information
- Lower confidence manually if decision seems unreliable
- Flag for review with detailed admin notes

All unusual claims can be manually escalated to human review regardless of recommendation.
