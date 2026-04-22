# First-Level Insurance Decision System

## Overview

A **FIRST-LEVEL DECISION SERVICE** that analyzes insurance claims and provides recommendations to administrators. This is NOT a final approval system - all decisions must be reviewed and approved by human administrators.

---

## How It Works

### 1. Claim Analysis Flow

```
User uploads image → Damage Analysis → First-Level Decision → Admin Review → Final Approval
```

### 2. Decision Process

The `FirstLevelDecisionService` takes:
- **Damage analysis result** (severity, cost, features, confidence)
- **Insurance type** (AUTO, HOME, HEALTH, TRAVEL, LIABILITY, SCHOOL, PROFESSIONAL)
- **Claim context** (optional: vehicle type, injury type, witnesses, etc.)

And generates:
- **Damage percentage** (0-100%)
- **Estimated cost** (insurance claim amount)
- **Detected features** (what damage indicators were found)
- **Recommended severity** (MINOR, MEDIUM, MAJOR)
- **Confidence score** (0-100%)
- **Insurance-specific assessment**
- **Coverage applicability**
- **Estimated payout** (after deductible)
- **Review flags** (issues requiring admin attention)
- **Recommendation** (APPROVE, REVIEW, DENY)
- **Admin notes** (detailed assessment for claims adjuster)

---

## Insurance Types Supported

### AUTO (Vehicle Damage)
- Evaluates collision severity
- Assesses repair complexity
- Determines safety risk
- Typical deductible: $500
- Estimates: $500-$25,000

**Assessment factors**:
- Impact damage detection
- Structural damage indicators
- Safety implications
- Drivability assessment

---

### HOME (Property Damage)
- Evaluates structural/property damage
- Assesses habitability
- Identifies damage type (water, fire, structural)
- Typical deductible: $1,000
- Estimates: $1,000-$50,000+

**Assessment factors**:
- Water damage extent
- Structural integrity
- Fire/heat damage
- Habitability status

---

### HEALTH (Injury Claims)
- Evaluates injury severity
- Determines treatment needs
- Assesses hospitalization requirement
- Typical deductible: $250
- Estimates: $500-$25,000

**Assessment factors**:
- Injury severity level
- Medical treatment needs
- Hospitalization requirement
- Recovery timeline implications

---

### TRAVEL (Trip Disruption)
- Evaluates incident impact
- Assesses trip disruption level
- Determines coverage applicability
- Typical deductible: $100
- Estimates: $100-$5,000

**Assessment factors**:
- Trip cancellation vs delay
- Baggage loss
- Medical emergency during travel
- Incident timing in trip

---

### LIABILITY (Risk Exposure)
- Evaluates third-party risk
- Assesses exposure level
- Determines dispute likelihood
- Typical deductible: $2,500
- Estimates: $2,500-$50,000+

**Assessment factors**:
- Third-party involvement
- Witness availability
- Injury to third parties
- Property damage to others
- Dispute likelihood

---

### SCHOOL (Student Incidents)
- Evaluates minor incident severity
- Assesses student injury
- Determines school liability
- Typical deductible: $50
- Estimates: $50-$2,500

**Assessment factors**:
- Incident type
- Injury severity (minor)
- School supervision implications
- Insurance applicability

---

### PROFESSIONAL (Business Loss)
- Evaluates business impact
- Assesses financial loss
- Determines coverage type
- Typical deductible: $5,000
- Estimates: $5,000-$100,000+

**Assessment factors**:
- Business interruption duration
- Equipment damage/loss
- Revenue impact
- Recovery costs

---

## API Endpoints

### Analyze Claim & Generate Decision

**POST** `/api/first-level-decision/analyze/{sinistreId}`

**Request Body**:
```json
{
    "insurance_type": "AUTO",
    "context": {
        "vehicle_type": "sedan",
        "third_party": false
    }
}
```

