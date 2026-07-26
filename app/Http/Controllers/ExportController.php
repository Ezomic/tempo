<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Services\Export\ActivityExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function activity(Request $request, Activity $activity, string $format, ActivityExporter $exporter): Response
    {
        abort_unless($activity->user_id === $request->user()->id, 403);

        $base = 'activity-'.$activity->id;

        return match ($format) {
            'fit' => $this->fit($activity, $base),
            'csv' => $this->download($exporter->streamCsv($activity), "{$base}.csv", 'text/csv'),
            'tcx' => $this->download($exporter->tcx($activity), "{$base}.tcx", 'application/vnd.garmin.tcx+xml'),
            default => abort(404),
        };
    }

    public function all(Request $request, ActivityExporter $exporter): Response
    {
        return $this->download(
            $exporter->allActivitiesCsv($request->user()),
            'tempo-activities.csv',
            'text/csv',
        );
    }

    private function fit(Activity $activity, string $base): Response
    {
        abort_if($activity->fit_path === null || ! Storage::disk('local')->exists($activity->fit_path), 404);

        return Storage::disk('local')->download($activity->fit_path, "{$base}.fit");
    }

    private function download(string $contents, string $filename, string $contentType): StreamedResponse
    {
        return response()->streamDownload(function () use ($contents): void {
            echo $contents;
        }, $filename, ['Content-Type' => $contentType]);
    }
}
