<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use App\Http\Resources\UploadResource;
use App\Jobs\ProcessCsvUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /**
     * Display a listing of uploads.
     */

    // public function index()
    // {
    //     $uploads = Upload::orderBy('created_at', 'desc')->get();
    //     return response()->json($uploads);
    // }

    public function index()
    {
        $uploads = Upload::orderBy('created_at', 'desc')->get();
        
        return UploadResource::collection($uploads);
    }

    /**
     * Store a newly uploaded file.
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimetypes:text/plain,text/csv,application/csv|max:51200', // 50MB max
        ]);

        $file = $request->file('file');
        
        // Calculate file hash for idempotency
        $fileHash = hash_file('sha256', $file->getRealPath());
        
        // Check if this file has already been uploaded
        $existingUpload = Upload::where('file_hash', $fileHash)->first();
        
        if ($existingUpload) {
            return response()->json([
                'message' => 'This file has already been uploaded.',
                'upload' => new UploadResource($existingUpload),
            ], 200);
        }

        // Store the file
        $filename = Str::uuid() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('uploads', $filename, 'local');

        // Create upload record
        $upload = Upload::create([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_hash' => $fileHash,
            'status' => 'pending',
        ]);

        // Dispatch job to process the CSV
        ProcessCsvUpload::dispatch($upload);

        return response()->json([
            'message' => 'File uploaded successfully and queued for processing.',
            'upload' => new UploadResource($upload),
        ], 201);
    }

    /**
     * Display the upload page.
     */
    public function showUploadPage()
    {
        return view('uploads.index');
    }
}