**Response**:
```json
{
    "success": true,
    "claim_id": 45,
    "decision": {
        "damage_percentage": 48,
        "estimated_cost": 4200,
        "detected_features": ["dark_areas", "edge_complexity", "brightness_variance"],
        "recommended_severity": "MEDIUM",
        "confidence_score": 74,
        "insurance_type": "AUTO",
        "reasoning": "First-level assessment for AUTO claim: MEDIUM damage (48% damage percentage). Vehicle damage, collision severity, repair cost. Repair complexity is moderate. This is a first-level recommendation and requires admin review for final approval.",
        "coverage_applicable": true,
        "flags_for_review": ["None - routine processing"],
        "estimated_payout": 3700,
        "deductible_impact": 500,
        "recommendation": "REVIEW",
        "admin_notes": "ADMIN REVIEW NOTES\n==================\n\nReview Flags:\n  • None - routine processing\n\nAssessment Details:\n  type_assessment: Vehicle damage analysis\n  damage_assessment: Vehicle operational but requires professional repair.\n  repair_complexity: standard\n  safety_risk: low\n  score: 0\n\nCoverage Information:\n  Applicable: Yes\n  Coverage Type: Collision\n  Deductible: $500\n  Estimated Payout: $3700\n\nIMPORTANT: This is a first-level recommendation only.\nFinal approval authority rests with admin/claims adjuster."
    },
    "note": "This is a first-level recommendation. Final approval authority rests with admin."
}
```

---

### Get Decision History

**GET** `/api/first-level-decision/history/{sinistreId}`

**Response**:
```json
{
    "success": true,
    "claim_id": 45,
    "analysis_history": [
        {
            "analyzed_at": "2026-04-19 12:00:00",
            "ai_model": "DamageDetectionOrchestrator-v3",
            "severity": "MEDIUM",
            "estimated_cost": 4200,
            "affected_area": 48
        }
    ]
}
```

---

## Decision Recommendations

The system recommends one of three actions:

### APPROVE
**Conditions**:
- Coverage applies
- Severity = MEDIUM with high confidence (≥70%)
- No review flags

**Action**: Can proceed to payment processing

**Example**: AUTO claim, $4,200 damage, no safety issues, high confidence

---

### REVIEW
**Conditions**:
- Coverage applies
- Severity = MAJOR (always), OR
- Low confidence (< 60%), OR
- Multiple review flags

**Action**: Requires human adjuster review before approval

**Example**: HOME claim, structural damage, uninhabitable property

---

### DENY
**Conditions**:
- Coverage does not apply
- Damage below deductible
- Claim fails coverage requirements

**Action**: No payment, notify claimant

**Example**: HOME claim with water damage not covered in policy

---

## Review Flags

System generates automated flags for admin attention:

- **Low Confidence Analysis** - Recommend manual inspection
- **Major Severity** - Requires adjuster inspection
- **Safety Risk High** - Vehicle/property inspection required
- **Third-Party Involvement** - Legal review recommended
- **Property Uninhabitable** - Emergency assistance may be needed
- **Extreme Damage (>90%)** - Consider total loss assessment
- **Minor Damage** - May not exceed deductible

---

## Confidence Scores

Confidence (0-100%) indicates reliability of recommendation:

- **80-100%**: High confidence - excellent image quality, multiple damage indicators
- **60-79%**: Medium confidence - good quality, some indicators detected
- **40-59%**: Low confidence - lower quality or few indicators
- **<40%**: Very low confidence - requires manual review

---

## Real-World Example

### Scenario: Car Accident Claim

**Step 1: Claim Filed**
- User uploads accident photos
- Images automatically analyzed by DamageDetectionOrchestrator

**Step 2: First-Level Decision**
```
POST /api/first-level-decision/analyze/45
Body: {"insurance_type": "AUTO"}

Analysis Results:
- Damage percentage: 72%
- Estimated cost: $19,800
- Severity: MAJOR
- Confidence: 74%

Decision:
- Recommendation: REVIEW
- Estimated payout: $19,300 (after $500 deductible)
- Flags: "Major severity - Requires adjuster inspection"
```

