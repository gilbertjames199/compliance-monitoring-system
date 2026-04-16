<?php

namespace App\Providers;

use App\Models\ComplyingOffice;
use App\Models\RequiredDocument;
use App\Observers\ComplyingOfficeObserver;
use App\Observers\RequiredDocumentObserver;
use Filament\Support\Facades\FilamentView;
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

        FilamentView::registerRenderHook(
            'panels::body.end',
            fn (): string => <<<'HTML'
<script>
    window.attachmentPreviewComponent = window.attachmentPreviewComponent || function () {
        return {
            files: [],
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
            previewMode: 'fallback',
            init(filesJson) {
                try {
                    this.files = JSON.parse(filesJson || '[]');
                } catch (error) {
                    this.files = [];
                    this.error = 'Unable to read attachment data.';
                }

                this.loadActiveFile();
            },
            activeFile() {
                return this.files[this.activeIndex] ?? null;
            },
            async selectFile(index) {
                if (index < 0 || index >= this.files.length) {
                    return;
                }

                this.activeIndex = index;
                this.zoom = 1;
                this.rotation = 0;
                this.panX = 0;
                this.panY = 0;
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
            },
            rotateRight() {
                this.rotation += 90;
            },
            resetView() {
                this.zoom = 1;
                this.rotation = 0;
                this.panX = 0;
                this.panY = 0;
            },
            startDrag(event) {
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
            previewStyle() {
                return `transform: translate(${this.panX}px, ${this.panY}px) scale(${this.zoom}) rotate(${this.rotation}deg); transform-origin: center center; cursor: ${this.isDragging ? 'grabbing' : 'grab'};`;
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
            async loadActiveFile() {
                const file = this.activeFile();

                this.loading = false;
                this.error = null;
                this.htmlPreview = '';
                this.previewMode = 'fallback';

                if (!file) {
                    return;
                }

                if (this.isImage(file)) {
                    this.previewMode = 'image';
                    return;
                }

                if (this.isPdf(file)) {
                    this.previewMode = 'pdf';
                    return;
                }

                if (this.isDocx(file)) {
                    await this.renderDocx(file);
                    return;
                }

                if (this.isSpreadsheet(file)) {
                    await this.renderSpreadsheet(file);
                }
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
                    const workbook = window.XLSX.read(arrayBuffer, { type: 'array' });

                    this.htmlPreview = workbook.SheetNames.map((sheetName) => `
                        <section class="${file.uidClass || 'attachment-preview'}__sheet">
                            <h4>${this.escapeHtml(sheetName)}</h4>
                            <div class="${file.uidClass || 'attachment-preview'}__sheet-table">
                                ${window.XLSX.utils.sheet_to_html(workbook.Sheets[sheetName])}
                            </div>
                        </section>
                    `).join('');

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
            async downloadCurrent() {
                const file = this.activeFile();

                if (!file) {
                    return;
                }

                if (!this.isImage(file)) {
                    window.open(file.url, '_blank', 'noopener');
                    return;
                }

                const image = await this.loadImage(file.url);
                const rotation = this.normalizedRotation();
                const shouldSwapSides = rotation === 90 || rotation === 270;
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');

                if (!context) {
                    window.open(file.url, '_blank', 'noopener');
                    return;
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

                const extension = String(file.ext || 'png').toLowerCase();
                const mimeType = ({
                    jpg: 'image/jpeg',
                    jpeg: 'image/jpeg',
                    png: 'image/png',
                    webp: 'image/webp',
                })[extension] || 'image/png';
                const downloadName = this.buildDownloadName(file.name, extension);
                const link = document.createElement('a');

                link.href = canvas.toDataURL(mimeType, 1);
                link.download = downloadName;
                link.click();
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
                    image.src = url;
                });
            },
            buildDownloadName(name, extension) {
                const safeExtension = extension || 'png';
                const baseName = String(name || 'image').replace(/\.[^.]+$/, '');

                return `${baseName}_rotated.${safeExtension}`;
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
