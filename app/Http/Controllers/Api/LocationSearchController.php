<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class LocationSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = trim($request->query('query', ''));

        if (empty($query)) {
            return response()->json(['error' => 'Query parameter is required'], 400);
        }

        // Cache key (same query → same result for 1 hour)
        $cacheKey = 'nominatim_search_' . md5($query);

        $results = Cache::remember($cacheKey, now()->addHour(), function () use ($query) {

            // Nominatim usage policy: must include User-Agent + usually Referer
            // Change "YourAppName" to something real (your app name + contact email is best)
            $userAgent = 'YourAppName (contact: your.email@example.com)';

            $response = Http::withHeaders([
                'User-Agent'   => $userAgent,
                'Referer'      => config('app.url', 'http://localhost'),
                'Accept'       => 'application/json',
            ])->get('https://nominatim.openstreetmap.org/search', [
                'q'              => $query,
                'format'         => 'json',
                'addressdetails' => 1,
                'limit'          => 15,           // max results to return
                'countrycodes'   => 'et',         // ← restrict to Ethiopia (very helpful!)
                'amenity'        => 'hospital|pharmacy|clinic|dentist|health_post', // ← key filter!
                'polygon_geojson' => 0,           // we don't need geometry
            ]);

            if ($response->failed()) {
                \Log::error('Nominatim failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return [];
            }

            $data = $response->json();

            // Clean & normalize the response for frontend
            return collect($data)->map(function ($item) {
                return [
                    'place_id'      => $item['place_id'] ?? null,
                    'lat'           => $item['lat'] ?? null,
                    'lon'           => $item['lon'] ?? null,
                    'display_name'  => $item['display_name'] ?? 'Unknown',
                    'name'          => $item['display_name'] ? explode(',', $item['display_name'])[0] : 'Unknown',
                    'type'          => $item['type'] ?? $item['class'] ?? 'unknown',
                    'category'      => $item['category'] ?? null,
                    'address'       => $item['address'] ?? [],
                ];
            })->filter(function ($item) {
                // Extra client-side safety filter (in case Nominatim returns unrelated)
                $types = ['hospital', 'pharmacy', 'clinic', 'dentist', 'health_post', 'doctor'];
                return in_array(strtolower($item['type'] ?? ''), $types) ||
                       str_contains(strtolower($item['category'] ?? ''), 'health');
            })->values()->all();
        });

        return response()->json([
            'results' => $results,
        ]);
    }
}