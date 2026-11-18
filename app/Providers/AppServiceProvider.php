<?php

namespace App\Providers;

use App\Models\AcademicYear;
use App\Models\AssessmentComponent;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\School;
use App\Models\ScheduleEntry;
use App\Models\Semester;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\TimeSlot;
use App\Models\User;
use App\Policies\DataMasterPolicy;
use App\Policies\DataPribadiPolicy;
use App\Policies\JadwalPolicy;
use App\Policies\PenilaianPolicy;
use App\Policies\SchoolPolicy;
use App\Policies\StudentPolicy;
use App\Policies\TahunAjaranPolicy;
use App\Policies\UserPolicy;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Gaya bersama halaman buatan sendiri dan penyempurnaan Filament (public/css/ui.css); versi mengikuti waktu ubah berkas agar tidak tersimpan usang di peramban.
        // Sisi kiri halaman masuk (teks sambutan); disembunyikan lewat CSS di layar kecil.
        FilamentView::registerRenderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, fn () => view('components.login-hero')->render());
        FilamentView::registerRenderHook(PanelsRenderHook::HEAD_END, fn () => '<link rel="stylesheet" href="'.asset('css/ui.css').'?v='.filemtime(public_path('css/ui.css')).'">');

        // Filament memeriksa policy pada setiap aksi, bukan hanya menu. Tanpa policy, aksi yang dipanggil langsung lolos.
        Gate::policy(School::class, SchoolPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(AcademicYear::class, TahunAjaranPolicy::class);
        Gate::policy(Semester::class, TahunAjaranPolicy::class);
        Gate::policy(Student::class, StudentPolicy::class);
        foreach ([Staff::class, Guardian::class, Enrollment::class] as $model) {
            Gate::policy($model, DataPribadiPolicy::class);
        }
        foreach ([Subject::class, Classroom::class, TeachingAssignment::class] as $model) {
            Gate::policy($model, DataMasterPolicy::class);
        }
        foreach ([TimeSlot::class, ScheduleEntry::class] as $model) {
            Gate::policy($model, JadwalPolicy::class);
        }
        Gate::policy(AssessmentComponent::class, PenilaianPolicy::class);
        Gate::policy(\App\Models\Announcement::class, \App\Policies\PengumumanPolicy::class);
        Gate::policy(\App\Models\AiAnalysis::class, \App\Policies\AnalisisAiPolicy::class);
        Gate::policy(\App\Models\AiPrompt::class, \App\Policies\PromptAiPolicy::class);
        Gate::policy(\App\Models\FaceTemplate::class, \App\Policies\DataWajahPolicy::class);
    }
}
