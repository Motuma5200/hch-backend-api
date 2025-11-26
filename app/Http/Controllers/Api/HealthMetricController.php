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
            'value' => 'required|numeric',
            'unit' => 'required|string',
            'additional_data' => 'sometimes|array',
            'recorded_at' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $metric = HealthMetric::create([
                'user_id' => auth()->id(),
                'metric_type' => $request->metric_type,
                'value' => $request->value,
                'unit' => $request->unit,
                'additional_data' => $request->additional_data,
                'recorded_at' => Carbon::parse($request->recorded_at)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Health data recorded successfully',
                'data' => $metric
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record health data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getStatus()
    {
        $user = auth()->user();
        
        $latestMetrics = HealthMetric::where('user_id', $user->id)
            ->whereIn('id', function($query) use ($user) {
                $query->selectRaw('MAX(id)')
                      ->from('health_metrics')
                      ->where('user_id', $user->id)
                      ->groupBy('metric_type');
            })
            ->get();

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
    }

    private function calculateHealthStatus($metric)
    {
        // Implementation from previous code
        // (Blood pressure, blood sugar, BMI calculations)
    }
}