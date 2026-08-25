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

    async startCamera() {
        if (this.stream) this.stopCamera();

        const isHttp = window.location.protocol === 'http:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1';

        if (isHttp || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            this.showHttpsWarning();
            return false;
        }

        const isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
        const intentos = [
            // Intento 1: Cámara trasera en móviles o alta resolución estándar
            {
                video: {
                    facingMode: isMobile ? { ideal: "environment" } : undefined,
                    width: { ideal: 1920, min: 640 },
                    height: { ideal: 1080, min: 480 }
                },
                audio: false
            },
            // Intento 2: Cualquier cámara disponible
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
                    this.videoElement.setAttribute('autoplay', 'true');
                    this.videoElement.muted = true;
                    try {
                        await this.videoElement.play();
                    } catch (playErr) {
                        this.videoElement.onloadedmetadata = () => {
                            this.videoElement.play().catch(e => console.warn("[Scanner] play error:", e));
                        };
                    }
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
        const vW = this.videoElement.videoWidth || this.videoElement.clientWidth || 1280;
        const vH = this.videoElement.videoHeight || this.videoElement.clientHeight || 720;

        this.canvasSource.width = vW;
        this.canvasSource.height = vH;
        const ctx = this.canvasSource.getContext('2d');
        try {
            ctx.drawImage(this.videoElement, 0, 0, vW, vH);
        } catch (e) {
            console.warn("[Scanner] Error drawing video to canvas:", e);
        }

        return new Promise((resolve) => {
            const img = new Image();
            img.onload = () => {
                this._lastCaptureMethod = `canvas (${vW}x${vH})`;
                this.loadCapturedImage(img);
                resolve(img);
            };
            img.onerror = () => {
                console.warn("[Scanner] Error cargando dataURL en Image");
                this.loadCapturedImage(this.canvasSource);
                resolve(this.canvasSource);
            };
            try {
                img.src = this.canvasSource.toDataURL('image/jpeg', 0.95);
            } catch (secErr) {
                console.warn("[Scanner] toDataURL error:", secErr);
                this.loadCapturedImage(this.canvasSource);
                resolve(this.canvasSource);
            }
        });
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
        // Esquinas ya suavizadas de la detección en vivo (media móvil exponencial), que son
        // exactamente las que el usuario vio resaltadas en verde al momento de disparar.
        const live = (this.liveCorners && this.liveCorners.length === 4)
            ? this.orderCornerPoints(this.liveCorners)
            : null;

        // 1) Detectar sobre la foto ya capturada, reutilizando el MISMO pipeline que la
        //    detección en vivo (assets/js/scanner_detect.js) en vez de una copia aparte
        //    con otros parámetros.
        if (typeof cv !== 'undefined' && cv.Mat && window.SISPAM_Scanner) {
            try {
                const maxDim = 640;
                const scale = Math.min(1, maxDim / Math.max(this.rawWidth, this.rawHeight));
                const w = Math.max(1, Math.round(this.rawWidth * scale));
                const h = Math.max(1, Math.round(this.rawHeight * scale));

                const tmp = document.createElement('canvas');
                tmp.width = w;
                tmp.height = h;
                const tctx = tmp.getContext('2d', { willReadFrequently: true });
                tctx.drawImage(this.rawImage, 0, 0, w, h);

                const imageData = tctx.getImageData(0, 0, w, h);
                const res = window.SISPAM_Scanner.detectDocumentQuad(imageData, w, h, { docType: tipo });

                if (res && res.corners) {
                    // La foto fija tiene más resolución y menos ruido de movimiento, así que
                    // afina el recorte — PERO solo se acepta si coincide con lo que la
                    // detección en vivo venía marcando. Si señala otra cosa, el usuario
                    // vería un recorte distinto del que tenía resaltado al disparar, que es
                    // justo la sorpresa que hay que evitar.
                    if (!live || this._quadsAgree(res.corners, live)) {
                        this.corners = res.corners;
                        console.log(`[Scanner] Bordes detectados en la foto capturada (método: ${res.method}).`);
                        return;
                    }
                    console.log("[Scanner] La detección sobre la foto señala otra región; se conservan las esquinas suavizadas del preview.");
                }
            } catch (err) {
                console.warn("Fallo auto-detección sobre la foto capturada:", err);
            }
        }

        // 2) Si la foto fija no dio resultado pero la detección EN VIVO sí tenía el
        //    documento localizado justo antes de disparar, reutilizar esas esquinas.
        if (live) {
            this.corners = live;
            console.log("[Scanner] Usando las esquinas suavizadas de la detección en vivo previa a la captura.");
            return;
        }

        // 3) Último recurso: cuadrilátero centrado al 85% para que el usuario lo ajuste.
        this.corners = [
            { x: 0.08, y: 0.08 },
            { x: 0.92, y: 0.08 },
            { x: 0.92, y: 0.92 },
            { x: 0.08, y: 0.92 }
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
        return this.previewImage || this.rawImage;
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
        if (categoria === 'CEDULA') {
            return 85.6 / 54;      // ISO/IEC 7810 ID-1 (cédula, licencia, tarjetas) ≈ 1.586
        }
        if (categoria === 'ORDEN_MEDICA') {
            return 216 / 279;      // Carta (US Letter), formato habitual de fórmulas ≈ 0.774
        }
        return null;               // Autorización, historia clínica, otros: sin forzar
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
        if (!this.rawImage || !this.canvasOverlay) return;

        const wrapper = this.canvasOverlay.parentElement;
        const maxW = wrapper.clientWidth || 600;
        const maxH = wrapper.clientHeight || 360;

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
        // Se dibuja la versión REALZADA: la pantalla de revisión debe mostrar la calidad
        // que va a quedar en el PDF, no la foto cruda.
        ctx.drawImage(this.displayImage, 0, 0, displayW, displayH);
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

        // --- Auto-captura por estabilidad (Fase 3) ---
        this.autoCaptureEnabled = true;
        this.stabilityBuffer = [];
        this.STABILITY_SAMPLES = 5;      // ~400ms de historial estable a ~12fps
        // Tolerancia medida sobre el frame de análisis (480px de ancho)
        this.STABILITY_THRESHOLD_PX = 15;
        this.COUNTDOWN_MS = 650;         // Margen visible y fluido entre "estable" y disparo
        this.countdownStart = null;
        this.autoCaptureFired = false;
        this.lastFrameH = 0;

        // --- Suavizado y anti-parpadeo del contorno en vivo ---
        this.smoothedCorners = null;   // resultado de la media móvil exponencial
        this.displayCorners = null;    // posición realmente dibujada (interpolada a 60fps)
        this.lastAcceptedArea = null;  // área del último contorno aceptado
        this.SMOOTHING_ALPHA = 0.35;   // seguimiento ágil del documento
        this.AREA_JUMP_TOLERANCE = 0.25; // tolerancia ante cambios de distancia
        this.MAX_AREA_REJECTS = 4;     // re-anclar ante movimiento real
        this.HOLD_FRAMES = 6;          // frames que se sostiene el último contorno válido
        this.areaRejectStreak = 0;
        this.missedFrames = 0;
        this.holdingLastQuad = false;

        // Tipo de documento seleccionado en el modal. Restringe la proporción aceptable
        // durante la detección en vivo (Bloque B): antes solo se usaba al final, para
        // forzar el aspect ratio de salida, y la detección no lo aprovechaba en absoluto.
        this.docType = null;

        // Modo depuración: dibuja mapa de bordes, todos los candidatos y las métricas del
        // ganador. Sin esto afinar los umbrales es adivinar.
        this.debugMode = false;
        this.debugCanvas = null;
        this.lastDebug = null;

        // Medición de fps efectivos del bucle de detección.
        this._detectTimes = [];
        this.detectFps = 0;

        this.useWorker = (typeof OffscreenCanvas !== 'undefined') && (typeof Worker !== 'undefined');
        this.worker = null;
        this.workerReady = false;
        this._pendingResolve = null;
        this.running = false;
        this.busy = false;
        this.lastFrameTime = 0;
        this.minFrameInterval = 1000 / 12; // límite ~12fps para no saturar CPU en gama media/baja
        this.targetW = 480;
        this._mainCanvas = null;
        this.rafId = null;

        this._loop = this._loop.bind(this);
    }

    // El tipo de documento puede cambiar mientras la cámara está encendida (el usuario
    // toca el desplegable): al cambiar, el historial de suavizado deja de ser válido
    // porque el ganador puede pasar a ser otro objeto.
    setDocType(docType) {
        if (this.docType === docType) return;
        this.docType = docType;
        this.smoothedCorners = null;
        this.displayCorners = null;
        this.lastAcceptedArea = null;
        this.stabilityBuffer = [];
        this.countdownStart = null;
    }

    setDebugMode(on, debugCanvas) {
        this.debugMode = !!on;
        if (debugCanvas) this.debugCanvas = debugCanvas;
        if (!on) this.lastDebug = null;
    }

    start() {
        if (this.running) return;
        this.running = true;
        this.resetAutoCapture();

        // El worker (y su copia de OpenCV.js, ~9MB) se crea UNA sola vez y se reutiliza en
        // cada start()/stop() posterior (ej. al reiniciar cámara entre páginas) — recrearlo
        // cada vez obligaría a recargar OpenCV.js en el worker en cada reinicio de cámara.
        if (this.useWorker && !this.worker) {
            try {
                this.worker = new Worker('assets/js/scanner_worker.js');
                this.worker.onmessage = (e) => this._handleWorkerMessage(e.data);
                this.worker.onerror = (err) => {
                    console.warn("Worker de detección falló, se sigue en hilo principal:", err);
                    this.useWorker = false;
                    this._resolvePending(null);
                    if (this.worker) { this.worker.terminate(); this.worker = null; }
                };
                console.log("[Scanner] Detección en vivo: usando Web Worker + OffscreenCanvas.");
            } catch (err) {
                console.warn("No se pudo crear Worker de detección, usando hilo principal:", err);
                this.useWorker = false;
            }
        } else if (!this.useWorker && !this._loggedMainThreadMode) {
            this._loggedMainThreadMode = true;
            console.log("[Scanner] Detección en vivo: navegador sin Worker/OffscreenCanvas, usando hilo principal throttlado.");
        }

        this.rafId = requestAnimationFrame(this._loop);
    }

    // Pausa el bucle (cancela el frame pendiente y limpia el overlay) SIN destruir el
    // worker — así el próximo start() no tiene que recargar OpenCV.js de nuevo.
    stop() {
        this.running = false;
        this.busy = false;
        this._resolvePending(null);
        if (this.rafId) {
            cancelAnimationFrame(this.rafId);
            this.rafId = null;
        }
        this._clearOverlay();
    }

    // Limpia el historial de estabilidad y rearma la auto-captura (al (re)iniciar cámara).
    resetAutoCapture() {
        this.stabilityBuffer = [];
        this.countdownStart = null;
        this.autoCaptureFired = false;
        this.smoothedCorners = null;
        this.displayCorners = null;
        this.lastAcceptedArea = null;
        this.areaRejectStreak = 0;
        this.missedFrames = 0;
        this.holdingLastQuad = false;
    }

    _resolvePending(value) {
        if (this._pendingResolve) {
            const resolve = this._pendingResolve;
            this._pendingResolve = null;
            resolve(value);
        }
    }

    _loop(ts) {
        if (!this.running) return;

        // La DETECCIÓN va throttlada (~12fps): es cara y satura CPU en gama media.
        if (!this.busy && this.video.videoWidth && (ts - this.lastFrameTime) >= this.minFrameInterval) {
            this.lastFrameTime = ts;
            this.busy = true;
            this._processFrame().catch((err) => {
                console.warn("Fallo procesando frame de detección en vivo:", err);
            }).finally(() => {
                this.busy = false;
            });
        }

        // El DIBUJO va en cada frame de pantalla (~60fps), interpolando hacia la última
        // posición suavizada. Antes solo se redibujaba al llegar una detección, así que el
        // contorno avanzaba a saltos de 12fps — la causa principal de que se viera brusco.
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

        // Disparar aquí (y no en la detección) da precisión de 60fps a la cuenta regresiva.
        if (progress >= 1 && !this.autoCaptureFired) {
            this.autoCaptureFired = true;
            this.countdownStart = null;
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

        // fps EFECTIVOS de la detección (no los del render): se mide el intervalo real
        // entre frames analizados sobre una ventana móvil de ~1s.
        const nowTs = performance.now();
        this._detectTimes.push(nowTs);
        while (this._detectTimes.length > 1 && nowTs - this._detectTimes[0] > 1000) {
            this._detectTimes.shift();
        }
        this.detectFps = this._detectTimes.length > 1
            ? (this._detectTimes.length - 1) * 1000 / (nowTs - this._detectTimes[0])
            : 0;

        const targetW = this.targetW;
        const targetH = Math.max(1, Math.round(targetW * vh / vw));
        this.lastFrameH = targetH; // referencia para medir estabilidad en px reales

        if (this.useWorker && this.worker) {
            const bitmap = await this._grabBitmap(targetW, targetH);
            if (!bitmap) return;
            // Espera la respuesta completa del worker antes de terminar: así _loop() (que
            // solo suelta busy=false cuando esta promesa resuelve) nunca envía el siguiente
            // frame mientras el worker sigue ocupado con el anterior — sin esto, frames se
            // acumulan más rápido de lo que el worker los procesa y el overlay nunca se ve
            // actualizado (efecto "no detecta nada").
            const res = await this._sendFrameToWorker(bitmap, targetW, targetH);
            this._handleResult(res ? res.corners : null, res);
            return;
        }

        // Fallback hilo principal: mismo pipeline compartido, ya throttlado por _loop.
        if (!window.SISPAM_Scanner) return;

        if (!this._mainCanvas) this._mainCanvas = document.createElement('canvas');
        this._mainCanvas.width = targetW;
        this._mainCanvas.height = targetH;
        const ctx = this._mainCanvas.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(this.video, 0, 0, targetW, targetH);

        const imageData = ctx.getImageData(0, 0, targetW, targetH);
        const result = window.SISPAM_Scanner.detectDocumentQuad(imageData, targetW, targetH, {
            docType: this.docType,
            debug: this.debugMode
        });
        this._handleResult(result.corners, result);
    }

    /**
     * Obtiene el frame reducido como ImageBitmap para enviarlo al worker.
     * Safari (iPhone/iPad) no implementa de forma fiable resizeWidth/resizeHeight en
     * createImageBitmap: según la versión los ignora o lanza excepción. Por eso se
     * intenta la vía rápida y, si falla, se reduce con un canvas intermedio.
     */
    async _grabBitmap(targetW, targetH) {
        if (!this._bitmapResizeUnsupported) {
            try {
                const bmp = await createImageBitmap(this.video, { resizeWidth: targetW, resizeHeight: targetH });
                if (bmp.width === targetW) return bmp;
                // Aceptó las opciones pero las ignoró (Safari): descartar y usar canvas.
                bmp.close();
                this._bitmapResizeUnsupported = true;
                console.log("[Scanner] createImageBitmap ignora el redimensionado; se reduce con canvas.");
            } catch (err) {
                this._bitmapResizeUnsupported = true;
                console.log("[Scanner] createImageBitmap sin soporte de redimensionado; se reduce con canvas.");
            }
        }

        try {
            if (!this._mainCanvas) this._mainCanvas = document.createElement('canvas');
            this._mainCanvas.width = targetW;
            this._mainCanvas.height = targetH;
            const ctx = this._mainCanvas.getContext('2d');
            ctx.drawImage(this.video, 0, 0, targetW, targetH);
            return await createImageBitmap(this._mainCanvas);
        } catch (err) {
            // Si tampoco hay ImageBitmap utilizable, abandonar el worker y seguir en el
            // hilo principal en vez de quedarse sin detección.
            console.warn("[Scanner] No se pudo obtener el frame para el worker; se pasa a hilo principal:", err);
            this.useWorker = false;
            return null;
        }
    }

    _sendFrameToWorker(bitmap, frameW, frameH) {
        return new Promise((resolve) => {
            this._pendingResolve = resolve;
            this.worker.postMessage({
                type: 'frame', bitmap, frameW, frameH,
                docType: this.docType,
                debug: this.debugMode
            }, [bitmap]);
        });
    }

    _handleWorkerMessage(data) {
        if (data.type === 'result') {
            if (!this.workerReady) {
                this.workerReady = true;
                console.log("[Scanner] OpenCV.js listo dentro del worker de detección en vivo.");
            }
            this._resolvePending(data);
        } else if (data.type === 'error') {
            console.warn("[Scanner] Worker de detección:", data.error);
            this._resolvePending(null);
        }
    }

    /**
     * Procesa una detección cruda y actualiza el contorno suavizado.
     * Aplica, en orden: rechazo por salto de área → sostenimiento anti-parpadeo →
     * media móvil exponencial. No dibuja: de eso se encarga _renderFrame() a 60fps.
     */
    _handleResult(rawCorners, stats) {
        let corners = rawCorners;

        if (this.debugMode && stats) {
            this.lastDebug = {
                candidates: stats.debugCandidates || [],
                edgeMap: stats.edgeMap || null,
                edgeW: stats.edgeW || 0,
                edgeH: stats.edgeH || 0,
                rejectStats: stats.rejectStats || null,
                metrics: stats.metrics || null,
                score: stats.score || 0,
                pass: stats.pass || null,
                method: stats.method || null
            };
            this._renderDebugPanel();
        }

        // (1) Rechazar detecciones cuya área salte más de ~15% respecto a la anterior:
        // suele ser ruido (un contorno espurio del fondo), no que el documento se haya
        // movido de verdad.
        if (corners) {
            const area = this._quadArea(corners);

            if (this.lastAcceptedArea !== null && this.lastAcceptedArea > 0) {
                const change = Math.abs(area - this.lastAcceptedArea) / this.lastAcceptedArea;

                if (change > this.AREA_JUMP_TOLERANCE) {
                    this.areaRejectStreak++;

                    if (this.areaRejectStreak <= this.MAX_AREA_REJECTS) {
                        corners = null; // se trata como frame sin detección → entra en sostenimiento
                    } else {
                        // El cambio persiste varios frames seguidos: no era ruido, el usuario
                        // acercó o alejó el documento. Re-anclar de golpe en vez de quedar
                        // rechazando para siempre y con el contorno congelado.
                        this.areaRejectStreak = 0;
                        this.lastAcceptedArea = area;
                        this.smoothedCorners = corners.map(p => ({ x: p.x, y: p.y }));
                        this.stabilityBuffer = [];
                        this.countdownStart = null;
                    }
                } else {
                    this.areaRejectStreak = 0;
                    this.lastAcceptedArea = area;
                }
            } else {
                this.lastAcceptedArea = area;
            }
        }

        if (corners) {
            this.missedFrames = 0;
            this.holdingLastQuad = false;

            // (3) Media móvil exponencial sobre las 4 esquinas.
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
            // (2) Sin detección válida: sostener el último contorno unos frames antes de
            // volver a "buscando", para que no parpadee ante huecos momentáneos.
            this.missedFrames++;

            if (this.smoothedCorners && this.missedFrames <= this.HOLD_FRAMES) {
                this.holdingLastQuad = true;
                // Durante el sostenimiento NO se alimenta el buffer de estabilidad: repetir
                // la misma posición lo haría parecer perfectamente quieto y podría disparar
                // una auto-captura falsa justo cuando se perdió el documento.
                this._updateStability(this.smoothedCorners, false);
            } else {
                this.holdingLastQuad = false;
                this.smoothedCorners = null;
                this.lastAcceptedArea = null;
                this.areaRejectStreak = 0;
                this._updateStability(null, false);
            }
        }

        const effectiveFound = !!this.smoothedCorners;
        this._logDiagnostics(effectiveFound, stats);
        this.onResult(effectiveFound, this.smoothedCorners);
    }

    /**
     * Alimenta el historial de estabilidad y arma/cancela la cuenta regresiva.
     * El disparo en sí ocurre en _renderFrame(), que corre a 60fps.
     * @param {Array|null} corners  esquinas ya suavizadas, o null si se perdió el documento
     * @param {boolean} canPush     false durante el sostenimiento anti-parpadeo
     */
    _updateStability(corners, canPush) {
        if (!this.autoCaptureEnabled || this.autoCaptureFired) return;

        // Perder el documento invalida el historial: hay que volver a estabilizar desde
        // cero, no reanudar donde iba la cuenta.
        if (!corners) {
            this.stabilityBuffer = [];
            this.countdownStart = null;
            return;
        }

        if (canPush) {
            this.stabilityBuffer.push(corners.map(p => ({ x: p.x, y: p.y })));
            if (this.stabilityBuffer.length > this.STABILITY_SAMPLES) {
                this.stabilityBuffer.shift();
            }
        }

        if (this.stabilityBuffer.length < this.STABILITY_SAMPLES || !this._isStable()) {
            this.countdownStart = null;
            return;
        }

        if (this.countdownStart === null) {
            this.countdownStart = performance.now();
        }
    }

    // Estable = ninguna de las 4 esquinas se aleja de su posición media más que el umbral,
    // medido en píxeles del frame de análisis (no en fracciones, que serían dependientes
    // de la resolución de la cámara).
    _isStable() {
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

        if (found && cornersFrac && this.video.videoWidth) {
            const pts = this._mapNativeFracToDisplay(cornersFrac, boxW, boxH);
            this._drawDetectedQuad(ctx, pts, countdownProgress);
            if (countdownProgress > 0) {
                this._drawCountdown(ctx, pts, countdownProgress);
            }
        } else {
            const insetX = boxW * 0.08;
            const insetY = boxH * 0.10;
            const pts = [
                { x: insetX, y: insetY },
                { x: boxW - insetX, y: insetY },
                { x: boxW - insetX, y: boxH - insetY },
                { x: insetX, y: boxH - insetY }
            ];
            this._strokeQuad(ctx, pts, 'rgba(148, 163, 184, 0.85)', true);
        }
    }

    /**
     * Contorno del documento detectado, al estilo de los escáneres móviles: velo
     * translúcido sobre el área reconocida, borde fino y esquinas en "L" marcadas.
     * Lee mucho mejor que una línea sola, sobre todo en pantalla de celular.
     */
    _drawDetectedQuad(ctx, pts, progress) {
        const accent = '#22c55e';

        ctx.save();

        // Velo del área detectada (se intensifica levemente durante la cuenta regresiva).
        ctx.beginPath();
        ctx.moveTo(pts[0].x, pts[0].y);
        for (let i = 1; i < 4; i++) ctx.lineTo(pts[i].x, pts[i].y);
        ctx.closePath();
        ctx.fillStyle = `rgba(34, 197, 94, ${0.14 + 0.12 * progress})`;
        ctx.fill();

        ctx.strokeStyle = accent;
        ctx.lineWidth = 2;
        ctx.stroke();

        // Esquinas en "L": se dibujan hacia adentro sobre cada lado adyacente.
        ctx.lineWidth = 5;
        ctx.lineCap = 'round';
        ctx.strokeStyle = accent;
        ctx.shadowColor = 'rgba(0,0,0,0.45)';
        ctx.shadowBlur = 4;

        for (let i = 0; i < 4; i++) {
            const cur = pts[i];
            const next = pts[(i + 1) % 4];
            const prev = pts[(i + 3) % 4];

            const legTo = (from, to) => {
                const dx = to.x - from.x;
                const dy = to.y - from.y;
                const len = Math.hypot(dx, dy) || 1;
                // Tramo corto proporcional al lado, acotado para que no se solape en
                // documentos pequeños ni se dispare en los grandes.
                const leg = Math.min(26, len * 0.25);
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

    // Anillo de progreso en el centro del documento detectado: avisa que la captura
    // automática está por dispararse, para que no tome al usuario por sorpresa.
    _drawCountdown(ctx, pts, progress) {
        const cx = (pts[0].x + pts[1].x + pts[2].x + pts[3].x) / 4;
        const cy = (pts[0].y + pts[1].y + pts[2].y + pts[3].y) / 4;
        const radius = 26;

        ctx.save();

        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(15, 23, 42, 0.55)';
        ctx.fill();

        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, Math.PI * 2);
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.25)';
        ctx.lineWidth = 4;
        ctx.stroke();

        ctx.beginPath();
        ctx.arc(cx, cy, radius, -Math.PI / 2, -Math.PI / 2 + Math.PI * 2 * progress);
        ctx.strokeStyle = '#22c55e';
        ctx.lineWidth = 4;
        ctx.lineCap = 'round';
        ctx.stroke();

        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 13px system-ui, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('AUTO', cx, cy);

        ctx.restore();
    }

    _mapNativeFracToDisplay(cornersFrac, boxW, boxH) {
        const vw = this.video.videoWidth;
        const vh = this.video.videoHeight;

        // object-fit:cover del <video>: qué porción del frame nativo es visible y a qué escala.
        const coverScale = Math.max(boxW / vw, boxH / vh);
        const visibleW = boxW / coverScale;
        const visibleH = boxH / coverScale;
        const cropX = (vw - visibleW) / 2;
        const cropY = (vh - visibleH) / 2;

        return cornersFrac.map(p => {
            const nativeX = p.x * vw;
            const nativeY = p.y * vh;
            return {
                x: (nativeX - cropX) * coverScale,
                y: (nativeY - cropY) * coverScale
            };
        });
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