**Step 3: Admin Review**
- Adjuster receives recommendation
- Reviews damage photos
- Checks for safety issues
- Verifies coverage
- Approves claim and payment

---

## Integration with Existing System

### Controller Integration

In `SinistreController`, after image analysis:

```php
$analysis = $this->orchestrator->analyzeImage($preuve, $sinistre);

// Generate first-level decision
$decision = $this->decisionService->generateDecision(
    [
        'severity' => $analysis->getSeverity(),
        'damage_percentage' => $analysis->getAffectedAreaPercentage(),
        'estimated_cost' => $analysis->getEstimatedCost(),
    ],
    'AUTO'  // or determined from contract type
);

// Store decision in session/database for admin review
$request->getSession()->set('first_level_decision', $decision);
```

### Display in Claim Details

Show first-level recommendation alongside analysis:

```twig
<div class="decision-panel">
    <h3>First-Level Assessment (Admin Review Required)</h3>
    
    <div class="alert alert-{{ decision.recommendation|lower }}">
        <strong>Recommendation:</strong> {{ decision.recommendation }}
    </div>
    
    <div class="details">
        <p><strong>Severity:</strong> {{ decision.recommended_severity }}</p>
        <p><strong>Damage:</strong> {{ decision.damage_percentage }}%</p>
        <p><strong>Confidence:</strong> {{ decision.confidence_score }}%</p>
        <p><strong>Est. Payout:</strong> ${{ decision.estimated_payout }}</p>
    </div>
    
    {% if decision.flags_for_review %}
        <div class="alert alert-warning">
            <strong>Review Flags:</strong>
            <ul>
            {% for flag in decision.flags_for_review %}
                <li>{{ flag }}</li>
            {% endfor %}
            </ul>
        </div>
    {% endif %}
    
    <div class="admin-notes">
        <strong>Admin Notes:</strong>
        <pre>{{ decision.admin_notes }}</pre>
    </div>
</div>
```

---

## Important Notes

⚠️ **THIS IS A RECOMMENDATION SYSTEM ONLY**

- First-level decisions are **NOT** final approvals
- All decisions **MUST** be reviewed by human administrators
- Final authority rests with claims adjuster/manager
- System cannot override policy exclusions
- Fraud detection requires manual investigation
- Special circumstances need human judgment

---

## Data Flow

```
                    Claim Submitted
                          ↓
                    Image Uploaded
                          ↓
            DamageDetectionOrchestrator
                          ↓
            Raw Analysis → Severity → Cost → Validation
                          ↓
            DamageAnalysis Entity (Saved)
                          ↓
            FirstLevelDecisionService
                          ↓
            Assessment by Insurance Type
                          ↓
            Generate Recommendation
                          ↓
            FirstLevelDecision (recommendation)
                          ↓
            Admin Dashboard (For Review)
                          ↓
            Human Admin Review & Final Approval
                          ↓
            Payment Processing or Denial
```

---

## Configuration

Services registered in `config/services.yaml`:

```yaml
App\Service\DamageDetection\FirstLevelDecisionService:
    public: true

App\Controller\API\FirstLevelDecisionController:
    public: true
```

---

## Future Enhancements

1. **Policy-Based Assessment** - Apply specific policy exclusions/limits
2. **Fraud Scoring** - Flag suspicious claims for investigation
3. **Historical Comparison** - Compare against similar claims
4. **ML-Based Confidence** - Improve confidence scoring
5. **Multi-Stage Workflows** - Different review paths by severity
6. **Integration with Payment Systems** - Auto-route approved claims to payment

---

## Support

For claims that don't fit standard patterns or have complex requirements, always require manual admin review by setting confidence to <60% or flagging with review note.
