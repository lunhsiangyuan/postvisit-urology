<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\Medication;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Prescription;
use App\Models\Visit;
use App\Services\Guidelines\GuidelinesRepository;
use App\Services\Guidelines\PmcClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GuidelinesRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private Visit $visit;

    private Patient $patient;

    private Practitioner $practitioner;

    private GuidelinesRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::factory()->create();
        $this->practitioner = Practitioner::factory()->create(['organization_id' => $org->id]);
        $this->patient = Patient::factory()->create();

        $this->visit = Visit::factory()->create([
            'patient_id' => $this->patient->id,
            'practitioner_id' => $this->practitioner->id,
            'organization_id' => $org->id,
        ]);

        $pmcClient = $this->app->make(PmcClient::class);
        $this->repository = new GuidelinesRepository($pmcClient);
    }

    public function test_returns_empty_for_visit_without_conditions_or_medications(): void
    {
        $result = $this->repository->getRelevantGuidelines($this->visit);

        $this->assertEquals('', $result);
    }

    public function test_loads_pvc_guidelines_for_pvc_condition(): void
    {
        Condition::factory()->create([
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'code' => 'I49.3',
            'code_display' => 'Premature ventricular contractions',
        ]);

        $result = $this->repository->getRelevantGuidelines($this->visit->fresh());

        $this->assertStringContainsString('CLINICAL GUIDELINES CONTEXT', $result);
        $this->assertStringContainsString('Premature Ventricular Contractions', $result);
        $this->assertStringContainsString('WikiDoc', $result);
    }

    public function test_loads_heart_failure_guidelines_for_hf_condition(): void
    {
        Condition::factory()->create([
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'code' => 'I50.22',
            'code_display' => 'Chronic systolic heart failure',
        ]);

        $result = $this->repository->getRelevantGuidelines($this->visit->fresh());

        $this->assertStringContainsString('CLINICAL GUIDELINES CONTEXT', $result);
        $this->assertStringContainsString('Heart Failure', $result);
    }

    public function test_loads_hypertension_guidelines(): void
    {
        Condition::factory()->create([
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'code' => 'I10',
            'code_display' => 'Essential hypertension',
        ]);

        $result = $this->repository->getRelevantGuidelines($this->visit->fresh());

        $this->assertStringContainsString('Hypertension', $result);
    }

    public function test_loads_drug_label_for_prescribed_medication(): void
    {
        $medication = Medication::factory()->create([
            'generic_name' => 'Propranolol Hydrochloride',
        ]);

        Prescription::factory()->create([
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'practitioner_id' => $this->practitioner->id,
            'medication_id' => $medication->id,
            'status' => 'active',
        ]);

        $result = $this->repository->getRelevantGuidelines($this->visit->fresh());

        $this->assertStringContainsString('Propranolol', $result);
        $this->assertStringContainsString('DailyMed', $result);
        $this->assertStringContainsString('Beta', $result);
    }

    public function test_includes_esc_copyright_notice(): void
    {
        Condition::factory()->create([
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'code' => 'I49.3',
            'code_display' => 'PVCs',
        ]);

        $result = $this->repository->getRelevantGuidelines($this->visit->fresh());

        $this->assertStringContainsString('ESC guidelines are unavailable', $result);
        $this->assertStringContainsString('EU Directive 2019/790', $result);
    }

    public function test_deduplicates_articles_for_same_drug_class(): void
    {
        $carvedilol = Medication::factory()->create([
            'generic_name' => 'Carvedilol',
        ]);

        $propranolol = Medication::factory()->create([
            'generic_name' => 'Propranolol Hydrochloride',
        ]);

        Prescription::factory()->create([
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'practitioner_id' => $this->practitioner->id,
            'medication_id' => $carvedilol->id,
            'status' => 'active',
        ]);

        Prescription::factory()->create([
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'practitioner_id' => $this->practitioner->id,
            'medication_id' => $propranolol->id,
            'status' => 'active',
        ]);

        $result = $this->repository->getRelevantGuidelines($this->visit->fresh());

        // Beta-blocker article should appear only once despite two beta-blockers
        $count = substr_count($result, '# Beta-Blockers');
        $this->assertEquals(1, $count, 'Beta-blocker article should not be duplicated');
    }

    public function test_prefix_matching_for_hf_subcodes(): void
    {
        Condition::factory()->create([
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'code' => 'I50.23',
            'code_display' => 'Acute on chronic systolic heart failure',
        ]);

        $result = $this->repository->getRelevantGuidelines($this->visit->fresh());

        $this->assertStringContainsString('Heart Failure', $result);
    }

    public function test_loads_pmc_articles_for_pvc_condition(): void
    {
        Http::fake([
            '*/BioC_json/PMC7880852/unicode' => Http::response([
                'documents' => [
                    [
                        'passages' => [
                            ['text' => 'PVC management consensus statement: Premature ventricular complexes are common.'],
                            ['text' => 'Treatment with beta-blockers is recommended for symptomatic PVCs.'],
                        ],
                    ],
                ],
            ]),
        ]);

        Condition::factory()->create([
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'code' => 'I49.3',
            'code_display' => 'Premature ventricular contractions',
        ]);

        $result = $this->repository->getRelevantGuidelines($this->visit->fresh());

        $this->assertStringContainsString('PMC Open Access', $result);
        $this->assertStringContainsString('PMC7880852', $result);
        $this->assertStringContainsString('PVC management consensus', $result);
    }

    public function test_continues_without_pmc_on_fetch_failure(): void
    {
        Http::fake([
            '*/BioC_json/PMC7880852/unicode' => Http::response([], 500),
        ]);

        Condition::factory()->create([
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'code' => 'I49.3',
            'code_display' => 'Premature ventricular contractions',
        ]);

        $result = $this->repository->getRelevantGuidelines($this->visit->fresh());

        // Should still have WikiDoc content even though PMC failed
        $this->assertStringContainsString('CLINICAL GUIDELINES CONTEXT', $result);
        $this->assertStringContainsString('Premature Ventricular Contractions', $result);
        // Should NOT contain PMC article content (only attribution line mentions PMC)
        $this->assertStringNotContainsString('PMC Open Access Article', $result);
        $this->assertStringContainsString('0 from PMC Open Access', $result);
    }

    public function test_loads_prostate_cancer_guidelines_for_c61_condition(): void
    {
        Http::fake([
            '*/BioC_json/*' => Http::response([], 500),
        ]);

        Condition::factory()->create([
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'code' => 'C61',
            'code_display' => 'Malignant neoplasm of prostate',
        ]);

        $result = $this->repository->getRelevantGuidelines($this->visit->fresh());

        $this->assertStringContainsString('CLINICAL GUIDELINES CONTEXT', $result);
        $this->assertStringContainsString('Prostate Cancer', $result);
    }

    public function test_loads_bph_guidelines_for_n40_condition(): void
    {
        Http::fake([
            '*/BioC_json/*' => Http::response([], 500),
        ]);

        Condition::factory()->create([
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'code' => 'N40.1',
            'code_display' => 'BPH with LUTS',
        ]);

        $result = $this->repository->getRelevantGuidelines($this->visit->fresh());

        $this->assertStringContainsString('CLINICAL GUIDELINES CONTEXT', $result);
        $this->assertStringContainsString('Benign Prostatic Hyperplasia', $result);
    }

    public function test_loads_tamsulosin_drug_label_and_alpha_blocker_class(): void
    {
        $medication = Medication::factory()->create([
            'generic_name' => 'Tamsulosin',
        ]);

        Prescription::factory()->create([
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'practitioner_id' => $this->practitioner->id,
            'medication_id' => $medication->id,
            'status' => 'active',
        ]);

        $result = $this->repository->getRelevantGuidelines($this->visit->fresh());

        $this->assertStringContainsString('Tamsulosin', $result);
        $this->assertStringContainsString('Alpha', $result);
    }

    public function test_loads_enzalutamide_drug_label_and_antiandrogen_class(): void
    {
        $medication = Medication::factory()->create([
            'generic_name' => 'Enzalutamide',
        ]);

        Prescription::factory()->create([
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'practitioner_id' => $this->practitioner->id,
            'medication_id' => $medication->id,
            'status' => 'active',
        ]);

        $result = $this->repository->getRelevantGuidelines($this->visit->fresh());

        $this->assertStringContainsString('Enzalutamide', $result);
        $this->assertStringContainsString('Antiandrogen', $result);
    }

    public function test_loads_leuprolide_drug_label_and_adt_class(): void
    {
        $medication = Medication::factory()->create([
            'generic_name' => 'Leuprolide Acetate',
        ]);

        Prescription::factory()->create([
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'practitioner_id' => $this->practitioner->id,
            'medication_id' => $medication->id,
            'status' => 'active',
        ]);

        $result = $this->repository->getRelevantGuidelines($this->visit->fresh());

        $this->assertStringContainsString('Leuprolide', $result);
        $this->assertStringContainsString('Androgen Deprivation', $result);
    }

    public function test_bone_metastasis_maps_to_prostate_cancer_guidelines(): void
    {
        Http::fake([
            '*/BioC_json/*' => Http::response([], 500),
        ]);

        Condition::factory()->create([
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'code' => 'C79.51',
            'code_display' => 'Secondary malignant neoplasm of bone',
        ]);

        $result = $this->repository->getRelevantGuidelines($this->visit->fresh());

        $this->assertStringContainsString('Prostate Cancer', $result);
    }

    public function test_pca_metastatic_visit_loads_comprehensive_guidelines(): void
    {
        Http::fake([
            '*/BioC_json/*' => Http::response([], 500),
        ]);

        Condition::factory()->create([
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'code' => 'C61',
            'code_display' => 'Malignant neoplasm of prostate',
        ]);

        $enzalutamide = Medication::factory()->create(['generic_name' => 'Enzalutamide']);
        $leuprolide = Medication::factory()->create(['generic_name' => 'Leuprolide Acetate']);
        $denosumab = Medication::factory()->create(['generic_name' => 'Denosumab']);

        foreach ([$enzalutamide, $leuprolide, $denosumab] as $med) {
            Prescription::factory()->create([
                'visit_id' => $this->visit->id,
                'patient_id' => $this->patient->id,
                'practitioner_id' => $this->practitioner->id,
                'medication_id' => $med->id,
                'status' => 'active',
            ]);
        }

        $result = $this->repository->getRelevantGuidelines($this->visit->fresh());

        $this->assertStringContainsString('Prostate Cancer', $result);
        $this->assertStringContainsString('Enzalutamide', $result);
        $this->assertStringContainsString('Leuprolide', $result);
        $this->assertStringContainsString('Denosumab', $result);
        $this->assertStringContainsString('Androgen Deprivation', $result);
        $this->assertStringContainsString('Antiandrogen', $result);
    }

    public function test_source_attribution_includes_pmc_count(): void
    {
        Http::fake([
            '*/BioC_json/PMC7880852/unicode' => Http::response([
                'documents' => [
                    [
                        'passages' => [
                            ['text' => 'PVC guideline content from PMC.'],
                        ],
                    ],
                ],
            ]),
        ]);

        Condition::factory()->create([
            'visit_id' => $this->visit->id,
            'patient_id' => $this->patient->id,
            'code' => 'I49.3',
            'code_display' => 'Premature ventricular contractions',
        ]);

        $result = $this->repository->getRelevantGuidelines($this->visit->fresh());

        $this->assertStringContainsString('1 from PMC Open Access', $result);
    }
}
