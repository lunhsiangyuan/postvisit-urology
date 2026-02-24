<?php

namespace Database\Seeders;

use App\Models\Condition;
use App\Models\Consent;
use App\Models\MedicalReference;
use App\Models\Medication;
use App\Models\Observation;
use App\Models\Organization;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Prescription;
use App\Models\Transcript;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitNote;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Urology Demo Seeder — BOO/BPH Scenario
 *
 * Seeds a complete urology post-visit demo for postvisit.yuanuro.com.
 * Patient: 王志明 (Wang Zhi-Ming, 65M) with BPH/BOO
 * Provider: Dr. Lun-Hsiang Yuan, Urology, NTUH Yunlin Branch
 *
 * Demo accounts:
 *   patient@demo.yuanuro.com / password
 *   doctor@demo.yuanuro.com  / password
 */
class UrologyDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->cleanupExistingData();

        // 1. Organization
        $org = Organization::create([
            'name' => 'NTUH Yunlin Urology',
            'type' => 'urology',
            'address' => 'No.579, Sec. 2, Yunlin Rd., Douliu City, Yunlin County 640, Taiwan',
            'phone' => '+886-5-532-3911',
            'email' => 'urology@yunlin.ntuh.gov.tw',
        ]);

        // 2. Practitioner — Dr. Lun-Hsiang Yuan
        $practitioner = Practitioner::create([
            'fhir_practitioner_id' => 'practitioner-'.Str::uuid(),
            'first_name' => 'Lun-Hsiang',
            'last_name' => 'Yuan',
            'email' => 'doctor@demo.yuanuro.com',
            'npi' => '9876543210',
            'license_number' => 'TW-URO-2024-LHY',
            'medical_degree' => 'MD, PhD',
            'primary_specialty' => 'urology',
            'secondary_specialties' => ['urologic_oncology', 'robotic_surgery'],
            'organization_id' => $org->id,
        ]);

        // 3. Patient — 王志明 Wang Zhi-Ming, 65M, BPH/BOO
        $patient = Patient::create([
            'fhir_patient_id' => 'patient-'.Str::uuid(),
            'first_name' => 'Zhi-Ming',
            'last_name' => 'Wang',
            'dob' => '1960-08-22',
            'gender' => 'male',
            'email' => 'patient@demo.yuanuro.com',
            'phone' => '+886-5-555-0165',
            'preferred_language' => 'zh-TW',
            'timezone' => 'Asia/Taipei',
            'mrn' => 'MRN-URO-001',
            'height_cm' => 169.0,
            'weight_kg' => 73.5,
            'blood_type' => 'B+',
            'allergies' => [
                ['name' => 'Penicillin', 'severity' => 'mild', 'reaction' => 'Skin rash'],
            ],
            'emergency_contact_name' => '王美玲 (Wang Mei-Ling)',
            'emergency_contact_phone' => '+886-5-555-0166',
            'emergency_contact_relationship' => 'Spouse',
            'consent_given' => true,
            'consent_date' => now(),
            'data_sharing_consent' => true,
        ]);

        // 4. Users
        $doctorUser = User::create([
            'name' => 'Dr. Lun-Hsiang Yuan',
            'email' => 'doctor@demo.yuanuro.com',
            'password' => 'password',
            'role' => 'doctor',
            'practitioner_id' => $practitioner->id,
            'is_active' => true,
        ]);

        User::create([
            'name' => '王志明 Wang Zhi-Ming',
            'email' => 'patient@demo.yuanuro.com',
            'password' => 'password',
            'role' => 'patient',
            'patient_id' => $patient->id,
            'is_active' => true,
            'demo_scenario_key' => 'boo',
        ]);

        // 5. Visit — BPH/BOO Consultation
        $visitStart = now()->subDay();
        $visit = Visit::create([
            'fhir_encounter_id' => 'encounter-'.Str::uuid(),
            'patient_id' => $patient->id,
            'practitioner_id' => $practitioner->id,
            'organization_id' => $org->id,
            'visit_type' => 'office_visit',
            'class' => 'AMB',
            'visit_status' => 'completed',
            'service_type' => 'urology_consultation',
            'reason_for_visit' => 'Progressive lower urinary tract symptoms — nocturia × 3, weak stream, incomplete emptying',
            'reason_codes' => [
                ['system' => 'ICD-10-CM', 'code' => 'R35.0', 'display' => 'Frequency of micturition'],
                ['system' => 'ICD-10-CM', 'code' => 'R39.12', 'display' => 'Poor urinary stream'],
                ['system' => 'ICD-10-CM', 'code' => 'N40.1', 'display' => 'Benign prostatic hyperplasia with lower urinary tract symptoms'],
            ],
            'summary' => 'Patient presents with 2-year history of progressive LUTS including nocturia × 3, weak intermittent stream, hesitancy, and incomplete emptying. Uroflowmetry: Qmax 8.2 mL/s (low). PVR: 120 mL (elevated). PSA: 2.8 ng/mL. DRE: ~45g prostate, smooth, no nodules. Diagnosis: BPH with BOO. Started on tamsulosin 0.4mg QD.',
            'started_at' => $visitStart,
            'ended_at' => $visitStart->copy()->addMinutes(374),
            'duration_minutes' => 374,
            'provider_notes_followup' => 'Follow up in 6 weeks with repeat uroflowmetry and PVR. PSA recheck in 6 months. If unable to void → ER immediately.',
            'created_by' => $doctorUser->id,
        ]);

        // 6. Observations
        $yesterday = now()->subDay()->toDateString();

        // 6a. Uroflowmetry — Peak Flow Rate (Qmax)
        Observation::create([
            'fhir_observation_id' => 'obs-qmax-'.Str::uuid(),
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'practitioner_id' => $practitioner->id,
            'code_system' => 'LOINC',
            'code' => '56842-0',
            'code_display' => 'Uroflowmetry peak flow rate',
            'category' => 'exam',
            'status' => 'final',
            'value_type' => 'quantity',
            'value_quantity' => 8.2,
            'value_unit' => 'mL/s',
            'reference_range_low' => 15,
            'reference_range_high' => null,
            'reference_range_text' => 'Normal: >15 mL/s; Obstruction suspected: <10 mL/s',
            'interpretation' => 'L',
            'effective_date' => $yesterday,
            'issued_at' => now()->subDay(),
            'specialty_data' => [
                'voided_volume' => 285,
                'voided_unit' => 'mL',
                'voiding_time' => 52,
                'voiding_time_unit' => 'seconds',
                'average_flow' => 5.5,
                'average_flow_unit' => 'mL/s',
                'pattern' => 'Obstructive plateau-shaped curve',
                'interpretation' => 'Severely reduced peak flow rate consistent with bladder outlet obstruction',
            ],
            'created_by' => $doctorUser->id,
        ]);

        // 6b. Post-Void Residual (PVR)
        Observation::create([
            'fhir_observation_id' => 'obs-pvr-'.Str::uuid(),
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'practitioner_id' => $practitioner->id,
            'code_system' => 'LOINC',
            'code' => '59083-7',
            'code_display' => 'Residual urine volume by ultrasound',
            'category' => 'exam',
            'status' => 'final',
            'value_type' => 'quantity',
            'value_quantity' => 120,
            'value_unit' => 'mL',
            'reference_range_low' => null,
            'reference_range_high' => 50,
            'reference_range_text' => 'Normal: <50 mL; Clinically significant: >100 mL',
            'interpretation' => 'H',
            'effective_date' => $yesterday,
            'issued_at' => now()->subDay(),
            'specialty_data' => [
                'measurement_method' => 'Bladder ultrasound (portable)',
                'clinical_significance' => 'Elevated PVR indicates impaired bladder emptying, consistent with BOO',
                'risk' => 'Increased risk of UTI and bladder stone formation if untreated',
            ],
            'created_by' => $doctorUser->id,
        ]);

        // 6c. PSA
        Observation::create([
            'fhir_observation_id' => 'obs-psa-'.Str::uuid(),
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'practitioner_id' => $practitioner->id,
            'code_system' => 'LOINC',
            'code' => '2857-1',
            'code_display' => 'Prostate specific Ag [Mass/volume] in Serum or Plasma',
            'category' => 'laboratory',
            'status' => 'final',
            'value_type' => 'quantity',
            'value_quantity' => 2.8,
            'value_unit' => 'ng/mL',
            'reference_range_low' => null,
            'reference_range_high' => 4.0,
            'reference_range_text' => 'Age 65: <4.0 ng/mL; Monitor closely with family history of PCa',
            'interpretation' => 'N',
            'effective_date' => $yesterday,
            'issued_at' => now()->subDay(),
            'specialty_data' => [
                'psa_density' => 0.062,
                'psa_density_unit' => 'ng/mL/mL',
                'prostate_volume_used' => 45,
                'interpretation' => 'PSA within normal range for age. Monitor every 6 months given family history.',
                'family_history_flag' => true,
                'next_check_months' => 6,
            ],
            'created_by' => $doctorUser->id,
        ]);

        // 6d. Prostate Size (DRE)
        Observation::create([
            'fhir_observation_id' => 'obs-prostate-'.Str::uuid(),
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'practitioner_id' => $practitioner->id,
            'code_system' => 'SNOMED-CT',
            'code' => '276760007',
            'code_display' => 'Prostate size (observable entity)',
            'category' => 'exam',
            'status' => 'final',
            'value_type' => 'quantity',
            'value_quantity' => 45,
            'value_unit' => 'g',
            'reference_range_low' => null,
            'reference_range_high' => 30,
            'reference_range_text' => 'Normal: 20-30g; Enlarged: >30g',
            'interpretation' => 'H',
            'effective_date' => $yesterday,
            'issued_at' => now()->subDay(),
            'specialty_data' => [
                'examination_method' => 'Digital Rectal Examination (DRE)',
                'texture' => 'Smooth, rubbery',
                'nodules' => false,
                'tenderness' => false,
                'median_sulcus' => 'Obliterated',
                'impression' => 'Benign prostatic enlargement. No suspicious nodules detected.',
            ],
            'created_by' => $doctorUser->id,
        ]);

        // 6e. IPSS Score
        Observation::create([
            'fhir_observation_id' => 'obs-ipss-'.Str::uuid(),
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'practitioner_id' => $practitioner->id,
            'code_system' => 'LOINC',
            'code' => '80976-4',
            'code_display' => 'International Prostate Symptom Score',
            'category' => 'survey',
            'status' => 'final',
            'value_type' => 'quantity',
            'value_quantity' => 19,
            'value_unit' => 'score',
            'reference_range_text' => 'Mild: 0-7; Moderate: 8-19; Severe: 20-35',
            'interpretation' => 'H',
            'effective_date' => $yesterday,
            'issued_at' => now()->subDay(),
            'specialty_data' => [
                'severity_category' => 'Moderate-to-severe',
                'quality_of_life' => 4,
                'qol_scale' => '0-6 (6 = worst)',
            ],
            'created_by' => $doctorUser->id,
        ]);

        // 6f. Blood Pressure
        Observation::create([
            'fhir_observation_id' => 'obs-bp-'.Str::uuid(),
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'practitioner_id' => $practitioner->id,
            'code_system' => 'LOINC',
            'code' => '85354-9',
            'code_display' => 'Blood pressure panel',
            'category' => 'vital-signs',
            'status' => 'final',
            'value_type' => 'string',
            'value_string' => '138/86 mmHg',
            'interpretation' => 'H',
            'effective_date' => $yesterday,
            'issued_at' => now()->subDay(),
            'specialty_data' => [
                'systolic' => ['value' => 138, 'unit' => 'mmHg', 'code' => '8480-6'],
                'diastolic' => ['value' => 86, 'unit' => 'mmHg', 'code' => '8462-4'],
            ],
            'created_by' => $doctorUser->id,
        ]);

        // 6g. Creatinine
        Observation::create([
            'fhir_observation_id' => 'obs-creat-'.Str::uuid(),
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'practitioner_id' => $practitioner->id,
            'code_system' => 'LOINC',
            'code' => '2160-0',
            'code_display' => 'Creatinine [Mass/volume] in Serum or Plasma',
            'category' => 'laboratory',
            'status' => 'final',
            'value_type' => 'quantity',
            'value_quantity' => 0.9,
            'value_unit' => 'mg/dL',
            'reference_range_low' => 0.7,
            'reference_range_high' => 1.3,
            'reference_range_text' => '0.7-1.3 mg/dL',
            'interpretation' => 'N',
            'effective_date' => $yesterday,
            'issued_at' => now()->subDay(),
            'specialty_data' => [
                'egfr' => 78,
                'egfr_unit' => 'mL/min/1.73m2',
                'egfr_stage' => 'G2 (mildly decreased)',
            ],
            'created_by' => $doctorUser->id,
        ]);

        // 6h. Urinalysis
        Observation::create([
            'fhir_observation_id' => 'obs-ua-'.Str::uuid(),
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'practitioner_id' => $practitioner->id,
            'code_system' => 'LOINC',
            'code' => '24357-6',
            'code_display' => 'Urinalysis macro (dipstick) panel',
            'category' => 'laboratory',
            'status' => 'final',
            'value_type' => 'string',
            'value_string' => 'No significant abnormalities. No hematuria, no pyuria.',
            'interpretation' => 'N',
            'effective_date' => $yesterday,
            'issued_at' => now()->subDay(),
            'specialty_data' => [
                'blood' => 'Negative',
                'nitrites' => 'Negative',
                'leukocyte_esterase' => 'Trace',
                'protein' => 'Trace',
                'glucose' => 'Negative',
                'ph' => 6.0,
                'appearance' => 'Clear, pale yellow',
                'microscopy' => '0-2 WBC/hpf, 0-1 RBC/hpf — within normal limits',
                'interpretation' => 'No evidence of urinary tract infection or hematuria.',
            ],
            'created_by' => $doctorUser->id,
        ]);

        // 7. Conditions
        Condition::create([
            'fhir_condition_id' => 'condition-'.Str::uuid(),
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'code_system' => 'ICD-10-CM',
            'code' => 'N40.1',
            'code_display' => 'Benign prostatic hyperplasia with lower urinary tract symptoms',
            'category' => 'encounter-diagnosis',
            'clinical_status' => 'active',
            'verification_status' => 'confirmed',
            'onset_date' => now()->subYears(2)->toDateString(),
            'clinical_notes' => 'BPH diagnosed clinically by DRE and supported by uroflowmetry, PVR, and IPSS score.',
            'created_by' => $doctorUser->id,
        ]);

        Condition::create([
            'fhir_condition_id' => 'condition-'.Str::uuid(),
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'code_system' => 'ICD-10-CM',
            'code' => 'N13.9',
            'code_display' => 'Obstructive and reflux uropathy, unspecified',
            'category' => 'encounter-diagnosis',
            'clinical_status' => 'active',
            'verification_status' => 'confirmed',
            'onset_date' => now()->subYears(2)->toDateString(),
            'clinical_notes' => 'BOO evidenced by Qmax 8.2 mL/s and elevated PVR 120mL.',
            'created_by' => $doctorUser->id,
        ]);

        Condition::create([
            'fhir_condition_id' => 'condition-'.Str::uuid(),
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'code_system' => 'ICD-10-CM',
            'code' => 'I10',
            'code_display' => 'Essential (primary) hypertension',
            'category' => 'problem-list-item',
            'clinical_status' => 'active',
            'verification_status' => 'confirmed',
            'onset_date' => now()->subYears(8)->toDateString(),
            'clinical_notes' => 'Controlled on amlodipine 5mg QD.',
            'created_by' => $doctorUser->id,
        ]);

        // 8. Medication + Prescription — Tamsulosin (Harnalidge)
        $medication = Medication::firstOrCreate(
            ['rxnorm_code' => '77492'],
            [
                'generic_name' => 'Tamsulosin Hydrochloride',
                'display_name' => 'Tamsulosin (Harnalidge)',
                'brand_names' => ['Harnalidge', 'Flomax'],
                'form' => 'Modified-release capsule',
                'strength_value' => 0.4,
                'strength_unit' => 'mg',
                'source' => 'rxnorm',
                'is_active' => true,
            ]
        );

        Prescription::create([
            'fhir_medication_request_id' => 'med-req-'.Str::uuid(),
            'patient_id' => $patient->id,
            'practitioner_id' => $practitioner->id,
            'visit_id' => $visit->id,
            'medication_id' => $medication->id,
            'status' => 'active',
            'intent' => 'order',
            'dose_quantity' => 0.4,
            'dose_unit' => 'mg',
            'route' => 'oral',
            'frequency' => 'once daily',
            'frequency_text' => 'Once daily at bedtime',
            'indication' => 'BPH with bladder outlet obstruction (BOO)',
            'special_instructions' => 'Take 1 capsule at bedtime. Rise slowly from sitting/lying positions (orthostatic hypotension risk). Report inability to urinate or severe dizziness immediately.',
            'substitution_allowed' => true,
            'number_of_refills' => 3,
            'start_date' => now()->subDay()->toDateString(),
            'created_by' => $doctorUser->id,
        ]);

        // 9. Visit Note (SOAP)
        VisitNote::create([
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'author_practitioner_id' => $practitioner->id,
            'composition_type' => 'clinic_note',
            'status' => 'final',
            'chief_complaint' => 'Progressive lower urinary tract symptoms (LUTS) for 2 years: nocturia × 3 per night, weak intermittent urinary stream, hesitancy, post-void dribbling, and sensation of incomplete bladder emptying.',
            'history_of_present_illness' => '65-year-old male with 2-year history of progressive LUTS. Primary symptoms include nocturia × 3, weak and intermittent urinary stream, hesitancy (prolonged delay before voiding begins), post-void dribbling, and persistent sensation of incomplete emptying. Reports one episode of severely prolonged voiding (10 minutes to initiate) last month. No acute urinary retention. No hematuria. No dysuria. Occasional urgency without urge incontinence. Uses antihistamines for seasonal allergies. Background hypertension managed with amlodipine 5mg QD. Family history significant for prostate cancer in father (successfully treated, age 88). No prior urologic procedures or hospitalizations.',
            'review_of_systems' => "POSITIVE: Nocturia, weak stream, hesitancy, intermittent stream, post-void dribbling, urgency, incomplete emptying sensation.\nNEGATIVE: Hematuria, dysuria, flank pain, fever/chills, weight loss, urinary incontinence.",
            'physical_exam' => "General: Well-appearing 65-year-old male in no acute distress. Alert and oriented ×3.\nVitals: BP 138/86 mmHg, HR 72 bpm, SpO2 97%, Wt 73.5 kg, Ht 169 cm, BMI 25.7.\nAbdomen: Soft, non-tender, no suprapubic distension or tenderness.\nDigital Rectal Examination (DRE): Prostate enlarged, estimated ~45g. Smooth, firm, symmetric texture. No discrete nodules. Median sulcus obliterated. Non-tender.",
            'assessment' => "1. Benign Prostatic Hyperplasia (BPH) with Lower Urinary Tract Symptoms — ICD-10: N40.1\n   Evidence: IPSS 19 (moderate-severe), DRE ~45g prostate, PSA 2.8 ng/mL (normal range).\n\n2. Bladder Outlet Obstruction (BOO) — ICD-10: N13.9\n   Evidence: Uroflowmetry Qmax 8.2 mL/s (severely reduced; normal >15 mL/s), PVR 120 mL (elevated).\n\n3. Hypertension — ICD-10: I10 (controlled on amlodipine 5mg QD)\n\n4. Family history of prostate cancer (father) — warrants PSA surveillance.",
            'plan' => "1. MEDICATION: Initiate tamsulosin (Harnalidge) 0.4mg once daily at BEDTIME.\n   Alpha-1 adrenergic blocker → relaxes prostate/bladder neck smooth muscle → improved flow.\n\n2. MEDICATION COUNSELING:\n   - Take at bedtime to minimize orthostatic hypotension risk\n   - DISCONTINUE antihistamines and decongestants (worsen BOO)\n   - Possible side effect: retrograde ejaculation (harmless)\n\n3. BEHAVIORAL MODIFICATIONS:\n   - Restrict fluids after 7 PM (reduce nocturia)\n   - Practice double voiding\n   - Limit caffeine and evening alcohol\n\n4. FOLLOW-UP: Repeat uroflowmetry + PVR in 6 weeks.\n\n5. PSA SURVEILLANCE: Recheck in 6 months (family history of PCa).\n\n6. EMERGENCY: Acute urinary retention → ER IMMEDIATELY.\n\n7. SURGICAL OPTIONS discussed if medical therapy fails:\n   - TURP (transurethral resection of prostate) — gold standard\n   - HoLEP (holmium laser enucleation) — available at this institution",
            'follow_up' => 'Return in 6 weeks for repeat uroflowmetry and post-void residual measurement. PSA recheck in 6 months. ER if acute urinary retention occurs.',
            'medical_terms' => [
                'LUTS' => 'Lower Urinary Tract Symptoms — a group of urinary problems including difficulty urinating, weak stream, and needing to go frequently.',
                'BPH' => 'Benign Prostatic Hyperplasia — non-cancerous enlargement of the prostate gland very common in older men.',
                'BOO' => 'Bladder Outlet Obstruction — a blockage at the base of the bladder preventing urine from flowing freely.',
                'Qmax' => 'Peak Flow Rate — the fastest speed of your urine stream; your 8.2 mL/s is below the normal minimum of 15 mL/s.',
                'PVR' => 'Post-Void Residual — urine remaining in the bladder after voiding; your 120 mL is above the normal limit of 50 mL.',
                'PSA' => 'Prostate-Specific Antigen — a blood marker for prostate health; your 2.8 ng/mL is within normal range.',
                'DRE' => 'Digital Rectal Examination — a physical exam where the doctor feels the prostate through the rectum.',
                'IPSS' => 'International Prostate Symptom Score — questionnaire measuring urinary symptoms; your score of 19 indicates moderate-to-severe symptoms.',
                'Tamsulosin' => 'An alpha-blocker medication (Harnalidge) that relaxes prostate and bladder neck muscles to improve urine flow.',
                'Alpha-blocker' => 'A medication class that relaxes smooth muscle in the prostate and bladder outlet, improving urine flow in BPH.',
                'TURP' => 'Transurethral Resection of the Prostate — surgery removing obstructing prostate tissue without external incisions.',
                'HoLEP' => 'Holmium Laser Enucleation of the Prostate — advanced laser surgery with minimal bleeding risk.',
                'Nocturia' => 'Waking at night to urinate; your 3 episodes per night significantly disrupts sleep.',
                'Hesitancy' => 'Difficulty starting to urinate, requiring waiting or straining before urine flows.',
                'Acute urinary retention' => 'A medical emergency — complete inability to urinate requiring immediate catheterization.',
            ],
            'is_signed' => true,
            'signed_at' => $visitStart->copy()->addMinutes(374),
        ]);

        // 10. Transcript
        $transcriptPath = base_path('demo/transcript-urology.txt');
        $rawTranscript = file_exists($transcriptPath)
            ? file_get_contents($transcriptPath)
            : 'Demo transcript not found. Please ensure demo/transcript-urology.txt exists.';

        Transcript::create([
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'source_type' => 'manual_upload',
            'stt_provider' => 'whisper',
            'raw_transcript' => $rawTranscript,
            'processing_status' => 'completed',
            'audio_duration_seconds' => 374,
            'patient_consent_given' => true,
            'consent_timestamp' => $visitStart,
        ]);

        // 11. Medical References
        MedicalReference::create([
            'title' => 'EAU Guidelines on Management of Non-Neurogenic Male LUTS including BPH 2024',
            'source_organization' => 'European Association of Urology',
            'category' => 'clinical_guideline',
            'specialty' => 'urology',
            'url' => 'https://uroweb.org/guidelines/management-of-non-neurogenic-male-luts',
            'summary' => 'Alpha-1 blockers (tamsulosin, silodosin, alfuzosin) are first-line medical treatment for LUTS/BPH with moderate-to-severe symptoms (IPSS ≥8). Improve Qmax and IPSS within 2-4 weeks. Surgery (TURP, HoLEP) indicated when medical therapy fails or complications arise.',
            'year' => 2024,
            'verified' => true,
        ]);

        MedicalReference::create([
            'title' => 'EAU Guidelines on Prostate Cancer 2024',
            'source_organization' => 'European Association of Urology',
            'category' => 'clinical_guideline',
            'specialty' => 'urology',
            'url' => 'https://uroweb.org/guidelines/prostate-cancer',
            'summary' => 'PSA screening recommended for well-informed men aged 50-70 at average risk, or 40-45 for high-risk groups (family history, African descent). PSA density >0.15 and PSA velocity >0.75 ng/mL/year warrant further evaluation.',
            'year' => 2024,
            'verified' => true,
        ]);

        // 12. Consent
        Consent::create([
            'patient_id' => $patient->id,
            'consent_type' => 'recording',
            'status' => 'active',
            'consented_at' => $visitStart,
            'version' => '1.0',
        ]);

        Log::info('UrologyDemoSeeder: Seeded successfully.', [
            'patient' => 'Wang Zhi-Ming (patient@demo.yuanuro.com)',
            'doctor' => 'Dr. Lun-Hsiang Yuan (doctor@demo.yuanuro.com)',
            'scenario' => 'BOO/BPH — tamsulosin initiated',
        ]);

        $this->command->info('');
        $this->command->info('✅ Urology Demo seeded successfully!');
        $this->command->info('   Patient: patient@demo.yuanuro.com / password');
        $this->command->info('   Doctor:  doctor@demo.yuanuro.com  / password');
        $this->command->info('   Scenario: BOO/BPH — 王志明, 65M');
    }

    private function cleanupExistingData(): void
    {
        // Collect IDs from users (may be partially created from a prior failed run)
        $users = User::where('email', 'LIKE', '%@demo.yuanuro.com')->get();
        $patientIds = $users->pluck('patient_id')->filter()->values();
        $practitionerIds = $users->pluck('practitioner_id')->filter()->values();
        $userIds = $users->pluck('id');

        // Also find orphaned patients/practitioners not linked to any user (including soft-deleted)
        $orphanPatients = Patient::withTrashed()->whereIn('email', ['patient@demo.yuanuro.com'])->pluck('id');
        $patientIds = $patientIds->merge($orphanPatients)->unique()->values();

        $orphanPractitioners = Practitioner::whereIn('email', ['doctor@demo.yuanuro.com'])->pluck('id');
        $practitionerIds = $practitionerIds->merge($orphanPractitioners)->unique()->values();

        // Step 1: delete visit children (no FK back to users/patients)
        if ($patientIds->isNotEmpty()) {
            $visitIds = Visit::whereIn('patient_id', $patientIds)->pluck('id');
            if ($visitIds->isNotEmpty()) {
                VisitNote::whereIn('visit_id', $visitIds)->delete();
                Transcript::whereIn('visit_id', $visitIds)->delete();
            }
            Observation::whereIn('patient_id', $patientIds)->delete();
            Condition::whereIn('patient_id', $patientIds)->delete();
            Prescription::whereIn('patient_id', $patientIds)->delete();
            Consent::whereIn('patient_id', $patientIds)->delete();
        }

        MedicalReference::where('specialty', 'urology')->delete();

        // Step 2: delete visits (visits.created_by → users; must go before users)
        if ($patientIds->isNotEmpty()) {
            Visit::whereIn('patient_id', $patientIds)->delete();
        }

        // Step 3: delete users (users.patient_id → patients; users.practitioner_id → practitioners)
        // Must delete users BEFORE patients AND practitioners
        if ($userIds->isNotEmpty()) {
            User::whereIn('id', $userIds)->delete();
        }

        // Step 4: forceDelete patients — Patient uses SoftDeletes; regular delete() leaves
        // email in DB with unique constraint active; forceDelete() truly removes the row
        if ($patientIds->isNotEmpty()) {
            Patient::withTrashed()->whereIn('id', $patientIds)->forceDelete();
        }

        // Step 5: delete practitioners (now safe — users gone)
        if ($practitionerIds->isNotEmpty()) {
            Practitioner::whereIn('id', $practitionerIds)->delete();
        }

        // Step 6: delete organization
        Organization::where('type', 'urology')->delete();
    }
}
