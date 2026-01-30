<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\HealthMetric;
use App\Models\SymptomLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class HealthMetricController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'metric_type' => 'required|string|in:blood_pressure,blood_sugar,weight,temperature,bmi,heart_rate',
            // value required unless it's a blood_pressure (we store systolic as value)
            'value' => 'required_unless:metric_type,blood_pressure|nullable|numeric',
            'unit' => 'required|string',
            'additional_data' => 'sometimes|array',
            'additional_data.systolic' => 'required_if:metric_type,blood_pressure|numeric',
            'additional_data.diastolic' => 'required_if:metric_type,blood_pressure|numeric',
            'recorded_at' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Ensure user is authenticated
        $userId = auth()->id();
        if (! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        try {
            // normalize additional_data
            $additional = $request->additional_data ?? null;
            $value = $request->value !== null ? (float) $request->value : null;

            if ($request->metric_type === 'blood_pressure') {
                $systolic = isset($additional['systolic']) ? (float) $additional['systolic'] : null;
                $diastolic = isset($additional['diastolic']) ? (float) $additional['diastolic'] : null;

                $value = $systolic; // store systolic as the main value for quick queries
                $additional = [
                    'systolic' => $systolic,
                    'diastolic' => $diastolic
                ];
            }

            $metric = HealthMetric::create([
                'user_id' => $userId,
                'metric_type' => $request->metric_type,
                'value' => $value,
                'unit' => $request->unit,
                'additional_data' => $additional,
                'recorded_at' => Carbon::parse($request->recorded_at)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Health data recorded successfully',
                'data' => $metric
            ], 201);

        } catch (\Exception $e) {
            // If DB connection failed, fallback to storing locally so frontend can still record data
            $message = $e->getMessage();

            if (str_contains(strtolower($message), 'sqlstate') || $e instanceof \PDOException) {
                try {
                    $localPath = storage_path('app/private/health_metrics.json');
                    $payload = [
                        'user_id' => $userId,
                        'metric_type' => $request->metric_type,
                        'value' => isset($value) ? $value : null,
                        'unit' => $request->unit,
                        'additional_data' => isset($additional) ? $additional : null,
                        'recorded_at' => Carbon::parse($request->recorded_at)->toDateTimeString(),
                        'created_at' => now()->toDateTimeString(),
                    ];

                    $items = [];
                    if (file_exists($localPath)) {
                        $json = file_get_contents($localPath);
                        $items = json_decode($json, true) ?: [];
                    }
                    $items[] = $payload;
                    file_put_contents($localPath, json_encode($items, JSON_PRETTY_PRINT));

                    return response()->json([
                        'success' => true,
                        'message' => 'Database unavailable — recorded locally and will sync later',
                        'data' => $payload
                    ], 201);
                } catch (\Exception $writeEx) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to record health data and failed to write local fallback',
                        'error' => $writeEx->getMessage()
                    ], 500);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to record health data',
                'error' => $message
            ], 500);
        }
    }

    public function getStatus()
    {
        $user = auth()->user();

        try {
            // Simpler and more robust approach: get all user's metrics ordered by recorded_at desc,
            // then pick the first occurrence of each metric_type (collection unique keeps the first)
            $latestMetrics = HealthMetric::where('user_id', $user->id)
                ->orderBy('recorded_at', 'desc')
                ->get()
                ->unique('metric_type')
                ->values();

            $status = [];
            foreach ($latestMetrics as $metric) {
                $status[$metric->metric_type] = $this->calculateHealthStatus($metric);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'metrics_status' => $status,
                    'last_updated' => $latestMetrics->max('recorded_at')
                ]
            ]);

        } catch (\Exception $e) {
            // DB may be unavailable or schema mismatched. Fall back to local file used by write-fallback.
            try {
                $file = storage_path('app/private/health_metrics.json');
                $items = [];
                if (file_exists($file)) {
                    $json = file_get_contents($file);
                    $items = json_decode($json, true) ?: [];
                }

                // Keep latest per metric_type
                $byMetric = [];
                foreach ($items as $it) {
                    if ((string)($it['user_id'] ?? '') !== (string)$user->id) continue;
                    $mt = $it['metric_type'] ?? null;
                    if (! $mt) continue;
                    $ts = isset($it['recorded_at']) ? strtotime($it['recorded_at']) : strtotime($it['created_at'] ?? '0');
                    if (!isset($byMetric[$mt]) || $ts > $byMetric[$mt]['ts']) {
                        $byMetric[$mt] = ['ts' => $ts, 'item' => $it];
                    }
                }

                $status = [];
                $lastUpdated = null;
                foreach ($byMetric as $mt => $data) {
                    $it = $data['item'];
                    // Create a pseudo-model-like object for calculateHealthStatus
                    $pseudo = (object) [
                        'metric_type' => $mt,
                        'value' => $it['value'] ?? null,
                        'unit' => $it['unit'] ?? null,
                        'additional_data' => $it['additional_data'] ?? null,
                        'recorded_at' => $it['recorded_at'] ?? ($it['created_at'] ?? null)
                    ];
                    $status[$mt] = $this->calculateHealthStatus($pseudo);
                    if (! $lastUpdated || strtotime($pseudo->recorded_at) > strtotime($lastUpdated)) {
                        $lastUpdated = $pseudo->recorded_at;
                    }
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'metrics_status' => $status,
                        'last_updated' => $lastUpdated
                    ]
                ]);

            } catch (\Exception $inner) {
                return response()->json(['success' => false, 'message' => 'Failed to fetch health status', 'error' => $e->getMessage()], 500);
            }
        }
    }

    // Temporary test status endpoint (no auth) for local frontend debugging
    public function statusTest(Request $request)
    {
        $userId = $request->header('X-User-Id', 0);

        try {
            $latestMetrics = HealthMetric::where('user_id', $userId)
                ->orderBy('recorded_at', 'desc')
                ->get()
                ->unique('metric_type')
                ->values();

            // If DB has no records for this user, fall back to local file storage (useful when DB down or user only used test route)
            if ($latestMetrics->isEmpty()) {
                $file = storage_path('app/private/health_metrics.json');
                $items = [];
                if (file_exists($file)) {
                    $json = file_get_contents($file);
                    $items = json_decode($json, true) ?: [];
                }

                $byMetric = [];
                foreach ($items as $it) {
                    if ((string)($it['user_id'] ?? '') !== (string)$userId) continue;
                    $mt = $it['metric_type'] ?? null;
                    if (! $mt) continue;
                    $ts = isset($it['recorded_at']) ? strtotime($it['recorded_at']) : strtotime($it['created_at'] ?? '0');
                    if (!isset($byMetric[$mt]) || $ts > $byMetric[$mt]['ts']) {
                        $byMetric[$mt] = ['ts' => $ts, 'item' => $it];
                    }
                }

                $status = [];
                $lastUpdated = null;
                foreach ($byMetric as $mt => $data) {
                    $it = $data['item'];
                    $pseudo = (object) [
                        'metric_type' => $mt,
                        'value' => $it['value'] ?? null,
                        'unit' => $it['unit'] ?? null,
                        'additional_data' => $it['additional_data'] ?? null,
                        'recorded_at' => $it['recorded_at'] ?? ($it['created_at'] ?? null)
                    ];
                    $status[$mt] = $this->calculateHealthStatus($pseudo);
                    if (! $lastUpdated || strtotime($pseudo->recorded_at) > strtotime($lastUpdated)) {
                        $lastUpdated = $pseudo->recorded_at;
                    }
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'metrics_status' => $status,
                        'last_updated' => $lastUpdated
                    ]
                ]);
            }

            $status = [];
            foreach ($latestMetrics as $metric) {
                $status[$metric->metric_type] = $this->calculateHealthStatus($metric);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'metrics_status' => $status,
                    'last_updated' => $latestMetrics->max('recorded_at')
                ]
            ]);

        } catch (\Exception $e) {
            // fallback to local file
            try {
                $file = storage_path('app/private/health_metrics.json');
                $items = [];
                if (file_exists($file)) {
                    $json = file_get_contents($file);
                    $items = json_decode($json, true) ?: [];
                }

                $byMetric = [];
                foreach ($items as $it) {
                    if ((string)($it['user_id'] ?? '') !== (string)$userId) continue;
                    $mt = $it['metric_type'] ?? null;
                    if (! $mt) continue;
                    $ts = isset($it['recorded_at']) ? strtotime($it['recorded_at']) : strtotime($it['created_at'] ?? '0');
                    if (!isset($byMetric[$mt]) || $ts > $byMetric[$mt]['ts']) {
                        $byMetric[$mt] = ['ts' => $ts, 'item' => $it];
                    }
                }

                $status = [];
                $lastUpdated = null;
                foreach ($byMetric as $mt => $data) {
                    $it = $data['item'];
                    $pseudo = (object) [
                        'metric_type' => $mt,
                        'value' => $it['value'] ?? null,
                        'unit' => $it['unit'] ?? null,
                        'additional_data' => $it['additional_data'] ?? null,
                        'recorded_at' => $it['recorded_at'] ?? ($it['created_at'] ?? null)
                    ];
                    $status[$mt] = $this->calculateHealthStatus($pseudo);
                    if (! $lastUpdated || strtotime($pseudo->recorded_at) > strtotime($lastUpdated)) {
                        $lastUpdated = $pseudo->recorded_at;
                    }
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'metrics_status' => $status,
                        'last_updated' => $lastUpdated
                    ]
                ]);

            } catch (\Exception $inner) {
                return response()->json(['success' => false, 'message' => 'Failed to fetch health status', 'error' => $e->getMessage()], 500);
            }
        }
    }

    // Temporary test endpoint without auth for local frontend debugging
    public function storeTest(Request $request)
    {
        // Accept an optional X-User-Id header to mimic authenticated user
        $userId = $request->header('X-User-Id', 0);

        $validator = Validator::make($request->all(), [
            'metric_type' => 'required|string|in:blood_pressure,blood_sugar,weight,temperature,bmi,heart_rate',
            'value' => 'required_unless:metric_type,blood_pressure|nullable|numeric',
            'unit' => 'required|string',
            'additional_data' => 'sometimes|array',
            'additional_data.systolic' => 'required_if:metric_type,blood_pressure|numeric',
            'additional_data.diastolic' => 'required_if:metric_type,blood_pressure|numeric',
            'recorded_at' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $additional = $request->additional_data ?? null;
            $value = $request->value !== null ? (float) $request->value : null;

            if ($request->metric_type === 'blood_pressure') {
                $systolic = isset($additional['systolic']) ? (float) $additional['systolic'] : null;
                $diastolic = isset($additional['diastolic']) ? (float) $additional['diastolic'] : null;

                $value = $systolic;
                $additional = [
                    'systolic' => $systolic,
                    'diastolic' => $diastolic
                ];
            }

            $metric = HealthMetric::create([
                'user_id' => $userId,
                'metric_type' => $request->metric_type,
                'value' => $value,
                'unit' => $request->unit,
                'additional_data' => $additional,
                'recorded_at' => Carbon::parse($request->recorded_at)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Health data recorded successfully (test endpoint)',
                'data' => $metric
            ], 201);

        } catch (\Exception $e) {
            $message = $e->getMessage();

            if (str_contains(strtolower($message), 'sqlstate') || $e instanceof \PDOException) {
                try {
                    $localPath = storage_path('app/private/health_metrics.json');
                    $payload = [
                        'user_id' => $userId,
                        'metric_type' => $request->metric_type,
                        'value' => isset($value) ? $value : null,
                        'unit' => $request->unit,
                        'additional_data' => isset($additional) ? $additional : null,
                        'recorded_at' => Carbon::parse($request->recorded_at)->toDateTimeString(),
                        'created_at' => now()->toDateTimeString(),
                    ];

                    $items = [];
                    if (file_exists($localPath)) {
                        $json = file_get_contents($localPath);
                        $items = json_decode($json, true) ?: [];
                    }
                    $items[] = $payload;
                    file_put_contents($localPath, json_encode($items, JSON_PRETTY_PRINT));

                    return response()->json([
                        'success' => true,
                        'message' => 'Database unavailable — recorded locally (test endpoint)',
                        'data' => $payload
                    ], 201);
                } catch (\Exception $writeEx) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to record health data (test endpoint) and failed to write local fallback',
                        'error' => $writeEx->getMessage()
                    ], 500);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to record health data (test endpoint)',
                'error' => $message
            ], 500);
        }
    }

    /**
     * Return historical series data for charts for a given metric_type
     * - Supports: blood_pressure (systolic & diastolic), blood_sugar, weight, temperature, bmi, heart_rate
     */
    public function chart(Request $request, $metric)
    {
        $allowed = ['blood_pressure','blood_sugar','weight','temperature','bmi','heart_rate'];
        if (! in_array($metric, $allowed)) {
            return response()->json(['success' => false, 'message' => 'Invalid metric type'], 422);
        }

        $user = auth()->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        try {
            $days = (int)$request->query('days', 0);
            $query = HealthMetric::where('user_id', $user->id)
                ->where('metric_type', $metric);
            if ($days > 0) {
                $query = $query->where('recorded_at', '>=', now()->subDays($days));
            }

            $rows = $query->orderBy('recorded_at', 'asc')
                ->get(['recorded_at', 'value', 'unit', 'additional_data']);

            if ($metric === 'blood_pressure') {
                $systolic = [];
                $diastolic = [];
                foreach ($rows as $r) {
                    $s = $r->additional_data['systolic'] ?? $r->value;
                    $d = $r->additional_data['diastolic'] ?? null;
                    $systolic[] = ['x' => $r->recorded_at->toDateTimeString(), 'y' => $s];
                    if (! is_null($d)) $diastolic[] = ['x' => $r->recorded_at->toDateTimeString(), 'y' => $d];
                }

                // Build flattened array for easier frontend consumption
                $flat = [];
                $dMap = [];
                foreach ($diastolic as $d) { $dMap[$d['x']] = $d['y']; }
                foreach ($systolic as $s) {
                    $flat[] = ['date' => $s['x'], 'systolic' => $s['y'], 'diastolic' => $dMap[$s['x']] ?? null];
                }

                return response()->json(['success' => true, 'data' => [
                    'metric' => $metric,
                    'series' => ['systolic' => $systolic, 'diastolic' => $diastolic],
                    'unit' => 'mmHg',
                    'flat' => $flat
                ]]);
            }

            $series = $rows->map(function ($r) {
                return ['x' => $r->recorded_at->toDateTimeString(), 'y' => $r->value];
            })->values()->all();

            $unit = $rows->first()->unit ?? null;

            $flat = array_map(function ($it) {
                return ['date' => $it['x'], 'value' => $it['y']];
            }, $series);

            return response()->json(['success' => true, 'data' => [
                'metric' => $metric,
                'series' => $series,
                'unit' => $unit,
                'flat' => array_values($flat)
            ]]);

        } catch (\Exception $e) {
            // DB might be down — fall back to local file used by write-fallback
            try {
                $file = storage_path('app/private/health_metrics.json');
                $items = [];
                if (file_exists($file)) {
                    $json = file_get_contents($file);
                    $items = json_decode($json, true) ?: [];
                }

                $filtered = array_filter($items, function ($it) use ($user, $metric) {
                    return (string)($it['user_id'] ?? '') === (string)$user->id && ($it['metric_type'] ?? '') === $metric;
                });

                // map to series
                if ($metric === 'blood_pressure') {
                    $systolic = [];
                    $diastolic = [];
                    foreach ($filtered as $it) {
                        $ts = $it['recorded_at'] ?? ($it['created_at'] ?? now()->toDateTimeString());
                        $systolic[] = ['x' => $ts, 'y' => $it['additional_data']['systolic'] ?? $it['value'] ?? null];
                        if (isset($it['additional_data']['diastolic'])) $diastolic[] = ['x' => $ts, 'y' => $it['additional_data']['diastolic']];
                    }

                    // Build flattened series
                    $flat = [];
                    $dMap = [];
                    foreach ($diastolic as $d) { $dMap[$d['x']] = $d['y']; }
                    foreach ($systolic as $s) {
                        $flat[] = ['date' => $s['x'], 'systolic' => $s['y'], 'diastolic' => $dMap[$s['x']] ?? null];
                    }

                    return response()->json(['success' => true, 'data' => [
                        'metric' => $metric,
                        'series' => ['systolic' => $systolic, 'diastolic' => $diastolic],
                        'unit' => 'mmHg',
                        'flat' => $flat
                    ]] );
                }

                $series = array_map(function ($it) {
                    $ts = $it['recorded_at'] ?? ($it['created_at'] ?? now()->toDateTimeString());
                    return ['x' => $ts, 'y' => $it['value'] ?? null];
                }, $filtered);

                $unit = count($filtered) ? ($filtered[array_key_first($filtered)]['unit'] ?? null) : null;

                $flat = array_map(function ($it) { return ['date' => $it['x'], 'value' => $it['y']]; }, $series);

                return response()->json(['success' => true, 'data' => [
                    'metric' => $metric,
                    'series' => array_values($series),
                    'unit' => $unit,
                    'flat' => array_values($flat)
                ]]);

            } catch (\Exception $inner) {
                return response()->json(['success' => false, 'message' => 'Failed to fetch chart data', 'error' => $e->getMessage()], 500);
            }
        }
    }

    // Temporary unauthenticated chart endpoint for local debugging
    public function chartTest(Request $request, $metric = 'bmi')
    {
        $userId = $request->header('X-User-Id', 0);
        $allowed = ['blood_pressure','blood_sugar','weight','temperature','bmi','heart_rate'];
        if (! in_array($metric, $allowed)) {
            return response()->json(['success' => false, 'message' => 'Invalid metric type'], 422);
        }

        try {
            // try DB first if available
            $days = (int)$request->query('days', 0);
            $query = HealthMetric::where('user_id', $userId)
                ->where('metric_type', $metric);
            if ($days > 0) {
                $query = $query->where('recorded_at', '>=', now()->subDays($days));
            }

            $rows = $query->orderBy('recorded_at', 'asc')
                ->get(['recorded_at', 'value', 'unit', 'additional_data']);

            if ($metric === 'blood_pressure') {
                $systolic = [];
                $diastolic = [];
                foreach ($rows as $r) {
                    $s = $r->additional_data['systolic'] ?? $r->value;
                    $d = $r->additional_data['diastolic'] ?? null;
                    $systolic[] = ['x' => $r->recorded_at->toDateTimeString(), 'y' => $s];
                    if (! is_null($d)) $diastolic[] = ['x' => $r->recorded_at->toDateTimeString(), 'y' => $d];
                }

                $flat = [];
                $dMap = [];
                foreach ($diastolic as $d) { $dMap[$d['x']] = $d['y']; }
                foreach ($systolic as $s) { $flat[] = ['date' => $s['x'], 'systolic' => $s['y'], 'diastolic' => $dMap[$s['x']] ?? null]; }

                return response()->json(['success' => true, 'data' => [
                    'metric' => $metric,
                    'series' => ['systolic' => $systolic, 'diastolic' => $diastolic],
                    'unit' => 'mmHg',
                    'flat' => $flat
                ]]);
            }

            $series = $rows->map(function ($r) {
                return ['x' => $r->recorded_at->toDateTimeString(), 'y' => $r->value];
            })->values()->all();

            $unit = $rows->first()->unit ?? null;

            if (empty($series)) {
                // fall back to local file
                $file = storage_path('app/private/health_metrics.json');
                $items = [];
                if (file_exists($file)) {
                    $json = file_get_contents($file);
                    $items = json_decode($json, true) ?: [];
                }

                $filtered = array_filter($items, function ($it) use ($userId, $metric) {
                    return (string)($it['user_id'] ?? '') === (string)$userId && ($it['metric_type'] ?? '') === $metric;
                });

                $series = array_map(function ($it) {
                    $ts = $it['recorded_at'] ?? ($it['created_at'] ?? now()->toDateTimeString());
                    return ['x' => $ts, 'y' => $it['value'] ?? null];
                }, $filtered);

                $unit = count($filtered) ? ($filtered[array_key_first($filtered)]['unit'] ?? null) : null;
            }

            $flat = array_map(function ($it) { return ['date' => $it['x'], 'value' => $it['y']]; }, array_values($series));

            return response()->json(['success' => true, 'data' => [
                'metric' => $metric,
                'series' => array_values($series),
                'unit' => $unit,
                'flat' => array_values($flat)
            ]]);

        } catch (\Exception $e) {
            // If DB is down, fall back to local file similar to the authenticated chart method
            try {
                $file = storage_path('app/private/health_metrics.json');
                $items = [];
                if (file_exists($file)) {
                    $json = file_get_contents($file);
                    $items = json_decode($json, true) ?: [];
                }

                $filtered = array_filter($items, function ($it) use ($userId, $metric) {
                    return (string)($it['user_id'] ?? '') === (string)$userId && ($it['metric_type'] ?? '') === $metric;
                });

                if ($metric === 'blood_pressure') {
                    $systolic = [];
                    $diastolic = [];
                    foreach ($filtered as $it) {
                        $ts = $it['recorded_at'] ?? ($it['created_at'] ?? now()->toDateTimeString());
                        $systolic[] = ['x' => $ts, 'y' => $it['additional_data']['systolic'] ?? $it['value'] ?? null];
                        if (isset($it['additional_data']['diastolic'])) $diastolic[] = ['x' => $ts, 'y' => $it['additional_data']['diastolic']];
                    }

                    return response()->json(['success' => true, 'data' => [
                        'metric' => $metric,
                        'series' => ['systolic' => $systolic, 'diastolic' => $diastolic],
                        'unit' => 'mmHg'
                    ]] );
                }

                $series = array_map(function ($it) {
                    $ts = $it['recorded_at'] ?? ($it['created_at'] ?? now()->toDateTimeString());
                    return ['x' => $ts, 'y' => $it['value'] ?? null];
                }, $filtered);

                $unit = count($filtered) ? ($filtered[array_key_first($filtered)]['unit'] ?? null) : null;

                return response()->json(['success' => true, 'data' => [
                    'metric' => $metric,
                    'series' => array_values($series),
                    'unit' => $unit
                ]]);

            } catch (\Exception $inner) {
                return response()->json(['success' => false, 'message' => 'Failed to fetch chart data', 'error' => $e->getMessage()], 500);
            }
        }
    }

    /**
     * Return historical records across metrics and symptoms for a user.
     * Optional query params: metric (string), days (int)
     */
    public function history(Request $request)
    {
        $metric = $request->query('metric', null);
        $days = (int)$request->query('days', 0);

        $user = auth()->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        try {
            $results = [];

            // Metrics
            if ($metric === null || $metric === '' || $metric !== 'symptom') {
                $q = HealthMetric::where('user_id', $user->id);
                if ($metric && $metric !== '') {
                    $q->where('metric_type', $metric);
                }
                if ($days > 0) {
                    $q->where('recorded_at', '>=', now()->subDays($days));
                }

                $metrics = $q->orderBy('recorded_at', 'desc')->get();

                foreach ($metrics as $m) {
                    if ($m->metric_type === 'blood_pressure') {
                        $results[] = [
                            'id' => $m->id,
                            'date' => $m->recorded_at->toDateTimeString(),
                            'metric_type' => 'blood_pressure',
                            'systolic' => $m->additional_data['systolic'] ?? $m->value,
                            'diastolic' => $m->additional_data['diastolic'] ?? null,
                            'unit' => $m->unit,
                            'raw' => $m
                        ];
                    } else {
                        $results[] = [
                            'id' => $m->id,
                            'date' => $m->recorded_at->toDateTimeString(),
                            'metric_type' => $m->metric_type,
                            'value' => $m->value,
                            'unit' => $m->unit,
                            'raw' => $m
                        ];
                    }
                }
            }

            // Symptoms (include if metric omitted/all or explicitly 'symptom')
            if ($metric === null || $metric === '' || $metric === 'symptom') {
                $sq = SymptomLog::where('user_id', $user->id);
                if ($days > 0) {
                    $sq->where('recorded_at', '>=', now()->subDays($days));
                }
                $symptoms = $sq->orderBy('recorded_at', 'desc')->get();
                foreach ($symptoms as $s) {
                    $results[] = [
                        'id' => $s->id,
                        'date' => $s->recorded_at->toDateTimeString(),
                        'metric_type' => 'symptom',
                        'symptom' => $s->symptom,
                        'description' => $s->description,
                        'severity' => $s->severity,
                        'raw' => $s
                    ];
                }
            }

            // Sort combined results descending by date
            usort($results, function ($a, $b) {
                return strtotime($b['date']) <=> strtotime($a['date']);
            });

            return response()->json(['success' => true, 'data' => $results]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch history', 'error' => $e->getMessage()], 500);
        }
    }

    // Unauthenticated test endpoint for local debugging (accepts X-User-Id header)
    public function historyTest(Request $request)
    {
        $userId = $request->header('X-User-Id', 0);
        $metric = $request->query('metric', null);
        $days = (int)$request->query('days', 0);

        try {
            $results = [];

            if ($metric === null || $metric === '' || $metric !== 'symptom') {
                $q = HealthMetric::where('user_id', $userId);
                if ($metric && $metric !== '') {
                    $q->where('metric_type', $metric);
                }
                if ($days > 0) {
                    $q->where('recorded_at', '>=', now()->subDays($days));
                }

                $metrics = $q->orderBy('recorded_at', 'desc')->get();
                foreach ($metrics as $m) {
                    if ($m->metric_type === 'blood_pressure') {
                        $results[] = [
                            'id' => $m->id,
                            'date' => $m->recorded_at->toDateTimeString(),
                            'metric_type' => 'blood_pressure',
                            'systolic' => $m->additional_data['systolic'] ?? $m->value,
                            'diastolic' => $m->additional_data['diastolic'] ?? null,
                            'unit' => $m->unit,
                            'raw' => $m
                        ];
                    } else {
                        $results[] = [
                            'id' => $m->id,
                            'date' => $m->recorded_at->toDateTimeString(),
                            'metric_type' => $m->metric_type,
                            'value' => $m->value,
                            'unit' => $m->unit,
                            'raw' => $m
                        ];
                    }
                }
            }

            if ($metric === null || $metric === '' || $metric === 'symptom') {
                $sq = SymptomLog::where('user_id', $userId);
                if ($days > 0) {
                    $sq->where('recorded_at', '>=', now()->subDays($days));
                }
                $symptoms = $sq->orderBy('recorded_at', 'desc')->get();
                foreach ($symptoms as $s) {
                    $results[] = [
                        'id' => $s->id,
                        'date' => $s->recorded_at->toDateTimeString(),
                        'metric_type' => 'symptom',
                        'symptom' => $s->symptom,
                        'description' => $s->description,
                        'severity' => $s->severity,
                        'raw' => $s
                    ];
                }
            }

            usort($results, function ($a, $b) { return strtotime($b['date']) <=> strtotime($a['date']); });

            return response()->json(['success' => true, 'data' => $results]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch history (test)', 'error' => $e->getMessage()], 500);
        }
    }

    private function calculateHealthStatus($metric)
    {
        // $metric can be an Eloquent model or a pseudo object from fallback.
        $type = $metric->metric_type ?? null;
        $value = isset($metric->value) ? (float)$metric->value : null;
        $unit = $metric->unit ?? null;
        $recorded_at = isset($metric->recorded_at) ? (string)$metric->recorded_at : null;

        $status = 'unknown';

        switch ($type) {
            case 'blood_pressure':
                // Expect additional_data with systolic/diastolic
                $s = $metric->additional_data['systolic'] ?? $value ?? null;
                $d = $metric->additional_data['diastolic'] ?? null;
                if ($s === null) { $status = 'unknown'; break; }
                if ($s < 90 || $d < 60) $status = 'low';
                elseif ($s <= 120 && $d <= 80) $status = 'normal';
                elseif ($s <= 139 || $d <= 89) $status = 'elevated';
                else $status = 'high';

                return ['status' => $status, 'value' => $s, 'unit' => 'mmHg', 'recorded_at' => $recorded_at];

            case 'blood_sugar':
                if ($value === null) { $status = 'unknown'; break; }
                if ($value < 70) $status = 'low';
                elseif ($value <= 140) $status = 'normal';
                else $status = 'high';
                return ['status' => $status, 'value' => $value, 'unit' => $unit ?? 'mg/dL', 'recorded_at' => $recorded_at];

            case 'bmi':
                if ($value === null) { $status = 'unknown'; break; }
                if ($value < 18.5) $status = 'underweight';
                elseif ($value < 25) $status = 'normal';
                elseif ($value < 30) $status = 'overweight';
                else $status = 'obese';
                return ['status' => $status, 'value' => $value, 'unit' => $unit ?? 'kg/m²', 'recorded_at' => $recorded_at];

            case 'weight':
            case 'temperature':
            case 'heart_rate':
                // Simple thresholds (informational) — adjust as needed
                if ($value === null) { $status = 'unknown'; break; }
                if ($type === 'temperature') {
                    if ($value < 36) $status = 'low';
                    elseif ($value <= 37.5) $status = 'normal';
                    else $status = 'fever';
                    $unit = $unit ?? '°C';
                } elseif ($type === 'heart_rate') {
                    if ($value < 60) $status = 'low';
                    elseif ($value <= 100) $status = 'normal';
                    else $status = 'high';
                    $unit = $unit ?? 'bpm';
                } else { // weight
                    $status = 'recorded';
                    $unit = $unit ?? 'kg';
                }

                return ['status' => $status, 'value' => $value, 'unit' => $unit, 'recorded_at' => $recorded_at];

            default:
                return ['status' => 'unknown', 'value' => $value, 'unit' => $unit, 'recorded_at' => $recorded_at];
        }

        return ['status' => 'unknown', 'value' => $value, 'unit' => $unit, 'recorded_at' => $recorded_at];
    }
}