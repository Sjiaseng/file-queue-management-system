<?php

namespace App\Jobs;

use App\Models\Upload;
use App\Models\Product;
use App\Events\UploadStatusChanged;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessCsvUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout

    public function __construct(public Upload $upload) {}

    public function handle(): void
    {
        try {
            $this->upload->update(['status' => 'processing']);
            broadcast(new UploadStatusChanged($this->upload));

            $filePath = Storage::path($this->upload->file_path);
            if (!file_exists($filePath)) {
                throw new \Exception('File not found: ' . $filePath);
            }

            $handle = fopen($filePath, 'r');
            if ($handle === false) {
                throw new \Exception('Could not open file for reading');
            }

            // Detect delimiter automatically (tab or comma)
            $firstLine = fgets($handle);
            rewind($handle);
            $delimiter = str_contains($firstLine, "\t") ? "\t" : ",";

            // Read header
            $header = fgetcsv($handle, 0, $delimiter);
            if ($header === false) {
                throw new \Exception('Could not read CSV header');
            }

            // Clean and normalize header names
            $header = array_map(function ($col) {
                $col = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $col);
                return strtoupper(trim($col));
            }, $header);

            $columnMap = array_flip($header);

            // Ensure required columns exist
            $requiredColumns = ['UNIQUE_KEY', 'SIZE'];
            foreach ($requiredColumns as $col) {
                if (!array_key_exists($col, $columnMap)) {
                    throw new \Exception("Missing required column: {$col}");
                }
            }

            $totalRows = 0;
            $processedRows = 0;
            $skippedRows = 0;
            $batchSize = 100;
            $batch = [];

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $totalRows++;

                // Clean row values
                $row = array_map(function ($value) {
                    if (!mb_check_encoding($value, 'UTF-8')) {
                        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                    }
                    return trim($value);
                }, $row);

                $productData = [
                    'unique_key' => $row[$columnMap['UNIQUE_KEY']] ?? null,
                    'product_title' => $row[$columnMap['PRODUCT_TITLE']] ?? null,
                    'product_description' => $row[$columnMap['PRODUCT_DESCRIPTION']] ?? null,
                    'style' => $row[$columnMap['STYLE#']] ?? null,
                    'sanmar_mainframe_color' => $row[$columnMap['SANMAR_MAINFRAME_COLOR']] ?? null,
                    'size' => $row[$columnMap['SIZE']] ?? null,
                    'color_name' => $row[$columnMap['COLOR_NAME']] ?? null,
                    'piece_price' => $row[$columnMap['PIECE_PRICE']] ?? null,
                ];

                if ($totalRows <= 3) {
                    Log::info("Row {$totalRows} data:", $productData);
                }

                if (empty($productData['unique_key']) || empty($productData['size'])) {
                    $skippedRows++;
                    Log::warning('Skipped row - missing unique_key or size', [
                        'row' => $totalRows,
                        'unique_key' => $productData['unique_key'],
                        'size' => $productData['size']
                    ]);
                    continue;
                }

                $batch[] = $productData;

                if (count($batch) >= $batchSize) {
                    $this->processBatch($batch);
                    $processedRows += count($batch);
                    $batch = [];

                    $this->upload->update([
                        'total_rows' => $totalRows,
                        'processed_rows' => $processedRows,
                    ]);
                }
            }

            if (!empty($batch)) {
                $this->processBatch($batch);
                $processedRows += count($batch);
            }

            fclose($handle);

            $this->upload->update([
                'status' => 'completed',
                'total_rows' => $totalRows,
                'processed_rows' => $processedRows,
            ]);

            if ($skippedRows > 0) {
                Log::info("Skipped {$skippedRows} rows due to missing unique_key or size");
            }

            broadcast(new UploadStatusChanged($this->upload));

        } catch (\Exception $e) {
            Log::error('CSV Processing Error: ' . $e->getMessage(), [
                'upload_id' => $this->upload->id,
                'trace' => $e->getTraceAsString(),
            ]);

            $this->upload->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            broadcast(new UploadStatusChanged($this->upload));
        }
    }

    private function processBatch(array $batch): void
    {
        foreach ($batch as $item) {
            // Skip invalid rows
            if (empty($item['unique_key']) || empty($item['size'])) {
                continue;
            }

            // Check if product with same unique_key and size exists
            $existing = Product::where('unique_key', $item['unique_key'])
                ->where('size', $item['size'])
                ->first();

            if ($existing) {
                // Update existing
                $existing->update([
                    'product_title' => $item['product_title'],
                    'product_description' => $item['product_description'],
                    'style' => $item['style'],
                    'sanmar_mainframe_color' => $item['sanmar_mainframe_color'],
                    'color_name' => $item['color_name'],
                    'piece_price' => $item['piece_price'],
                    'updated_at' => now(),
                ]);
            } else {
                // Insert new
                Product::create([
                    'unique_key' => $item['unique_key'],
                    'product_title' => $item['product_title'],
                    'product_description' => $item['product_description'],
                    'style' => $item['style'],
                    'sanmar_mainframe_color' => $item['sanmar_mainframe_color'],
                    'size' => $item['size'],
                    'color_name' => $item['color_name'],
                    'piece_price' => $item['piece_price'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

}
