/**
 * SISPAM - Escáner Profesional de Documentos para Dispositivos Móviles y Tabletas
 * Funcionalidades tipo CamScanner: Detección de bordes, recorte cuadrilátero interactivo,
 * lupa de precisión para pantallas táctiles, des-perspectiva (warp), filtros de realce,
 * escaneo multipágina y exportación a PDF multipágina.
 */

class DocumentScannerPro {
    constructor(options = {}) {
        this.videoElement = document.getElementById(options.videoId || 'webcam-video');
        this.canvasSource = document.createElement('canvas');
        this.canvasOverlay = document.getElementById(options.overlayId || 'scanner-canvas-overlay');
        this.canvasProcessed = document.getElementById(options.processedCanvasId || 'canvas-processed');
        this.canvasLoupe = document.getElementById(options.loupeCanvasId || 'canvas-loupe');
        this.canvasLiveOverlay = document.getElementById(options.liveOverlayId || 'scanner-live-overlay');

        this.liveDetector = null;
        this.liveCorners = null; // últimas esquinas detectadas en vivo (fracciones 0..1 del frame nativo de video)
        this.onAutoCaptureRequested = null; // lo asigna la vista para disparar la captura automática

        this.docType = null;      // tipo de documento activo (restringe la proporción detectable)
        this.debugMode = false;
        this.debugCanvas = null;
        // Se pone en true si el usuario arrastra una esquina o un lado en cualquier página
        // del lote. Se acumula durante todo el lote y se reinicia en resetDoc().
        this.huboAjusteManual = false;
        
        this.currentFacingMode = 'environment';
        this.stream = null;
        this.imageCapture = null;
        this.torchSupported = false;
        this._lastCaptureMethod = null;
        this.rawImage = null;
        this.previewImage = null;  // versión realzada que se muestra en la revisión
        this.rawWidth = 0;
        this.rawHeight = 0;

        this.scannedPages = [];
        this.currentPageIndex = -1;

        this.corners = [
            { x: 0.1, y: 0.1 },
            { x: 0.9, y: 0.1 },
            { x: 0.9, y: 0.9 },
            { x: 0.1, y: 0.9 }
        ];

        this.activeCornerIndex = -1;
        this.activeEdgeIndex = -1;   // lado que se está arrastrando en bloque (-1 = ninguno)
        this._edgeDrag = null;       // estado inicial del arrastre de lado
        this.currentFilter = 'magic';
        this.rotationAngle = 0;

        this.isOpenCVReady = typeof cv !== 'undefined' && cv.Mat;
        if (!this.isOpenCVReady) {
            window.addEventListener('opencv-ready', () => {
                this.isOpenCVReady = true;
                console.log("OpenCV.js listo para escáner profesional.");
            });
        }

        this.initEvents();

        window.addEventListener('resize', () => {
            if (this.rawImage && this.canvasOverlay && !this.canvasOverlay.closest('.d-none')) {
                this.drawOverlay();
            }
        });
    }

    initEvents() {
        if (!this.canvasOverlay) return;

        /**
         * Coordenadas del puntero en DOS espacios: normalizado (0..1, que es como se
         * guardan las esquinas) y en píxeles tal como se ven en pantalla.
         *
         * Las pruebas de cercanía y toda la geometría de lados van en PÍXELES. En espacio
         * normalizado el frame queda estirado a un cuadrado, así que un radio de agarre
         * "0.12" valía distinto en horizontal que en vertical, y encima cambiaba de tamaño
         * real según lo grande que se estuviera dibujando el canvas.
         */
        const getCoords = (e) => {
            const rect = this.canvasOverlay.getBoundingClientRect();
            const t = e.touches && e.touches.length ? e.touches[0] : e;
            const pxX = t.clientX - rect.left;
            const pxY = t.clientY - rect.top;
            return {
                x: pxX / rect.width,
                y: pxY / rect.height,
                pxX, pxY,
                w: rect.width,
                h: rect.height,
                esTactil: !!(e.touches && e.touches.length)
            };
        };

        const aPixeles = (pt, c) => ({ x: pt.x * c.w, y: pt.y * c.h });

        // Distancia de un punto al segmento AB, y en qué fracción del segmento cae.
        const distASegmento = (p, a, b) => {
            const vx = b.x - a.x, vy = b.y - a.y;
            const largo2 = vx * vx + vy * vy;
            if (largo2 < 1e-6) return { dist: Math.hypot(p.x - a.x, p.y - a.y), t: 0 };
            let t = ((p.x - a.x) * vx + (p.y - a.y) * vy) / largo2;
            t = Math.max(0, Math.min(1, t));
            return { dist: Math.hypot(p.x - (a.x + t * vx), p.y - (a.y + t * vy)), t };
        };

        const onStart = (e) => {
            if (!this.rawImage) return;
            const c = getCoords(e);
            const p = { x: c.pxX, y: c.pxY };

            // Objetivo más generoso con el dedo que con el puntero del mouse.
            const RADIO_ESQUINA = c.esTactil ? 34 : 24;
            const RADIO_LADO = c.esTactil ? 26 : 18;

            // 1) Esquinas primero: siempre ganan sobre los lados.
            let mejorDist = RADIO_ESQUINA;
            let idxEsquina = -1;
            this.corners.forEach((pt, idx) => {
                const q = aPixeles(pt, c);
                const d = Math.hypot(q.x - p.x, q.y - p.y);
                if (d < mejorDist) { mejorDist = d; idxEsquina = idx; }
            });

            if (idxEsquina !== -1) {
                this.activeCornerIndex = idxEsquina;
                this.activeEdgeIndex = -1;
                e.preventDefault();
                this.drawOverlay();
                this.showLoupe(c);
                return;
            }

            // 2) Lados: arrastrar un borde completo evita tener que ajustar sus dos
            //    esquinas por separado solo para enderezarlo.
            let mejorLado = RADIO_LADO;
            let idxLado = -1;
            for (let i = 0; i < 4; i++) {
                const a = aPixeles(this.corners[i], c);
                const b = aPixeles(this.corners[(i + 1) % 4], c);
                const r = distASegmento(p, a, b);
                // Se ignora el tramo pegado a las esquinas: ahí manda el agarre de esquina.
                if (r.t < 0.18 || r.t > 0.82) continue;
                if (r.dist < mejorLado) { mejorLado = r.dist; idxLado = i; }
            }

            if (idxLado !== -1) {
                this.activeEdgeIndex = idxLado;
                this.activeCornerIndex = -1;
                // Se guarda el estado inicial para poder desplazar el lado en bloque.
                this._edgeDrag = {
                    inicioPx: p,
                    a: { ...this.corners[idxLado] },
                    b: { ...this.corners[(idxLado + 1) % 4] }
                };
                e.preventDefault();
                this.drawOverlay();
            }
        };

        const onMove = (e) => {
            if (this.activeCornerIndex === -1 && this.activeEdgeIndex === -1) return;
            e.preventDefault();
            const c = getCoords(e);

            if (this.activeCornerIndex !== -1) {
                this.corners[this.activeCornerIndex].x = Math.max(0, Math.min(1, c.x));
                this.corners[this.activeCornerIndex].y = Math.max(0, Math.min(1, c.y));
                this.drawOverlay();
                this.showLoupe(c);
                return;
            }

            // Desplazamiento del lado: solo la componente PERPENDICULAR al propio lado, de
            // modo que el borde se acerca o se aleja quedando paralelo a sí mismo. Si se
            // aplicara el desplazamiento completo, el lado también resbalaría a lo largo de
            // sí mismo y las esquinas se irían saliendo del documento.
            const d = this._edgeDrag;
            if (!d) return;

            const a = aPixeles(d.a, c);
            const b = aPixeles(d.b, c);
            let nx = -(b.y - a.y), ny = (b.x - a.x);
            const largo = Math.hypot(nx, ny) || 1;
            nx /= largo; ny /= largo;

            const movX = c.pxX - d.inicioPx.x;
            const movY = c.pxY - d.inicioPx.y;
            const proy = movX * nx + movY * ny;

            const desplX = (proy * nx) / c.w;   // de vuelta a espacio normalizado
            const desplY = (proy * ny) / c.h;

            const iA = this.activeEdgeIndex;
            const iB = (this.activeEdgeIndex + 1) % 4;
            this.corners[iA] = {
                x: Math.max(0, Math.min(1, d.a.x + desplX)),
                y: Math.max(0, Math.min(1, d.a.y + desplY))
            };
            this.corners[iB] = {
                x: Math.max(0, Math.min(1, d.b.x + desplX)),
                y: Math.max(0, Math.min(1, d.b.y + desplY))
            };

            this.drawOverlay();
        };

        const onEnd = () => {
            if (this.activeCornerIndex === -1 && this.activeEdgeIndex === -1) return;

            // Señal para auditoría: hubo que corregir a mano el recorte automático. Es el
            // termómetro de si la detección funciona en la operación real; sin este dato,
            // un mal desempeño solo se detectaría por quejas del orientador.
            this.huboAjusteManual = true;

            this.activeCornerIndex = -1;
            this.activeEdgeIndex = -1;
            this._edgeDrag = null;
            // Re-ordenar al SOLTAR (nunca durante el arrastre, que haría "saltar" de
            // esquina a media operación): si el usuario cruzó una esquina al cuadrante
            // opuesto, esto evita que el polígono se dibuje como un lazo/moño.
            this.corners = this.orderCornerPoints(this.corners);
            this.hideLoupe();
            this.drawOverlay();
        };

        this.canvasOverlay.addEventListener('mousedown', onStart);
        // mousemove va en window, no en el canvas: si el puntero se sale del canvas a media
        // operación (muy común al llevar una esquina hasta el borde), con el listener en el
        // canvas el arrastre se congelaba aunque el botón siguiera pulsado.
        window.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onEnd);

