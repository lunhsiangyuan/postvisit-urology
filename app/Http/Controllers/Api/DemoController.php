<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Visit;
use App\Services\SlackAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

class DemoController extends Controller
{
    public function __construct()
    {
        if (! app()->environment('local', 'staging', 'testing', 'production')) {
            abort(403, 'Demo endpoints are only available in local/staging environments.');
        }
    }

    public function start(Request $request): JsonResponse
    {
        $role = $request->input('role', 'patient');

        $email = $role === 'doctor'
            ? 'doctor@demo.yuanuro.com'
            : 'patient@demo.yuanuro.com';

        // Fallback to original postvisit.ai emails for backward compatibility
        $user = User::where('email', $email)
            ->orWhere('email', str_replace('@demo.yuanuro.com', '@demo.postvisit.ai', $email))
            ->first();

        if (! $user) {
            return response()->json([
                'error' => ['message' => 'Demo data not seeded. Run: php artisan db:seed --class=UrologyDemoSeeder'],
            ], 404);
        }

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
        $token = $user->createToken('demo-token')->plainTextToken;

        $visit = Visit::where('patient_id', $user->patient_id)
            ->orWhere('created_by', $user->id)
            ->with(['patient:id,first_name,last_name', 'practitioner:id,first_name,last_name'])
            ->latest('started_at')
            ->first();

        return response()->json([
            'data' => [
                'user' => $user,
                'token' => $token,
                'visit' => $visit,
                'role' => $role,
            ],
        ]);
    }

    public function status(): JsonResponse
    {
        $hasDemoData = User::where('email', 'patient@demo.yuanuro.com')
            ->orWhere('email', 'patient@demo.postvisit.ai')
            ->exists();

        return response()->json([
            'data' => [
                'seeded' => $hasDemoData,
                'patient_email' => 'patient@demo.yuanuro.com',
                'doctor_email' => 'doctor@demo.yuanuro.com',
                'password' => 'password',
            ],
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        // Block in production — this wipes the entire database
        if (app()->environment('production')) {
            SlackAlertService::resetAttempt($request->ip());

            return response()->json([
                'error' => ['message' => 'Demo reset is disabled in production.'],
            ], 403);
        }

        Artisan::call('migrate:fresh');
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoSeeder']);

        return response()->json([
            'data' => ['message' => 'Demo data has been reset successfully.'],
        ]);
    }

    public function simulateAlert(): JsonResponse
    {
        $doctorUser = User::where('email', 'doctor@demo.yuanuro.com')
            ->orWhere('email', 'doctor@demo.postvisit.ai')
            ->first();

        if (! $doctorUser) {
            return response()->json(['error' => ['message' => 'Demo data not seeded']], 404);
        }

        $visit = Visit::latest('started_at')->first();

        $doctorUser->notifications()->create([
            'visit_id' => $visit?->id,
            'type' => 'escalation_alert',
            'title' => 'Patient Escalation Alert',
            'body' => 'Patient reported concerning symptoms that may require immediate attention: chest pain and shortness of breath since starting medication.',
            'data' => [
                'severity' => 'high',
                'trigger' => 'simulated',
            ],
        ]);

        return response()->json([
            'data' => ['message' => 'Escalation alert simulated successfully.'],
        ]);
    }
}
