<div class="space-y-4">
    {{-- Pass the data directly to the existing attachment-preview view --}}
    @include('filament.forms.components.attachment-preview', [
        'preview' => $preview,
        'editable' => $editable ?? false,
        'annotationEditable' => $annotationEditable ?? false,
        'draftsStatePath' => $draftsStatePath ?? 'data.attachment_remark_drafts',
        'annotationsStatePath' => $annotationsStatePath ?? 'data.attachment_annotations',
        'draftLabel' => $draftLabel ?? 'Your reply',
        'draftPlaceholder' => $draftPlaceholder ?? 'Reply to the requiring agency about this file.',
    ])
</div>