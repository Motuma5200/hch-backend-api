<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContentController extends Controller
{
    /**
     * Display a listing of the resource.
     * This receives the GET request from your React Learn page.
     */
    public function index()
    {
        try {
            // Fetch all records from the contents table, sorted latest first
            $contents = Content::latest()->get();

            return response()->json([
                'success' => true,
                'data' => $contents
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database exception encountered while fetching content structural arrays.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     * This receives the POST request from your React Admin Dashboard.
     */
    public function store(Request $request)
    {
        // 1. Validate incoming multi-part data payload fields
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'videoUrl' => 'nullable|url',
            'category' => 'required|string',
            'detail' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();

        // 2. Handle File Upload Sequence
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            // Stores image in 'storage/app/public/content_thumbnails'
            $path = $file->store('content_thumbnails', 'public'); 
            // Generates absolute public URL asset reference string
            $validatedData['image'] = asset('storage/' . $path);
        }

        // 3. Persist record to MySQL
        $content = Content::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Educational Content Module published successfully.',
            'data' => $content
        ], 201);
    }
}