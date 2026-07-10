<div wire:ignore>
    @php
        $preview ??= ['uid' => 'attachment_preview_empty', 'count' => 0, 'files' => [], 'threads' => [], 'drafts' => [], 'annotations' => [], 'viewStates' => [], 'viewerType' => null];
        $files = $preview['files'] ?? [];
        $threads = $preview['threads'] ?? [];
        $drafts = $preview['drafts'] ?? [];
        $annotations = $preview['annotations'] ?? [];
        $viewStates = $preview['viewStates'] ?? [];
        $viewerType = $preview['viewerType'] ?? null;
        $uid = $preview['uid'] ?? 'attachment_preview_empty';
        $stateKey = $preview['stateKey'] ?? ('attachment_preview_state_' . $uid);
        $count = $preview['count'] ?? count($files);
        $editable ??= false;
        $annotationEditable ??= false;
        $draftsStatePath ??= null;
        $annotationsStatePath ??= null;
        $viewStatesStatePath ??= null;
        $annotationAuthorName ??= null;
        $annotationAuthorLabel ??= 'Annotation';
        $annotationAuthorType ??= null;
        $draftLabel ??= 'Reply';
        $draftPlaceholder ??= 'Write your reply for this file. It will be added as a new message.';
    @endphp

    @if (blank($files))
        <p style="font-size:0.875rem;color:#9ca3af;font-style:italic;">No files submitted.</p>
    @else
        <div
            id="{{ $uid }}"
            class="{{ $uid }}"
            x-data="window.attachmentPreviewComponent({
                componentId: @js($uid),
                stateKey: @js($stateKey),
                editable: @js($editable),
                annotationEditable: @js($annotationEditable),
                draftsStatePath: @js($draftsStatePath),
                annotationsStatePath: @js($annotationsStatePath),
                viewStatesStatePath: @js($viewStatesStatePath),
                viewerType: @js($viewerType),
                annotationAuthorName: @js($annotationAuthorName),
                annotationAuthorLabel: @js($annotationAuthorLabel),
                annotationAuthorType: @js($annotationAuthorType),
            })"
            x-init="init($refs.filesJson.textContent, $refs.threadsJson.textContent, $refs.draftsJson.textContent, $refs.annotationsJson.textContent, $refs.viewStatesJson.textContent)"
            @mousemove.window="dragAnnotation($event)"
            @mouseup.window="stopAnnotationDrag()"
            @mouseleave.window="stopAnnotationDrag()"
        >
            <script type="application/json" x-ref="filesJson">@json($files)</script>
            <script type="application/json" x-ref="threadsJson">@json($threads)</script>
            <script type="application/json" x-ref="draftsJson">@json($drafts)</script>
            <script type="application/json" x-ref="annotationsJson">@json($annotations)</script>
            <script type="application/json" x-ref="viewStatesJson">@json($viewStates)</script>

            <div class="{{ $uid }}__header">
                <div>
                    <p class="{{ $uid }}__eyebrow">Attachments</p>
                    <h4 class="{{ $uid }}__title">Preview submitted files</h4>
                </div>
                <span class="{{ $uid }}__count">{{ $count }} file(s)</span>
            </div>

            <div class="{{ $uid }}__layout">
                <div class="{{ $uid }}__sidebar">
                    @foreach ($files as $index => $file)
                        <button
                            type="button"
                            class="{{ $uid }}__thumb"
                            @click="selectFile({{ $index }})"
                            :class="{ 'is-active': activeIndex === {{ $index }} }"
                        >
                            <div class="{{ $uid }}__thumb-preview">
                                @if (!empty($file['isImage']))
                                    <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" loading="lazy">
                                @else
                                    <div class="{{ $uid }}__thumb-fallback">{{ strtoupper($file['ext'] ?? 'FILE') }}</div>
                                @endif
                            </div>

                            <div class="{{ $uid }}__thumb-meta">
                                <span class="{{ $uid }}__thumb-name" title="{{ $file['name'] }}">{{ $file['name'] }}</span>

                                <template x-if="hasThreadForIndex({{ $index }})">
                                    <span class="{{ $uid }}__thumb-badge">Has replies</span>
                                </template>
                            </div>
                        </button>
                    @endforeach
                </div>

                <div class="{{ $uid }}__viewer">
                    <div class="{{ $uid }}__viewer-bar">
                        <div>
                            <p class="{{ $uid }}__file-label">Current file</p>
                            <p class="{{ $uid }}__file-name" x-text="activeFile() ? activeFile().name : 'No file selected'"></p>
                        </div>

                        <div class="{{ $uid }}__actions">
                            <button type="button" class="{{ $uid }}__action-btn" @click="zoomOut()">-</button>
                            <span class="{{ $uid }}__zoom-label" x-text="Math.round(zoom * 100) + '%'"></span>
                            <button type="button" class="{{ $uid }}__action-btn" @click="zoomIn()">+</button>
                            <button type="button" class="{{ $uid }}__action-btn" @click="rotateLeft()">Rotate Left</button>
                            <button type="button" class="{{ $uid }}__action-btn" @click="rotateRight()">Rotate Right</button>
                            <button type="button" class="{{ $uid }}__action-btn" @click="resetView()">Reset</button>
                            <button type="button" class="{{ $uid }}__action-btn" @click="openCurrent()">Open</button>
                            <button type="button" class="{{ $uid }}__action-btn" @click="downloadCurrent()">Download</button>
                        </div>
                    </div>

                    <div class="{{ $uid }}__preview">
                        <template x-if="loading">
                            <div class="{{ $uid }}__status-card">
                                <p>Loading preview...</p>
                            </div>
                        </template>

                        <template x-if="!loading && previewMode === 'image'">
                            <div class="{{ $uid }}__stage-wrap">
                                <div class="{{ $uid }}__stage" :style="stageStyle()" @mousedown="startDrag($event)" @mousemove="drag($event)" @mouseup="stopDrag()" @mouseleave="stopDrag()">
                                    <div class="{{ $uid }}__page-shell {{ $uid }}__page-shell--image">
                                        <div class="{{ $uid }}__transform-surface" :style="contentTransformStyle()">
                                            <img class="{{ $uid }}__image" :src="activeFile().url" :alt="activeFile().name">
                                            <div
                                                class="{{ $uid }}__annotation-layer"
                                                :class="{ 'is-placing': isPlacingAnnotation() }"
                                                @click="placeAnnotation($event, 1)"
                                            >
                                                <template x-for="annotation in annotationsForPage(1)" :key="annotation.id">
                                                    <div
                                                        class="{{ $uid }}__annotation-marker"
                                                        :class="{ 'is-own': isOwnAnnotation(annotation), 'is-active': isAnnotationActive(annotation.id) }"
                                                        :style="annotationStyle(annotation)"
                                                        @mousedown.stop="startAnnotationDrag(annotation.id, 1, $event)"
                                                        @click.stop="toggleAnnotationActive(annotation.id)"
                                                        @mouseenter="hoverAnnotation(annotation.id)"
                                                        @mouseleave="unhoverAnnotation(annotation.id)"
                                                    >
                                                        <span class="{{ $uid }}__annotation-dot" :style="'--annotation-accent:' + (annotation.color || '#f97316')"></span>

                                                        <div
                                                            class="{{ $uid }}__annotation-popover"
                                                            x-show="isAnnotationActive(annotation.id)"
                                                            x-cloak
                                                            :class="{ 'is-own': isOwnAnnotation(annotation), 'flip-left': shouldFlipAnnotation(annotation) }"
                                                            @mousedown.stop
                                                        >
                                                            <button
                                                                type="button"
                                                                class="{{ $uid }}__annotation-delete"
                                                                x-show="canDeleteAnnotation(annotation)"
                                                                @click.stop="removeAnnotation(annotation.id)"
                                                            >
                                                                x
                                                            </button>
                                                            <p class="{{ $uid }}__annotation-text" x-text="annotation.text"></p>
                                                            <p class="{{ $uid }}__annotation-meta" x-text="formatAnnotationMeta(annotation)"></p>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template x-if="!loading && previewMode === 'pdf'">
                            <div class="{{ $uid }}__stage-wrap">
                                <div class="{{ $uid }}__stage {{ $uid }}__stage--document {{ $uid }}__pdf-stack" :style="stageStyle()" @mousedown="startDrag($event)" @mousemove="drag($event)" @mouseup="stopDrag()" @mouseleave="stopDrag()">
                                    <template x-for="page in pdfPages" :key="page.pageNumber">
                                        <div class="{{ $uid }}__page-shell" :style="'width:' + page.width + 'px;height:' + page.height + 'px'">
                                            <canvas
                                                class="{{ $uid }}__pdf-canvas"
                                                :id="pdfCanvasId(page.pageNumber)"
                                                :width="page.width"
                                                :height="page.height"
                                            ></canvas>

                                            <div
                                                class="{{ $uid }}__annotation-layer"
                                                :style="'width:' + page.width + 'px;height:' + page.height + 'px'"
                                                :class="{ 'is-placing': isPlacingAnnotation() }"
                                                @click="placeAnnotation($event, page.pageNumber)"
                                            >
                                                <template x-for="annotation in annotationsForPage(page.pageNumber)" :key="annotation.id">
                                                <div
                                                    class="{{ $uid }}__annotation-marker"
                                                    :class="{ 'is-own': isOwnAnnotation(annotation), 'is-active': isAnnotationActive(annotation.id) }"
                                                    :style="annotationStyle(annotation, page.pageNumber)"
                                                    @mousedown.stop="startAnnotationDrag(annotation.id, page.pageNumber, $event)"
                                                    @click.stop="toggleAnnotationActive(annotation.id)"
                                                    @mouseenter="hoverAnnotation(annotation.id)"
                                                    @mouseleave="unhoverAnnotation(annotation.id)"
                                                >
                                                        <span class="{{ $uid }}__annotation-dot" :style="'--annotation-accent:' + (annotation.color || '#f97316')"></span>

                                                        <div
                                                            class="{{ $uid }}__annotation-popover"
                                                            x-show="isAnnotationActive(annotation.id)"
                                                            x-cloak
                                                            :class="{ 'is-own': isOwnAnnotation(annotation), 'flip-left': shouldFlipAnnotation(annotation) }"
                                                            @mousedown.stop
                                                        >
                                                            <button
                                                                type="button"
                                                                class="{{ $uid }}__annotation-delete"
                                                                x-show="canDeleteAnnotation(annotation)"
                                                                @click.stop="removeAnnotation(annotation.id)"
                                                            >
                                                                x
                                                            </button>
                                                            <p class="{{ $uid }}__annotation-text" x-text="annotation.text"></p>
                                                            <p class="{{ $uid }}__annotation-meta" x-text="formatAnnotationMeta(annotation)"></p>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <template x-if="!loading && previewMode === 'pdf-native' && !shouldUseInteractivePdfOnly()">
                            <div class="{{ $uid }}__stage-wrap">
                                <div class="{{ $uid }}__stage {{ $uid }}__stage--document" :style="stageStyle()" @mousedown="startDrag($event)" @mousemove="drag($event)" @mouseup="stopDrag()" @mouseleave="stopDrag()">
                                    <div class="{{ $uid }}__page-shell {{ $uid }}__page-shell--pdf-native">
                                        <iframe
                                            class="{{ $uid }}__frame"
                                            :src="pdfPreviewUrl(activeFile())"
                                            :title="activeFile() ? activeFile().name : 'PDF preview'"
                                        ></iframe>

                                        <div
                                            class="{{ $uid }}__annotation-layer"
                                            :class="{ 'is-placing': isPlacingAnnotation() }"
                                            @click="placeAnnotation($event, 1)"
                                        >
                                            <template x-for="annotation in annotationsForPage(1)" :key="annotation.id">
                                                <div
                                                    class="{{ $uid }}__annotation-marker"
                                                    :class="{ 'is-own': isOwnAnnotation(annotation), 'is-active': isAnnotationActive(annotation.id) }"
                                                    :style="annotationStyle(annotation)"
                                                    @mousedown.stop="startAnnotationDrag(annotation.id, 1, $event)"
                                                    @click.stop="toggleAnnotationActive(annotation.id)"
                                                    @mouseenter="hoverAnnotation(annotation.id)"
                                                    @mouseleave="unhoverAnnotation(annotation.id)"
                                                >
                                                    <span class="{{ $uid }}__annotation-dot" :style="'--annotation-accent:' + (annotation.color || '#f97316')"></span>

                                                    <div
                                                        class="{{ $uid }}__annotation-popover"
                                                        x-show="isAnnotationActive(annotation.id)"
                                                        x-cloak
                                                        :class="{ 'is-own': isOwnAnnotation(annotation), 'flip-left': shouldFlipAnnotation(annotation) }"
                                                        @mousedown.stop
                                                    >
                                                        <button
                                                            type="button"
                                                            class="{{ $uid }}__annotation-delete"
                                                            x-show="canDeleteAnnotation(annotation)"
                                                            @click.stop="removeAnnotation(annotation.id)"
                                                        >
                                                            x
                                                        </button>
                                                        <p class="{{ $uid }}__annotation-text" x-text="annotation.text"></p>
                                                        <p class="{{ $uid }}__annotation-meta" x-text="formatAnnotationMeta(annotation)"></p>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template x-if="!loading && previewMode === 'html'">
                            <div class="{{ $uid }}__stage-wrap">
                                <div class="{{ $uid }}__stage {{ $uid }}__stage--document" :style="stageStyle()" @mousedown="startDrag($event)" @mousemove="drag($event)" @mouseup="stopDrag()" @mouseleave="stopDrag()">
                                    <div class="{{ $uid }}__page-shell {{ $uid }}__page-shell--html" :class="{ '{{ $uid }}__page-shell--spreadsheet': isWorkbookFile(activeFile()) }">
                                        <div class="{{ $uid }}__html-scroll" :class="{ 'is-spreadsheet': isWorkbookFile(activeFile()) }">
                                            <div class="{{ $uid }}__transform-surface" :style="contentTransformStyle()">
                                                <div class="{{ $uid }}__html-surface">
                                                    <div class="{{ $uid }}__html-preview" x-html="htmlPreview"></div>
                                                    <div
                                                        class="{{ $uid }}__annotation-layer"
                                                        :class="{ 'is-placing': isPlacingAnnotation() }"
                                                        @click="placeAnnotation($event, 1)"
                                                    >
                                                        <template x-for="annotation in annotationsForPage(1)" :key="annotation.id">
                                                            <div
                                                                class="{{ $uid }}__annotation-marker"
                                                                :class="{ 'is-own': isOwnAnnotation(annotation), 'is-active': isAnnotationActive(annotation.id) }"
                                                                :style="annotationStyle(annotation)"
                                                                @mousedown.stop="startAnnotationDrag(annotation.id, 1, $event)"
                                                                @click.stop="toggleAnnotationActive(annotation.id)"
                                                                @mouseenter="hoverAnnotation(annotation.id)"
                                                                @mouseleave="unhoverAnnotation(annotation.id)"
                                                            >
                                                                <span class="{{ $uid }}__annotation-dot" :style="'--annotation-accent:' + (annotation.color || '#f97316')"></span>

                                                                <div
                                                                    class="{{ $uid }}__annotation-popover"
                                                                    x-show="isAnnotationActive(annotation.id)"
                                                                    x-cloak
                                                                    :class="{ 'is-own': isOwnAnnotation(annotation), 'flip-left': shouldFlipAnnotation(annotation) }"
                                                                    @mousedown.stop
                                                                >
                                                                    <button
                                                                        type="button"
                                                                        class="{{ $uid }}__annotation-delete"
                                                                        x-show="canDeleteAnnotation(annotation)"
                                                                        @click.stop="removeAnnotation(annotation.id)"
                                                                    >
                                                                        x
                                                                    </button>
                                                                    <p class="{{ $uid }}__annotation-text" x-text="annotation.text"></p>
                                                                    <p class="{{ $uid }}__annotation-meta" x-text="formatAnnotationMeta(annotation)"></p>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template x-if="!loading && previewMode === 'fallback'">
                            <div class="{{ $uid }}__fallback">
                                <div class="{{ $uid }}__fallback-type" x-text="String(activeFile()?.ext || 'file').toUpperCase()"></div>
                                <p x-text="error || 'Inline preview is available for images, PDFs, DOCX, XLS, XLSX, and CSV.'"></p>
                                <p x-text="'Use Open or Download to inspect ' + (activeFile()?.name || 'this file') + '.'"></p>
                            </div>
                        </template>
                    </div>

                    <template x-if="supportsAnnotations()">
                        <div class="{{ $uid }}__annotation-card">
                            <div class="{{ $uid }}__remark-header">
                                <div>
                                    <p class="{{ $uid }}__file-label">On-file annotations</p>
                                    <p class="{{ $uid }}__remark-title" x-text="annotationSummary()"></p>
                                </div>
                                <span class="{{ $uid }}__remark-status" x-text="annotationStatusText()"></span>
                            </div>

                            <template x-if="annotationEditable">
                                <div class="{{ $uid }}__annotation-composer">
                                    <label class="{{ $uid }}__remark-field">
                                        <span class="{{ $uid }}__remark-label">Annotation text</span>
                                        <textarea
                                            rows="3"
                                            class="{{ $uid }}__remark-input"
                                            placeholder="Type the remark you want to place on the file, then click Place Remark and click on the page."
                                            x-model="annotationDraft"
                                        ></textarea>
                                    </label>

                                    <div class="{{ $uid }}__annotation-controls">
                                        <label class="{{ $uid }}__annotation-color-field">
                                            <span class="{{ $uid }}__remark-label">Color</span>
                                            <input type="color" class="{{ $uid }}__annotation-color" x-model="annotationColor">
                                        </label>

                                        <button
                                            type="button"
                                            class="{{ $uid }}__action-btn"
                                            :class="{ 'is-active': annotationMode }"
                                            @click="toggleAnnotationMode()"
                                        >
                                            <span x-text="annotationMode ? 'Click on file to place' : 'Place remark'"></span>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- <div class="{{ $uid }}__remark-card">
                        <div class="{{ $uid }}__remark-header">
                            <div>
                                <p class="{{ $uid }}__file-label">File conversation</p>
                                <p class="{{ $uid }}__remark-title" x-text="activeFile() ? activeFile().name : 'No file selected'"></p>
                            </div>
                            <span class="{{ $uid }}__remark-status" x-text="currentThread().length ? (currentThread().length + ' message(s)') : 'No messages yet'"></span>
                        </div>

                        <div class="{{ $uid }}__remark-thread">
                            <template x-if="currentThread().length">
                                <div class="{{ $uid }}__remark-list">
                                    <template x-for="(entry, entryIndex) in currentThread()" :key="entryIndex">
                                        <article
                                            class="{{ $uid }}__remark-row"
                                            :class="{ 'is-own': isOwnEntry(entry), 'is-other': !isOwnEntry(entry) }"
                                        >
                                            <div
                                                class="{{ $uid }}__remark-entry"
                                                :class="{ 'is-own': isOwnEntry(entry), 'is-other': !isOwnEntry(entry) }"
                                            >
                                            <p class="{{ $uid }}__remark-entry-meta" x-text="formatEntryMeta(entry) || 'Comment'"></p>
                                            <p class="{{ $uid }}__remark-entry-body" x-text="entry.message || ''"></p>
                                            </div>
                                        </article>
                                    </template>
                                </div>
                            </template>

                            <template x-if="!currentThread().length">
                                <p class="{{ $uid }}__remark-empty">No comment has been added for this file yet.</p>
                            </template>
                        </div>

                        <template x-if="editable">
                            <label class="{{ $uid }}__remark-field">
                                <span class="{{ $uid }}__remark-label">{{ $draftLabel }}</span>
                                <div class="{{ $uid }}__remark-composer">
                                    <textarea
                                        rows="4"
                                        class="{{ $uid }}__remark-input"
                                        placeholder="{{ $draftPlaceholder }}"
                                        :value="currentDraft()"
                                        @input="updateCurrentDraft($event.target.value)"
                                    ></textarea>
                                    <button
                                        type="submit"
                                        class="{{ $uid }}__send-btn"
                                        :disabled="!currentDraft().trim()"
                                        aria-label="Send reply"
                                    >
                                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="22" y1="2" x2="11" y2="13"></line>
                                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                        </svg>
                                    </button>
                                </div>
                            </label>
                        </template>
                    </div> --}}
                </div>
            </div>
        </div>

        <style>
            #{{ $uid }} {
                border: 1px solid #dbe2ea;
                border-radius: 16px;
                background: linear-gradient(180deg, #f8fbff 0%, #eef4fb 100%);
                overflow: hidden;
            }

            #{{ $uid }} .{{ $uid }}__header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 12px;
                padding: 16px 18px;
                border-bottom: 1px solid #dbe2ea;
                background: rgba(255, 255, 255, 0.88);
            }

            #{{ $uid }} .{{ $uid }}__eyebrow,
            #{{ $uid }} .{{ $uid }}__file-label {
                margin: 0 0 4px;
                font-size: 11px;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: #64748b;
                font-weight: 700;
            }

            #{{ $uid }} .{{ $uid }}__title,
            #{{ $uid }} .{{ $uid }}__file-name,
            #{{ $uid }} .{{ $uid }}__remark-title {
                margin: 0;
                font-size: 15px;
                font-weight: 700;
                color: #0f172a;
                word-break: break-word;
            }

            #{{ $uid }} .{{ $uid }}__count,
            #{{ $uid }} .{{ $uid }}__zoom-label,
            #{{ $uid }} .{{ $uid }}__remark-status,
            #{{ $uid }} .{{ $uid }}__thumb-badge {
                display: inline-flex;
                align-items: center;
                padding: 6px 10px;
                border-radius: 999px;
                background: #dbeafe;
                color: #1d4ed8;
                font-size: 12px;
                font-weight: 600;
                white-space: nowrap;
            }

            #{{ $uid }} .{{ $uid }}__thumb-badge {
                padding: 4px 8px;
                font-size: 11px;
                margin-top: 6px;
            }

            #{{ $uid }} .{{ $uid }}__layout {
                display: grid;
                grid-template-columns: minmax(180px, 220px) minmax(0, 1fr);
                min-height: 520px;
            }

            #{{ $uid }} .{{ $uid }}__sidebar {
                padding: 14px;
                border-right: 1px solid #dbe2ea;
                background: rgba(255, 255, 255, 0.82);
                display: flex;
                flex-direction: column;
                gap: 10px;
                max-height: 700px;
                overflow-y: auto;
            }

            #{{ $uid }} .{{ $uid }}__thumb {
                width: 100%;
                border: 1px solid #dbe2ea;
                border-radius: 12px;
                padding: 10px;
                background: #ffffff;
                text-align: left;
                cursor: pointer;
                transition: border-color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
            }

            #{{ $uid }} .{{ $uid }}__thumb:hover,
            #{{ $uid }} .{{ $uid }}__thumb.is-active {
                border-color: #60a5fa;
                box-shadow: 0 10px 24px rgba(37, 99, 235, 0.12);
                transform: translateY(-1px);
            }

            #{{ $uid }} .{{ $uid }}__thumb-preview {
                height: 96px;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
                border-radius: 10px;
                background: #f8fafc;
            }

            #{{ $uid }} .{{ $uid }}__thumb-preview img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }

            #{{ $uid }} .{{ $uid }}__thumb-fallback {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 64px;
                min-height: 64px;
                border-radius: 12px;
                padding: 12px;
                background: #eff6ff;
                color: #1d4ed8;
                font-size: 14px;
                font-weight: 700;
                letter-spacing: 0.06em;
            }

            #{{ $uid }} .{{ $uid }}__thumb-meta {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
            }

            #{{ $uid }} .{{ $uid }}__thumb-name {
                display: block;
                margin-top: 8px;
                font-size: 12px;
                color: #334155;
                width: 100%;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            #{{ $uid }} .{{ $uid }}__viewer {
                min-width: 0;
                display: grid;
                grid-template-rows: auto minmax(320px, 1fr) auto;
            }

            #{{ $uid }} .{{ $uid }}__viewer-bar,
            #{{ $uid }} .{{ $uid }}__annotation-card,
            #{{ $uid }} .{{ $uid }}__remark-card {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 12px;
                padding: 16px 18px;
                background: rgba(255, 255, 255, 0.72);
            }

            #{{ $uid }} .{{ $uid }}__viewer-bar {
                border-bottom: 1px solid #dbe2ea;
            }

            #{{ $uid }} .{{ $uid }}__remark-card {
                border-top: 1px solid #dbe2ea;
                flex-direction: column;
            }

            #{{ $uid }} .{{ $uid }}__annotation-card {
                border-top: 1px solid #dbe2ea;
                flex-direction: column;
                background: rgba(248, 250, 252, 0.92);
            }

            #{{ $uid }} .{{ $uid }}__remark-header {
                width: 100%;
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 12px;
            }

            #{{ $uid }} .{{ $uid }}__actions {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
                align-items: center;
                justify-content: flex-end;
            }

            #{{ $uid }} .{{ $uid }}__actions a,
            #{{ $uid }} .{{ $uid }}__action-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 8px 12px;
                border-radius: 10px;
                border: 1px solid #bfdbfe;
                background: #eff6ff;
                color: #1d4ed8;
                text-decoration: none;
                font-size: 12px;
                font-weight: 700;
                cursor: pointer;
            }

            #{{ $uid }} .{{ $uid }}__preview {
                min-height: 360px;
                max-height: 760px;
                overflow: auto;
                padding: 20px;
                display: flex;
                align-items: flex-start;
                justify-content: center;
                background:
                    radial-gradient(circle at top right, rgba(191, 219, 254, 0.75), transparent 35%),
                    linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
            }

            #{{ $uid }} .{{ $uid }}__stage-wrap {
                min-width: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 8px 0 32px;
            }

            #{{ $uid }} .{{ $uid }}__stage {
                transition: transform 0.18s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                width: fit-content;
                margin: 0 auto;
            }

            #{{ $uid }} .{{ $uid }}__stage--document {
                width: 100%;
                max-width: 1200px;
            }

            #{{ $uid }} .{{ $uid }}__pdf-stack {
                flex-direction: column;
                gap: 24px;
            }

            #{{ $uid }} .{{ $uid }}__page-shell {
                position: relative;
                max-width: 100%;
                overflow: visible;
            }

            #{{ $uid }} .{{ $uid }}__page-shell--image {
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            #{{ $uid }} .{{ $uid }}__page-shell--html {
                width: fit-content;
                max-width: 100%;
            }

            #{{ $uid }} .{{ $uid }}__page-shell--spreadsheet {
                width: min(1120px, 100%);
            }

            #{{ $uid }} .{{ $uid }}__page-shell--pdf-native {
                width: 100%;
                max-width: 1200px;
                min-height: 980px;
            }

            #{{ $uid }} .{{ $uid }}__frame,
            #{{ $uid }} .{{ $uid }}__pdf-canvas,
            #{{ $uid }} .{{ $uid }}__image,
            #{{ $uid }} .{{ $uid }}__html-scroll,
            #{{ $uid }} .{{ $uid }}__html-surface,
            #{{ $uid }} .{{ $uid }}__html-preview {
                border: 0;
                border-radius: 14px;
                background: #ffffff;
                box-shadow: 0 18px 36px rgba(15, 23, 42, 0.12);
            }

            #{{ $uid }} .{{ $uid }}__frame {
                display: block;
                width: 100%;
                min-height: 78vh;
                height: 980px;
                position: relative;
                z-index: 1;
                pointer-events: auto;
            }

            #{{ $uid }} .{{ $uid }}__page-shell--pdf-native .{{ $uid }}__annotation-layer {
                z-index: 2;
            }

            #{{ $uid }} .{{ $uid }}__pdf-canvas {
                display: block;
                width: 100%;
                height: auto;
            }

            #{{ $uid }} .{{ $uid }}__image {
                display: block;
                width: auto;
                max-width: min(100%, 960px);
                max-height: 70vh;
                object-fit: contain;
                padding: 16px;
            }

            #{{ $uid }} .{{ $uid }}__transform-surface {
                position: relative;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                transition: transform 0.18s ease;
            }

            #{{ $uid }} .{{ $uid }}__annotation-layer {
                position: absolute;
                inset: 0;
                border-radius: 14px;
                pointer-events: none;
            }

            #{{ $uid }} .{{ $uid }}__annotation-layer.is-placing {
                cursor: crosshair;
                pointer-events: auto;
            }

           #{{ $uid }} .{{ $uid }}__annotation-marker {
                position: absolute;
                width: 18px;
                height: 18px;
                transform: translate(-50%, -50%);
                pointer-events: auto;
                cursor: pointer;
                z-index: 5;
            }

            #{{ $uid }} .{{ $uid }}__annotation-dot {
                display: block;
                width: 100%;
                height: 100%;
                border-radius: 50%;
                background: var(--annotation-accent, #f97316);
                border: 2px solid #ffffff;
                box-shadow: 0 2px 6px rgba(15, 23, 42, 0.35);
                transition: transform 0.12s ease;
            }

            #{{ $uid }} .{{ $uid }}__annotation-marker:hover .{{ $uid }}__annotation-dot,
            #{{ $uid }} .{{ $uid }}__annotation-marker.is-active .{{ $uid }}__annotation-dot {
                transform: scale(1.25);
            }

            #{{ $uid }} .{{ $uid }}__annotation-popover {
                position: absolute;
                top: 50%;
                left: calc(100% + 12px);
                transform: translateY(-50%);
                min-width: 200px;
                max-width: 260px;
                padding: 12px 14px;
                border-radius: 14px;
                background: color-mix(in srgb, var(--annotation-accent, #f97316) 12%, #ffffff);
                border: 1px solid var(--annotation-accent, #fdba74);
                color: #7c2d12;
                box-shadow: 0 16px 32px rgba(124, 45, 18, 0.18);
                z-index: 20;
                pointer-events: auto;
            }

            #{{ $uid }} .{{ $uid }}__annotation-popover.is-own {
                background: #eff6ff;
                border-color: #93c5fd;
                color: #1e3a8a;
            }

            #{{ $uid }} .{{ $uid }}__annotation-popover.flip-left {
                left: auto;
                right: calc(100% + 12px);
            }

            #{{ $uid }} .{{ $uid }}__annotation-popover::before {
                content: '';
                position: absolute;
                top: 50%;
                left: -6px;
                transform: translateY(-50%);
                width: 0;
                height: 0;
                border-top: 6px solid transparent;
                border-bottom: 6px solid transparent;
                border-right: 6px solid var(--annotation-accent, #fdba74);
            }

            #{{ $uid }} .{{ $uid }}__annotation-popover.flip-left::before {
                left: auto;
                right: -6px;
                border-right: none;
                border-left: 6px solid var(--annotation-accent, #fdba74);
            }

            #{{ $uid }} .{{ $uid }}__annotation-text,
            #{{ $uid }} .{{ $uid }}__annotation-meta {
                margin: 0;
                white-space: pre-wrap;
                overflow-wrap: anywhere;
                word-break: break-word;
            }

            #{{ $uid }} .{{ $uid }}__annotation-text {
                font-size: 13px;
                line-height: 1.5;
                font-weight: 600;
            }

            #{{ $uid }} .{{ $uid }}__annotation-meta {
                margin-top: 6px;
                font-size: 11px;
                opacity: 0.84;
            }

            #{{ $uid }} .{{ $uid }}__annotation-delete {
                position: absolute;
                top: 6px;
                right: 6px;
                width: 22px;
                height: 22px;
                border: 0;
                border-radius: 999px;
                background: rgba(15, 23, 42, 0.1);
                color: inherit;
                font-size: 11px;
                font-weight: 700;
                cursor: pointer;
            }

            #{{ $uid }} .{{ $uid }}__annotation-composer {
                width: 100%;
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                gap: 16px;
                align-items: end;
            }

            #{{ $uid }} .{{ $uid }}__annotation-controls {
                display: flex;
                align-items: end;
                gap: 12px;
                flex-wrap: wrap;
            }

            #{{ $uid }} .{{ $uid }}__annotation-color-field {
                display: flex;
                flex-direction: column;
                gap: 6px;
            }

            #{{ $uid }} .{{ $uid }}__annotation-color {
                width: 52px;
                height: 44px;
                padding: 4px;
                border: 1px solid #cbd5e1;
                border-radius: 12px;
                background: #ffffff;
                cursor: pointer;
            }

            #{{ $uid }} .{{ $uid }}__action-btn.is-active {
                background: #1d4ed8;
                border-color: #1d4ed8;
                color: #ffffff;
            }

            #{{ $uid }} .{{ $uid }}__html-scroll {
                width: fit-content;
                max-width: min(100%, 1100px);
                max-height: 70vh;
                overflow: auto;
            }

            #{{ $uid }} .{{ $uid }}__html-scroll.is-spreadsheet {
                width: min(100%, 1040px);
                height: min(72vh, 680px);
                min-width: 520px;
                min-height: 340px;
                max-width: 100%;
                max-height: 78vh;
                resize: both;
                overflow: auto;
                padding: 0;
                border: 1px solid #cbd5e1;
                border-radius: 14px;
                background:
                    linear-gradient(90deg, rgba(148, 163, 184, 0.08) 1px, transparent 1px) 0 0 / 32px 32px,
                    linear-gradient(rgba(148, 163, 184, 0.08) 1px, transparent 1px) 0 0 / 32px 32px,
                    #ffffff;
                box-shadow: 0 18px 36px rgba(15, 23, 42, 0.12);
            }

            #{{ $uid }} .{{ $uid }}__html-surface {
                position: relative;
                width: max-content;
                min-width: min(820px, 100%);
                min-height: 420px;
            }

            #{{ $uid }} .{{ $uid }}__html-scroll.is-spreadsheet .{{ $uid }}__html-surface {
                min-width: fit-content;
                min-height: fit-content;
            }

            #{{ $uid }} .{{ $uid }}__html-surface .{{ $uid }}__annotation-layer {
                z-index: 2;
            }

            #{{ $uid }} .{{ $uid }}__html-preview {
                width: max-content;
                min-width: 100%;
                min-height: 420px;
                padding: 20px;
                overflow: visible;
                background:
                    linear-gradient(90deg, rgba(148, 163, 184, 0.08) 1px, transparent 1px) 0 0 / 32px 32px,
                    linear-gradient(rgba(148, 163, 184, 0.08) 1px, transparent 1px) 0 0 / 32px 32px,
                    #ffffff;
            }

            #{{ $uid }} .{{ $uid }}__html-scroll.is-spreadsheet .{{ $uid }}__transform-surface {
                display: inline-block;
                min-width: fit-content;
            }

            #{{ $uid }} .{{ $uid }}__html-scroll.is-spreadsheet .{{ $uid }}__html-preview {
                min-width: fit-content;
                min-height: fit-content;
                padding: 16px;
                background: transparent;
                box-shadow: none;
                border-radius: 0;
            }

            #{{ $uid }} .{{ $uid }}__docx-body {
                color: #0f172a;
                line-height: 1.7;
                font-size: 14px;
            }

            #{{ $uid }} .{{ $uid }}__docx-body h1,
            #{{ $uid }} .{{ $uid }}__docx-body h2,
            #{{ $uid }} .{{ $uid }}__docx-body h3,
            #{{ $uid }} .{{ $uid }}__sheet h4 {
                margin: 0 0 12px;
                color: #0f172a;
            }

            #{{ $uid }} .{{ $uid }}__sheet + .{{ $uid }}__sheet {
                margin-top: 24px;
                border-top: 1px solid #e2e8f0;
                padding-top: 24px;
            }

            #{{ $uid }} .{{ $uid }}__sheet h4 {
                position: sticky;
                left: 0;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 12px;
                border: 1px solid #cbd5e1;
                border-radius: 10px;
                background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
                box-shadow: 0 8px 16px rgba(15, 23, 42, 0.06);
                z-index: 1;
            }

            #{{ $uid }} .{{ $uid }}__sheet-table {
                display: block;
                width: max-content;
                min-width: 100%;
                max-width: none;
                overflow: auto;
                border: 1px solid #cbd5e1;
                border-radius: 14px;
                background: #ffffff;
                box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
            }

            #{{ $uid }} .{{ $uid }}__sheet-table * {
                box-sizing: border-box;
            }

            #{{ $uid }} .{{ $uid }}__sheet-table table {
                width: max-content !important;
                min-width: max-content !important;
                border-collapse: collapse;
                table-layout: auto;
                font-size: 12px;
                margin: 0;
                background: #ffffff;
            }

            #{{ $uid }} .{{ $uid }}__sheet-table td,
            #{{ $uid }} .{{ $uid }}__sheet-table th {
                border: 1px solid #dbe2ea;
                padding: 7px 10px;
                vertical-align: top;
                white-space: pre-wrap;
                overflow-wrap: anywhere;
                word-break: break-word;
                min-width: 128px;
                max-width: 280px;
                line-height: 1.45;
                color: #0f172a;
            }

            #{{ $uid }} .{{ $uid }}__sheet-table thead th {
                position: sticky;
                top: 0;
                z-index: 3;
                background: linear-gradient(180deg, #e2e8f0 0%, #cbd5e1 100%);
                text-align: center;
                font-weight: 700;
                color: #334155;
            }

            #{{ $uid }} .{{ $uid }}__sheet-table .attachment-preview__sheet-index {
                position: sticky;
                left: 0;
                z-index: 2;
                min-width: 52px;
                max-width: 52px;
                text-align: center;
                background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
                color: #475569;
                font-weight: 700;
            }

            #{{ $uid }} .{{ $uid }}__sheet-table .attachment-preview__sheet-corner {
                left: 0;
                z-index: 4;
                min-width: 52px;
                max-width: 52px;
            }

            #{{ $uid }} .{{ $uid }}__sheet-table tbody tr:nth-child(even) td {
                background: #f8fafc;
            }

            #{{ $uid }} .{{ $uid }}__sheet-table tbody tr:hover td,
            #{{ $uid }} .{{ $uid }}__sheet-table tbody tr:hover .attachment-preview__sheet-index {
                background: #eff6ff;
            }

            #{{ $uid }} .{{ $uid }}__sheet-table .attachment-preview__truncated,
            #{{ $uid }} .{{ $uid }}__sheet-table .attachment-preview__empty {
                text-align: center;
                color: #64748b;
                font-style: italic;
                background: #f8fafc;
            }

            #{{ $uid }} .{{ $uid }}__limit-note {
                margin: 12px 0 0;
                color: #64748b;
                font-size: 12px;
            }

            #{{ $uid }} .{{ $uid }}__text-file {
                margin: 0;
                padding: 20px;
                font-size: 12px;
                line-height: 1.6;
                color: #0f172a;
                white-space: pre-wrap;
                background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
            }

            #{{ $uid }} .{{ $uid }}__fallback,
            #{{ $uid }} .{{ $uid }}__status-card {
                max-width: 460px;
                padding: 28px;
                border: 1px dashed #94a3b8;
                border-radius: 18px;
                text-align: center;
                background: rgba(255, 255, 255, 0.92);
                color: #334155;
            }

            #{{ $uid }} .{{ $uid }}__fallback-type {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 68px;
                height: 68px;
                margin-bottom: 14px;
                border-radius: 20px;
                background: #eff6ff;
                color: #1d4ed8;
                font-size: 18px;
                font-weight: 700;
                letter-spacing: 0.06em;
            }

            #{{ $uid }} .{{ $uid }}__fallback p,
            #{{ $uid }} .{{ $uid }}__status-card p {
                margin: 0 0 8px;
                font-size: 13px;
            }

            #{{ $uid }} .{{ $uid }}__remark-thread,
            #{{ $uid }} .{{ $uid }}__remark-field {
                width: 100%;
            }

            #{{ $uid }} .{{ $uid }}__remark-thread {
                max-height: 240px;
                overflow-y: auto;
                padding-right: 4px;
            }

            #{{ $uid }} .{{ $uid }}__remark-list {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            #{{ $uid }} .{{ $uid }}__remark-row {
                display: flex;
                width: 100%;
            }

            #{{ $uid }} .{{ $uid }}__remark-row.is-own {
                justify-content: flex-end;
            }

            #{{ $uid }} .{{ $uid }}__remark-row.is-other {
                justify-content: flex-start;
            }

            #{{ $uid }} .{{ $uid }}__remark-entry {
                padding: 12px 14px;
                border: 1px solid #dbe2ea;
                border-radius: 18px;
                background: #ffffff;
                width: min(100%, 540px);
                box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
            }

            #{{ $uid }} .{{ $uid }}__remark-entry.is-own {
                background: linear-gradient(180deg, #dbeafe 0%, #bfdbfe 100%);
                border-color: #93c5fd;
                border-bottom-right-radius: 6px;
            }

            #{{ $uid }} .{{ $uid }}__remark-entry.is-other {
                background: #ffffff;
                border-color: #dbe2ea;
                border-bottom-left-radius: 6px;
            }

            #{{ $uid }} .{{ $uid }}__remark-entry-meta,
            #{{ $uid }} .{{ $uid }}__remark-label {
                margin: 0 0 6px;
                font-size: 12px;
                font-weight: 700;
                color: #475569;
            }

            #{{ $uid }} .{{ $uid }}__remark-entry-body {
                margin: 0;
                font-size: 13px;
                line-height: 1.6;
                color: #0f172a;
                white-space: pre-wrap;
            }

            #{{ $uid }} .{{ $uid }}__remark-input {
                width: 100%;
                border: 1px solid #cbd5e1;
                border-radius: 12px;
                padding: 12px 14px;
                padding-right: 64px;
                background: #ffffff;
                color: #0f172a;
                font-size: 14px;
                resize: vertical;
                min-height: 110px;
            }

            #{{ $uid }} .{{ $uid }}__remark-input:focus {
                outline: 0;
                border-color: #60a5fa;
                box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.2);
            }

            #{{ $uid }} .{{ $uid }}__remark-composer {
                position: relative;
            }

            #{{ $uid }} .{{ $uid }}__send-btn {
                position: absolute;
                right: 14px;
                bottom: 14px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 42px;
                height: 42px;
                border-radius: 50%;
                border: 1px solid transparent;
                background: #2563eb;
                color: #ffffff;
                font-size: 0;
                cursor: pointer;
                transition: transform 0.15s ease, background-color 0.15s ease, border-color 0.15s ease;
            }

            #{{ $uid }} .{{ $uid }}__send-btn:hover:not(:disabled) {
                background: #1d4ed8;
                transform: translateY(-1px);
            }

            #{{ $uid }} .{{ $uid }}__send-btn:disabled {
                background: #93c5fd;
                border-color: transparent;
                opacity: 0.65;
                cursor: not-allowed;
            }

            #{{ $uid }} .{{ $uid }}__send-btn svg {
                width: 18px;
                height: 18px;
            }

            #{{ $uid }} .{{ $uid }}__remark-empty {
                color: #64748b;
                font-style: italic;
            }

            @media (max-width: 1100px) {
                #{{ $uid }} .{{ $uid }}__layout {
                    grid-template-columns: 1fr;
                }

                #{{ $uid }} .{{ $uid }}__sidebar {
                    border-right: 0;
                    border-bottom: 1px solid #dbe2ea;
                    max-height: 280px;
                }
            }

            @media (max-width: 960px) {
                #{{ $uid }} .{{ $uid }}__viewer-bar,
                #{{ $uid }} .{{ $uid }}__remark-header {
                    flex-direction: column;
                    align-items: flex-start;
                }

                #{{ $uid }} .{{ $uid }}__actions {
                    justify-content: flex-start;
                }

                #{{ $uid }} .{{ $uid }}__annotation-composer {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    @endif
</div>