        this.canvasOverlay.addEventListener('touchstart', onStart, { passive: false });
        this.canvasOverlay.addEventListener('touchmove', onMove, { passive: false });
        window.addEventListener('touchend', onEnd);
        window.addEventListener('touchcancel', onEnd);
    }

    async switchCamera() {
        this.currentFacingMode = (this.currentFacingMode === 'environment') ? 'user' : 'environment';
        await this.startCamera();
        return this.currentFacingMode;
    }

    async startCamera() {
        // SIEMPRE detener primero para limpiar el estado del LiveEdgeDetector
        // (autoCaptureFired, running, etc.), no solo cuando this.stream existe.
        this.stopCamera();

        const isHttp = window.location.protocol === 'http:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1';

        if (isHttp || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            this.showHttpsWarning();
            return false;
        }

        const isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
        const facing = this.currentFacingMode || 'environment';

        const intentos = [
            // Intento 1: Alta definición con cámara seleccionada
            {
                video: {
                    facingMode: { ideal: facing },
                    width: { ideal: 1920, min: 640 },
                    height: { ideal: 1080, min: 480 },
                    focusMode: { ideal: "continuous" }
                },
                audio: false
            },
            // Intento 2: Resolución estándar 720p
            {
                video: {
                    facingMode: { ideal: facing },
                    width: { ideal: 1280, min: 480 },
                    height: { ideal: 720, min: 360 }
                },
                audio: false
            },
            // Intento 3: Sólo facingMode
            {
                video: {
                    facingMode: facing
                },
                audio: false
            },
            // Intento 4: Genérico
            {
                video: true,
                audio: false
            }
        ];

        for (const constraints of intentos) {
            try {
                this.stream = await navigator.mediaDevices.getUserMedia(constraints);
                if (this.stream && this.videoElement) {
                    this.videoElement.srcObject = this.stream;
                    this.videoElement.setAttribute('playsinline', 'true');
                    this.videoElement.setAttribute('webkit-playsinline', 'true');
                    this.videoElement.setAttribute('autoplay', 'true');
                    this.videoElement.muted = true;
                    try {
                        await this.videoElement.play();
                    } catch (playErr) {
                        this.videoElement.onloadedmetadata = () => {
                            this.videoElement.play().catch(e => console.warn("[Scanner] play error:", e));
                        };
                    }

                    try {
                        const track = this.stream.getVideoTracks()[0];
                        if (track && track.applyConstraints) {
                            track.applyConstraints({
                                advanced: [{ focusMode: "continuous" }]
                            }).catch(() => {});
                        }
                    } catch (fErr) {}

                    this._setupCaptureCapabilities();
                    this.startLiveDetection();
                    return true;
                }
            } catch (err) {
                console.warn("[Scanner] Intento de cámara falló:", err.name, err.message);
            }
        }

        this.handleCameraError(new Error("No se pudo iniciar el stream de video de la cámara"));
        return false;
    }

    // Detecta, para el track de video activo, si hay ImageCapture (captura a resolución
    // nativa del sensor, separada del stream de preview) y si el dispositivo soporta linterna.
    _setupCaptureCapabilities() {
        this.imageCapture = null;
        this.torchSupported = false;

        const track = this.stream ? this.stream.getVideoTracks()[0] : null;
        if (!track) return;

        if (typeof ImageCapture !== 'undefined') {
            try {
                const ic = new ImageCapture(track);
                if (typeof ic.takePhoto === 'function') {
                    this.imageCapture = ic;
                }
            } catch (err) {
                console.warn("ImageCapture no disponible en este navegador/dispositivo:", err);
            }
        }

        try {
            const capabilities = track.getCapabilities ? track.getCapabilities() : {};
            this.torchSupported = !!capabilities.torch;
        } catch (err) {
            this.torchSupported = false;
        }
    }

    showHttpsWarning() {
        const httpsUrl = window.location.href.replace('http:', 'https:');
        const alertBox = document.getElementById('camera-https-alert');
        if (alertBox) {
            alertBox.innerHTML = `
                <i class="fa-solid fa-lock me-1"></i> <strong>Conexión Segura (HTTPS) Requerida en Hostinger:</strong><br>
                Los navegadores bloquean la cámara web en conexiones HTTP no seguras.<br>
                <a href="${httpsUrl}" class="btn btn-sm btn-light text-dark fw-bold mt-1 me-2 shadow-sm">
                    <i class="fa-solid fa-shield-halved me-1"></i> Cambiar a HTTPS ahora
                </a>
                <button type="button" class="btn btn-sm btn-outline-light fw-bold mt-1" onclick="document.getElementById('input-foto-nativa').click()">
                    <i class="fa-solid fa-camera me-1"></i> Usar Cámara / Galería Nativa
                </button>
            `;
            alertBox.classList.remove('d-none');
        }
        console.warn("[Scanner] Conexión HTTP no segura. Se requiere HTTPS para WebRTC:", httpsUrl);
    }

    handleCameraError(err) {
        console.warn("[Scanner] Error de cámara:", err);
        const alertBox = document.getElementById('camera-https-alert');
        if (alertBox) {
            let msg = 'No se pudo acceder a la cámara en vivo.';
            if (err && (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError')) {
                msg = 'Permiso de cámara denegado en el navegador. Habilítelo en el icono del candado de la barra de direcciones.';
            } else if (err && (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError')) {
                msg = 'No se detectó cámara web conectada a este equipo.';
            }
            alertBox.innerHTML = `
                <i class="fa-solid fa-triangle-exclamation me-1"></i> <strong>Aviso de Cámara:</strong> ${msg}<br>
                <button type="button" class="btn btn-sm btn-outline-light fw-bold mt-1" onclick="document.getElementById('input-foto-nativa').click()">
                    <i class="fa-solid fa-camera me-1"></i> Tomar Foto / Galería
                </button>
            `;
            alertBox.classList.remove('d-none');
        }
    }

    stopCamera() {
        this.stopLiveDetection();
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
    }

    // Arranca el bucle de detección de bordes en vivo (Worker+OffscreenCanvas si el
    // navegador lo soporta, si no cae a hilo principal throttlado). Dibuja el resultado
    // sobre canvasLiveOverlay: verde cuando detecta un documento, gris/neutro si no.
    startLiveDetection() {
        if (!this.canvasLiveOverlay || !this.videoElement) return;

        try {
            if (typeof LiveEdgeDetector !== 'undefined') {
                if (!this.liveDetector) {
                    this.liveDetector = new LiveEdgeDetector({
                        videoElement: this.videoElement,
                        overlayCanvas: this.canvasLiveOverlay,
                        onResult: (found, cornersFrac) => {
                            this.liveCorners = found ? cornersFrac : null;
                        },
                        onAutoCapture: () => {
                            if (typeof this.onAutoCaptureRequested === 'function') {
                                this.onAutoCaptureRequested();
                            }
                        },
                        onProgress: (p) => {
                            if (typeof this.onCountdownProgress === 'function') {
                                this.onCountdownProgress(p);
                            }
                        }
                    });
                }
                this.liveDetector.setDocType(this.docType);
                this.liveDetector.setDebugMode(this.debugMode, this.debugCanvas);
                this.liveDetector.start();
            }
        } catch (e) {
            console.warn("[Scanner] LiveEdgeDetector iniciando...", e);
        }
    }

    stopLiveDetection() {
        if (this.liveDetector) {
            this.liveDetector.stop();
        }
        this.liveCorners = null;
    }

    async toggleTorch() {
        if (!this.stream) return false;
        const track = this.stream.getVideoTracks()[0];
        if (!track) return false;

        const capabilities = track.getCapabilities ? track.getCapabilities() : {};
        if (capabilities.torch) {
            const settings = track.getSettings();
            const currentTorch = settings.torch || false;
            await track.applyConstraints({
                advanced: [{ torch: !currentTorch }]
            });
            return !currentTorch;
        } else {
            alert("Su dispositivo o navegador no soporta linterna.");
            return false;
        }
    }

    // Captura la foto en la mayor resolución disponible. Vía ImageCapture.takePhoto()
    // cuando el navegador lo soporta (usa el pipeline de foto fija del sensor, normalmente
    async takeSnapshot() {
        if (!this.videoElement) return null;
        return this._takeSnapshotViaCanvas();
    }

    _takeSnapshotViaCanvas() {
        if (!this.videoElement) return null;
        const vw = this.videoElement.videoWidth || 1280;
        const vh = this.videoElement.videoHeight || 720;

        const snapCanvas = document.createElement('canvas');
        snapCanvas.width = vw;
        snapCanvas.height = vh;
        const ctx = snapCanvas.getContext('2d');
        try {
            ctx.drawImage(this.videoElement, 0, 0, vw, vh);
        } catch (e) {
            console.warn("[Scanner] Error drawing video to canvas:", e);
        }

        this._lastCaptureMethod = `canvas (${vw}x${vh})`;
        this.loadCapturedImage(snapCanvas);
        return Promise.resolve(snapCanvas);
    }

    _blobToImage(blob) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const url = URL.createObjectURL(blob);
            img.onload = () => {
                URL.revokeObjectURL(url);
                resolve(img);
            };
            img.onerror = (err) => {
                URL.revokeObjectURL(url);
                reject(err);
            };
            img.src = url;
        });
    }

    getRawDataUrl(quality = 0.95) {
        if (!this.rawImage) return null;
        try {
            if (this.rawImage instanceof HTMLCanvasElement || (typeof HTMLCanvasElement !== 'undefined' && this.rawImage.toDataURL)) {
                return this.rawImage.toDataURL('image/jpeg', quality);
            }
            if (this.rawImage.src && typeof this.rawImage.src === 'string' && this.rawImage.src.startsWith('data:')) {
                return this.rawImage.src;
            }
            const c = document.createElement('canvas');
            c.width = this.rawWidth || 1280;
            c.height = this.rawHeight || 720;
            const ctx = c.getContext('2d');
            ctx.drawImage(this.rawImage, 0, 0, c.width, c.height);
            return c.toDataURL('image/jpeg', quality);
        } catch (e) {
            console.warn('[Scanner] Error en getRawDataUrl:', e);
            return (this.rawImage && this.rawImage.src) ? this.rawImage.src : null;
        }
    }

    loadCapturedImage(imgElement) {
        this.rawImage = imgElement;
        this.previewImage = null;   // se reconstruye con construirPreviewRealzado()
        this.rawWidth = imgElement.naturalWidth || imgElement.width || 1280;
        this.rawHeight = imgElement.naturalHeight || imgElement.height || 720;
        this.rotationAngle = 0;

        console.log(`[Scanner] Foto capturada: ${this.rawWidth}x${this.rawHeight}px (método: ${this._lastCaptureMethod || 'desconocido'})`);

        this.autoDetectEdges();
    }

    autoDetectEdges(docType = null) {
        if (!this.rawImage) return;

        const tipo = docType || this.docType || null;
        if (tipo === 'CEDULA') {
            // Encuadre optimizado al tamaño real de la Cédula (tarjeta horizontal en pantalla vertical)
            const isPortrait = this.rawHeight > this.rawWidth;
            let w = 0.90;
            let h = isPortrait ? ((w * this.rawWidth) / (1.586 * this.rawHeight)) : 0.76;
            if (!isPortrait) w = (h * this.rawHeight * 1.586) / this.rawWidth;
            
            const minX = Math.max(0.02, (1 - w) / 2);
            const maxX = Math.min(0.98, minX + w);
            const minY = Math.max(0.04, (1 - h) / 2);
            const maxY = Math.min(0.96, minY + h);

            this.corners = [
                { x: minX, y: minY },
                { x: maxX, y: minY },
                { x: maxX, y: maxY },
                { x: minX, y: maxY }
            ];
            return;
        }

        // Para Orden Médica / otros documentos: 99% de la hoja completa
        this.corners = [
            { x: 0.01, y: 0.01 },
            { x: 0.99, y: 0.01 },
            { x: 0.99, y: 0.99 },
            { x: 0.01, y: 0.99 }
        ];
    }

    /**
     * Construye la versión REALZADA de la foto completa, que es la que se muestra en la
     * pantalla de revisión con la máscara de recorte encima.
     *
     * Se realza el fotograma entero (no el recorte) a propósito: así el orientador ve la
     * calidad final Y puede seguir arrastrando las esquinas hacia afuera para recuperar
     * algo que la detección hubiera dejado fuera. Sobre un recorte ya aplicado eso sería
     * imposible.
     *
     * Se trabaja sobre una copia reducida: el realce usa kernels grandes y sobre una foto
     * de varios megapíxeles tardaría segundos, mientras que el visor no pasa de ~600px.
     */
    async construirPreviewRealzado(filterName = 'magic') {
        this.previewImage = null;
        if (!this.rawImage) return null;

        // Sin realce: no se construye previa alguna y `displayImage` cae a la foto cruda,
        // que es exactamente lo que va a quedar en el PDF. Así lo que revisa el orientador
        // coincide con el resultado.
        if (this.sinRealce(filterName)) return null;

        if (typeof cv === 'undefined' || !cv.Mat) return null;

        const MAX_PREVIEW = 1100;
        const escala = Math.min(1, MAX_PREVIEW / Math.max(this.rawWidth, this.rawHeight));
        const w = Math.max(1, Math.round(this.rawWidth * escala));
        const h = Math.max(1, Math.round(this.rawHeight * escala));

        const base = document.createElement('canvas');
        base.width = w;
        base.height = h;
        base.getContext('2d').drawImage(this.rawImage, 0, 0, w, h);

        let src = null, out = null;
        try {
            src = cv.imread(base);
            out = this.enhanceMat(src, filterName);
            if (!out) return null;

            const destino = document.createElement('canvas');
            cv.imshow(destino, out);
            this.previewImage = destino;
            return destino;
        } catch (err) {
            console.warn('[Scanner] No se pudo realzar la vista previa; se usa la foto sin realzar:', err);
            return null;
        } finally {
            if (src) src.delete();
            if (out) out.delete();
        }
    }

    // Imagen que se dibuja en la pantalla de revisión: la realzada si se pudo construir.
    // El recorte final SIEMPRE se calcula sobre rawImage, que conserva la resolución.
    get displayImage() {
        return this._customDisplayImage || this.previewImage || this.rawImage;
    }

    set displayImage(val) {
        this._customDisplayImage = val;
    }

    /**
     * ¿Dos cuadriláteros señalan aproximadamente la misma región? Se compara el centroide
     * (tolerancia 12% de la diagonal del frame) y el área (tolerancia 35%). Sirve para
     * decidir si la detección sobre la foto fija está afinando el mismo documento que
     * venía siguiendo el preview, o si se fue a otro objeto.
     */
    _quadsAgree(a, b) {
        const centro = (q) => ({
            x: (q[0].x + q[1].x + q[2].x + q[3].x) / 4,
            y: (q[0].y + q[1].y + q[2].y + q[3].y) / 4
        });
        const area = (q) => {
            let s = 0;
            for (let i = 0; i < 4; i++) {
                const j = (i + 1) % 4;
                s += q[i].x * q[j].y - q[j].x * q[i].y;
            }
            return Math.abs(s / 2);
        };

        const ca = centro(a), cb = centro(b);
        if (Math.hypot(ca.x - cb.x, ca.y - cb.y) > 0.12 * Math.SQRT2) return false;

        const aa = area(a), ab = area(b);
        if (aa <= 0 || ab <= 0) return false;
        return Math.abs(aa - ab) / Math.max(aa, ab) <= 0.35;
    }

    // Tipo de documento activo. Se propaga a la detección en vivo, que lo usa para
    // restringir la proporción aceptable de los candidatos (Bloque B).
    setDocType(docType) {
        this.docType = docType;
        if (this.liveDetector) this.liveDetector.setDocType(docType);
    }

    setDebugMode(on, debugCanvas) {
        this.debugMode = !!on;
        this.debugCanvas = debugCanvas || this.debugCanvas;
        if (this.liveDetector) this.liveDetector.setDebugMode(this.debugMode, this.debugCanvas);
    }

    /**
     * Relación de aspecto (ancho/alto) a forzar según el tipo de documento seleccionado.
     * Devuelve null cuando no se debe forzar y hay que respetar el cuadrilátero detectado.
     */
    getTargetAspectRatio(categoria) {
        return null;
    }

    /**
     * Estima el fondo de iluminación (sombras, viñeteo, luz despareja) de una imagen en
     * escala de grises. El fondo es información de baja frecuencia, así que se calcula
     * sobre una versión reducida y luego se reescala: con kernels de 25x25 y medianBlur de
     * 21 sobre una foto de varios megapíxeles el cálculo directo tardaría segundos.
     * @returns {cv.Mat} fondo estimado, del mismo tamaño que la entrada (el llamador lo borra)
     */
    _estimateBackground(gray) {
        const SMALL_W = 600;
        const scale = Math.min(1, SMALL_W / gray.cols);

        const small = new cv.Mat();
        const bgSmall = new cv.Mat();
        const bg = new cv.Mat();
        let kernel = null;

        try {
            if (scale < 1) {
                cv.resize(gray, small, new cv.Size(
                    Math.max(1, Math.round(gray.cols * scale)),
                    Math.max(1, Math.round(gray.rows * scale))
                ), 0, 0, cv.INTER_AREA);
            } else {
                gray.copyTo(small);
            }

            // MORPH_CLOSE con kernel grande borra el texto y deja solo la iluminación;
            // el medianBlur posterior suaviza lo que haya quedado del contenido.
            kernel = cv.getStructuringElement(cv.MORPH_RECT, new cv.Size(25, 25));
            cv.morphologyEx(small, bgSmall, cv.MORPH_CLOSE, kernel);
            cv.medianBlur(bgSmall, bgSmall, 21);

            cv.resize(bgSmall, bg, new cv.Size(gray.cols, gray.rows), 0, 0, cv.INTER_LINEAR);
            return bg;
        } finally {
            small.delete();
            bgSmall.delete();
            if (kernel) kernel.delete();
        }
    }

    // Normaliza la iluminación de un canal 8-bit: divide por el fondo estimado, con lo
    // que las sombras desaparecen y el papel queda blanco parejo.
    _normalizeIllumination(gray) {
        const bg = this._estimateBackground(gray);
        const norm = new cv.Mat();
        try {
            cv.divide(gray, bg, norm, 255);
            return norm;
        } finally {
            bg.delete();
        }
    }

    _applyCLAHE(channel) {
        let clahe = null;
        try {
            clahe = new cv.CLAHE(2.0, new cv.Size(8, 8));
        } catch (e) {
            try { clahe = cv.createCLAHE(2.0, new cv.Size(8, 8)); } catch (e2) { clahe = null; }
        }
        if (!clahe) return false;
        try {
            clahe.apply(channel, channel);
            return true;
        } finally {
            if (clahe.delete) clahe.delete();
        }
    }

    /**
     * Modo Color: realce SUAVE, pensado para documentos con contenido fotográfico
     * (la foto del titular y los hologramas de la cédula).
     *
     * IMPORTANTE — aquí NO se usa `_normalizeIllumination()`, a diferencia de los modos
     * Gris y B/N. Esa función divide la imagen por el fondo estimado, que es justo lo que
     * hace falta para volver blanco el papel de un documento de texto... y lo que destruye
     * cualquier contenido fotográfico: en toda zona de tono plano la imagen se parece a su
     * propio fondo, así que el cociente se va a 255.
     *
     * Medido sobre una escena fotográfica de prueba: con la división, el percentil 5 subía
     * de 65 a 251 y el 65% de los píxeles quedaba quemado (irrecuperable), es decir la
     * imagen entera colapsaba a blanco. Solo con CLAHE el rango tonal se conserva
     * (103 → 107) y no se quema ni un píxel.
     *
     * Si algún día hace falta corregir sombras en color, el camino NO es reintroducir la
     * división: se probó también un aplanado aditivo (L - fondo + media) y sobre contenido
     * fotográfico hunde el rango tonal de 103 a 8, porque confunde el degradado propio del
     * rostro con iluminación.
     */
    _enhanceColor(srcRgba) {
        const rgb = new cv.Mat();
        const lab = new cv.Mat();
        const chans = new cv.MatVector();
        const hsv = new cv.Mat();
        const hsvChans = new cv.MatVector();
        const blurred = new cv.Mat();
        const sharp = new cv.Mat();
        const out = new cv.Mat();

        // OJO: MatVector.get(i) devuelve una Mat nueva que hay que liberar aparte; no
        // basta con borrar el MatVector. Por eso cada canal se guarda en su variable y se
        // libera en el finally: si no, cada escaneo dejaba varios megabytes en el heap de
        // WASM y tras unas cuantas páginas se agotaba.
        let L = null, A = null, B = null;
        let H = null, S = null, V = null;
        let merged = null, hsvMerged = null;

        try {
            cv.cvtColor(srcRgba, rgb, cv.COLOR_RGBA2RGB);
            cv.cvtColor(rgb, lab, cv.COLOR_RGB2Lab);
            cv.split(lab, chans);

            L = chans.get(0);
            A = chans.get(1);
            B = chans.get(2);

            // Solo contraste local sobre la luminancia; a y b intactos, así el color no se
            // altera. Sin división por el fondo (ver el comentario del método).
            this._applyCLAHE(L);

            merged = new cv.MatVector();
            merged.push_back(L);
            merged.push_back(A);
            merged.push_back(B);
            cv.merge(merged, lab);
            cv.cvtColor(lab, rgb, cv.COLOR_Lab2RGB);

            // Realce muy leve de saturación: lo justo para que los sellos y el fondo de
            // seguridad no se vean lavados, sin virar los tonos de piel de la foto.
            cv.cvtColor(rgb, hsv, cv.COLOR_RGB2HSV);
            cv.split(hsv, hsvChans);
            H = hsvChans.get(0);
            S = hsvChans.get(1);
            V = hsvChans.get(2);
            S.convertTo(S, -1, 1.08, 0);

            hsvMerged = new cv.MatVector();
            hsvMerged.push_back(H);
            hsvMerged.push_back(S);
            hsvMerged.push_back(V);
            cv.merge(hsvMerged, hsv);
            cv.cvtColor(hsv, rgb, cv.COLOR_HSV2RGB);

            // Máscara de enfoque contenida: la anterior (1.5/-0.5 con sigma 3) marcaba
            // halos y granulaba las zonas de tono plano de la foto.
            cv.GaussianBlur(rgb, blurred, new cv.Size(0, 0), 2);
            cv.addWeighted(rgb, 1.25, blurred, -0.25, 0, sharp);

            cv.cvtColor(sharp, out, cv.COLOR_RGB2RGBA);
            return out;
        } catch (err) {
            out.delete();
            throw err;
        } finally {
            rgb.delete(); lab.delete(); chans.delete();
            hsv.delete(); hsvChans.delete(); blurred.delete(); sharp.delete();
            [L, A, B, H, S, V].forEach(m => { if (m) m.delete(); });
            if (merged) merged.delete();
            if (hsvMerged) hsvMerged.delete();
        }
    }

    // Modo Gris: imagen normalizada sin binarizar (conserva medios tonos, útil cuando el
    // documento trae sellos, firmas tenues o fotos).
    _enhanceGray(srcRgba) {
        const gray = new cv.Mat();
        const out = new cv.Mat();
        let norm = null;
        try {
            cv.cvtColor(srcRgba, gray, cv.COLOR_RGBA2GRAY);
            norm = this._normalizeIllumination(gray);
            cv.cvtColor(norm, out, cv.COLOR_GRAY2RGBA);
            return out;
        } catch (err) {
            out.delete();
            throw err;
        } finally {
            gray.delete();
            if (norm) norm.delete();
        }
    }

    // Modo Blanco y Negro: umbral adaptativo sobre la imagen ya normalizada. Da el texto
    // más legible y el archivo más liviano.
    _enhanceBW(srcRgba) {
        const gray = new cv.Mat();
        const bw = new cv.Mat();
        const out = new cv.Mat();
        let norm = null;
        try {
            cv.cvtColor(srcRgba, gray, cv.COLOR_RGBA2GRAY);
            norm = this._normalizeIllumination(gray);
            cv.adaptiveThreshold(norm, bw, 255, cv.ADAPTIVE_THRESH_GAUSSIAN_C, cv.THRESH_BINARY, 31, 12);
            cv.cvtColor(bw, out, cv.COLOR_GRAY2RGBA);
            return out;
        } catch (err) {
            out.delete();
            throw err;
        } finally {
            gray.delete(); bw.delete();
            if (norm) norm.delete();
        }
    }

    /**
     * Aplica el modo de realce solicitado sobre la imagen ya des-perspectivada.
     * @returns {cv.Mat} nueva Mat RGBA, o la original si el modo no aplica realce.
     */
    enhanceMat(srcRgba, filterName) {
        try {
            if (filterName === 'grayscale') return this._enhanceGray(srcRgba);
            if (filterName === 'binary') return this._enhanceBW(srcRgba);
            return this._enhanceColor(srcRgba);
        } catch (err) {
            console.warn("[Scanner] Falló el realce con OpenCV, se usa la imagen sin realzar:", err);
            return null;
        }
    }

    // Delega en la implementación compartida (scanner_detect.js) para que el orden de
    // esquinas sea idéntico en detección en vivo, detección sobre la foto y warp final.
    orderCornerPoints(pts) {
        if (window.SISPAM_Scanner && window.SISPAM_Scanner.orderQuadPoints) {
            return window.SISPAM_Scanner.orderQuadPoints(pts);
        }

        // Respaldo equivalente por si scanner_detect.js no llegó a cargar.
        const cx = (pts[0].x + pts[1].x + pts[2].x + pts[3].x) / 4;
        const cy = (pts[0].y + pts[1].y + pts[2].y + pts[3].y) / 4;
        const byAngle = [...pts].sort(
            (a, b) => Math.atan2(a.y - cy, a.x - cx) - Math.atan2(b.y - cy, b.x - cx)
        );
        let startIdx = 0, minSum = Infinity;
        for (let i = 0; i < 4; i++) {
            const sum = byAngle[i].x + byAngle[i].y;
            if (sum < minSum) { minSum = sum; startIdx = i; }
        }
        return [
            byAngle[startIdx],
            byAngle[(startIdx + 1) % 4],
            byAngle[(startIdx + 2) % 4],
            byAngle[(startIdx + 3) % 4]
        ];
    }

    setCropPreset(preset) {
        if (preset === 'full') {
            this.corners = [
                { x: 0, y: 0 },
                { x: 1, y: 0 },
                { x: 1, y: 1 },
                { x: 0, y: 1 }
            ];
        } else if (preset === 'a4') {
            this.corners = [
                { x: 0.15, y: 0.05 },
                { x: 0.85, y: 0.05 },
                { x: 0.85, y: 0.95 },
                { x: 0.15, y: 0.95 }
            ];
        } else if (preset === 'id') {
            this.corners = [
                { x: 0.15, y: 0.25 },
                { x: 0.85, y: 0.25 },
                { x: 0.85, y: 0.75 },
                { x: 0.15, y: 0.75 }
            ];
        } else if (preset === 'auto') {
            this.autoDetectEdges(this.docType);
        }
        this.drawOverlay();
    }

    /**
     * Gira 90° la foto capturada, junto con el cuadrilátero de recorte.
     *
     * Antes esto solo incrementaba `this.rotationAngle`, un valor que ningún método leía:
     * el botón "Rotar" no producía ningún efecto visible. En vez de arrastrar ese ángulo
     * por cada punto donde se dibuja o procesa la imagen (overlay, lupa, warp final), aquí
     * se rota el mapa de bits de verdad hacia un canvas nuevo y se remapean las esquinas.
     * Así todo lo que viene después sigue trabajando con una imagen ya derecha, sin saber
     * que hubo rotación. `cv.imread()` y `drawImage()` aceptan un canvas igual que un
     * <img>, de modo que el resto del flujo no cambia.
     */
    rotateImage(direction = 'right') {
        if (!this.rawImage) return;

        const horario = direction !== 'left';

        // Gira cualquier fuente 90° hacia un canvas nuevo con los lados intercambiados.
        const girar = (fuente) => {
            if (!fuente) return null;
            const a = fuente.naturalWidth || fuente.width;
            const b = fuente.naturalHeight || fuente.height;

            const destino = document.createElement('canvas');
            destino.width = b;
            destino.height = a;

            const ctx = destino.getContext('2d');
            ctx.save();
            if (horario) {
                ctx.translate(b, 0);
                ctx.rotate(Math.PI / 2);
            } else {
                ctx.translate(0, a);
                ctx.rotate(-Math.PI / 2);
            }
            ctx.drawImage(fuente, 0, 0, a, b);
            ctx.restore();
            return destino;
        };

        const rotada = girar(this.rawImage);
        // La previsualización realzada se gira igual: si no, quedaría desalineada respecto
        // al cuadrilátero y el orientador vería el recorte sobre una imagen que no coincide.
        this.previewImage = girar(this.previewImage);

        // Las esquinas viven en fracciones 0..1, así que el remapeo no depende del tamaño.
        this.corners = this.corners.map(p => horario
            ? { x: 1 - p.y, y: p.x }
            : { x: p.y, y: 1 - p.x }
        );

        this.rawImage = rotada;
        this.rawWidth = rotada.width;
        this.rawHeight = rotada.height;
        this.rotationAngle = (this.rotationAngle + (horario ? 90 : -90) + 360) % 360;

        this.corners = this.orderCornerPoints(this.corners);
        this.drawOverlay();
    }

    drawOverlay() {
        if (!this.canvasOverlay) return;

        // Si rawImage es null pero tenemos páginas escaneadas, cargar la última para visualización en revisión
        if (!this.rawImage && this.scannedPages && this.scannedPages.length > 0) {
            const lastPage = this.scannedPages[this.scannedPages.length - 1];
            if (lastPage && lastPage.dataUrl) {
                const img = new Image();
                img.onload = () => {
                    this.rawImage = img;
                    this.displayImage = img;
                    this.rawWidth = img.naturalWidth || img.width;
                    this.rawHeight = img.naturalHeight || img.height;
                    this.corners = [
                        { x: 0.005, y: 0.005 },
                        { x: 0.995, y: 0.005 },
                        { x: 0.995, y: 0.995 },
                        { x: 0.005, y: 0.995 }
                    ];
                    this.drawOverlay();
                };
                img.src = lastPage.dataUrl;
                return;
            }
        }

        if (!this.rawImage) return;

        const wrapper = this.canvasOverlay.parentElement;
        const maxW = (wrapper && wrapper.clientWidth > 100) ? wrapper.clientWidth : (window.innerWidth || 600);
        const maxH = (wrapper && wrapper.clientHeight > 200) ? wrapper.clientHeight : Math.round((window.innerHeight || 700) * 0.70);

        const imgAspect = this.rawWidth / this.rawHeight;
        const containerAspect = maxW / maxH;

        let displayW, displayH;
        if (imgAspect > containerAspect) {
            displayW = maxW;
            displayH = maxW / imgAspect;
        } else {
            displayH = maxH;
            displayW = maxH * imgAspect;
        }

        displayW = Math.round(displayW);
        displayH = Math.round(displayH);

        this.canvasOverlay.width = displayW;
        this.canvasOverlay.height = displayH;
        this.canvasOverlay.style.width = displayW + 'px';
        this.canvasOverlay.style.height = displayH + 'px';

        const ctx = this.canvasOverlay.getContext('2d');
        ctx.clearRect(0, 0, displayW, displayH);

        ctx.save();
        const imgToDraw = this.displayImage || this.rawImage;
        if (imgToDraw) {
            try {
                ctx.drawImage(imgToDraw, 0, 0, displayW, displayH);
            } catch (errDraw) {
                console.warn("[Scanner] drawOverlay drawImage error:", errDraw);
            }
        }
        ctx.restore();

        const pts = this.corners.map(p => ({
            x: p.x * displayW,
            y: p.y * displayH
        }));

        ctx.save();
        ctx.fillStyle = 'rgba(15, 23, 42, 0.65)';
        ctx.beginPath();
        ctx.rect(0, 0, displayW, displayH);
        ctx.moveTo(pts[0].x, pts[0].y);
        ctx.lineTo(pts[1].x, pts[1].y);
        ctx.lineTo(pts[2].x, pts[2].y);
        ctx.lineTo(pts[3].x, pts[3].y);
        ctx.closePath();
        ctx.fill('evenodd');
        ctx.restore();

        ctx.save();
        ctx.strokeStyle = '#00f2fe';
        ctx.lineWidth = 3;
        ctx.shadowColor = '#00f2fe';
        ctx.shadowBlur = 8;
        ctx.beginPath();
        ctx.moveTo(pts[0].x, pts[0].y);
        ctx.lineTo(pts[1].x, pts[1].y);
        ctx.lineTo(pts[2].x, pts[2].y);
        ctx.lineTo(pts[3].x, pts[3].y);
        ctx.closePath();
        ctx.stroke();

        ctx.strokeStyle = 'rgba(255, 255, 255, 0.35)';
        ctx.lineWidth = 1;
        ctx.shadowBlur = 0;
        for (let i = 1; i <= 2; i++) {
            let t = i / 3;
            ctx.beginPath();
            ctx.moveTo(pts[0].x + (pts[3].x - pts[0].x) * t, pts[0].y + (pts[3].y - pts[0].y) * t);
            ctx.lineTo(pts[1].x + (pts[2].x - pts[1].x) * t, pts[1].y + (pts[2].y - pts[1].y) * t);
            ctx.stroke();

            ctx.beginPath();
            ctx.moveTo(pts[0].x + (pts[1].x - pts[0].x) * t, pts[0].y + (pts[1].y - pts[0].y) * t);
            ctx.lineTo(pts[3].x + (pts[2].x - pts[3].x) * t, pts[3].y + (pts[2].y - pts[3].y) * t);
            ctx.stroke();
        }
        ctx.restore();

        // Tiradores en el centro de cada lado: sin una marca visible nadie descubre que el
        // borde completo se puede arrastrar. El lado activo se pinta resaltado y más grueso.
        for (let i = 0; i < 4; i++) {
            const a = pts[i];
            const b = pts[(i + 1) % 4];
            const mx = (a.x + b.x) / 2;
            const my = (a.y + b.y) / 2;

            let dx = b.x - a.x, dy = b.y - a.y;
            const largo = Math.hypot(dx, dy) || 1;
            dx /= largo; dy /= largo;

            // Barra corta apoyada sobre el propio lado, para que se lea como "mover borde".
            const mitad = Math.min(18, largo * 0.16);
            const activo = i === this.activeEdgeIndex;

            ctx.save();
            ctx.strokeStyle = activo ? '#ff0055' : 'rgba(0, 242, 254, 0.9)';
            ctx.lineWidth = activo ? 8 : 6;
            ctx.lineCap = 'round';
            ctx.shadowColor = 'rgba(0,0,0,0.6)';
            ctx.shadowBlur = 5;
            ctx.beginPath();
            ctx.moveTo(mx - dx * mitad, my - dy * mitad);
            ctx.lineTo(mx + dx * mitad, my + dy * mitad);
            ctx.stroke();
            ctx.restore();
        }

        pts.forEach((pt, idx) => {
            const isActive = idx === this.activeCornerIndex;
            ctx.save();
            ctx.fillStyle = isActive ? '#ff0055' : '#00f2fe';
            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth = 3;
            ctx.shadowColor = 'rgba(0,0,0,0.6)';
            ctx.shadowBlur = 6;

            ctx.beginPath();
            ctx.arc(pt.x, pt.y, isActive ? 16 : 12, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();

            ctx.fillStyle = '#ffffff';
            ctx.beginPath();
            ctx.arc(pt.x, pt.y, 4, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
        });
    }

    showLoupe(coords) {
        if (!this.canvasLoupe || !this.rawImage || this.activeCornerIndex === -1) return;

        const loupeContainer = document.getElementById('loupe-container');
        if (loupeContainer) {
            loupeContainer.classList.remove('d-none');
            // La lupa vive fija arriba a la derecha, justo donde estorba cuando se ajusta
            // la esquina superior derecha: quedaba tapando exactamente lo que amplía. Si el
            // punto en edición entra en esa zona, se pasa al lado opuesto.
            const enZonaLupa = coords && coords.x > 0.55 && coords.y < 0.45;
            loupeContainer.classList.toggle('loupe-izquierda', enZonaLupa);
        }

        const ctxLoupe = this.canvasLoupe.getContext('2d');
        const size = this.canvasLoupe.width = 130;
        this.canvasLoupe.height = 130;

        ctxLoupe.clearRect(0, 0, size, size);

        // La lupa amplía la MISMA imagen que se ve en el visor (la realzada), para que lo
        // ampliado coincida con lo que hay debajo del dedo.
        const fuente = this.displayImage;
        const fw = fuente.naturalWidth || fuente.width;
        const fh = fuente.naturalHeight || fuente.height;

        const imgX = coords.x * fw;
        const imgY = coords.y * fh;

        const zoom = 3;
        const sw = size / zoom;
        const sh = size / zoom;
        const sx = Math.max(0, Math.min(fw - sw, imgX - sw / 2));
        const sy = Math.max(0, Math.min(fh - sh, imgY - sh / 2));

        ctxLoupe.save();
        ctxLoupe.drawImage(fuente, sx, sy, sw, sh, 0, 0, size, size);

        ctxLoupe.strokeStyle = '#ff0055';
        ctxLoupe.lineWidth = 2;
        ctxLoupe.beginPath();
        ctxLoupe.moveTo(size / 2 - 15, size / 2);
        ctxLoupe.lineTo(size / 2 + 15, size / 2);
        ctxLoupe.moveTo(size / 2, size / 2 - 15);
        ctxLoupe.lineTo(size / 2, size / 2 + 15);
        ctxLoupe.stroke();

        ctxLoupe.restore();
    }

    hideLoupe() {
        const loupeContainer = document.getElementById('loupe-container');
        if (loupeContainer) loupeContainer.classList.add('d-none');
    }

    processScan(filterName = 'magic', targetCategory = null) {
        if (!this.rawImage || !this.canvasProcessed) return;

        this.currentFilter = filterName;

        // Ordenar SIEMPRE antes de transformar. Antes solo se ordenaban las esquinas
        // auto-detectadas: si el usuario arrastraba una esquina cruzando a otro cuadrante,
        // el orden dejaba de ser [sup-izq, sup-der, inf-der, inf-izq] y warpPerspective
        // producía una imagen espejada o retorcida.
        const orderedFrac = this.orderCornerPoints(this.corners);

        const ptsImg = orderedFrac.map(p => ({
            x: p.x * this.rawWidth,
            y: p.y * this.rawHeight
        }));

        const topW = Math.hypot(ptsImg[1].x - ptsImg[0].x, ptsImg[1].y - ptsImg[0].y);
        const botW = Math.hypot(ptsImg[2].x - ptsImg[3].x, ptsImg[2].y - ptsImg[3].y);
        let outW = Math.round(Math.max(topW, botW));

        const leftH = Math.hypot(ptsImg[3].x - ptsImg[0].x, ptsImg[3].y - ptsImg[0].y);
        const rightH = Math.hypot(ptsImg[2].x - ptsImg[1].x, ptsImg[2].y - ptsImg[1].y);
        let outH = Math.round(Math.max(leftH, rightH));

        if (outW < 1 || outH < 1) return;

        // Forzar relación de aspecto según el tipo de documento, si aplica.
        let ratio = this.getTargetAspectRatio(targetCategory);
        if (ratio) {
            // El documento puede haberse fotografiado en horizontal o vertical: se usa la
            // orientación del cuadrilátero detectado para decidir si aplicar la relación
            // o su inversa; de lo contrario una cédula sostenida en vertical saldría aplastada.
            const detected = outW / outH;
            if (Math.abs(detected - ratio) > Math.abs(detected - (1 / ratio))) {
                ratio = 1 / ratio;
            }

            // Solo se AMPLÍA una dimensión, nunca se reduce: así el ajuste de proporción
            // no descarta resolución de la captura original.
            if ((outW / outH) > ratio) {
                outH = Math.round(outW / ratio);
            } else {
                outW = Math.round(outH * ratio);
            }
        }

        // Tope de resolución de salida. 2400px en el lado largo equivale a ~280 dpi en una
        // hoja carta: de sobra para leer y archivar, y mantiene el realce (que usa kernels
        // grandes) en tiempos razonables incluso con cámaras de 4K.
        const MAX_DIM = 2400;
        const over = Math.max(outW, outH) / MAX_DIM;
        if (over > 1) {
            outW = Math.max(1, Math.round(outW / over));
            outH = Math.max(1, Math.round(outH / over));
        }

        if (typeof cv !== 'undefined' && cv.Mat) {
            try {
                let src = cv.imread(this.rawImage);
                
                let srcTri = cv.matFromArray(4, 1, cv.CV_32FC2, [
                    ptsImg[0].x, ptsImg[0].y,
                    ptsImg[1].x, ptsImg[1].y,
                    ptsImg[2].x, ptsImg[2].y,
                    ptsImg[3].x, ptsImg[3].y
                ]);

                let dstTri = cv.matFromArray(4, 1, cv.CV_32FC2, [
                    0, 0,
                    outW, 0,
                    outW, outH,
                    0, outH
                ]);

                let M = cv.getPerspectiveTransform(srcTri, dstTri);
                let dst = new cv.Mat();
                let dsize = new cv.Size(outW, outH);
                cv.warpPerspective(src, dst, M, dsize, cv.INTER_LINEAR, cv.BORDER_CONSTANT, new cv.Scalar());

                // Realce, SOLO si el tipo de documento lo pide. En documentos de identidad
                // se sale de aquí con el warp y el recorte, nada más: los colores llegan al
                // PDF tal cual los entregó la cámara.
                const enhanced = this.sinRealce(filterName) ? null : this.enhanceMat(dst, filterName);
                cv.imshow(this.canvasProcessed, enhanced || dst);
                if (enhanced) enhanced.delete();

                src.delete(); srcTri.delete(); dstTri.delete(); M.delete(); dst.delete();

                // Si el realce con OpenCV falló, al menos aplicar el ajuste básico en JS.
                if (!enhanced) this.applyFilterPostProcess(filterName);
                return;
            } catch (err) {
                console.warn("Fallo warpPerspective OpenCV, ejecutando fallback JS:", err);
            }
        }

        this.warpPerspectiveJS(ptsImg, outW, outH, filterName);
    }

    // Fallback si OpenCV no estuviera disponible: no puede corregir perspectiva (Canvas 2D
    // no hace transformación proyectiva), así que recorta el rectángulo que encierra el
    // cuadrilátero. Antes tomaba un rectángulo de outW×outH desde la esquina superior
    // izquierda, lo que recortaba fuera del documento cuando estaba inclinado.
    warpPerspectiveJS(pts, outW, outH, filterName) {
        const minX = Math.max(0, Math.min(...pts.map(p => p.x)));
        const minY = Math.max(0, Math.min(...pts.map(p => p.y)));
        const maxX = Math.min(this.rawWidth, Math.max(...pts.map(p => p.x)));
        const maxY = Math.min(this.rawHeight, Math.max(...pts.map(p => p.y)));

        const srcW = Math.max(1, Math.round(maxX - minX));
        const srcH = Math.max(1, Math.round(maxY - minY));

        this.canvasProcessed.width = outW;
        this.canvasProcessed.height = outH;
        const ctxOut = this.canvasProcessed.getContext('2d');

        ctxOut.save();
        ctxOut.drawImage(this.rawImage,
            minX, minY, srcW, srcH,
            0, 0, outW, outH
        );
        ctxOut.restore();

        console.warn("[Scanner] OpenCV no disponible: se recortó el documento sin corregir perspectiva.");
        this.applyFilterPostProcess(filterName);
    }

    /**
     * ¿Este documento va SIN ningún realce?
     *
     * Los documentos de identidad son plastificados y a color: el pipeline de realce está
     * pensado para hoja blanca con texto negro, donde forzar el fondo a blanco puro es lo
     * deseado. Sobre una cédula hace lo contrario de lo que se necesita — aplana la foto
     * del titular, se come el holograma y puede volver ilegible el número, que es motivo
     * de glosa al radicar ante la EPS.
     *
     * Único punto de decisión: lo consultan processScan(), warpPerspectiveJS() y la
     * construcción de la vista previa, de modo que la ruta automática y la de recorte
     * manual se comportan igual.
     */
    sinRealce(filterName) {
        return !filterName || filterName === 'ninguno' || filterName === 'none';
    }

    applyFilterPostProcess(filterName) {
        // Sin realce: el canvas ya tiene el recorte enderezado y así se queda.
        if (this.sinRealce(filterName)) return;

        const ctx = this.canvasProcessed.getContext('2d');
        const w = this.canvasProcessed.width;
        const h = this.canvasProcessed.height;
        if (!w || !h) return;

        const imgData = ctx.getImageData(0, 0, w, h);
        const data = imgData.data;

        if (filterName === 'magic') {
            let minLum = 255, maxLum = 0;
            for (let i = 0; i < data.length; i += 4) {
                const lum = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
                if (lum < minLum) minLum = lum;
                if (lum > maxLum) maxLum = lum;
            }

            const range = Math.max(1, maxLum - minLum);

            for (let i = 0; i < data.length; i += 4) {
                let r = ((data[i] - minLum) / range) * 255;
                let g = ((data[i + 1] - minLum) / range) * 255;
                let b = ((data[i + 2] - minLum) / range) * 255;

                r = Math.min(255, Math.pow(r / 255, 0.75) * 270);
                g = Math.min(255, Math.pow(g / 255, 0.75) * 270);
                b = Math.min(255, Math.pow(b / 255, 0.75) * 270);

                data[i] = r;
                data[i + 1] = g;
                data[i + 2] = b;
            }
        } else if (filterName === 'grayscale') {
            for (let i = 0; i < data.length; i += 4) {
                const gray = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
                data[i] = gray;
                data[i + 1] = gray;
                data[i + 2] = gray;
            }
        } else if (filterName === 'binary') {
            for (let i = 0; i < data.length; i += 4) {
                const gray = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
                const bw = gray > 140 ? 255 : 0;
                data[i] = bw;
                data[i + 1] = bw;
                data[i + 2] = bw;
            }
        }

        ctx.putImageData(imgData, 0, 0);
    }

    saveCurrentPageToDoc() {
        if (!this.canvasProcessed || !this.canvasProcessed.width) return null;

        const dataUrl = this.canvasProcessed.toDataURL('image/jpeg', 0.92);
        const pageObj = {
            id: Date.now(),
            dataUrl: dataUrl,
            width: this.canvasProcessed.width,
            height: this.canvasProcessed.height
        };

        this.scannedPages.push(pageObj);
        this.currentPageIndex = this.scannedPages.length - 1;

        return pageObj;
    }

    /**
     * Devuelve las páginas del lote listas para armar el PDF: reescaladas para que su lado
     * largo no supere maxDim y recodificadas a JPEG con la calidad indicada.
     *
     * Las páginas se conservan en memoria a máxima calidad (para las miniaturas y por si
     * se cambia de modo de realce); la compresión se aplica solo aquí, al exportar, que es
     * donde importa el peso del archivo que se sube.
     */
    async getPagesForPdf(maxDim = 2000, quality = 0.8) {
        const salida = [];

        for (const page of this.scannedPages) {
            const escala = Math.min(1, maxDim / Math.max(page.width, page.height));

            if (escala >= 1) {
                // Ya está por debajo del límite: se recodifica igual para bajar la calidad
                // de 0.92 a 0.8, que es donde está la mayor parte del ahorro de peso.
                salida.push(await this._recodePage(page, page.width, page.height, quality));
            } else {
                salida.push(await this._recodePage(
                    page,
                    Math.max(1, Math.round(page.width * escala)),
                    Math.max(1, Math.round(page.height * escala)),
                    quality
                ));
            }
        }

        return salida;
    }

    _recodePage(page, w, h, quality) {
        return new Promise((resolve) => {
            const img = new Image();
            img.onload = () => {
                const c = document.createElement('canvas');
                c.width = w;
                c.height = h;
                const ctx = c.getContext('2d');
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(img, 0, 0, w, h);
                resolve({ dataUrl: c.toDataURL('image/jpeg', quality), width: w, height: h });
            };
            img.onerror = () => resolve(page); // ante cualquier fallo, se usa la original
            img.src = page.dataUrl;
        });
    }

    deletePage(index) {
        if (index >= 0 && index < this.scannedPages.length) {
            this.scannedPages.splice(index, 1);
            if (this.currentPageIndex >= this.scannedPages.length) {
                this.currentPageIndex = this.scannedPages.length - 1;
            }
        }
        return this.scannedPages;
    }

    /**
     * Mueve una página dentro del lote. El orden del arreglo ES el orden de las hojas del
     * PDF, así que sin esto una página capturada fuera de secuencia obligaba a borrarla y
     * volver a escanearla.
     * @param {number} index  página a mover
     * @param {number} delta  -1 = hacia atrás, +1 = hacia adelante
     * @returns {number} índice final de la página movida (o el original si no se movió)
     */
    movePage(index, delta) {
        const destino = index + delta;
        if (index < 0 || index >= this.scannedPages.length) return index;
        if (destino < 0 || destino >= this.scannedPages.length) return index;

        const [pagina] = this.scannedPages.splice(index, 1);
        this.scannedPages.splice(destino, 0, pagina);

        // Mantener el resaltado sobre la misma página, no sobre la misma posición.
        if (this.currentPageIndex === index) this.currentPageIndex = destino;
        else if (this.currentPageIndex === destino) this.currentPageIndex = index;

        return destino;
    }

    getPage(index) {
        return this.scannedPages[index] || null;
    }

    /**
     * Peso en bytes que ocuparán dentro del PDF unas páginas YA comprimidas por
     * getPagesForPdf(). No es una estimación del efecto de la compresión: se mide sobre el
     * resultado real de comprimirlas, así que el único margen es la estructura del PDF.
     *
     * base64 codifica 3 bytes en 4 caracteres, y jsPDF embebe los bytes del JPEG tal cual
     * (no recodifica), por lo que la conversión es exacta salvo el relleno final.
     *
     * @param {Array} paginas  salida de getPagesForPdf()
     * @returns {number} bytes aproximados del PDF resultante
     */
    static pesoPaginas(paginas) {
        let bytes = 0;
        for (const p of paginas) {
            const inicio = p.dataUrl.indexOf(',') + 1;
            let base64 = p.dataUrl.length - inicio;
            // Descontar el relleno '=' para no inflar el resultado.
            if (p.dataUrl.endsWith('==')) base64 -= 2;
            else if (p.dataUrl.endsWith('=')) base64 -= 1;
            bytes += Math.round(base64 * 0.75);
        }
        // Margen por la estructura del PDF (catálogo, objetos de página, xref).
        return bytes + 2048 * Math.max(1, paginas.length);
    }

    resetDoc() {
        this.scannedPages = [];
        this.currentPageIndex = -1;
        this.rawImage = null;
        this.previewImage = null;
        this.huboAjusteManual = false;   // el indicador es por lote, no por página
    }

    async getProcessedBlob(type = 'image/jpeg', quality = 0.92) {
        if (!this.canvasProcessed) return null;

        return new Promise((resolve) => {
            this.canvasProcessed.toBlob((blob) => {
                resolve(blob);
            }, type, quality);
        });
    }
}

/**
 * Detección de bordes en tiempo real durante el preview de cámara (Fase 2).
 * Usa Worker + OffscreenCanvas cuando el navegador lo soporta (procesamiento fuera del
 * hilo principal); si no, corre el mismo pipeline en el hilo principal con throttling a
 * ~12fps. Nunca analiza a resolución completa: siempre reduce el frame a ~480px de ancho
 * antes de correr OpenCV, y solo entrega fracciones (0..1) — así el resultado escala
 * directamente a cualquier resolución (preview, captura en alta resolución, etc.) sin
 * matemática adicional.
 */
class LiveEdgeDetector {
    constructor({ videoElement, overlayCanvas, onResult, onAutoCapture, onProgress }) {
        this.video = videoElement;
        this.overlay = overlayCanvas;
        this.onResult = onResult || function () {};
        this.onAutoCapture = onAutoCapture || function () {};
        this.onProgress = onProgress || function () {};
        this._ultimoProgreso = -1;

        // --- Auto-captura por estabilidad (Fase 3 calibrada) ---
        this.autoCaptureEnabled = true;
        this.stabilityBuffer = [];
        this.STABILITY_SAMPLES = 6;
        this.STABILITY_THRESHOLD_PX = 14;  // Umbral de quietud en frame de análisis
        this.COUNTDOWN_MS = 2200;         // 2.2 segundos para posicionamiento cómodo y seguro
        this.GRACE_PERIOD_MS = 1400;      // 1.4 segundos de gracia inicial para ubicar papel
        this.sessionStartTime = performance.now();
        this.countdownStart = null;
        this.autoCaptureFired = false;
        this.lastFrameH = 0;

        // --- Suavizado y anti-parpadeo del contorno en vivo ---
        this.smoothedCorners = null;
        this.displayCorners = null;
        this.SMOOTHING_ALPHA = 0.65;   // Seguimiento veloz
        this.HOLD_FRAMES = 2;
        this.missedFrames = 0;
        this.holdingLastQuad = false;

        // Tipo de documento seleccionado en el modal.
        this.docType = null;

        this.debugMode = false;
        this.debugCanvas = null;
        this.lastDebug = null;

        // Medición de fps efectivos del bucle de detección.
        this._detectTimes = [];
        this.detectFps = 0;

        this.useWorker = false;
        this.running = false;
        this.busy = false;
        this.lastFrameTime = 0;
        this.minFrameInterval = 1000 / 30; // 30fps de análisis instantáneo
        this.targetW = 240;                // 240px ultra-rápido (0.1ms por frame)
        this._mainCanvas = null;
        this.rafId = null;

        this._loop = this._loop.bind(this);
    }

    setDocType(docType) {
        if (this.docType === docType) return;
        this.docType = docType;
        this.smoothedCorners = null;
        this.displayCorners = null;
        this.stabilityBuffer = [];
        this.countdownStart = null;
        this.sessionStartTime = performance.now();
        
        // Calibración adaptativa según tamaño del documento
        if (docType === 'CEDULA') {
            this.COUNTDOWN_MS = 2000; // 2.0 segundos para documento pequeño
        } else {
            this.COUNTDOWN_MS = 2300; // 2.3 segundos para fórmulas y órdenes médicas
        }
    }

    setDebugMode(on, debugCanvas) {
        this.debugMode = !!on;
        if (debugCanvas) this.debugCanvas = debugCanvas;
        if (!on) this.lastDebug = null;
    }

    start() {
        // SIEMPRE resetear el estado de auto-captura al iniciar, incluso si ya estaba
        // corriendo. Esto corrige el bug donde autoCaptureFired quedaba en true
        // de una sesión anterior (ej: escanear ORDEN_MEDICA y luego CEDULA).
        this.resetAutoCapture();

        if (this.running) {
            // Ya estaba corriendo, solo necesitábamos resetear el estado
            return;
        }
        this.running = true;
        this.rafId = requestAnimationFrame(this._loop);
    }

    stop() {
        this.running = false;
        this.busy = false;
        if (this.rafId) {
            cancelAnimationFrame(this.rafId);
            this.rafId = null;
        }
        this._clearOverlay();
    }

    resetAutoCapture() {
        this.stabilityBuffer = [];
        this.countdownStart = null;
        this.sessionStartTime = performance.now();
        this.autoCaptureFired = false;
        this.smoothedCorners = null;
        this.displayCorners = null;
        this.missedFrames = 0;
        this.holdingLastQuad = false;
    }

    _loop(ts) {
        if (!this.running) return;

        if (!this.busy && this.video.videoWidth && (ts - this.lastFrameTime) >= this.minFrameInterval) {
            this.lastFrameTime = ts;
            this.busy = true;
            this._processFrame().catch((err) => {
                console.warn("Fallo procesando frame de detección en vivo:", err);
            }).finally(() => {
                this.busy = false;
            });
        }

        this._renderFrame();
        this.rafId = requestAnimationFrame(this._loop);
    }

    _renderFrame() {
        const target = this.smoothedCorners;

        if (target) {
            if (!this.displayCorners) {
                this.displayCorners = target.map(p => ({ x: p.x, y: p.y }));
            } else {
                const k = 0.35; // avance por frame hacia el objetivo
                this.displayCorners = this.displayCorners.map((d, i) => ({
                    x: d.x + (target[i].x - d.x) * k,
                    y: d.y + (target[i].y - d.y) * k
                }));
            }
        } else {
            this.displayCorners = null;
        }

        const progress = this._countdownProgress();

        // Solo se notifica cuando el valor cambia de verdad: esto corre a 60fps y el
        // consumidor toca el DOM.
        if (Math.abs(progress - this._ultimoProgreso) > 0.01 || progress === 0) {
            if (this._ultimoProgreso !== 0 || progress !== 0) this.onProgress(progress);
            this._ultimoProgreso = progress;
        }

        // Log periódico para diagnóstico (cada ~2 segundos)
        if (!this._lastDiagLog) this._lastDiagLog = 0;
        const now = performance.now();
        if (now - this._lastDiagLog > 2000) {
            this._lastDiagLog = now;
            console.log(`[Scanner DIAG] running=${this.running} autoEnabled=${this.autoCaptureEnabled} fired=${this.autoCaptureFired} countdownStart=${this.countdownStart !== null} progress=${progress.toFixed(3)} hasCorners=${!!this.displayCorners} docType=${this.docType}`);
        }

        // Disparar aquí (y no en la detección) da precisión de 60fps a la cuenta regresiva.
        if (progress >= 1 && !this.autoCaptureFired) {
            this.autoCaptureFired = true;
            this.countdownStart = null;
            if (typeof navigator !== 'undefined' && navigator.vibrate) {
                try { navigator.vibrate([40, 30, 40]); } catch (e) {}
            }
            console.log("[Scanner] Documento estable — disparando auto-captura.");
            this.onAutoCapture();
        }

        this._drawOverlay(!!this.displayCorners, this.displayCorners, progress);
    }

    _countdownProgress() {
        if (this.countdownStart === null) return 0;
        return Math.min(1, (performance.now() - this.countdownStart) / this.COUNTDOWN_MS);
    }

    // Área del cuadrilátero (fórmula del cordón), en fracciones² del frame.
    _quadArea(pts) {
        let a = 0;
        for (let i = 0; i < 4; i++) {
            const j = (i + 1) % 4;
            a += pts[i].x * pts[j].y - pts[j].x * pts[i].y;
        }
        return Math.abs(a / 2);
    }

    async _processFrame() {
        const vw = this.video.videoWidth;
        const vh = this.video.videoHeight;
        if (!vw || !vh) return;

        const wrapper = this.overlay ? this.overlay.parentElement : null;
        const boxW = wrapper ? wrapper.clientWidth : 0;
        const boxH = wrapper ? wrapper.clientHeight : 0;
        if (!boxW || !boxH) return;

        const nowTs = performance.now();
        this._detectTimes.push(nowTs);
        while (this._detectTimes.length > 1 && nowTs - this._detectTimes[0] > 1000) {
            this._detectTimes.shift();
        }
        this.detectFps = this._detectTimes.length > 1
            ? (this._detectTimes.length - 1) * 1000 / (nowTs - this._detectTimes[0])
            : 0;

        // Extraer la porción exacta del sensor que el usuario ve en pantalla (celular vertical u horizontal)
        const scale = Math.max(boxW / vw, boxH / vh);
        const visibleNativeW = Math.min(vw, Math.round(boxW / scale));
        const visibleNativeH = Math.min(vh, Math.round(boxH / scale));
        const cropNativeX = Math.max(0, Math.round((vw - visibleNativeW) / 2));
        const cropNativeY = Math.max(0, Math.round((vh - visibleNativeH) / 2));

        this._visibleCrop = { cropNativeX, cropNativeY, visibleNativeW, visibleNativeH };

        const targetW = 240;
        const targetH = Math.max(1, Math.round(targetW * boxH / boxW));
        this.lastFrameH = targetH;

        if (!this._mainCanvas) this._mainCanvas = document.createElement('canvas');
        this._mainCanvas.width = targetW;
        this._mainCanvas.height = targetH;
        const ctx = this._mainCanvas.getContext('2d', { willReadFrequently: true });
        
        // Mapea exactamente el encuadre visible en pantalla
        ctx.drawImage(this.video, cropNativeX, cropNativeY, visibleNativeW, visibleNativeH, 0, 0, targetW, targetH);

        const imageData = ctx.getImageData(0, 0, targetW, targetH);

        let result = null;
        if (window.SISPAM_Scanner && typeof window.SISPAM_Scanner.detectDocumentQuadFast === 'function') {
            result = window.SISPAM_Scanner.detectDocumentQuadFast(imageData, targetW, targetH, {
                docType: this.docType,
                isPortrait: (boxH > boxW),
                debug: this.debugMode
            });
        } else if (window.SISPAM_Scanner && typeof window.SISPAM_Scanner.detectDocumentQuad === 'function') {
            result = window.SISPAM_Scanner.detectDocumentQuad(imageData, targetW, targetH, {
                docType: this.docType,
                isPortrait: (boxH > boxW),
                debug: this.debugMode
            });
        }

        const corners = (result && result.corners) ? result.corners : null;
        this._handleResult(corners, result);
    }

    _handleResult(rawCorners, stats) {
        const corners = rawCorners;

        if (corners) {
            this.missedFrames = 0;
            this.holdingLastQuad = false;

            if (!this.smoothedCorners) {
                this.smoothedCorners = corners.map(p => ({ x: p.x, y: p.y }));
            } else {
                const a = this.SMOOTHING_ALPHA;
                this.smoothedCorners = this.smoothedCorners.map((prev, i) => ({
                    x: a * corners[i].x + (1 - a) * prev.x,
                    y: a * corners[i].y + (1 - a) * prev.y
                }));
            }

            this._updateStability(this.smoothedCorners, true);
        } else {
            this.missedFrames++;
            if (this.smoothedCorners && this.missedFrames <= this.HOLD_FRAMES) {
                this.holdingLastQuad = true;
                this._updateStability(this.smoothedCorners, false);
            } else {
                this.holdingLastQuad = false;
                this.smoothedCorners = null;
                this._updateStability(null, false);
            }
        }

        const effectiveFound = !!this.smoothedCorners;
        this.onResult(effectiveFound, this.smoothedCorners);
    }

    _updateStability(corners, canPush) {
        if (!this.autoCaptureEnabled || this.autoCaptureFired) return;

        const now = performance.now();

        // 1. Período de gracia inicial al abrir la cámara o cambiar de cara
        if (now - this.sessionStartTime < this.GRACE_PERIOD_MS) {
            this.countdownStart = null;
            this.stabilityBuffer = [];
            return;
        }

        // 2. Si no hay esquinas válidas o se perdió la detección, reiniciar temporizador
        if (!corners) {
            this.countdownStart = null;
            this.stabilityBuffer = [];
            return;
        }

        // 3. Registrar muestra en buffer de estabilidad
        if (canPush) {
            this.stabilityBuffer.push(corners);
            if (this.stabilityBuffer.length > this.STABILITY_SAMPLES) {
                this.stabilityBuffer.shift();
            }
        }

        // 4. Si hay movimiento apreciable mientras se acomoda el papel, reiniciar cuenta regresiva
        if (this._hasDrasticMovement(24) || !this._isStable()) {
            this.countdownStart = null;
            return;
        }

        // 5. Iniciar la cuenta regresiva únicamente cuando el documento está firme y estable
        if (this.countdownStart === null) {
            this.countdownStart = now;
        }
    }

    _hasDrasticMovement(thresholdPx = 24) {
        if (this.stabilityBuffer.length < 2) return false;
        const refW = this.targetW;
        const refH = this.lastFrameH || this.targetW;
        const cur = this.stabilityBuffer[this.stabilityBuffer.length - 1];
        const prev = this.stabilityBuffer[0];

        for (let i = 0; i < 4; i++) {
            const dx = (cur[i].x - prev[i].x) * refW;
            const dy = (cur[i].y - prev[i].y) * refH;
            if (Math.hypot(dx, dy) > thresholdPx) return true;
        }
        return false;
    }

    // Estable = ninguna de las 4 esquinas se aleja de su posición media más que el umbral,
    // medido en píxeles del frame de análisis.
    _isStable() {
        if (this.stabilityBuffer.length < 3) return false; // Exigir al menos 3 muestras consecutivas
        const refW = this.targetW;
        const refH = this.lastFrameH || this.targetW;
        const buf = this.stabilityBuffer;

        for (let i = 0; i < 4; i++) {
            let sumX = 0, sumY = 0;
            for (const corners of buf) {
                sumX += corners[i].x;
                sumY += corners[i].y;
            }
            const meanX = sumX / buf.length;
            const meanY = sumY / buf.length;

            for (const corners of buf) {
                const dx = (corners[i].x - meanX) * refW;
                const dy = (corners[i].y - meanY) * refH;
                if (Math.hypot(dx, dy) > this.STABILITY_THRESHOLD_PX) return false;
            }
        }
        return true;
    }

    // Diagnóstico acotado: confirma la primera detección y, si tras unos segundos no
    // detecta nada, explica una sola vez qué está viendo el pipeline (área del mejor
    // candidato, cuántos contornos) en vez de dejar al usuario adivinando.
    _logDiagnostics(found, stats) {
        if (found) {
            if (!this._loggedFirstDetection) {
                this._loggedFirstDetection = true;
                const pass = stats && stats.pass ? stats.pass : 'n/d';
                const method = stats && stats.method ? stats.method : 'n/d';
                console.log(`[Scanner] Documento detectado (pasada: ${pass}, método: ${method}). Detección en vivo funcionando.`);
            }
            this._noDetectionSince = null;
            return;
        }

        if (this._loggedFirstDetection) return;

        const now = Date.now();
        if (!this._noDetectionSince) {
            this._noDetectionSince = now;
            return;
        }

        if (!this._loggedNoDetectionHint && (now - this._noDetectionSince) > 6000) {
            this._loggedNoDetectionHint = true;
            const ratio = stats && typeof stats.bestAreaRatio === 'number'
                ? (stats.bestAreaRatio * 100).toFixed(1) + '%'
                : 'n/d';
            const contours = stats && typeof stats.contourCount === 'number' ? stats.contourCount : 'n/d';
            console.log(
                `[Scanner] Sin detección tras 6s. Mejor candidato: ${ratio} del frame, ${contours} contornos. ` +
                `Sugerencia: apoye el documento sobre un fondo de color contrastante y que ocupe buena parte del encuadre.`
            );
        }
    }

    /**
     * Panel de depuración: mapa de bordes de Canny, TODOS los candidatos que pasaron la
     * validación (naranja) y el ganador (verde), más las métricas que deciden la elección.
     * Es la única forma de afinar los umbrales con datos en vez de a ciegas.
     */
    _renderDebugPanel() {
        const canvas = this.debugCanvas;
        const d = this.lastDebug;
        if (!canvas || !d) return;

        const w = d.edgeW || this.targetW;
        const h = d.edgeH || Math.round(this.targetW * 0.75);
        if (!w || !h) return;

        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d');

        // Mapa de bordes: buffer de 1 byte/píxel → RGBA en escala de grises.
        if (d.edgeMap && d.edgeMap.length >= w * h) {
            const img = ctx.createImageData(w, h);
            for (let i = 0, j = 0; i < w * h; i++, j += 4) {
                const v = d.edgeMap[i];
                img.data[j] = v; img.data[j + 1] = v; img.data[j + 2] = v; img.data[j + 3] = 255;
            }
            ctx.putImageData(img, 0, 0);
        } else {
            ctx.fillStyle = '#000';
            ctx.fillRect(0, 0, w, h);
        }

        const trazar = (corners, color, grosor) => {
            ctx.save();
            ctx.strokeStyle = color;
            ctx.lineWidth = grosor;
            ctx.beginPath();
            ctx.moveTo(corners[0].x * w, corners[0].y * h);
            for (let i = 1; i < 4; i++) ctx.lineTo(corners[i].x * w, corners[i].y * h);
            ctx.closePath();
            ctx.stroke();
            ctx.restore();
        };

        const ganador = this.smoothedCorners;
        (d.candidates || []).forEach(c => {
            if (c && c.corners) trazar(c.corners, 'rgba(251, 146, 60, 0.9)', 1.5);
        });
        if (ganador) trazar(ganador, '#22c55e', 3);

        // Métricas del candidato ganador + contadores de descarte por criterio.
        const m = d.metrics;
        const r = d.rejectStats || {};
        const lineas = [
            `fps deteccion: ${this.detectFps.toFixed(1)}   tipo: ${this.docType || 'libre'}`,
            m
                ? `GANADOR  solidez ${m.solidity.toFixed(3)}  ext ${m.extent.toFixed(3)}  ratio ${m.ratio.toFixed(3)}  area ${(m.areaRatio * 100).toFixed(1)}%`
                : 'GANADOR  (ninguno)',
            m
                ? `score ${d.score.toFixed(3)}  pase ${d.pass}  metodo ${d.method}  angulos ${m.angles.map(a => a.toFixed(0)).join('/')}`
                : '',
            `descartes  area< ${r.areaMin || 0}  area> ${r.areaMax || 0}  no4v ${r.noEs4 || 0}  noConv ${r.noConvexo || 0}`,
            `           solidez ${r.solidez || 0}  extension ${r.extension || 0}  angulos ${r.angulos || 0}  ratio ${r.proporcion || 0}`,
            `contornos ${r.contornos || 0}   aceptados ${r.aceptados || 0}`
        ].filter(Boolean);

        ctx.save();
        ctx.font = '11px ui-monospace, Consolas, monospace';
        ctx.textBaseline = 'top';
        const alto = lineas.length * 14 + 8;
        ctx.fillStyle = 'rgba(0,0,0,0.72)';
        ctx.fillRect(0, h - alto, w, alto);
        ctx.fillStyle = '#e2e8f0';
        lineas.forEach((t, i) => ctx.fillText(t, 6, h - alto + 4 + i * 14));
        ctx.restore();
    }

    // Dibuja el cuadrilátero (o la guía neutra) mapeando de fracciones del frame nativo
    // al recuadro tal como se ve en pantalla, respetando el recorte de object-fit:cover.
    _drawOverlay(found, cornersFrac, countdownProgress = 0) {
        const wrapper = this.overlay.parentElement;
        const boxW = wrapper ? wrapper.clientWidth : 0;
        const boxH = wrapper ? wrapper.clientHeight : 0;
        if (!boxW || !boxH) return;

        this.overlay.width = boxW;
        this.overlay.height = boxH;

        const ctx = this.overlay.getContext('2d');
        ctx.clearRect(0, 0, boxW, boxH);

        // Aspecto esperado para la caja del visor según tipo de documento y orientación
        const isPortrait = (boxH > boxW);
        const isCedula = (this.docType === 'CEDULA');

        // Calcular caja guía adaptada
        let vfW = boxW * 0.88;
        let vfH = boxH * 0.80;

        if (isPortrait) {
            if (isCedula) {
                // Tarjeta horizontal sobre pantalla vertical
                vfW = boxW * 0.88;
                vfH = vfW / 1.586;
            } else {
                // Hoja / fórmula vertical sobre pantalla vertical
                vfH = boxH * 0.82;
                vfW = vfH * 0.72;
                if (vfW > boxW * 0.90) vfW = boxW * 0.90;
            }
        } else {
            if (isCedula) {
                vfH = boxH * 0.80;
                vfW = vfH * 1.586;
                if (vfW > boxW * 0.90) vfW = boxW * 0.90;
            } else {
                vfH = boxH * 0.82;
                vfW = vfH * 1.33;
                if (vfW > boxW * 0.90) vfW = boxW * 0.90;
            }
        }

        const vfMinX = (boxW - vfW) / 2;
        const vfMaxX = vfMinX + vfW;
        const vfMinY = (boxH - vfH) / 2;
        const vfMaxY = vfMinY + vfH;

        const vfCorners = [
            { x: vfMinX, y: vfMinY },
            { x: vfMaxX, y: vfMinY },
            { x: vfMaxX, y: vfMaxY },
            { x: vfMinX, y: vfMaxY }
        ];

        let pts = null;
        if (found && cornersFrac) {
            pts = this._mapNativeFracToDisplay(cornersFrac, boxW, boxH);
        } else {
            pts = vfCorners;
        }

        // 1. Dibujar máscara de viñeta oscura exterior (Estilo WhatsApp Scanner)
        this._drawVignetteMask(ctx, pts, boxW, boxH);

        // 2. Dibujar contorno y esquinas en "L"
        if (found && cornersFrac) {
            this._drawDetectedQuad(ctx, pts, countdownProgress);
            if (countdownProgress > 0) {
                this._drawCountdown(ctx, pts, countdownProgress);
            }
        } else {
            this._drawSearchingViewfinder(ctx, pts);
        }

        // 3. Dibujar rayo láser de escaneo animado
        this._drawScanningLaser(ctx, pts, found);

        // 4. Dibujar pastilla / badge flotante de instrucción en la parte superior
        this._drawGuidancePill(ctx, boxW, vfMinY, found, isCedula, countdownProgress);
    }

    /**
     * Dibuja máscara oscura alrededor del área del documento (estilo WhatsApp)
     */
    _drawVignetteMask(ctx, pts, w, h) {
        ctx.save();
        ctx.fillStyle = 'rgba(0, 0, 0, 0.42)';
        ctx.beginPath();
        // Rectángulo completo de la pantalla
        ctx.rect(0, 0, w, h);
        // Recorte interno en sentido inverso
        ctx.moveTo(pts[0].x, pts[0].y);
        ctx.lineTo(pts[3].x, pts[3].y);
        ctx.lineTo(pts[2].x, pts[2].y);
        ctx.lineTo(pts[1].x, pts[1].y);
        ctx.closePath();
        ctx.fill('evenodd');
        ctx.restore();
    }

    /**
     * Dibuja el marco de búsqueda cuando aún se está encuadrando
     */
    _drawSearchingViewfinder(ctx, pts) {
        ctx.save();
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.35)';
        ctx.lineWidth = 1.5;
        ctx.setLineDash([8, 6]);

        ctx.beginPath();
        ctx.moveTo(pts[0].x, pts[0].y);
        for (let i = 1; i < 4; i++) ctx.lineTo(pts[i].x, pts[i].y);
        ctx.closePath();
        ctx.stroke();

        ctx.setLineDash([]);
        // Esquinas blancas brillantes
        this._drawCornerBrackets(ctx, pts, 'rgba(255, 255, 255, 0.95)', 4, 30);
        ctx.restore();
    }

    /**
     * Dibuja el contorno detectado bloqueado en verde WhatsApp
     */
    _drawDetectedQuad(ctx, pts, progress) {
        const accent = '#22c55e';
        ctx.save();

        // Velo verde translúcido
        ctx.beginPath();
        ctx.moveTo(pts[0].x, pts[0].y);
        for (let i = 1; i < 4; i++) ctx.lineTo(pts[i].x, pts[i].y);
        ctx.closePath();
        ctx.fillStyle = `rgba(34, 197, 94, ${0.16 + 0.14 * progress})`;
        ctx.fill();

        ctx.strokeStyle = accent;
        ctx.lineWidth = 2.5;
        ctx.stroke();

        // Esquinas verde neón
        this._drawCornerBrackets(ctx, pts, accent, 5, 34);
        ctx.restore();
    }

    _drawCornerBrackets(ctx, pts, color, thickness, bracketLength) {
        ctx.save();
        ctx.lineWidth = thickness;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = color;
        ctx.shadowColor = color;
        ctx.shadowBlur = 6;

        for (let i = 0; i < 4; i++) {
            const cur = pts[i];
            const next = pts[(i + 1) % 4];
            const prev = pts[(i + 3) % 4];

            const legTo = (from, to) => {
                const dx = to.x - from.x;
                const dy = to.y - from.y;
                const len = Math.hypot(dx, dy) || 1;
                const leg = Math.min(bracketLength, len * 0.28);
                return { x: from.x + (dx / len) * leg, y: from.y + (dy / len) * leg };
            };

            const a = legTo(cur, next);
            const b = legTo(cur, prev);

            ctx.beginPath();
            ctx.moveTo(a.x, a.y);
            ctx.lineTo(cur.x, cur.y);
            ctx.lineTo(b.x, b.y);
            ctx.stroke();
        }
        ctx.restore();
    }

    /**
     * Rayo láser de escaneo animado vertical (WhatsApp Scanner Effect)
     */
    _drawScanningLaser(ctx, pts, found) {
        const minY = Math.min(pts[0].y, pts[1].y);
        const maxY = Math.max(pts[2].y, pts[3].y);
        const minX = Math.min(pts[0].x, pts[3].x);
        const maxX = Math.max(pts[1].x, pts[2].x);

        const time = performance.now();
        const laserProgress = (Math.sin(time / 450) + 1) / 2;
        const laserY = minY + laserProgress * (maxY - minY);

        ctx.save();
        // Gradiente vertical para efecto halo
        const grad = ctx.createLinearGradient(0, laserY - 14, 0, laserY + 14);
        const col = found ? '34, 197, 94' : '4, 172, 140';
        grad.addColorStop(0, `rgba(${col}, 0)`);
        grad.addColorStop(0.5, `rgba(${col}, ${found ? 0.85 : 0.6})`);
        grad.addColorStop(1, `rgba(${col}, 0)`);

        ctx.fillStyle = grad;
        ctx.fillRect(minX + 4, laserY - 10, (maxX - minX) - 8, 20);

        // Línea central brillante
        ctx.strokeStyle = found ? '#22c55e' : '#04ac8c';
        ctx.lineWidth = 2.5;
        ctx.shadowColor = found ? '#22c55e' : '#04ac8c';
        ctx.shadowBlur = 8;
        ctx.beginPath();
        ctx.moveTo(minX + 8, laserY);
        ctx.lineTo(maxX - 8, laserY);
        ctx.stroke();
        ctx.restore();
    }

    /**
     * Pastilla flotante con instrucción clara en la parte superior
     */
    _drawGuidancePill(ctx, boxW, vfMinY, found, isCedula, progress = 0) {
        const totalSecs = (this.COUNTDOWN_MS / 1000).toFixed(1);
        let text = '';
        if (found) {
            if (progress > 0) {
                const segs = Math.max(0.1, ((this.COUNTDOWN_MS / 1000) * (1 - progress))).toFixed(1);
                text = `🟢 Mantenga quieto • Capturando en ${segs}s...`;
            } else {
                text = '🟢 Documento alineado • Mantenga quieto';
            }
        } else {
            text = isCedula
                ? '🪪 Ubique la CÉDULA en el atril'
                : '📄 Ubique la FÓRMULA (Media Carta / Carta) en el atril';
        }

        ctx.save();
        ctx.font = 'bold 13px system-ui, -apple-system, sans-serif';
        const textMetrics = ctx.measureText(text);
        const pillW = textMetrics.width + 28;
        const pillH = 32;
        const pillX = (boxW - pillW) / 2;
        const pillY = Math.max(12, vfMinY - 42);

        // Fondo de la pastilla
        ctx.beginPath();
        ctx.roundRect(pillX, pillY, pillW, pillH, 16);
        ctx.fillStyle = found ? (progress > 0 ? 'rgba(21, 128, 61, 0.95)' : 'rgba(22, 101, 52, 0.9)') : 'rgba(15, 23, 42, 0.85)';
        ctx.fill();
        ctx.strokeStyle = found ? '#22c55e' : 'rgba(255, 255, 255, 0.3)';
        ctx.lineWidth = 1.5;
        ctx.stroke();

        // Texto
        ctx.fillStyle = '#ffffff';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(text, boxW / 2, pillY + pillH / 2);
        ctx.restore();
    }

    // Anillo de progreso en el centro del documento detectado: avisa que la captura
    // automática está por dispararse, para que no tome al usuario por sorpresa.
    _drawCountdown(ctx, pts, progress) {
        const cx = (pts[0].x + pts[1].x + pts[2].x + pts[3].x) / 4;
        const cy = (pts[0].y + pts[1].y + pts[2].y + pts[3].y) / 4;
        const radius = 32;

        ctx.save();

        // Fondo circular
        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(15, 23, 42, 0.75)';
        ctx.fill();

        // Anillo exterior base
        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, Math.PI * 2);
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.25)';
        ctx.lineWidth = 4;
        ctx.stroke();

        // Anillo de cuenta regresiva neón
        ctx.beginPath();
        ctx.arc(cx, cy, radius, -Math.PI / 2, -Math.PI / 2 + Math.PI * 2 * progress);
        ctx.strokeStyle = '#22c55e';
        ctx.lineWidth = 5;
        ctx.lineCap = 'round';
        ctx.shadowColor = '#22c55e';
        ctx.shadowBlur = 8;
        ctx.stroke();

        // Segundos restantes
        const segs = Math.max(0.1, ((this.COUNTDOWN_MS / 1000) * (1 - progress))).toFixed(1);
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 13px system-ui, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(`${segs}s`, cx, cy);

        ctx.restore();
    }

    _mapNativeFracToDisplay(cornersFrac, boxW, boxH) {
        return cornersFrac.map(p => ({
            x: p.x * boxW,
            y: p.y * boxH
        }));
    }

    _strokeQuad(ctx, pts, color, dashed) {
        ctx.save();
        ctx.strokeStyle = color;
        ctx.lineWidth = dashed ? 2 : 3;
        if (dashed) ctx.setLineDash([10, 8]);
        if (!dashed) { ctx.shadowColor = color; ctx.shadowBlur = 10; }

        ctx.beginPath();
        ctx.moveTo(pts[0].x, pts[0].y);
        ctx.lineTo(pts[1].x, pts[1].y);
        ctx.lineTo(pts[2].x, pts[2].y);
        ctx.lineTo(pts[3].x, pts[3].y);
        ctx.closePath();
        ctx.stroke();

        if (!dashed) {
            pts.forEach(pt => {
                ctx.beginPath();
                ctx.arc(pt.x, pt.y, 6, 0, Math.PI * 2);
                ctx.fillStyle = color;
                ctx.fill();
            });
        }
        ctx.restore();
    }

    _clearOverlay() {
        if (!this.overlay) return;
        const ctx = this.overlay.getContext('2d');
        ctx.clearRect(0, 0, this.overlay.width, this.overlay.height);
    }
}

// Nota: el pipeline de detección (Canny + contornos + aproximación a cuadrilátero) vive en
// assets/js/scanner_detect.js, compartido tal cual entre el hilo principal y el worker.
