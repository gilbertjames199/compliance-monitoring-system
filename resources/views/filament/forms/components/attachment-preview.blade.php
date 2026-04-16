@php
    $preview ??= ['uid' => 'attachment_preview_empty', 'count' => 0, 'files' => []];
    $files = $preview['files'] ?? [];
    $uid = $preview['uid'] ?? 'attachment_preview_empty';
    $count = $preview['count'] ?? count($files);
@endphp

@if (blank($files))
    <p style="font-size:0.875rem;color:#9ca3af;font-style:italic;">No files submitted.</p>
@else
    <div
        id="{{ $uid }}"
        class="{{ $uid }}"
        x-data="window.attachmentPreviewComponent()"
        x-init="init($refs.filesJson.textContent)"
    >
        <script type="application/json" x-ref="filesJson">@json($files)</script>

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
                        <span class="{{ $uid }}__thumb-name" title="{{ $file['name'] }}">{{ $file['name'] }}</span>
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
                        <a :href="activeFile() ? activeFile().url : '#'" target="_blank" rel="noopener noreferrer">Open</a>
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
                            <div class="{{ $uid }}__stage" :style="previewStyle()" @mousedown="startDrag($event)" @mousemove="drag($event)" @mouseup="stopDrag()" @mouseleave="stopDrag()">
                                <img class="{{ $uid }}__image" :src="activeFile().url" :alt="activeFile().name">
                            </div>
                        </div>
                    </template>

                    <template x-if="!loading && previewMode === 'pdf'">
                        <div class="{{ $uid }}__stage-wrap">
                            <div class="{{ $uid }}__stage {{ $uid }}__stage--document" :style="previewStyle()" @mousedown="startDrag($event)" @mousemove="drag($event)" @mouseup="stopDrag()" @mouseleave="stopDrag()">
                                <iframe class="{{ $uid }}__frame" :src="activeFile().url" :title="activeFile().name"></iframe>
                            </div>
                        </div>
                    </template>

                    <template x-if="!loading && previewMode === 'html'">
                        <div class="{{ $uid }}__stage-wrap">
                            <div class="{{ $uid }}__stage {{ $uid }}__stage--document" :style="previewStyle()" @mousedown="startDrag($event)" @mousemove="drag($event)" @mouseup="stopDrag()" @mouseleave="stopDrag()">
                                <div class="{{ $uid }}__html-preview" x-html="htmlPreview"></div>
                            </div>
                        </div>
                    </template>

                    <template x-if="!loading && previewMode === 'fallback'">
                        <div class="{{ $uid }}__fallback">
                            <div class="{{ $uid }}__fallback-type" x-text="String(activeFile()?.ext || 'file').toUpperCase()"></div>
                            <p x-text="error || 'Inline preview is available for images, PDFs, DOCX, and XLSX.'"></p>
                            <p x-text="'Use Open or Download to inspect ' + (activeFile()?.name || 'this file') + '.'"></p>
                        </div>
                    </template>
                </div>
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
        #{{ $uid }} .{{ $uid }}__file-name {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            word-break: break-word;
        }

        #{{ $uid }} .{{ $uid }}__count,
        #{{ $uid }} .{{ $uid }}__zoom-label {
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

        #{{ $uid }} .{{ $uid }}__thumb-name {
            display: block;
            margin-top: 8px;
            font-size: 12px;
            color: #334155;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #{{ $uid }} .{{ $uid }}__viewer {
            min-width: 0;
            display: grid;
            grid-template-rows: auto minmax(320px, 1fr);
        }

        #{{ $uid }} .{{ $uid }}__viewer-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 16px 18px;
            border-bottom: 1px solid #dbe2ea;
            background: rgba(255, 255, 255, 0.72);
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
            overflow: auto;
            padding: 20px;
            display: flex;
            align-items: center;
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
        }

        #{{ $uid }} .{{ $uid }}__stage--document {
            width: min(920px, 100%);
        }

        #{{ $uid }} .{{ $uid }}__frame,
        #{{ $uid }} .{{ $uid }}__image,
        #{{ $uid }} .{{ $uid }}__html-preview {
            border: 0;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.12);
        }

        #{{ $uid }} .{{ $uid }}__frame {
            width: 100%;
            min-height: 1080px;
        }

        #{{ $uid }} .{{ $uid }}__image {
            width: auto;
            max-width: min(100%, 960px);
            max-height: 70vh;
            object-fit: contain;
            padding: 16px;
        }

        #{{ $uid }} .{{ $uid }}__html-preview {
            width: 100%;
            min-height: 420px;
            padding: 28px;
            overflow: auto;
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

        #{{ $uid }} .{{ $uid }}__sheet-table table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        #{{ $uid }} .{{ $uid }}__sheet-table td,
        #{{ $uid }} .{{ $uid }}__sheet-table th {
            border: 1px solid #dbe2ea;
            padding: 8px 10px;
            vertical-align: top;
        }

        #{{ $uid }} .{{ $uid }}__sheet-table tr:nth-child(even) {
            background: #f8fafc;
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
            #{{ $uid }} .{{ $uid }}__viewer-bar {
                flex-direction: column;
                align-items: flex-start;
            }

            #{{ $uid }} .{{ $uid }}__actions {
                justify-content: flex-start;
            }

            #{{ $uid }} .{{ $uid }}__frame {
                min-height: 720px;
            }
        }
    </style>
@endif
