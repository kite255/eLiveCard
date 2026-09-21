<x-filament-panels::page>
    @php
        $templatePath = trim((string) $campaign->template_image);
        $imageUrl = null;

        if (filled($templatePath)) {
            if (
                str_starts_with($templatePath, 'http://')
                || str_starts_with($templatePath, 'https://')
            ) {
                $imageUrl = $templatePath;
            } else {
                $templatePath = ltrim($templatePath, '/');

                if (str_starts_with($templatePath, 'public/')) {
                    $templatePath = substr($templatePath, 7);
                }

                $imageUrl = str_starts_with($templatePath, 'storage/')
                    ? asset($templatePath)
                    : asset('storage/' . $templatePath);
            }
        }

        $fontFamilies = $this->fontFamilyOptions();
    @endphp

    <div
        class="contribution-designer"
        x-data="contributionCardDesigner({
            x: @entangle('nameXPercent').live,
            y: @entangle('nameYPercent').live,
            width: @entangle('nameWidthPercent').live,
            fontSize: @entangle('nameFontSize').live,
            color: @entangle('nameFontColor').live,
            family: @entangle('nameFontFamily').live,
            weight: @entangle('nameFontWeight').live,
            align: @entangle('nameTextAlign').live,
            previewName: @entangle('previewName').live,
            fontFamilies: @js($fontFamilies),
        })"
        x-init="init()"
    >
        <section class="designer-intro">
            <div>
                <p class="eyebrow">Contribution Cards</p>
                <h2>{{ $campaign->name }}</h2>
                <p>
                    Drag the member-name placeholder on the card. Resize it from
                    the lower-right handle and confirm the result in Live Preview.
                </p>
            </div>

            <div class="designer-actions">
                <x-filament::button
                    type="button"
                    icon="heroicon-o-check"
                    x-on:click="saveDesign()"
                >
                    Save Design
                </x-filament::button>

                <x-filament::button
                    type="button"
                    color="gray"
                    icon="heroicon-o-arrow-path"
                    wire:click="resetDesign"
                    wire:confirm="Reset the placeholder to its default position and style?"
                >
                    Reset
                </x-filament::button>
            </div>
        </section>

        @if (! $imageUrl)
            <x-filament::section>
                <div class="empty-template">
                    <x-heroicon-o-photo />
                    <div>
                        <strong>No contribution-card template uploaded</strong>
                        <p>Open Campaign Settings, upload the blank card, save it, and return to the designer.</p>
                    </div>
                </div>
            </x-filament::section>
        @else
            <div class="designer-grid">
                <x-filament::section>
                    <x-slot name="heading">Drag & Drop Placeholder</x-slot>
                    <x-slot name="description">
                        The blue outline is an editor guide and will not appear on generated cards.
                    </x-slot>

                    <div class="canvas-shell">
                        <div class="card-stage" x-ref="designCanvas">
                            <img
                                src="{{ $imageUrl }}"
                                alt="Contribution card template"
                                x-on:load="imageLoaded($event)"
                            >

                            <div
                                class="name-placeholder"
                                x-bind:style="placeholderStyle(true)"
                                x-on:pointerdown.stop="startDrag($event)"
                            >
                                <span x-text="previewName || 'Committee Member Name'"></span>
                                <button
                                    type="button"
                                    class="resize-handle"
                                    aria-label="Resize member name placeholder"
                                    x-on:pointerdown.stop="startResize($event)"
                                ></button>
                            </div>
                        </div>
                    </div>

                    <p class="canvas-help">
                        Drag anywhere inside the outlined name box. Use the round
                        handle to change its width.
                    </p>
                </x-filament::section>

                <div class="right-column">
                    <x-filament::section>
                        <x-slot name="heading">Placeholder Settings</x-slot>

                        <div class="control-grid">
                            <label class="control control-wide">
                                <span>Preview name</span>
                                <input
                                    type="text"
                                    maxlength="255"
                                    x-model="previewName"
                                    placeholder="Committee Member Name"
                                >
                            </label>

                            <label class="control">
                                <span>Left (%)</span>
                                <input type="number" min="0" max="99" step="0.1" x-model.number="x">
                            </label>

                            <label class="control">
                                <span>Top (%)</span>
                                <input type="number" min="0" max="99" step="0.1" x-model.number="y">
                            </label>

                            <label class="control">
                                <span>Width (%)</span>
                                <input type="number" min="1" max="100" step="0.1" x-model.number="width">
                            </label>

                            <label class="control">
                                <span>Font size (px)</span>
                                <input type="number" min="8" max="300" step="1" x-model.number="fontSize">
                            </label>

                            <label class="control">
                                <span>Font family</span>
                                <select x-model="family">
                                    @foreach ($fontFamilies as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="control">
                                <span>Font weight</span>
                                <select x-model="weight">
                                    <option value="normal">Regular</option>
                                    <option value="bold">Bold</option>
                                </select>
                            </label>

                            <label class="control">
                                <span>Alignment</span>
                                <select x-model="align">
                                    <option value="left">Left</option>
                                    <option value="center">Center</option>
                                    <option value="right">Right</option>
                                </select>
                            </label>

                            <label class="control color-control">
                                <span>Font colour</span>
                                <div>
                                    <input type="color" x-model="color">
                                    <input
                                        type="text"
                                        maxlength="7"
                                        pattern="#[0-9A-Fa-f]{6}"
                                        x-model="color"
                                    >
                                </div>
                            </label>
                        </div>

                        <div class="position-pad">
                            <span>Fine position</span>
                            <div>
                                <button type="button" x-on:click="nudge(0, -0.25)">↑</button>
                            </div>
                            <div>
                                <button type="button" x-on:click="nudge(-0.25, 0)">←</button>
                                <button type="button" x-on:click="nudge(0, 0.25)">↓</button>
                                <button type="button" x-on:click="nudge(0.25, 0)">→</button>
                            </div>
                        </div>
                    </x-filament::section>

                    <x-filament::section>
                        <x-slot name="heading">Live Preview</x-slot>
                        <x-slot name="description">
                            This clean preview excludes editor guides and resize handles.
                        </x-slot>

                        <div class="preview-shell">
                            <div class="card-stage preview-stage" x-ref="previewCanvas">
                                <img
                                    src="{{ $imageUrl }}"
                                    alt="Contribution card preview"
                                    x-on:load="previewImageLoaded($event)"
                                >

                                <div
                                    class="preview-name"
                                    x-bind:style="placeholderStyle(false)"
                                >
                                    <span x-text="previewName || 'Committee Member Name'"></span>
                                </div>
                            </div>
                        </div>
                    </x-filament::section>
                </div>
            </div>
        @endif
    </div>

    <style>
        [x-cloak] { display: none !important; }

        .contribution-designer {
            display: grid;
            gap: 1.25rem;
        }

        .designer-intro {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem 1.4rem;
            border: 1px solid rgb(226 232 240);
            border-radius: 1rem;
            background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);
        }

        .dark .designer-intro {
            border-color: rgb(51 65 85);
            background: linear-gradient(135deg, rgb(15 23 42), rgb(30 41 59));
        }

        .designer-intro h2 {
            margin: .15rem 0 .35rem;
            font-size: 1.35rem;
            font-weight: 750;
        }

        .designer-intro p {
            margin: 0;
            color: rgb(100 116 139);
            max-width: 46rem;
        }

        .eyebrow {
            color: #213b73 !important;
            font-size: .75rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .designer-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .65rem;
        }

        .designer-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(22rem, .75fr);
            gap: 1.25rem;
            align-items: start;
        }

        .right-column {
            display: grid;
            gap: 1.25rem;
        }

        .canvas-shell,
        .preview-shell {
            padding: 1rem;
            overflow: auto;
            border-radius: .85rem;
            background:
                linear-gradient(45deg, #e2e8f0 25%, transparent 25%),
                linear-gradient(-45deg, #e2e8f0 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, #e2e8f0 75%),
                linear-gradient(-45deg, transparent 75%, #e2e8f0 75%);
            background-position: 0 0, 0 8px, 8px -8px, -8px 0;
            background-size: 16px 16px;
        }

        .card-stage {
            position: relative;
            width: min(100%, 760px);
            margin: 0 auto;
            overflow: hidden;
            line-height: 1;
            box-shadow: 0 12px 32px rgb(15 23 42 / 18%);
            user-select: none;
            touch-action: none;
        }

        .card-stage img {
            display: block;
            width: 100%;
            height: auto;
            pointer-events: none;
        }

        .name-placeholder,
        .preview-name {
            position: absolute;
            display: flex;
            align-items: flex-start;
            min-height: 1.35em;
            line-height: 1.18;
            white-space: nowrap;
            overflow: hidden;
        }

        .name-placeholder {
            cursor: move;
            border: 2px dashed #0ea5e9;
            border-radius: .35rem;
            background: rgb(14 165 233 / 10%);
            box-shadow: 0 0 0 2px rgb(255 255 255 / 65%);
        }

        .name-placeholder span,
        .preview-name span {
            width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .resize-handle {
            position: absolute;
            right: -.1rem;
            bottom: -.1rem;
            width: 1rem;
            height: 1rem;
            border: 2px solid white;
            border-radius: 999px;
            background: #0284c7;
            cursor: ew-resize;
            box-shadow: 0 1px 5px rgb(15 23 42 / 35%);
        }

        .canvas-help {
            margin: .8rem 0 0;
            color: rgb(100 116 139);
            font-size: .82rem;
        }

        .control-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .8rem;
        }

        .control {
            display: grid;
            gap: .35rem;
            font-size: .8rem;
            font-weight: 650;
            color: rgb(51 65 85);
        }

        .dark .control {
            color: rgb(203 213 225);
        }

        .control-wide {
            grid-column: 1 / -1;
        }

        .control input,
        .control select {
            width: 100%;
            min-height: 2.55rem;
            border: 1px solid rgb(203 213 225);
            border-radius: .55rem;
            padding: .55rem .7rem;
            background: white;
            color: rgb(15 23 42);
            font-weight: 500;
        }

        .dark .control input,
        .dark .control select {
            border-color: rgb(71 85 105);
            background: rgb(15 23 42);
            color: white;
        }

        .color-control > div {
            display: grid;
            grid-template-columns: 3rem 1fr;
            gap: .5rem;
        }

        .color-control input[type="color"] {
            padding: .2rem;
        }

        .position-pad {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgb(226 232 240);
            text-align: center;
        }

        .dark .position-pad {
            border-color: rgb(51 65 85);
        }

        .position-pad > span {
            display: block;
            margin-bottom: .55rem;
            color: rgb(100 116 139);
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .position-pad div {
            display: flex;
            justify-content: center;
            gap: .4rem;
            margin-bottom: .4rem;
        }

        .position-pad button {
            width: 2.5rem;
            height: 2.35rem;
            border: 1px solid rgb(203 213 225);
            border-radius: .55rem;
            background: white;
            font-weight: 800;
        }

        .dark .position-pad button {
            border-color: rgb(71 85 105);
            background: rgb(30 41 59);
        }

        .empty-template {
            display: flex;
            align-items: center;
            gap: .9rem;
            padding: 1rem;
        }

        .empty-template svg {
            width: 2.25rem;
            color: rgb(100 116 139);
        }

        .empty-template p {
            margin: .2rem 0 0;
            color: rgb(100 116 139);
        }

        @media (max-width: 1100px) {
            .designer-grid {
                grid-template-columns: 1fr;
            }

            .right-column {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .designer-intro {
                flex-direction: column;
            }

            .right-column {
                grid-template-columns: 1fr;
            }

            .control-grid {
                grid-template-columns: 1fr;
            }

            .control-wide {
                grid-column: auto;
            }
        }
    </style>

    <script>
        function contributionCardDesigner(config) {
            return {
                x: Number(config.x || 0),
                y: Number(config.y || 0),
                width: Number(config.width || 80),
                fontSize: Number(config.fontSize || 42),
                color: config.color || '#111827',
                family: config.family || 'Montserrat',
                weight: config.weight || 'bold',
                align: config.align || 'center',
                previewName: config.previewName || 'Committee Member Name',
                fontFamilies: config.fontFamilies || {},
                sourceWidth: 1080,
                designWidth: 760,
                previewWidth: 420,
                dragging: null,
                resizing: null,
                resizeObserver: null,
                boundDragMove: null,
                boundResizeMove: null,
                boundStopInteraction: null,

                init() {
                    this.boundDragMove = (event) => this.handleDragMove(event);
                    this.boundResizeMove = (event) => this.handleResizeMove(event);
                    this.boundStopInteraction = () => this.stopInteraction();

                    if (typeof ResizeObserver !== 'undefined') {
                        this.resizeObserver = new ResizeObserver(() => {
                            this.updateCanvasSizes();
                        });
                    }

                    this.$nextTick(() => {
                        if (this.resizeObserver && this.$refs.designCanvas) {
                            this.resizeObserver.observe(this.$refs.designCanvas);
                        }

                        if (this.resizeObserver && this.$refs.previewCanvas) {
                            this.resizeObserver.observe(this.$refs.previewCanvas);
                        }

                        this.updateCanvasSizes();
                    });
                },

                destroy() {
                    this.stopInteraction();

                    if (this.resizeObserver) {
                        this.resizeObserver.disconnect();
                    }
                },

                imageLoaded(event) {
                    this.sourceWidth = Math.max(
                        1,
                        event.target.naturalWidth || 1080
                    );
                    this.updateCanvasSizes();
                },

                previewImageLoaded() {
                    this.updateCanvasSizes();
                },

                updateCanvasSizes() {
                    this.designWidth = this.$refs.designCanvas
                        ? this.$refs.designCanvas.getBoundingClientRect().width
                        : 760;

                    this.previewWidth = this.$refs.previewCanvas
                        ? this.$refs.previewCanvas.getBoundingClientRect().width
                        : this.designWidth;
                },

                clamp(value, minimum, maximum) {
                    return Math.round(
                        Math.max(
                            minimum,
                            Math.min(maximum, Number(value || 0))
                        ) * 10000
                    ) / 10000;
                },

                normalizedWidth() {
                    return this.clamp(this.width, 1, 100);
                },

                normalizedX() {
                    return this.clamp(
                        this.x,
                        0,
                        100 - this.normalizedWidth()
                    );
                },

                normalizedY() {
                    return this.clamp(this.y, 0, 99);
                },

                cssFamily() {
                    return this.fontFamilies[this.family]
                        || this.family
                        || 'sans-serif';
                },

                placeholderStyle(interactive) {
                    const canvasWidth = interactive
                        ? this.designWidth
                        : this.previewWidth;
                    const scale = canvasWidth / Math.max(1, this.sourceWidth);

                    return {
                        left: this.normalizedX() + '%',
                        top: this.normalizedY() + '%',
                        width: this.normalizedWidth() + '%',
                        color: this.color,
                        fontFamily: this.cssFamily(),
                        fontWeight: this.weight,
                        fontSize: Math.max(
                            8,
                            Number(this.fontSize || 42) * scale
                        ) + 'px',
                        justifyContent: this.align === 'left'
                            ? 'flex-start'
                            : (
                                this.align === 'right'
                                    ? 'flex-end'
                                    : 'center'
                            ),
                        textAlign: this.align,
                        padding: interactive ? '0.16em 0.24em' : '0',
                    };
                },

                startDrag(event) {
                    if (!this.$refs.designCanvas) {
                        return;
                    }

                    event.preventDefault();

                    const canvas =
                        this.$refs.designCanvas.getBoundingClientRect();
                    const element =
                        event.currentTarget.getBoundingClientRect();

                    this.dragging = {
                        offsetX:
                            ((event.clientX - element.left) / canvas.width)
                            * 100,
                        offsetY:
                            ((event.clientY - element.top) / canvas.height)
                            * 100,
                    };

                    window.addEventListener(
                        'pointermove',
                        this.boundDragMove
                    );
                    window.addEventListener(
                        'pointerup',
                        this.boundStopInteraction,
                        { once: true }
                    );
                    window.addEventListener(
                        'pointercancel',
                        this.boundStopInteraction,
                        { once: true }
                    );
                },

                handleDragMove(event) {
                    if (!this.dragging || !this.$refs.designCanvas) {
                        return;
                    }

                    event.preventDefault();

                    const canvas =
                        this.$refs.designCanvas.getBoundingClientRect();
                    const pointerX =
                        ((event.clientX - canvas.left) / canvas.width) * 100;
                    const pointerY =
                        ((event.clientY - canvas.top) / canvas.height) * 100;

                    this.x = this.clamp(
                        pointerX - this.dragging.offsetX,
                        0,
                        100 - this.normalizedWidth()
                    );
                    this.y = this.clamp(
                        pointerY - this.dragging.offsetY,
                        0,
                        99
                    );
                },

                startResize(event) {
                    if (!this.$refs.designCanvas) {
                        return;
                    }

                    event.preventDefault();

                    const canvas =
                        this.$refs.designCanvas.getBoundingClientRect();

                    this.resizing = {
                        left: this.normalizedX(),
                        canvasLeft: canvas.left,
                        canvasWidth: canvas.width,
                    };

                    window.addEventListener(
                        'pointermove',
                        this.boundResizeMove
                    );
                    window.addEventListener(
                        'pointerup',
                        this.boundStopInteraction,
                        { once: true }
                    );
                    window.addEventListener(
                        'pointercancel',
                        this.boundStopInteraction,
                        { once: true }
                    );
                },

                handleResizeMove(event) {
                    if (!this.resizing) {
                        return;
                    }

                    event.preventDefault();

                    const pointerPercent = (
                        (event.clientX - this.resizing.canvasLeft)
                        / this.resizing.canvasWidth
                    ) * 100;

                    this.width = this.clamp(
                        pointerPercent - this.resizing.left,
                        5,
                        100 - this.resizing.left
                    );
                },

                stopInteraction() {
                    this.dragging = null;
                    this.resizing = null;

                    if (this.boundDragMove) {
                        window.removeEventListener(
                            'pointermove',
                            this.boundDragMove
                        );
                    }

                    if (this.boundResizeMove) {
                        window.removeEventListener(
                            'pointermove',
                            this.boundResizeMove
                        );
                    }

                    if (this.boundStopInteraction) {
                        window.removeEventListener(
                            'pointerup',
                            this.boundStopInteraction
                        );
                        window.removeEventListener(
                            'pointercancel',
                            this.boundStopInteraction
                        );
                    }
                },

                nudge(deltaX, deltaY) {
                    this.x = this.clamp(
                        this.normalizedX() + deltaX,
                        0,
                        100 - this.normalizedWidth()
                    );
                    this.y = this.clamp(
                        this.normalizedY() + deltaY,
                        0,
                        99
                    );
                },

                async saveDesign() {
                    this.x = this.normalizedX();
                    this.y = this.normalizedY();
                    this.width = this.normalizedWidth();

                    await this.$wire.set('nameXPercent', this.x);
                    await this.$wire.set('nameYPercent', this.y);
                    await this.$wire.set(
                        'nameWidthPercent',
                        this.width
                    );
                    await this.$wire.set(
                        'nameFontSize',
                        Number(this.fontSize)
                    );
                    await this.$wire.set(
                        'nameFontColor',
                        this.color
                    );
                    await this.$wire.set(
                        'nameFontFamily',
                        this.family
                    );
                    await this.$wire.set(
                        'nameFontWeight',
                        this.weight
                    );
                    await this.$wire.set(
                        'nameTextAlign',
                        this.align
                    );
                    await this.$wire.set(
                        'previewName',
                        this.previewName
                    );
                    await this.$wire.saveDesign();
                },
            };
        }
    </script>
</x-filament-panels::page>
