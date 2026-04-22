# First-Level Decision System - Quick Reference

## 📋 Quick Start

### Generate a Decision (3 lines of code)

```php
$decision = $this->decisionService->generateDecision(
    ['severity' => 'MEDIUM', 'damage_percentage' => 52, 'estimated_cost' => 5200, 'confidence_score' => 0.78],
    'AUTO'
);
echo $decision['recommendation'];  // "REVIEW"
```

### Call the API

```bash
curl -X POST http://localhost:8000/api/first-level-decision/analyze/45 \
  -H "Content-Type: application/json" \
  -d '{"insurance_type": "AUTO"}'
```

---

## 📊 Decision Logic at a Glance

| Condition | Recommendation |
|-----------|----------------|
| MINOR (except SCHOOL) | ❌ DENY |
| MINOR + SCHOOL | ✅ APPROVE (if confidence ≥ 70%) |
| MEDIUM + confidence ≥ 70% | ✅ APPROVE |
| MEDIUM + confidence < 70% | 🔍 REVIEW |
| MAJOR (any) | 🔍 REVIEW |
| Coverage doesn't apply | ❌ DENY |

---

## 💰 Deductibles by Type

| Type | Deductible |
|------|-----------|
| AUTO | $500 |
| HOME | $1,000 |
| HEALTH | $250 |
| TRAVEL | $100 |
| LIABILITY | $2,500 |
| SCHOOL | $50 |
| PROFESSIONAL | $5,000 |

---

## 🚩 Review Flags (Auto-Generated)

- Low confidence (< 60%) - Recommend manual inspection
- Major severity - Requires adjuster inspection
- Safety risk high - Vehicle inspection required
- Third-party involvement - Legal review recommended
- Property uninhabitable - Emergency assistance may be needed
- Extreme damage (> 90%) - Consider total loss assessment
- Minor damage - May not exceed deductible

---

## 📁 File Locations

| Component | Location |
|-----------|----------|
| Service | `src/Service/DamageDetection/FirstLevelDecisionService.php` |
| API Controller | `src/Controller/API/FirstLevelDecisionController.php` |
| Examples | `src/Examples/FirstLevelDecisionExamples.php` |
| System Guide | `docs/FIRST_LEVEL_DECISION_SYSTEM.md` |
| Integration Guide | `docs/FIRST_LEVEL_DECISION_INTEGRATION.md` |
| Delivery Doc | `docs/FIRST_LEVEL_DECISION_DELIVERY.md` |

---

## 🔗 API Endpoints

### Analyze Claim
```
POST /api/first-level-decision/analyze/{sinistreId}
```
**Body**: `{"insurance_type": "AUTO", "context": {...}}`
**Response**: Complete decision object

### Decision History
```
GET /api/first-level-decision/history/{sinistreId}
```
**Response**: Array of past analyses

---

## 📝 Decision Object Structure

```json
{
  "damage_percentage": 52,
  "estimated_cost": 5200,
  "detected_features": ["impact_damage", "edge_complexity"],
  "recommended_severity": "MEDIUM",
  "confidence_score": 78,
  "insurance_type": "AUTO",
  "reasoning": "...",
  "coverage_applicable": true,
  "flags_for_review": [],
  "estimated_payout": 4700,
  "deductible_impact": 500,
  "recommendation": "REVIEW",
  "admin_notes": "..."
}
```

---

## 🎯 Confidence Score Meaning

- **90-100%**: Excellent - Clear evidence, can trust recommendation
- **75-89%**: Good - Good evidence, likely accurate  
- **60-74%**: Moderate - Some uncertainty, should review
- **<60%**: Low - High uncertainty, requires manual inspection

---

## 🔐 Authorization

- **Access**: Claims owner or admin only
- **Requires**: Authenticated user session
- **Returns**: 403 Forbidden if not authorized

---

## ⚠️ Important Reminders

- ✋ This is a **RECOMMENDATION ONLY**, not final approval
- 👨‍💼 All decisions **MUST be reviewed by admin/adjuster**
- 🚫 Cannot override policy exclusions
- 🔍 Fraud detection requires manual investigation
- 🎓 Special cases need human judgment

---

## 🚀 Integration Example

```php
// 1. Inject the service
public function __construct(FirstLevelDecisionService $decisionService) {
    $this->decisionService = $decisionService;
}

// 2. Generate decision after damage analysis
$damageAnalysis = $this->orchestrator->analyzeImage($image, $claim);

$decision = $this->decisionService->generateDecision(
    [
        'severity' => $damageAnalysis->getSeverity(),
        'damage_percentage' => $damageAnalysis->getAffectedAreaPercentage(),
        'estimated_cost' => $damageAnalysis->getEstimatedCost(),
        'detected_features' => $this->extractFeatures($damageAnalysis),
        'confidence_score' => 0.75,
    ],
    $claim->getContrat()->getType(),  // AUTO, HOME, etc.
    []  // optional context
);

// 3. Store and route based on recommendation
$claim->setRecommendation($decision['recommendation']);

if ($decision['recommendation'] === 'APPROVE') {
    // Auto-process low-risk claims
    $this->paymentService->process($claim, $decision['estimated_payout']);
} else {
    // Route to adjuster review
    $this->notificationService->notifyAdjuster($claim, $decision);
}
```

---

## 🔧 Configuration

**Service Registration** (already done in `config/services.yaml`):
```yaml
App\Service\DamageDetection\FirstLevelDecisionService:
    public: true
```

---

## 📞 Support

**For questions about**:
- System logic → See `docs/FIRST_LEVEL_DECISION_SYSTEM.md`
- Integration → See `docs/FIRST_LEVEL_DECISION_INTEGRATION.md`
- Examples → See `src/Examples/FirstLevelDecisionExamples.php`
- Delivery → See `docs/FIRST_LEVEL_DECISION_DELIVERY.md`

---

**Status**: ✅ Ready for production integration

**Created**: April 19, 2026
**Version**: 1.0 Complete
