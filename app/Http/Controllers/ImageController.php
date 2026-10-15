<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Support\ImageStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sends a photo that is stored in the database (product photo or payment confirmation).
 * The browser keeps its own copy, so the database is asked for a photo only once, not on every page.
 */
class ImageController extends Controller
{
    public function show(Request $request, int $image)
    {
        // Look at the small details first, so the photo itself is only read when it is really needed.
        $meta = DB::table('images')->where('id', $image)->first(['id', 'mime_type', 'file_size', 'updated_at']);
        abort_unless($meta, 404);

        // A payment photo can only be opened by the Owner or by the cashier who made that sale.
        $sale = Sale::where('payment_proof_id', $image)->first(['id', 'user_id']);
        if ($sale && ! auth()->user()->hasFullAccess() && $sale->user_id !== auth()->id()) {
            abort(403);
        }

        $etag    = '"' . md5($meta->id . '-' . $meta->file_size . '-' . $meta->updated_at) . '"';
        $headers = [
            'Content-Type'           => $meta->mime_type,
            'Cache-Control'          => 'private, max-age=604800',
            'ETag'                   => $etag,
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304, $headers);
        }

        $data = ImageStore::data($image);
        abort_unless($data !== null, 404);

        return response($data, 200, $headers + ['Content-Length' => strlen($data)]);
    }
}
