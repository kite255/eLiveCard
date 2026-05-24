<x-filament-panels::page>
    @php
        $imageUrl = $template->template_image
            ? asset('storage/' . $template->template_image)
            : null;

        $templateWidth = $template->width ?: 542;
        $templateHeight = $template->height ?: 768;

        /*
        |--------------------------------------------------------------------------
        | Actual Sample QR Code
        |--------------------------------------------------------------------------
        | This comes from CardTemplateDesigner.php:
        | public ?string $sampleQrCodeUrl = null;
        |
        | It uses a real invitee QR code from the same event.
        */
        $actualQrUrl = $sampleQrCodeUrl ?? null;
    @endphp

    <div
        x-data="simpleCardDesigner({
            placeholders: @entangle('placeholders').live,
            selectedKey: @entangle('selectedPlaceholder').live,
            zoom: @entangle('zoom').live,
            showPreview: @entangle('showPreview').live,
            templateWidth: {{ (int) $templateWidth }},
            templateHeight: {{ (int) $templateHeight }},
            sampleQrCodeUrl: @js($actualQrUrl),
        })"
        x-init="init()"
        class="elive-designer"
    >
        <div class="designer-header">
            <div>
                <div class="breadcrumb">
                    <span>Card Templates</span>
                    <span>/</span>
                    <strong>Designer</strong>
                </div>

                <h1>Card Template Designer</h1>
                <p>Drag placeholders or use direction buttons to position details exactly on the card.</p>
            </div>

            <div class="designer-actions">
                <button type="button" @click="syncToLivewire(); $wire.savePositions()" class="btn-primary">
                    Save Design
                </button>

                <button type="button" @click="syncToLivewire(); $wire.previewCard()" class="btn-outline cyan">
                    Preview
                </button>

                <button type="button" wire:click="resetPositions" class="btn-outline">
                    Reset
                </button>

                <a href="{{ \App\Filament\Resources\CardTemplateResource::getUrl('index') }}" class="btn-outline">
                    Back
                </a>
            </div>
        </div>

        <div class="template-summary">
            <div class="template-thumb">
                @if ($imageUrl)
                    <img src="{{ $imageUrl }}" alt="{{ $template->name }}">
                @else
                    <div class="empty-thumb">No Image</div>
                @endif
            </div>

            <div>
                <span>Template</span>
                <strong>{{ $template->name }}</strong>
            </div>

            <div>
                <span>Event</span>
                <strong>{{ $template->event?->title ?? 'No event selected' }}</strong>
            </div>

            <div>
                <span>Status</span>
                <strong class="status-badge">{{ ucfirst($template->status) }}</strong>
            </div>

            <div>
                <span>Size</span>
                <strong>{{ $templateWidth }} × {{ $templateHeight }} px</strong>
            </div>
        </div>

        <div class="designer-layout">
            <div class="canvas-panel">
                <div class="canvas-toolbar">
                    <button type="button" class="tool-btn" @click="zoom = 75">75%</button>
                    <button type="button" class="tool-btn" @click="zoom = 100">100%</button>
                    <button type="button" class="tool-btn" @click="zoom = 125">125%</button>

                    <div class="zoom-control">
                        <button type="button" @click="decreaseZoom">−</button>
                        <input type="range" min="40" max="160" step="5" x-model.number="zoom">
                        <button type="button" @click="increaseZoom">+</button>
                    </div>

                    <span class="zoom-label" x-text="zoom + '%'"></span>
                </div>

                <div class="workspace">
                    <div class="canvas-scroll">
                        <div
                            class="card-canvas"
                            tabindex="0"
                            :style="{
                                width: '{{ $templateWidth }}px',
                                height: '{{ $templateHeight }}px',
                                transform: `scale(${zoom / 100})`
                            }"
                            @click="selectedKey = null"
                            @keydown.arrow-up.prevent="moveSelected('up', $event.shiftKey)"
                            @keydown.arrow-down.prevent="moveSelected('down', $event.shiftKey)"
                            @keydown.arrow-left.prevent="moveSelected('left', $event.shiftKey)"
                            @keydown.arrow-right.prevent="moveSelected('right', $event.shiftKey)"
                        >
                            @if ($imageUrl)
                                <img src="{{ $imageUrl }}" class="template-image" alt="Template">
                            @else
                                <div class="template-placeholder-bg">
                                    Upload a template image first
                                </div>
                            @endif

                            <template x-for="(placeholder, key) in placeholders" :key="key">
                                <div
                                    x-show="placeholder.is_visible"
                                    class="placeholder-box"
                                    :class="{
                                        selected: selectedKey === key,
                                        qr: placeholder.type === 'qr_code',
                                    }"
                                    :style="placeholderStyle(placeholder)"
                                    @mousedown.stop="startDrag($event, key)"
                                    @click.stop="selectPlaceholder(key)"
                                >
                                    <template x-if="placeholder.type === 'qr_code'">
                                        <div
                                            class="qr-preview actual-qr"
                                            :style="{
                                                backgroundColor: placeholder.qr_background_color || '#ffffff'
                                            }"
                                        >
                                            <template x-if="sampleQrCodeUrl">
                                                <img :src="sampleQrCodeUrl" alt="Actual QR Code Preview">
                                            </template>

                                            <template x-if="!sampleQrCodeUrl">
                                                <div class="qr-fallback">
                                                    <strong>QR</strong>
                                                    <small>No QR yet</small>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    <template x-if="placeholder.type !== 'qr_code'">
                                        <span x-text="previewValue(placeholder)"></span>
                                    </template>

                                    <template x-if="selectedKey === key">
                                        <span class="resize-handle" @mousedown.stop="startResize($event, key)"></span>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="designer-note">
                    Drag placeholders, resize using the corner handle, or use direction buttons for precise movement.
                </div>
            </div>

            <div class="simple-settings-panel">
                <div class="simple-panel-section">
                    <h3>Add Placeholder</h3>
                    <p>Choose the fields you want to appear on this card.</p>

                    <div class="simple-picker-grid">
                        @foreach (\App\Models\CardTemplatePlaceholder::availablePlaceholders() as $key => $label)
                            <button
                                type="button"
                                wire:click="addPlaceholder('{{ $key }}')"
                                @click="selectedKey = '{{ $key }}'"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="simple-panel-section" x-show="current" x-cloak>
                    <h3>Selected Placeholder</h3>
                    <p>Adjust the selected item.</p>

                    <div class="selected-card">
                        <strong x-text="current?.label"></strong>

                        <label>
                            <span>Show on card</span>
                            <input type="checkbox" x-model="current.is_visible">
                        </label>
                    </div>

                    <div class="direction-control">
                        <button type="button" @click="moveSelected('up', $event.shiftKey)">↑</button>

                        <div>
                            <button type="button" @click="moveSelected('left', $event.shiftKey)">←</button>
                            <button type="button" @click="moveSelected('down', $event.shiftKey)">↓</button>
                            <button type="button" @click="moveSelected('right', $event.shiftKey)">→</button>
                        </div>

                        <small>Click to move. Shift + click moves faster.</small>
                    </div>

                    <div class="simple-form-grid">
                        <label>
                            X %
                            <input type="number" min="0" max="100" step="0.1" x-model.number="current.x_percent">
                        </label>

                        <label>
                            Y %
                            <input type="number" min="0" max="100" step="0.1" x-model.number="current.y_percent">
                        </label>

                        <label>
                            Width %
                            <input type="number" min="1" max="100" step="0.1" x-model.number="current.width_percent">
                        </label>

                        <label>
                            Height %
                            <input type="number" min="1" max="100" step="0.1" x-model.number="current.height_percent">
                        </label>

                        <template x-if="current?.type !== 'qr_code'">
                            <label>
                                Font Family
                                <select x-model="current.font_family">
                                    <option value="Montserrat">Montserrat</option>
                                    <option value="Roboto">Roboto</option>
                                    <option value="Lexend">Lexend</option>
                                    <option value="Corben">Corben</option>
                                </select>
                            </label>
                        </template>

                        <template x-if="current?.type !== 'qr_code'">
                            <label>
                                Font Size
                                <input type="number" min="8" max="120" x-model.number="current.font_size">
                            </label>
                        </template>

                        <template x-if="current?.type !== 'qr_code'">
                            <label>
                                Font Color
                                <input type="color" x-model="current.font_color">
                            </label>
                        </template>

                        <template x-if="current?.type !== 'qr_code'">
                            <label>
                                Font Weight
                                <select x-model="current.font_weight">
                                    <option value="normal">Normal</option>
                                    <option value="bold">Bold</option>
                                </select>
                            </label>
                        </template>

                        <template x-if="current?.type !== 'qr_code'">
                            <label>
                                Text Align
                                <select x-model="current.text_align">
                                    <option value="left">Left</option>
                                    <option value="center">Center</option>
                                    <option value="right">Right</option>
                                </select>
                            </label>
                        </template>

                        <template x-if="current?.type === 'qr_code'">
                            <label>
                                QR Size
                                <input type="number" min="40" max="600" x-model.number="current.qr_size">
                            </label>
                        </template>

                        <template x-if="current?.type === 'qr_code'">
                            <label>
                                QR Color
                                <input type="color" x-model="current.qr_color">
                            </label>
                        </template>

                        <template x-if="current?.type === 'qr_code'">
                            <label>
                                QR Background
                                <input type="color" x-model="current.qr_background_color">
                            </label>
                        </template>
                    </div>

                    <div class="panel-actions">
                        <button type="button" class="btn-small danger" @click="$wire.removePlaceholder(selectedKey)">
                            Remove
                        </button>

                        <button type="button" class="btn-small" @click="current.is_visible = ! current.is_visible">
                            Show / Hide
                        </button>
                    </div>
                </div>

                <div class="simple-panel-section">
                    <h3>Current Placeholders</h3>
                    <p>Click a placeholder to edit it.</p>

                    <div class="placeholder-list">
                        <template x-for="(placeholder, key) in placeholders" :key="key">
                            <button
                                type="button"
                                class="placeholder-list-item"
                                :class="{ active: selectedKey === key }"
                                @click="selectPlaceholder(key)"
                            >
                                <span x-text="placeholder.label"></span>
                                <small x-text="placeholder.is_visible ? 'Visible' : 'Hidden'"></small>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div class="preview-modal" x-show="showPreview" x-cloak>
            <div class="preview-backdrop" @click="$wire.closePreview()"></div>

            <div class="preview-card">
                <div class="preview-header">
                    <div>
                        <h3>Card Preview</h3>
                        <p>This preview uses sample invitee data and an actual generated QR code.</p>
                    </div>

                    <button type="button" @click="$wire.closePreview()">×</button>
                </div>

                <div class="preview-body">
                    <div
                        class="preview-canvas"
                        :style="{
                            width: '{{ $templateWidth }}px',
                            height: '{{ $templateHeight }}px'
                        }"
                    >
                        @if ($imageUrl)
                            <img src="{{ $imageUrl }}" class="template-image" alt="Template Preview">
                        @else
                            <div class="template-placeholder-bg">
                                No template image
                            </div>
                        @endif

                        <template x-for="(placeholder, key) in placeholders" :key="'preview-' + key">
                            <div
                                x-show="placeholder.is_visible"
                                class="placeholder-box preview-mode"
                                :class="{ qr: placeholder.type === 'qr_code' }"
                                :style="placeholderStyle(placeholder)"
                            >
                                <template x-if="placeholder.type === 'qr_code'">
                                    <div
                                        class="qr-preview actual-qr"
                                        :style="{
                                            backgroundColor: placeholder.qr_background_color || '#ffffff'
                                        }"
                                    >
                                        <template x-if="sampleQrCodeUrl">
                                            <img :src="sampleQrCodeUrl" alt="Actual QR Code Preview">
                                        </template>

                                        <template x-if="!sampleQrCodeUrl">
                                            <div class="qr-fallback">
                                                <strong>QR</strong>
                                                <small>No QR yet</small>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="placeholder.type !== 'qr_code'">
                                    <span x-text="previewValue(placeholder)"></span>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="preview-footer">
                    <button type="button" class="btn-primary" @click="$wire.closePreview()">
                        Close Preview
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        @font-face {
            font-family: 'Montserrat';
            src: url('/fonts/Montserrat-Regular.ttf') format('truetype');
            font-weight: 400;
        }

        @font-face {
            font-family: 'Montserrat';
            src: url('/fonts/Montserrat-Bold.ttf') format('truetype');
            font-weight: 700;
        }

        @font-face {
            font-family: 'Roboto';
            src: url('/fonts/Roboto-Regular.ttf') format('truetype');
            font-weight: 400;
        }

        @font-face {
            font-family: 'Roboto';
            src: url('/fonts/Roboto-Bold.ttf') format('truetype');
            font-weight: 700;
        }

        @font-face {
            font-family: 'Lexend';
            src: url('/fonts/Lexend-Regular.ttf') format('truetype');
            font-weight: 400;
        }

        @font-face {
            font-family: 'Lexend';
            src: url('/fonts/Lexend-Bold.ttf') format('truetype');
            font-weight: 700;
        }

        @font-face {
            font-family: 'Corben';
            src: url('/fonts/Corben-Regular.ttf') format('truetype');
            font-weight: 400;
        }

        @font-face {
            font-family: 'Corben';
            src: url('/fonts/Corben-Bold.ttf') format('truetype');
            font-weight: 700;
        }

        [x-cloak] {
            display: none !important;
        }

        .elive-designer {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .designer-header,
        .template-summary,
        .canvas-panel,
        .simple-settings-panel {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 1.25rem;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }

        .designer-header {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem;
        }

        .breadcrumb {
            display: flex;
            gap: .5rem;
            align-items: center;
            font-size: .8rem;
            color: #64748b;
            margin-bottom: .35rem;
        }

        .designer-header h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }

        .designer-header p {
            color: #64748b;
            margin-top: .25rem;
        }

        .designer-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            align-items: center;
        }

        .btn-primary,
        .btn-outline,
        .btn-small {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            border-radius: .8rem;
            padding: .65rem .9rem;
            font-size: .85rem;
            font-weight: 700;
            transition: .2s ease;
            cursor: pointer;
        }

        .btn-primary {
            background: #0f172a;
            color: white;
            border: 1px solid #0f172a;
        }

        .btn-outline {
            background: white;
            color: #0f172a;
            border: 1px solid #d1d5db;
        }

        .btn-outline.cyan {
            color: #0369a1;
            border-color: #7dd3fc;
            background: #f0f9ff;
        }

        .btn-small {
            border: 1px solid #d1d5db;
            background: #f8fafc;
            color: #0f172a;
            padding: .5rem .7rem;
        }

        .btn-small.danger {
            color: #b91c1c;
            border-color: #fecaca;
            background: #fef2f2;
        }

        .template-summary {
            display: grid;
            grid-template-columns: 80px repeat(4, minmax(0, 1fr));
            gap: 1rem;
            align-items: center;
            padding: 1rem;
        }

        .template-summary span {
            display: block;
            font-size: .75rem;
            color: #64748b;
            margin-bottom: .25rem;
        }

        .template-summary strong {
            color: #0f172a;
            font-size: .9rem;
        }

        .template-thumb {
            width: 60px;
            height: 76px;
            border-radius: .9rem;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            background: #f8fafc;
        }

        .template-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .empty-thumb {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .7rem;
            color: #94a3b8;
        }

        .status-badge {
            display: inline-flex;
            padding: .25rem .55rem;
            border-radius: 999px;
            background: #fef3c7;
            color: #92400e !important;
        }

        .designer-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 380px;
            gap: 1.25rem;
            align-items: start;
        }

        .canvas-panel {
            overflow: hidden;
        }

        .canvas-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .6rem;
            padding: .9rem 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .tool-btn {
            border: 1px solid #d1d5db;
            background: #f8fafc;
            color: #0f172a;
            border-radius: .65rem;
            padding: .45rem .65rem;
            font-size: .8rem;
            font-weight: 700;
        }

        .zoom-control {
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .zoom-control button {
            width: 30px;
            height: 30px;
            border: 1px solid #d1d5db;
            border-radius: .55rem;
            background: white;
            font-weight: 800;
        }

        .zoom-label {
            font-size: .85rem;
            font-weight: 700;
            color: #334155;
        }

        .workspace {
            background:
                linear-gradient(45deg, #f1f5f9 25%, transparent 25%),
                linear-gradient(-45deg, #f1f5f9 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, #f1f5f9 75%),
                linear-gradient(-45deg, transparent 75%, #f1f5f9 75%);
            background-size: 22px 22px;
            background-position: 0 0, 0 11px, 11px -11px, -11px 0;
            padding: 2rem;
            min-height: 720px;
            overflow: auto;
        }

        .canvas-scroll {
            min-width: max-content;
            min-height: max-content;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        .card-canvas,
        .preview-canvas {
            position: relative;
            transform-origin: top center;
            background: white;
            overflow: hidden;
            border-radius: .75rem;
            box-shadow: 0 22px 70px rgba(15, 23, 42, 0.2);
        }

        .template-image,
        .template-placeholder-bg {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .template-placeholder-bg {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
        }

        .placeholder-box {
            position: absolute;
            z-index: 10;
            border: 1.5px dashed rgba(14, 165, 233, .95);
            background: rgba(255, 255, 255, .42);
            color: #0f172a;
            border-radius: .45rem;
            cursor: move;
            display: flex;
            align-items: center;
            overflow: hidden;
            user-select: none;
        }

        .placeholder-box.selected {
            border: 2px solid #f59e0b;
            box-shadow: 0 0 0 4px rgba(245, 158, 11, .18);
        }

        .placeholder-box.preview-mode {
            border-color: transparent;
            background: transparent;
            box-shadow: none;
            cursor: default;
        }

        .placeholder-box span {
            width: 100%;
            padding: .15rem .35rem;
            line-height: 1.1;
        }

        .placeholder-box.qr {
            align-items: center;
            justify-content: center;
            padding: 0;
            background: transparent;
        }

        .qr-preview {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            background: #ffffff;
            border: none;
            border-radius: 0;
            box-shadow: none;
            overflow: hidden;
        }

        .qr-preview.actual-qr img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            background: #ffffff;
        }

        .qr-fallback {
            width: 100%;
            height: 100%;
            background: #ffffff;
            color: #0f172a;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: .15rem;
            text-align: center;
            font-size: .72rem;
            font-weight: 800;
        }

        .qr-fallback small {
            font-size: .62rem;
            font-weight: 700;
            color: #64748b;
        }

        .resize-handle {
            position: absolute;
            width: 14px !important;
            height: 14px;
            right: -2px;
            bottom: -2px;
            background: #f59e0b;
            border-radius: 999px;
            border: 2px solid white;
            cursor: nwse-resize;
            padding: 0 !important;
        }

        .designer-note {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .9rem 1rem;
            border-top: 1px solid #e5e7eb;
            color: #64748b;
            font-size: .85rem;
        }

        .simple-settings-panel {
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .simple-panel-section {
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            padding: 1rem;
            background: #ffffff;
        }

        .simple-panel-section h3 {
            font-size: 1rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }

        .simple-panel-section p {
            color: #64748b;
            font-size: .8rem;
            margin: .25rem 0 .8rem;
        }

        .simple-picker-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .5rem;
        }

        .simple-picker-grid button {
            border: 1px solid #e5e7eb;
            background: #f8fafc;
            border-radius: .75rem;
            padding: .65rem;
            font-size: .78rem;
            font-weight: 700;
            color: #334155;
            text-align: left;
        }

        .simple-picker-grid button:hover {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #1d4ed8;
        }

        .selected-card {
            border: 1px solid #e5e7eb;
            border-radius: .85rem;
            padding: .8rem;
            background: #f8fafc;
            margin-bottom: .9rem;
        }

        .selected-card strong {
            display: block;
            margin-bottom: .55rem;
            color: #0f172a;
        }

        .selected-card label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: .8rem;
            color: #475569;
        }

        .direction-control {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .45rem;
            margin-bottom: .9rem;
            padding: .75rem;
            border: 1px dashed #cbd5e1;
            border-radius: .85rem;
            background: #f8fafc;
        }

        .direction-control div {
            display: flex;
            gap: .45rem;
        }

        .direction-control button {
            width: 38px;
            height: 34px;
            border-radius: .65rem;
            border: 1px solid #cbd5e1;
            background: white;
            color: #0f172a;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
        }

        .direction-control button:hover {
            background: #eff6ff;
            border-color: #60a5fa;
            color: #1d4ed8;
        }

        .direction-control small {
            color: #64748b;
            font-size: .72rem;
            font-weight: 600;
        }

        .simple-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .7rem;
        }

        .simple-form-grid label {
            display: flex;
            flex-direction: column;
            gap: .25rem;
            font-size: .75rem;
            font-weight: 700;
            color: #475569;
        }

        .simple-form-grid input,
        .simple-form-grid select {
            border: 1px solid #d1d5db;
            border-radius: .7rem;
            padding: .55rem .65rem;
            font-size: .85rem;
            color: #0f172a;
            background: white;
        }

        .panel-actions {
            display: flex;
            gap: .5rem;
            margin-top: .9rem;
        }

        .placeholder-list {
            display: flex;
            flex-direction: column;
            gap: .5rem;
        }

        .placeholder-list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid #e5e7eb;
            background: #f8fafc;
            border-radius: .75rem;
            padding: .65rem;
            text-align: left;
        }

        .placeholder-list-item.active {
            border-color: #38bdf8;
            background: #f0f9ff;
        }

        .placeholder-list-item span {
            font-size: .82rem;
            font-weight: 800;
            color: #0f172a;
        }

        .placeholder-list-item small {
            font-size: .7rem;
            color: #64748b;
        }

        .preview-modal {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .preview-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, .72);
        }

        .preview-card {
            position: relative;
            z-index: 1;
            width: min(96vw, 860px);
            max-height: 92vh;
            overflow: hidden;
            background: white;
            border-radius: 1.25rem;
            box-shadow: 0 30px 90px rgba(0, 0, 0, .35);
        }

        .preview-header,
        .preview-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .preview-footer {
            border-top: 1px solid #e5e7eb;
            border-bottom: none;
            justify-content: flex-end;
        }

        .preview-header h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 800;
        }

        .preview-header p {
            margin: .2rem 0 0;
            color: #64748b;
            font-size: .85rem;
        }

        .preview-header button {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            background: #f8fafc;
            font-size: 1.5rem;
            line-height: 1;
        }

        .preview-body {
            padding: 1.5rem;
            background: #f8fafc;
            overflow: auto;
            max-height: 72vh;
            display: flex;
            justify-content: center;
        }

        .preview-canvas {
            transform: scale(.8);
            transform-origin: top center;
            flex-shrink: 0;
        }

        @media (max-width: 1100px) {
            .designer-layout {
                grid-template-columns: 1fr;
            }

            .simple-settings-panel {
                order: -1;
            }
        }

        @media (max-width: 760px) {
            .designer-header,
            .template-summary {
                grid-template-columns: 1fr;
                flex-direction: column;
                align-items: flex-start;
            }

            .designer-header {
                display: block;
            }

            .designer-actions {
                margin-top: 1rem;
            }

            .template-summary {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .simple-picker-grid,
            .simple-form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        function simpleCardDesigner(config) {
            return {
                placeholders: config.placeholders,
                selectedKey: config.selectedKey,
                zoom: config.zoom,
                showPreview: config.showPreview,
                templateWidth: config.templateWidth,
                templateHeight: config.templateHeight,
                sampleQrCodeUrl: config.sampleQrCodeUrl,

                dragging: null,
                resizing: null,

                init() {
                    if (!this.selectedKey && Object.keys(this.placeholders || {}).length > 0) {
                        this.selectedKey = Object.keys(this.placeholders)[0];
                    }
                },

                get current() {
                    if (!this.selectedKey || !this.placeholders) {
                        return null;
                    }

                    return this.placeholders[this.selectedKey] || null;
                },

                selectPlaceholder(key) {
                    this.selectedKey = key;
                },

                moveSelected(direction, fast = false) {
                    if (!this.current) {
                        return;
                    }

                    const step = fast ? 1 : 0.25;

                    if (direction === 'up') {
                        this.current.y_percent = clamp(
                            Number(this.current.y_percent || 0) - step,
                            0,
                            100 - Number(this.current.height_percent || 8)
                        );
                    }

                    if (direction === 'down') {
                        this.current.y_percent = clamp(
                            Number(this.current.y_percent || 0) + step,
                            0,
                            100 - Number(this.current.height_percent || 8)
                        );
                    }

                    if (direction === 'left') {
                        this.current.x_percent = clamp(
                            Number(this.current.x_percent || 0) - step,
                            0,
                            100 - Number(this.current.width_percent || 20)
                        );
                    }

                    if (direction === 'right') {
                        this.current.x_percent = clamp(
                            Number(this.current.x_percent || 0) + step,
                            0,
                            100 - Number(this.current.width_percent || 20)
                        );
                    }
                },

                increaseZoom() {
                    this.zoom = Math.min(160, Number(this.zoom || 100) + 5);
                },

                decreaseZoom() {
                    this.zoom = Math.max(40, Number(this.zoom || 100) - 5);
                },

                placeholderStyle(placeholder) {
                    return {
                        left: `${Number(placeholder.x_percent || 0)}%`,
                        top: `${Number(placeholder.y_percent || 0)}%`,
                        width: `${Number(placeholder.width_percent || 20)}%`,
                        height: `${Number(placeholder.height_percent || 8)}%`,
                        color: placeholder.font_color || '#000000',
                        fontFamily: placeholder.font_family || 'Montserrat',
                        fontSize: `${Number(placeholder.font_size || 16)}px`,
                        fontWeight: placeholder.font_weight === 'bold' ? '700' : '400',
                        textAlign: placeholder.text_align || 'center',
                        justifyContent: this.justifyContent(placeholder.text_align || 'center'),
                    };
                },

                justifyContent(align) {
                    if (align === 'left') {
                        return 'flex-start';
                    }

                    if (align === 'right') {
                        return 'flex-end';
                    }

                    return 'center';
                },

                previewValue(placeholder) {
                    const values = {
                        name: 'John Doe',
                        card_type: 'VIP',
                        qr_code: 'QR Code',
                        serial_number: 'ELC-0001',
                        guest_count: '2 Guests',
                        allowed_guests: '2',
                        table_number: 'Table 5',
                        category: 'Family',
                        event_name: 'Wedding Ceremony',
                        event_date: '25 Dec 2026',
                        event_time: '04:00 PM',
                        event_venue: 'Royal Hall',
                    };

                    return values[placeholder.key] || placeholder.label || 'Placeholder';
                },

                startDrag(event, key) {
                    const placeholder = this.placeholders[key];

                    this.selectedKey = key;

                    this.dragging = {
                        key,
                        startX: event.clientX,
                        startY: event.clientY,
                        originalX: Number(placeholder.x_percent || 0),
                        originalY: Number(placeholder.y_percent || 0),
                    };

                    document.addEventListener('mousemove', this.onDragMove);
                    document.addEventListener('mouseup', this.stopInteraction);
                },

                onDragMove: null,

                startResize(event, key) {
                    const placeholder = this.placeholders[key];

                    this.selectedKey = key;

                    this.resizing = {
                        key,
                        startX: event.clientX,
                        startY: event.clientY,
                        originalWidth: Number(placeholder.width_percent || 20),
                        originalHeight: Number(placeholder.height_percent || 8),
                    };

                    document.addEventListener('mousemove', this.onResizeMove);
                    document.addEventListener('mouseup', this.stopInteraction);
                },

                onResizeMove: null,

                stopInteraction: null,

                syncToLivewire() {
                    this.$wire.set('placeholders', JSON.parse(JSON.stringify(this.placeholders)));
                    this.$wire.set('selectedPlaceholder', this.selectedKey);
                    this.$wire.set('zoom', this.zoom);
                },
            }
        }

        document.addEventListener('alpine:init', () => {
            Alpine.data('simpleCardDesigner', (config) => {
                const designer = simpleCardDesigner(config);

                designer.onDragMove = function (event) {
                    if (!designer.dragging) {
                        return;
                    }

                    const item = designer.placeholders[designer.dragging.key];

                    const zoomRatio = Number(designer.zoom || 100) / 100;
                    const deltaX = (event.clientX - designer.dragging.startX) / zoomRatio;
                    const deltaY = (event.clientY - designer.dragging.startY) / zoomRatio;

                    const deltaXPercent = (deltaX / designer.templateWidth) * 100;
                    const deltaYPercent = (deltaY / designer.templateHeight) * 100;

                    item.x_percent = clamp(
                        designer.dragging.originalX + deltaXPercent,
                        0,
                        100 - Number(item.width_percent || 20)
                    );

                    item.y_percent = clamp(
                        designer.dragging.originalY + deltaYPercent,
                        0,
                        100 - Number(item.height_percent || 8)
                    );
                };

                designer.onResizeMove = function (event) {
                    if (!designer.resizing) {
                        return;
                    }

                    const item = designer.placeholders[designer.resizing.key];

                    const zoomRatio = Number(designer.zoom || 100) / 100;
                    const deltaX = (event.clientX - designer.resizing.startX) / zoomRatio;
                    const deltaY = (event.clientY - designer.resizing.startY) / zoomRatio;

                    const deltaWidthPercent = (deltaX / designer.templateWidth) * 100;
                    const deltaHeightPercent = (deltaY / designer.templateHeight) * 100;

                    item.width_percent = clamp(
                        designer.resizing.originalWidth + deltaWidthPercent,
                        1,
                        100 - Number(item.x_percent || 0)
                    );

                    item.height_percent = clamp(
                        designer.resizing.originalHeight + deltaHeightPercent,
                        1,
                        100 - Number(item.y_percent || 0)
                    );
                };

                designer.stopInteraction = function () {
                    designer.dragging = null;
                    designer.resizing = null;

                    document.removeEventListener('mousemove', designer.onDragMove);
                    document.removeEventListener('mousemove', designer.onResizeMove);
                    document.removeEventListener('mouseup', designer.stopInteraction);
                };

                return designer;
            });
        });

        function clamp(value, min, max) {
            value = Number(value || 0);
            min = Number(min || 0);
            max = Number(max || 100);

            return Math.round(Math.max(min, Math.min(max, value)) * 10000) / 10000;
        }
    </script>
</x-filament-panels::page>