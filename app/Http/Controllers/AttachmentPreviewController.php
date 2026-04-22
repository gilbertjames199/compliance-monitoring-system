<?php

namespace App\Http\Controllers;

use App\Support\AttachmentContentPreview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AttachmentPreviewController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $path = trim((string) $request->query('path', ''));
        $path = ltrim(str_replace('\\', '/', $path), '/');

        abort_if($path === '' || str_contains($path, '..'), 404);
        abort_unless(Storage::disk('public')->exists($path), 404);
        abort_unless(AttachmentContentPreview::supports($path), 415);

        return response(AttachmentContentPreview::render($path), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
