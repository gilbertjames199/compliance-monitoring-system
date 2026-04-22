<?php

namespace App\Providers;

use App\Listeners\AuthEventListener;
use App\Models\ComplyingOffice;
use App\Models\RequiredDocument;
use App\Observers\ComplyingOfficeObserver;
use App\Observers\RequiredDocumentObserver;
use Filament\Support\Facades\FilamentView;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RequiredDocument::observe(RequiredDocumentObserver::class);
        ComplyingOffice::observe(ComplyingOfficeObserver::class);

        Event::listen(Login::class,  [AuthEventListener::class, 'handleLogin']);
        Event::listen(Logout::class, [AuthEventListener::class, 'handleLogout']);

        FilamentView::registerRenderHook(
            'panels::body.end',
            fn (): string => <<<'HTML'
<script>
    window.attachmentPreviewComponent = window.attachmentPreviewComponent || function (config = {}) {
        return {
            componentId: config.componentId || 'attachment-preview',
            editable: !!config.editable,
            annotationEditable: !!config.annotationEditable,
            draftsStatePath: config.draftsStatePath || null,
            annotationsStatePath: config.annotationsStatePath || null,
            viewStatesStatePath: config.viewStatesStatePath || null,
            viewerType: config.viewerType || null,
            annotationAuthorName: config.annotationAuthorName || null,
            annotationAuthorLabel: config.annotationAuthorLabel || 'Annotation',
            annotationAuthorType: config.annotationAuthorType || null,
            files: [],
            threads: {},
            drafts: {},
            annotations: {},
            viewStates: {},
            activeIndex: 0,
            zoom: 1,
            rotation: 0,
            panX: 0,
            panY: 0,
            isDragging: false,
            dragStartX: 0,
            dragStartY: 0,
            loading: false,
            error: null,
            htmlPreview: '',
            pdfPages: [],
            annotationDraft: '',
            annotationColor: '#f97316',
            annotationMode: false,
            draggingAnnotation: null,
            previewMode: 'fallback',
            init(filesJson, threadsJson, draftsJson, annotationsJson, viewStatesJson) {
                try {
                    this.files = JSON.parse(filesJson || '[]');
                } catch (error) {
                    this.files = [];
                    this.error = 'Unable to read attachment data.';
                }

                try {
                    this.threads = JSON.parse(threadsJson || '{}') || {};
                } catch (error) {
                    this.threads = {};
                }

                try {
                    this.drafts = JSON.parse(draftsJson || '{}') || {};
                } catch (error) {
                    this.drafts = {};
                }

                try {
                    this.annotations = JSON.parse(annotationsJson || '{}') || {};
                } catch (error) {
                    this.annotations = {};
                }

                try {
                    this.viewStates = JSON.parse(viewStatesJson || '{}') || {};
                } catch (error) {
                    this.viewStates = {};
                }

                this.pruneThreads();
                this.pruneDrafts();
                this.pruneAnnotations();
                this.pruneViewStates();
                this.syncDrafts();
                this.syncAnnotations();
                this.applyActiveViewState();
                this.loadActiveFile();
            },
            activeFile() {
                return this.files[this.activeIndex] ?? null;
            },
            activeFileKey() {
                return this.activeFile()?.path || null;
            },
            currentThread() {
                const key = this.activeFileKey();

                return key ? (this.threads[key] || []) : [];
            },
            currentDraft() {
                const key = this.activeFileKey();

                return key ? (this.drafts[key] || '') : '';
            },
            currentAnnotations() {
                const key = this.activeFileKey();

                return key ? (this.annotations[key] || []) : [];
            },
            hasThreadForIndex(index) {
                const key = this.files[index]?.path || null;

                return key ? Array.isArray(this.threads[key]) && this.threads[key].length > 0 : false;
            },
            updateCurrentDraft(value) {
                const key = this.activeFileKey();

                if (!key) {
                    return;
                }

                this.drafts = {
                    ...this.drafts,
                    [key]: value,
                };

                this.pruneDrafts();
                this.syncDrafts();
            },
            pruneAnnotations() {
                const validKeys = new Set(
                    this.files
                        .map((file) => file?.path || null)
                        .filter((path) => typeof path === 'string' && path.length > 0)
                );

                this.annotations = Object.entries(this.annotations || {}).reduce((carry, [key, value]) => {
                    if (!validKeys.has(key)) {
                        return carry;
                    }

                    carry[key] = Array.isArray(value)
                        ? value
                            .filter((entry) => entry && typeof entry === 'object' && String(entry.text || '').trim() !== '')
                            .map((entry) => ({
                                ...entry,
                                page: Math.max(1, Number(entry.page || 1)),
                                x: this.clampPercent(entry.x),
                                y: this.clampPercent(entry.y),
                                color: entry.color || '#f97316',
                            }))
                        : [];

                    return carry;
                }, {});
            },
            pruneThreads() {
                const validKeys = new Set(
                    this.files
                        .map((file) => file?.path || null)
                        .filter((path) => typeof path === 'string' && path.length > 0)
                );

                this.threads = Object.entries(this.threads || {}).reduce((carry, [key, value]) => {
                    if (!validKeys.has(key)) {
                        return carry;
                    }

                    carry[key] = Array.isArray(value) ? value : [];

                    return carry;
                }, {});
            },
            pruneDrafts() {
                const validKeys = new Set(
                    this.files
                        .map((file) => file?.path || null)
                        .filter((path) => typeof path === 'string' && path.length > 0)
                );

                this.drafts = Object.entries(this.drafts || {}).reduce((carry, [key, value]) => {
                    if (!validKeys.has(key)) {
                        return carry;
                    }

                    carry[key] = typeof value === 'string' ? value : String(value ?? '');

                    return carry;
                }, {});
            },
            pruneViewStates() {
                const validKeys = new Set(
                    this.files
                        .map((file) => file?.path || null)
                        .filter((path) => typeof path === 'string' && path.length > 0)
                );

                this.viewStates = Object.entries(this.viewStates || {}).reduce((carry, [key, value]) => {
                    if (!validKeys.has(key)) {
                        return carry;
                    }

                    carry[key] = {
                        rotation: this.normalizeStoredRotation(value?.rotation),
                    };

                    return carry;
                }, {});
            },
            syncDrafts() {
                if (!this.draftsStatePath || !this.$wire || typeof this.$wire.set !== 'function') {
                    return;
                }

                this.$wire.set(this.draftsStatePath, { ...this.drafts });
            },
            syncAnnotations() {
                if (!this.annotationsStatePath || !this.$wire || typeof this.$wire.set !== 'function') {
                    return;
                }

                this.$wire.set(this.annotationsStatePath, { ...this.annotations });
            },
            syncViewStates() {
                if (!this.viewStatesStatePath || !this.$wire || typeof this.$wire.set !== 'function') {
                    return;
                }

                this.$wire.set(this.viewStatesStatePath, { ...this.viewStates });
            },
            formatEntryMeta(entry) {
                const parts = [];

                if (entry?.author_name) {
                    parts.push(entry.author_name);
                }

                if (entry?.author_label) {
                    parts.push(entry.author_label);
                }

                if (entry?.created_at) {
                    parts.push(entry.created_at);
                }

                return parts.join(' | ');
            },
            isOwnEntry(entry) {
                if (!this.viewerType) {
                    return false;
                }

                return entry?.author_type === this.viewerType;
            },
            supportsAnnotations() {
                const file = this.activeFile();

                return this.isAnnotatableFile(file);
            },
            isAnnotatableFile(file) {
                return this.isImage(file) || this.isPdf(file) || this.isDocx(file) || this.isSpreadsheet(file);
            },
            annotationSummary() {
                const count = this.currentAnnotations().length;

                return count ? `${count} remark${count === 1 ? '' : 's'} placed on this file` : 'No direct remark placed on this file yet';
            },
            annotationStatusText() {
                if (!this.supportsAnnotations()) {
                    return 'Annotations are available for PDF, DOCX, XLS, XLSX, CSV, PNG, JPG, and other image files.';
                }

                if (this.annotationEditable) {
                    return this.annotationMode
                        ? 'Placement mode is on'
                        : 'Agency can place remarks directly on the file';
                }

                return this.currentAnnotations().length ? 'Saved remarks are visible on the file' : 'No saved direct remarks';
            },
            toggleAnnotationMode() {
                if (!this.annotationEditable || !this.supportsAnnotations()) {
                    return;
                }

                this.annotationMode = !this.annotationMode;
            },
            isPlacingAnnotation() {
                return this.annotationEditable && this.annotationMode && this.supportsAnnotations();
            },
            shouldUseInteractivePdfOnly() {
                return !!this.annotationEditable || this.currentAnnotations().length > 0;
            },
            annotationsForPage(pageNumber) {
                return this.currentAnnotations().filter((annotation) => Number(annotation.page || 1) === Number(pageNumber || 1));
            },
            annotationStyle(annotation, pageNumber = null) {
                const color = annotation?.color || '#f97316';
                let left = this.clampPercent(annotation?.x);
                let top = this.clampPercent(annotation?.y);

                if (this.previewMode === 'pdf') {
                    const annotationPage = Number(annotation?.page || 1);
                    const targetPage = Number(pageNumber || annotationPage || 1);

                    if (annotationPage !== targetPage) {
                        return 'display:none;';
                    }

                    // PDF annotations are stored as percentages within their page shell,
                    // so keep them anchored to that page rather than the viewport.
                    left = this.clampPercent(annotation?.x);
                    top = this.clampPercent(annotation?.y);
                }

                return `left:${left}%;top:${top}%;border-color:${color};--annotation-accent:${color};`;
            },
            formatAnnotationMeta(annotation) {
                const parts = [];

                if (annotation?.author_name) {
                    parts.push(annotation.author_name);
                }

                if (annotation?.author_label) {
                    parts.push(annotation.author_label);
                }

                if (annotation?.created_at) {
                    parts.push(annotation.created_at);
                }

                return parts.join(' | ');
            },
            isOwnAnnotation(annotation) {
                const activeType = this.annotationAuthorType || this.viewerType;

                if (!activeType) {
                    return false;
                }

                return annotation?.author_type === activeType;
            },
            isDraggingAnnotation(annotationId) {
                return this.draggingAnnotation?.id === annotationId;
            },
            canDeleteAnnotation(annotation) {
                if (!this.annotationEditable) {
                    return false;
                }

                if (!annotation?.author_type || !this.annotationAuthorType) {
                    return true;
                }

                return annotation.author_type === this.annotationAuthorType;
            },
            startAnnotationDrag(annotationId, pageNumber, event) {
                if (!this.annotationEditable) {
                    return;
                }

                const layer = event.currentTarget.closest('[class$="__annotation-layer"]');

                if (!layer) {
                    return;
                }

                this.draggingAnnotation = {
                    id: annotationId,
                    page: Math.max(1, Number(pageNumber || 1)),
                    layer,
                };
                this.isDragging = false;
            },
            dragAnnotation(event) {
                if (!this.draggingAnnotation) {
                    return;
                }

                const { id, page, layer } = this.draggingAnnotation;
                const rect = layer.getBoundingClientRect();

                if (!rect.width || !rect.height) {
                    return;
                }

                const nextX = this.clampPercent(((event.clientX - rect.left) / rect.width) * 100);
                const nextY = this.clampPercent(((event.clientY - rect.top) / rect.height) * 100);
                const key = this.activeFileKey();

                if (!key) {
                    return;
                }

                this.annotations = {
                    ...this.annotations,
                    [key]: this.currentAnnotations().map((annotation) => {
                        if (annotation.id !== id || Number(annotation.page || 1) !== page) {
                            return annotation;
                        }

                        return {
                            ...annotation,
                            x: nextX,
                            y: nextY,
                        };
                    }),
                };
            },
            stopAnnotationDrag() {
                if (!this.draggingAnnotation) {
                    return;
                }

                this.draggingAnnotation = null;
                this.pruneAnnotations();
                this.syncAnnotations();
            },
            placeAnnotation(event, pageNumber = 1) {
                if (!this.isPlacingAnnotation() || this.draggingAnnotation) {
                    return;
                }

                const key = this.activeFileKey();
                const text = String(this.annotationDraft || '').trim();

                if (!key || !text) {
                    this.error = 'Add the remark text first, then click on the file.';
                    this.annotationMode = false;
                    return;
                }

                const rect = event.currentTarget.getBoundingClientRect();

                if (!rect.width || !rect.height) {
                    return;
                }

                const nextEntry = {
                    id: this.createAnnotationId(),
                    text,
                    page: Math.max(1, Number(pageNumber || 1)),
                    x: this.clampPercent(((event.clientX - rect.left) / rect.width) * 100),
                    y: this.clampPercent(((event.clientY - rect.top) / rect.height) * 100),
                    color: this.annotationColor || '#f97316',
                    author_name: this.annotationAuthorName,
                    author_label: this.annotationAuthorLabel,
                    author_type: this.annotationAuthorType,
                    created_at: this.currentTimestamp(),
                };

                this.annotations = {
                    ...this.annotations,
                    [key]: [...this.currentAnnotations(), nextEntry],
                };

                this.pruneAnnotations();
                this.syncAnnotations();
                this.annotationDraft = '';
                this.annotationMode = false;
            },
            removeAnnotation(annotationId) {
                const key = this.activeFileKey();

                if (!key) {
                    return;
                }

                this.annotations = {
                    ...this.annotations,
                    [key]: this.currentAnnotations().filter((annotation) => annotation.id !== annotationId),
                };

                this.syncAnnotations();
            },
            async selectFile(index) {
                if (index < 0 || index >= this.files.length) {
                    return;
                }

                this.activeIndex = index;
                this.zoom = 1;
                this.panX = 0;
                this.panY = 0;
                this.annotationMode = false;
                this.annotationDraft = '';
                this.draggingAnnotation = null;
                this.applyActiveViewState();
                await this.loadActiveFile();
            },
            zoomIn() {
                this.zoom = +(this.zoom + 0.25).toFixed(2);
            },
            zoomOut() {
                this.zoom = Math.max(0.25, +(this.zoom - 0.25).toFixed(2));
            },
            rotateLeft() {
                this.rotation -= 90;
                this.persistActiveViewState();
            },
            rotateRight() {
                this.rotation += 90;
                this.persistActiveViewState();
            },
            resetView() {
                this.zoom = 1;
                this.rotation = 0;
                this.panX = 0;
                this.panY = 0;
                this.persistActiveViewState();
            },
            startDrag(event) {
                if (this.isPlacingAnnotation() || this.draggingAnnotation) {
                    return;
                }

                this.isDragging = true;
                this.dragStartX = event.clientX - this.panX;
                this.dragStartY = event.clientY - this.panY;
            },
            drag(event) {
                if (!this.isDragging) return;
                this.panX = event.clientX - this.dragStartX;
                this.panY = event.clientY - this.dragStartY;
            },
            stopDrag() {
                this.isDragging = false;
            },
            normalizedRotation() {
                return ((this.rotation % 360) + 360) % 360;
            },
            stageStyle() {
                const cursor = this.isPlacingAnnotation()
                    ? 'crosshair'
                    : (this.isDragging ? 'grabbing' : 'grab');

                if (this.usesContentTransform()) {
                    return `transform: translate(${this.panX}px, ${this.panY}px); cursor: ${cursor};`;
                }

                const transformOrigin = this.previewMode === 'pdf' ? 'top left' : 'center center';

                return `transform: translate(${this.panX}px, ${this.panY}px) scale(${this.zoom}) rotate(${this.rotation}deg); transform-origin: ${transformOrigin}; cursor: ${cursor};`;
            },
            contentTransformStyle() {
                const origin = this.isWorkbookFile(this.activeFile()) ? 'top left' : 'center center';

                return `transform: scale(${this.zoom}) rotate(${this.rotation}deg); transform-origin: ${origin};`;
            },
            usesContentTransform() {
                return this.previewMode === 'image' || this.previewMode === 'html';
            },
            applyActiveViewState() {
                const key = this.activeFileKey();
                const state = key ? this.viewStates[key] : null;

                this.rotation = this.normalizeStoredRotation(state?.rotation);
            },
            persistActiveViewState() {
                const key = this.activeFileKey();

                if (!key) {
                    return;
                }

                this.viewStates = {
                    ...this.viewStates,
                    [key]: {
                        rotation: this.normalizeStoredRotation(this.rotation),
                    },
                };

                this.pruneViewStates();
                this.syncViewStates();
            },
            normalizeStoredRotation(value) {
                const normalized = ((Number(value || 0) % 360) + 360) % 360;

                return [90, 180, 270].includes(normalized) ? normalized : 0;
            },
            isImage(file) {
                return !!file && file.isImage;
            },
            isPdf(file) {
                return !!file && file.isPdf;
            },
            isDocx(file) {
                return !!file && file.isDocx;
            },
            isSpreadsheet(file) {
                return !!file && file.isSpreadsheet;
            },
            isWorkbookFile(file) {
                const extension = String(file?.ext || '').toLowerCase();

                return extension === 'xlsx' || extension === 'xls';
            },
            hasServerHtmlPreview(file) {
                return !!String(file?.contentPreviewUrl || '').trim();
            },
            async loadActiveFile() {
                const file = this.activeFile();

                this.loading = false;
                this.error = null;
                this.htmlPreview = '';
                this.pdfPages = [];
                this.previewMode = 'fallback';

                if (!file) {
                    return;
                }

                if (this.isImage(file)) {
                    this.previewMode = 'image';
                    return;
                }

                if (this.isPdf(file)) {
                    await this.renderPdf(file);
                    return;
                }

                if (this.isDocx(file)) {
                    await this.renderDocx(file);
                    return;
                }

                if (this.isSpreadsheet(file)) {
                    if (this.isWorkbookFile(file)) {
                        await this.renderSpreadsheet(file);
                        return;
                    }

                    if (this.hasServerHtmlPreview(file)) {
                        await this.renderServerHtmlPreview(file);
                        return;
                    }

                    await this.renderSpreadsheet(file);
                }
            },
            async renderServerHtmlPreview(file) {
                this.loading = true;

                try {
                    const response = await fetch(file.contentPreviewUrl, {
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Unable to load the file preview.');
                    }

                    this.htmlPreview = await response.text();
                    this.previewMode = 'html';
                } catch (error) {
                    this.error = error?.message || 'Unable to preview this file.';
                    this.previewMode = 'fallback';
                } finally {
                    this.loading = false;
                }
            },
            async renderPdf(file) {
                this.loading = true;

                try {
                    await this.loadScriptOnce(
                        'pdfjs',
                        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js'
                    );

                    if (!window.pdfjsLib) {
                        throw new Error('Unable to load the PDF viewer.');
                    }

                    if (window.pdfjsLib.GlobalWorkerOptions) {
                        window.pdfjsLib.GlobalWorkerOptions.workerSrc =
                            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                    }

                    const pdfData = await this.fetchFileBytes(file.url, 'Unable to load the PDF file.');
                    const loadingTask = window.pdfjsLib.getDocument({
                        data: pdfData,
                        disableWorker: false,
                        useWorkerFetch: false,
                        isEvalSupported: true,
                        enableXfa: true,
                        useSystemFonts: true,
                    });
                    const pdf = await loadingTask.promise;
                    const pages = [];

                    for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
                        const page = await pdf.getPage(pageNumber);
                        const viewport = page.getViewport({ scale: 1.4 });

                        pages.push({
                            pageNumber,
                            width: Math.round(viewport.width),
                            height: Math.round(viewport.height),
                        });
                    }

                    this.pdfPages = pages;
                    this.previewMode = 'pdf';
                    this.loading = false;
                    await this.$nextTick();
                    await this.waitForPdfCanvases(pdf.numPages);

                    for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
                        const page = await pdf.getPage(pageNumber);
                        const viewport = page.getViewport({ scale: 1.4 });
                        const canvas = document.getElementById(this.pdfCanvasId(pageNumber));

                        if (!canvas) {
                            throw new Error(`Missing PDF canvas for page ${pageNumber}.`);
                        }

                        const context = canvas.getContext('2d', { alpha: false });

                        if (!context) {
                            throw new Error(`Unable to create a drawing context for page ${pageNumber}.`);
                        }

                        canvas.width = Math.ceil(viewport.width);
                        canvas.height = Math.ceil(viewport.height);
                        context.save();
                        context.fillStyle = '#ffffff';
                        context.fillRect(0, 0, canvas.width, canvas.height);
                        context.restore();

                        await page.render({
                            canvasContext: context,
                            viewport,
                            background: 'rgba(255,255,255,1)',
                        }).promise;
                    }
                } catch (error) {
                    if (this.shouldUseInteractivePdfOnly()) {
                        this.error = error?.message || 'Interactive PDF rendering failed. Native PDF mode is disabled while annotations are enabled.';
                        this.previewMode = 'fallback';
                    } else {
                        this.error = error?.message || 'Interactive PDF rendering failed. Showing the browser PDF viewer instead.';
                        this.previewMode = 'pdf-native';
                    }
                } finally {
                    this.loading = false;
                }
            },
            waitForPdfCanvases(pageCount) {
                const totalPages = Math.max(1, Number(pageCount || 0));

                return new Promise((resolve, reject) => {
                    let attempts = 0;
                    const maxAttempts = 40;

                    const check = () => {
                        const allReady = Array.from({ length: totalPages }, (_, index) =>
                            document.getElementById(this.pdfCanvasId(index + 1))
                        ).every(Boolean);

                        if (allReady) {
                            resolve();
                            return;
                        }

                        attempts += 1;

                        if (attempts >= maxAttempts) {
                            reject(new Error('Missing PDF canvas elements after Alpine render.'));
                            return;
                        }

                        requestAnimationFrame(check);
                    };

                    requestAnimationFrame(check);
                });
            },
            async renderDocx(file) {
                this.loading = true;

                try {
                    await this.loadScriptOnce(
                        'mammoth',
                        'https://cdn.jsdelivr.net/npm/mammoth@1.8.0/mammoth.browser.min.js'
                    );

                    const response = await fetch(file.url);

                    if (!response.ok) {
                        throw new Error('Unable to load the DOCX file.');
                    }

                    const arrayBuffer = await response.arrayBuffer();
                    const result = await window.mammoth.convertToHtml({ arrayBuffer });

                    this.htmlPreview = `
                        <article class="${file.uidClass || 'attachment-preview'}__docx-body">
                            ${result.value || '<p>No content found in this document.</p>'}
                        </article>
                    `;
                    this.previewMode = 'html';
                } catch (error) {
                    this.error = error?.message || 'Unable to preview this DOCX file.';
                    this.previewMode = 'fallback';
                } finally {
                    this.loading = false;
                }
            },
            async renderSpreadsheet(file) {
                this.loading = true;

                try {
                    await this.loadScriptOnce(
                        'xlsx',
                        'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js'
                    );

                    const response = await fetch(file.url);

                    if (!response.ok) {
                        throw new Error('Unable to load the spreadsheet.');
                    }

                    const arrayBuffer = await response.arrayBuffer();
                    const workbook = window.XLSX.read(arrayBuffer, {
                        type: 'array',
                        raw: false,
                        cellStyles: true,
                        cellDates: true,
                    });

                    this.htmlPreview = workbook.SheetNames
                        .map((sheetName) => this.renderWorkbookSheetHtml(workbook.Sheets[sheetName], sheetName, file.uidClass || 'attachment-preview'))
                        .filter(Boolean)
                        .join('');

                    if (!this.htmlPreview) {
                        this.htmlPreview = '<p>No sheet data found.</p>';
                    }

                    this.previewMode = 'html';
                } catch (error) {
                    this.error = error?.message || 'Unable to preview this spreadsheet.';
                    this.previewMode = 'fallback';
                } finally {
                    this.loading = false;
                }
            },
            renderWorkbookSheetHtml(sheet, sheetName, uidClass) {
                if (!sheet || !sheet['!ref']) {
                    return '';
                }

                const range = window.XLSX.utils.decode_range(sheet['!ref']);
                const maxRows = 120;
                const maxCols = 24;
                const endRow = Math.min(range.e.r, range.s.r + maxRows - 1);
                const endCol = Math.min(range.e.c, range.s.c + maxCols - 1);
                const merges = Array.isArray(sheet['!merges']) ? sheet['!merges'] : [];
                const coveredCells = new Set();
                const mergeStarts = new Map();
                const rows = [];
                const colgroup = ['<col class="attachment-preview__sheet-rownum-col">'];

                for (let columnIndex = range.s.c; columnIndex <= endCol; columnIndex += 1) {
                    const width = this.sheetColumnWidth(sheet, columnIndex);
                    const style = width ? ` style="width:${width}px;min-width:${Math.min(width, 240)}px"` : '';
                    colgroup.push(`<col${style}>`);
                }

                merges.forEach((merge) => {
                    if (!merge || merge.s.r > endRow || merge.s.c > endCol) {
                        return;
                    }

                    const key = `${merge.s.r}:${merge.s.c}`;
                    mergeStarts.set(key, merge);

                    for (let row = merge.s.r; row <= Math.min(merge.e.r, endRow); row += 1) {
                        for (let column = merge.s.c; column <= Math.min(merge.e.c, endCol); column += 1) {
                            if (row === merge.s.r && column === merge.s.c) {
                                continue;
                            }

                            coveredCells.add(`${row}:${column}`);
                        }
                    }
                });

                const headerCells = ['<th class="attachment-preview__sheet-corner"></th>'];

                for (let columnIndex = range.s.c; columnIndex <= endCol; columnIndex += 1) {
                    headerCells.push(`<th scope="col">${this.escapeHtml(window.XLSX.utils.encode_col(columnIndex))}</th>`);
                }

                for (let rowIndex = range.s.r; rowIndex <= endRow; rowIndex += 1) {
                    const cells = [`<th scope="row" class="attachment-preview__sheet-index">${rowIndex + 1}</th>`];

                    for (let columnIndex = range.s.c; columnIndex <= endCol; columnIndex += 1) {
                        const coveredKey = `${rowIndex}:${columnIndex}`;

                        if (coveredCells.has(coveredKey)) {
                            continue;
                        }

                        const cellAddress = window.XLSX.utils.encode_cell({ r: rowIndex, c: columnIndex });
                        const cell = sheet[cellAddress];
                        const merge = mergeStarts.get(coveredKey);
                        const attrs = [];

                        if (merge) {
                            const colspan = Math.min(merge.e.c, endCol) - merge.s.c + 1;
                            const rowspan = Math.min(merge.e.r, endRow) - merge.s.r + 1;

                            if (colspan > 1) {
                                attrs.push(`colspan="${colspan}"`);
                            }

                            if (rowspan > 1) {
                                attrs.push(`rowspan="${rowspan}"`);
                            }
                        }

                        if (cell?.z) {
                            attrs.push(`data-format="${this.escapeHtml(String(cell.z))}"`);
                        }

                        const formatted = cell
                            ? window.XLSX.utils.format_cell(cell, cell.v, { dateNF: 'yyyy-mm-dd hh:mm' })
                            : '';

                        cells.push(`<td ${attrs.join(' ')}>${this.escapeHtml(String(formatted || ''))}</td>`);
                    }

                    rows.push(`<tr>${cells.join('')}</tr>`);
                }

                let limitNotice = '';

                if (range.e.r > endRow || range.e.c > endCol) {
                    limitNotice = `<p class="${uidClass}__limit-note">Preview trimmed to the first ${endRow - range.s.r + 1} row(s) and ${endCol - range.s.c + 1} column(s).</p>`;
                }

                return `
                    <section class="${uidClass}__sheet">
                        <h4>${this.escapeHtml(sheetName)}</h4>
                        <div class="${uidClass}__sheet-table">
                            <table>
                                <colgroup>${colgroup.join('')}</colgroup>
                                <thead><tr>${headerCells.join('')}</tr></thead>
                                <tbody>${rows.join('')}</tbody>
                            </table>
                        </div>
                        ${limitNotice}
                    </section>
                `;
            },
            sheetColumnWidth(sheet, columnIndex) {
                const column = Array.isArray(sheet?.['!cols']) ? sheet['!cols'][columnIndex] : null;

                if (!column) {
                    return 120;
                }

                if (typeof column.wpx === 'number' && column.wpx > 0) {
                    return Math.min(Math.max(column.wpx, 70), 320);
                }

                if (typeof column.wch === 'number' && column.wch > 0) {
                    return Math.min(Math.max(Math.round(column.wch * 9), 70), 320);
                }

                return 120;
            },
            async downloadCurrent() {
                const file = this.activeFile();

                if (!file) {
                    return;
                }

                if (this.isPdf(file)) {
                    await this.downloadPdfWithAnnotations(file);
                    return;
                }

                if (this.isImage(file)) {
                    await this.downloadImageWithAnnotations(file);
                    return;
                }

                if (this.previewMode === 'html') {
                    await this.downloadHtmlPreviewWithAnnotations(file);
                    return;
                }

                if (!this.isImage(file)) {
                    await this.downloadFileFallback(file);
                    return;
                }
            },
            async openCurrent() {
                const file = this.activeFile();

                if (!file) {
                    return;
                }

                if (!this.currentAnnotations().length && !(this.isImage(file) && this.normalizedRotation() !== 0)) {
                    window.open(file.url, '_blank', 'noopener');
                    return;
                }

                if (this.isPdf(file)) {
                    const blob = await this.buildAnnotatedPdfBlob(file);

                    if (blob) {
                        this.openBlobInNewTab(blob);
                        return;
                    }
                }

                if (this.isImage(file)) {
                    const blob = await this.buildAnnotatedImageBlob(file);

                    if (blob) {
                        this.openBlobInNewTab(blob);
                        return;
                    }
                }

                if (this.previewMode === 'html') {
                    const blob = await this.buildAnnotatedHtmlBlob(file);

                    if (blob) {
                        this.openBlobInNewTab(blob);
                        return;
                    }
                }

                await this.openFileFallback(file);
            },
            async downloadPdfWithAnnotations(file) {
                const blob = await this.buildAnnotatedPdfBlob(file);

                if (!blob) {
                    await this.downloadFileFallback(file);
                    return;
                }

                this.triggerBlobDownload(blob, this.buildDownloadName(file.name, 'pdf', '_annotated'));
            },
            async downloadImageWithAnnotations(file) {
                const blob = await this.buildAnnotatedImageBlob(file);
                const extension = String(file.ext || 'png').toLowerCase();

                if (!blob) {
                    await this.downloadFileFallback(file);
                    return;
                }

                this.triggerBlobDownload(
                    blob,
                    this.buildDownloadName(file.name, extension, '_annotated')
                );
            },
            async downloadHtmlPreviewWithAnnotations(file) {
                const blob = await this.buildAnnotatedHtmlBlob(file);

                if (!blob) {
                    await this.downloadFileFallback(file);
                    return;
                }

                this.triggerBlobDownload(
                    blob,
                    this.buildDownloadName(file.name, 'png', '_annotated')
                );
            },
            async buildAnnotatedPdfBlob(file) {
                if (!this.currentAnnotations().length) {
                    return null;
                }

                await this.loadScriptOnce(
                    'pdf-lib',
                    'https://cdn.jsdelivr.net/npm/pdf-lib@1.17.1/dist/pdf-lib.min.js'
                );

                if (!window.PDFLib?.PDFDocument) {
                    return null;
                }

                const pdfBytes = await this.fetchFileBytes(file.url, 'Unable to download the PDF file.');
                const pdfDocument = await window.PDFLib.PDFDocument.load(pdfBytes);
                const font = await pdfDocument.embedFont(window.PDFLib.StandardFonts.Helvetica);
                const pages = pdfDocument.getPages();
                const groupedAnnotations = this.groupAnnotationsByPage();

                pages.forEach((page, index) => {
                    const pageNumber = index + 1;
                    const pageAnnotations = groupedAnnotations[pageNumber] || [];
                    const width = page.getWidth();
                    const height = page.getHeight();

                    pageAnnotations.forEach((annotation) => {
                        const x = (this.clampPercent(annotation.x) / 100) * width;
                        const y = height - ((this.clampPercent(annotation.y) / 100) * height);
                        const fontSize = 10;
                        const metaFontSize = 9;
                        const lineHeight = 12;
                        const padding = 8;
                        const maxTextWidth = 220 - (padding * 2);
                        const textLines = this.wrapPdfText(annotation.text, font, fontSize, maxTextWidth);
                        const metaLines = this.wrapPdfText(this.formatAnnotationMeta(annotation), font, metaFontSize, maxTextWidth);
                        const lines = [
                            ...textLines.map((line) => ({ text: line, size: fontSize, color: 'primary' })),
                            ...metaLines.map((line) => ({ text: line, size: metaFontSize, color: 'secondary' })),
                        ];
                        const widestLine = Math.max(
                            ...lines.map((line) => font.widthOfTextAtSize(String(line.text), line.size)),
                            120
                        );
                        const boxWidth = Math.min(260, widestLine + (padding * 2));
                        const boxHeight = (lines.length * lineHeight) + (padding * 2);
                        const drawX = Math.max(12, Math.min(width - boxWidth - 12, x));
                        const drawY = Math.max(boxHeight + 12, Math.min(height - 12, y));
                        const rgb = this.hexToPdfLibColor(annotation.color || '#f97316');

                        page.drawRectangle({
                            x: drawX,
                            y: drawY - boxHeight,
                            width: boxWidth,
                            height: boxHeight,
                            color: window.PDFLib.rgb(1, 1, 1),
                            borderColor: rgb,
                            borderWidth: 1,
                            opacity: 0.92,
                        });

                        lines.forEach((line, lineIndex) => {
                            page.drawText(String(line.text), {
                                x: drawX + padding,
                                y: drawY - padding - line.size - (lineIndex * lineHeight),
                                size: line.size,
                                font,
                                color: line.color === 'primary' ? rgb : window.PDFLib.rgb(0.25, 0.29, 0.36),
                            });
                        });
                    });
                });

                const outputBytes = await pdfDocument.save();

                return new Blob([outputBytes], { type: 'application/pdf' });
            },
            async buildAnnotatedImageBlob(file) {
                const image = await this.loadImage(file.url);
                const rotation = this.normalizedRotation();
                const shouldSwapSides = rotation === 90 || rotation === 270;
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');

                if (!context) {
                    return null;
                }

                canvas.width = shouldSwapSides ? image.naturalHeight : image.naturalWidth;
                canvas.height = shouldSwapSides ? image.naturalWidth : image.naturalHeight;

                context.translate(canvas.width / 2, canvas.height / 2);
                context.rotate((rotation * Math.PI) / 180);
                context.drawImage(
                    image,
                    -image.naturalWidth / 2,
                    -image.naturalHeight / 2,
                    image.naturalWidth,
                    image.naturalHeight
                );

                this.drawImageAnnotations(context, canvas.width, canvas.height, this.currentAnnotations(), rotation);

                const extension = String(file.ext || 'png').toLowerCase();
                const mimeType = ({
                    jpg: 'image/jpeg',
                    jpeg: 'image/jpeg',
                    png: 'image/png',
                    webp: 'image/webp',
                })[extension] || 'image/png';

                return this.canvasToBlob(canvas, mimeType, 1);
            },
            async buildAnnotatedHtmlBlob(file) {
                const container = document.querySelector(`#${this.componentId} .${this.componentId}__page-shell--html`);

                if (!container) {
                    return null;
                }

                await this.loadScriptOnce(
                    'html2canvas',
                    'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js'
                );

                if (!window.html2canvas) {
                    return null;
                }

                const canvas = await window.html2canvas(container, {
                    backgroundColor: '#ffffff',
                    scale: 2,
                    useCORS: true,
                });

                return this.canvasToBlob(canvas, 'image/png', 1);
            },
            groupAnnotationsByPage() {
                return this.currentAnnotations().reduce((carry, annotation) => {
                    const pageNumber = Math.max(1, Number(annotation.page || 1));

                    carry[pageNumber] = carry[pageNumber] || [];
                    carry[pageNumber].push(annotation);

                    return carry;
                }, {});
            },
            drawImageAnnotations(context, canvasWidth, canvasHeight, annotations, rotation) {
                if (!Array.isArray(annotations) || !annotations.length) {
                    return;
                }

                const normalizedRotation = ((rotation % 360) + 360) % 360;

                annotations.forEach((annotation) => {
                    const point = this.mapAnnotationPointForRotation(
                        (this.clampPercent(annotation.x) / 100) * canvasWidth,
                        (this.clampPercent(annotation.y) / 100) * canvasHeight,
                        canvasWidth,
                        canvasHeight,
                        normalizedRotation
                    );

                    this.drawAnnotationBoxOnCanvas(
                        context,
                        point.x,
                        point.y,
                        annotation.text,
                        this.formatAnnotationMeta(annotation),
                        annotation.color || '#f97316',
                        canvasWidth,
                        canvasHeight
                    );
                });
            },
            drawAnnotationBoxOnCanvas(context, x, y, text, meta, color, width, height) {
                const padding = 10;
                const lineHeight = 16;
                const metaLineHeight = 14;
                const maxTextWidth = 280;
                context.save();
                context.font = 'bold 13px sans-serif';
                const textLines = this.wrapCanvasText(context, String(text || ''), maxTextWidth);
                context.font = '12px sans-serif';
                const metaLines = this.wrapCanvasText(context, String(meta || ''), maxTextWidth);
                const widestText = Math.max(
                    ...textLines.map((line) => this.measureCanvasLine(context, line, 'bold 13px sans-serif')),
                    ...metaLines.map((line) => this.measureCanvasLine(context, line, '12px sans-serif')),
                    120
                );
                const boxWidth = Math.min(320, widestText + (padding * 2));
                const boxHeight = (textLines.length * lineHeight) + (metaLines.length * metaLineHeight) + (padding * 2);
                const drawX = Math.max(12, Math.min(width - boxWidth - 12, x));
                const drawY = Math.max(boxHeight + 12, Math.min(height - 12, y));

                context.fillStyle = 'rgba(255, 255, 255, 0.94)';
                context.strokeStyle = color;
                context.lineWidth = 2;
                context.beginPath();
                context.roundRect(drawX, drawY - boxHeight, boxWidth, boxHeight, 12);
                context.fill();
                context.stroke();
                context.fillStyle = color;
                context.font = 'bold 13px sans-serif';
                textLines.forEach((line, index) => {
                    context.fillText(line, drawX + padding, drawY - boxHeight + padding + 12 + (index * lineHeight), boxWidth - (padding * 2));
                });

                context.fillStyle = '#475569';
                context.font = '12px sans-serif';
                metaLines.forEach((line, index) => {
                    context.fillText(
                        line,
                        drawX + padding,
                        drawY - boxHeight + padding + 12 + (textLines.length * lineHeight) + (index * metaLineHeight),
                        boxWidth - (padding * 2)
                    );
                });

                context.restore();
            },
            mapAnnotationPointForRotation(x, y, width, height, rotation) {
                if (rotation === 90) {
                    return { x: width - y, y: x };
                }

                if (rotation === 180) {
                    return { x: width - x, y: height - y };
                }

                if (rotation === 270) {
                    return { x: y, y: height - x };
                }

                return { x, y };
            },
            loadScriptOnce(key, src) {
                window.__attachmentPreviewScripts = window.__attachmentPreviewScripts || {};

                if (window.__attachmentPreviewScripts[key]) {
                    return window.__attachmentPreviewScripts[key];
                }

                window.__attachmentPreviewScripts[key] = new Promise((resolve, reject) => {
                    const existing = document.querySelector(`script[data-attachment-preview="${key}"]`);

                    if (existing) {
                        if (existing.dataset.loaded === 'true') {
                            resolve();
                            return;
                        }

                        existing.addEventListener('load', () => resolve(), { once: true });
                        existing.addEventListener('error', () => reject(new Error('Failed to load preview library.')), { once: true });
                        return;
                    }

                    const script = document.createElement('script');
                    script.src = src;
                    script.async = true;
                    script.dataset.attachmentPreview = key;
                    script.onload = () => {
                        script.dataset.loaded = 'true';
                        resolve();
                    };
                    script.onerror = () => reject(new Error('Failed to load preview library.'));
                    document.head.appendChild(script);
                });

                return window.__attachmentPreviewScripts[key];
            },
            loadImage(url) {
                return new Promise((resolve, reject) => {
                    const image = new Image();

                    image.onload = () => resolve(image);
                    image.onerror = () => reject(new Error('Unable to load this image.'));
                    image.crossOrigin = 'anonymous';
                    image.src = url;
                });
            },
            async fetchFileBytes(url, errorMessage = 'Unable to load this file.') {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error(errorMessage);
                }

                return new Uint8Array(await response.arrayBuffer());
            },
            canvasToBlob(canvas, mimeType, quality = 1) {
                return new Promise((resolve, reject) => {
                    canvas.toBlob((blob) => {
                        if (!blob) {
                            reject(new Error('Unable to export the preview.'));
                            return;
                        }

                        resolve(blob);
                    }, mimeType, quality);
                });
            },
            triggerBlobDownload(blob, downloadName) {
                const link = document.createElement('a');
                const objectUrl = URL.createObjectURL(blob);

                link.href = objectUrl;
                link.download = downloadName;
                link.click();

                setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
            },
            openBlobInNewTab(blob) {
                const objectUrl = URL.createObjectURL(blob);
                const opened = window.open(objectUrl, '_blank', 'noopener');

                if (!opened) {
                    setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
                    return;
                }

                setTimeout(() => URL.revokeObjectURL(objectUrl), 60000);
            },
            buildDownloadName(name, extension, suffix = '_rotated') {
                const safeExtension = extension || 'png';
                const baseName = String(name || 'image').replace(/\.[^.]+$/, '');

                return `${baseName}${suffix}.${safeExtension}`;
            },
            async downloadFileFallback(file) {
                try {
                    const response = await fetch(file.url);

                    if (!response.ok) {
                        throw new Error('Failed to fetch file');
                    }

                    const blob = await response.blob();
                    const extension = String(file.ext || 'xlsx').toLowerCase();
                    this.triggerBlobDownload(blob, this.buildDownloadName(file.name, extension, ''));
                } catch (error) {
                    console.error('Download failed:', error);
                    window.open(file.url, '_blank', 'noopener');
                }
            },
            async openFileFallback(file) {
                try {
                    const response = await fetch(file.url);

                    if (!response.ok) {
                        throw new Error('Failed to fetch file');
                    }

                    const blob = await response.blob();
                    this.openBlobInNewTab(blob);
                } catch (error) {
                    console.error('Open failed:', error);
                    window.open(file.url, '_blank', 'noopener');
                }
            },
            pdfPreviewUrl(file) {
                const url = String(file?.previewUrl || file?.url || '').trim();

                if (!url) {
                    return '';
                }

                const separator = url.includes('?') ? '&' : '?';

                return `${url}${separator}inline=1#toolbar=1&navpanes=0&view=FitH`;
            },
            hexToPdfLibColor(value) {
                const hex = String(value || '#f97316').replace('#', '').padEnd(6, '0').slice(0, 6);
                const red = parseInt(hex.slice(0, 2), 16) / 255;
                const green = parseInt(hex.slice(2, 4), 16) / 255;
                const blue = parseInt(hex.slice(4, 6), 16) / 255;

                return window.PDFLib.rgb(red, green, blue);
            },
            pdfCanvasId(pageNumber) {
                return `${this.componentId}-pdf-${this.activeIndex}-${pageNumber}`;
            },
            createAnnotationId() {
                return `annotation-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
            },
            currentTimestamp() {
                return new Date().toLocaleString('en-US', {
                    year: 'numeric',
                    month: 'numeric',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true,
                });
            },
            wrapPdfText(text, font, fontSize, maxWidth) {
                return this.wrapText(String(text || ''), (value) => font.widthOfTextAtSize(value, fontSize), maxWidth);
            },
            wrapCanvasText(context, text, maxWidth) {
                return this.wrapText(String(text || ''), (value) => context.measureText(value).width, maxWidth);
            },
            wrapText(text, measure, maxWidth) {
                const normalized = String(text || '').trim();

                if (!normalized) {
                    return [];
                }

                const paragraphs = normalized.split(/\r?\n/);
                const lines = [];

                paragraphs.forEach((paragraph) => {
                    const words = paragraph.split(/\s+/).filter(Boolean);

                    if (!words.length) {
                        lines.push('');
                        return;
                    }

                    let currentLine = words.shift() || '';

                    words.forEach((word) => {
                        const candidate = `${currentLine} ${word}`.trim();

                        if (measure(candidate) <= maxWidth) {
                            currentLine = candidate;
                            return;
                        }

                        if (measure(word) > maxWidth) {
                            lines.push(currentLine);
                            lines.push(...this.breakLongToken(word, measure, maxWidth));
                            currentLine = '';
                            return;
                        }

                        lines.push(currentLine);
                        currentLine = word;
                    });

                    if (currentLine) {
                        lines.push(currentLine);
                    }
                });

                return lines.filter((line, index, array) => line !== '' || array.length === 1);
            },
            breakLongToken(token, measure, maxWidth) {
                const parts = [];
                let current = '';

                for (const character of String(token || '')) {
                    const candidate = `${current}${character}`;

                    if (current && measure(candidate) > maxWidth) {
                        parts.push(current);
                        current = character;
                        continue;
                    }

                    current = candidate;
                }

                if (current) {
                    parts.push(current);
                }

                return parts;
            },
            measureCanvasLine(context, text, font) {
                context.save();
                context.font = font;
                const width = context.measureText(String(text || '')).width;
                context.restore();

                return width;
            },
            clampPercent(value) {
                const number = Number(value);

                if (Number.isNaN(number)) {
                    return 50;
                }

                return Math.max(0, Math.min(100, +number.toFixed(2)));
            },
            escapeHtml(value) {
                return String(value)
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll(String.fromCharCode(34), '&quot;')
                    .replaceAll(String.fromCharCode(39), '&#039;');
            },
        };
    };
</script>
HTML
        );
    }
}
