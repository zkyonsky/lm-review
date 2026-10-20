<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialAiAnalysis;
use App\Models\MaterialVersion;
use App\Models\Review;
use App\Models\Subject;
use App\Models\Training;
use App\Models\User;
use App\Services\GeminiAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $reviewer;
    protected User $developer;
    protected User $otherUser;
    protected Training $training;
    protected Subject $subject;
    protected Material $material;
    protected MaterialVersion $version;
    protected Review $review;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'pengembang']);
        Role::create(['name' => 'reviewer']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->reviewer = User::factory()->create(['name' => 'Reviewer 1']);
        $this->reviewer->assignRole('reviewer');

        $this->developer = User::factory()->create(['name' => 'Pengembang 1']);
        $this->developer->assignRole('pengembang');

        $this->otherUser = User::factory()->create(['name' => 'Orang Lain']);
        $this->otherUser->assignRole('reviewer');

        $period = \App\Models\ReviewPeriod::create([
            'name' => 'Periode Aktif 2026',
            'year' => 2026,
            'status' => \App\Enums\ReviewPeriodStatus::Active,
            'created_by' => $this->admin->id,
        ]);

        $this->training = Training::create([
            'title' => 'Pelatihan Pengadaan Barang dan Jasa Pemerintah',
            'created_by' => $this->admin->id,
        ]);
        $this->training->periods()->attach($period->id);

        $this->subject = Subject::create([
            'training_id' => $this->training->id,
            'title' => 'Tata Cara Pemilihan Penyedia PBJ',
            'sort_order' => 1,
        ]);

        // Assign developer & reviewer to subject
        $this->subject->assignedUsers()->attach($this->developer->id, [
            'role' => 'pengembang',
            'assigned_by' => $this->admin->id,
        ]);
        $this->subject->assignedUsers()->attach($this->reviewer->id, [
            'role' => 'reviewer',
            'assigned_by' => $this->admin->id,
        ]);

        $this->material = Material::create([
            'subject_id' => $this->subject->id,
            'type' => 'pdf',
            'created_by' => $this->developer->id,
        ]);

        $this->version = $this->material->versions()->create([
            'version_number' => 1,
            'created_by' => $this->developer->id,
        ]);
        $this->material->update(['current_version_id' => $this->version->id]);

        $assignment = \App\Models\ReviewAssignment::create([
            'material_id' => $this->material->id,
            'reviewer_id' => $this->reviewer->id,
            'assigned_by' => $this->admin->id,
            'status' => 'pending',
        ]);

        $this->review = Review::create([
            'review_assignment_id' => $assignment->id,
            'reviewer_id' => $this->reviewer->id,
            'material_version_id' => $this->version->id,
            'status' => 'draft',
        ]);
    }

    public function test_reviewer_can_trigger_ai_analysis(): void
    {
        $response = $this->actingAs($this->reviewer)->post(route('reviews.ai-analyze', $this->review), [
            'custom_context' => 'Fokus pada batasan nilai tender cepat.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('material_ai_analyses', [
            'material_version_id' => $this->version->id,
            'user_id' => $this->reviewer->id,
            'model' => 'gemini-3.8-flash',
            'status' => 'completed',
        ]);

        $analysis = MaterialAiAnalysis::where('material_version_id', $this->version->id)->first();
        $this->assertNotNull($analysis->summary);
        $this->assertNotEmpty($analysis->identified_regulations);
        $this->assertNotNull($analysis->suggested_conclusion);
    }

    public function test_developer_can_trigger_ai_analysis(): void
    {
        $response = $this->actingAs($this->developer)->post(route('reviews.ai-analyze', $this->review));

        $response->assertRedirect();

        $this->assertDatabaseHas('material_ai_analyses', [
            'material_version_id' => $this->version->id,
            'user_id' => $this->developer->id,
            'model' => 'gemini-3.8-flash',
        ]);
    }

    public function test_unassigned_user_cannot_trigger_ai_analysis(): void
    {
        $response = $this->actingAs($this->otherUser)->post(route('reviews.ai-analyze', $this->review));

        $response->assertForbidden();
    }

    public function test_gemini_service_calls_google_api_when_key_is_configured(): void
    {
        Config::set('services.gemini.api_key', 'dummy-test-key-123');
        Config::set('services.gemini.model', 'gemini-3.8-flash');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'summary' => 'Ringkasan materi pengadaan barang jasa dari Gemini Live.',
                                        'identified_regulations' => [
                                            [
                                                'title' => 'Perpres No. 16 Tahun 2018',
                                                'status' => 'amended',
                                                'status_label' => 'Telah Diubah',
                                                'latest_regulation' => 'Perpres No. 12 Tahun 2021',
                                                'notes' => 'Telah diubah oleh Perpres 12/2021.'
                                            ]
                                        ],
                                        'recommendations' => [
                                            'Update pasal nilai tender UMKM'
                                        ],
                                        'suggested_conclusion' => 'Materi masih relevan, tetapi perlu perbaikan minor sesuai komentar',
                                        'conclusion_rationale' => 'Perlu penyesuaian regulasi baru.'
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $service = app(GeminiAnalysisService::class);
        $analysis = $service->analyzeMaterialVersion($this->version, $this->reviewer);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'models/gemini-3.8-flash:generateContent')
                && str_contains($request->url(), 'key=dummy-test-key-123');
        });

        $this->assertEquals('Ringkasan materi pengadaan barang jasa dari Gemini Live.', $analysis->summary);
        $this->assertEquals('Materi masih relevan, tetapi perlu perbaikan minor sesuai komentar', $analysis->suggested_conclusion);
        $this->assertEquals('gemini-3.8-flash', $analysis->model);
        $this->assertCount(1, $analysis->identified_regulations);
    }

    public function test_reviewer_workspace_loads_latest_ai_analysis(): void
    {
        // Create an existing analysis
        MaterialAiAnalysis::create([
            'material_version_id' => $this->version->id,
            'user_id' => $this->reviewer->id,
            'model' => 'gemini-3.8-flash',
            'status' => 'completed',
            'summary' => 'Telaah materi yang sudah pernah dibuat.',
            'suggested_conclusion' => 'Materi masih relevan',
            'identified_regulations' => [],
            'recommendations' => [],
        ]);

        $response = $this->actingAs($this->reviewer)->get(route('reviewer.workspace.show', $this->review));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('reviewer/workspace/index')
            ->has('review.material_version.latest_ai_analysis')
            ->where('review.material_version.latest_ai_analysis.suggested_conclusion', 'Materi masih relevan')
        );
    }

    public function test_developer_workspace_loads_latest_ai_analysis(): void
    {
        MaterialAiAnalysis::create([
            'material_version_id' => $this->version->id,
            'user_id' => $this->developer->id,
            'model' => 'gemini-3.8-flash',
            'status' => 'completed',
            'summary' => 'Telaah materi untuk pengembang.',
            'suggested_conclusion' => 'Materi sudah tidak relevan, perlu perubahan major',
            'identified_regulations' => [],
            'recommendations' => [],
        ]);

        $response = $this->actingAs($this->developer)->get(route('developer.workspace.show', $this->review));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('developer/workspace/index')
            ->has('review.material_version.latest_ai_analysis')
            ->where('review.material_version.latest_ai_analysis.suggested_conclusion', 'Materi sudah tidak relevan, perlu perubahan major')
        );
    }
}
