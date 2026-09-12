<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\DataExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataExportController extends Controller
{
    /**
     * Built synchronously rather than queued — current account sizes make
     * this fast, and it sidesteps depending on a queue worker actually
     * running in production (see the Platform Infrastructure notes on
     * that). Revisit as a queued, emailed export if accounts grow large
     * enough for this to become slow.
     */
    public function download(Request $request): JsonResponse
    {
        $filename = 'valueafrik-data-'.$request->user()->username.'-'.now()->format('Y-m-d').'.json';

        return response()->json(DataExporter::build($request->user()), options: JSON_PRETTY_PRINT)
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }
}
