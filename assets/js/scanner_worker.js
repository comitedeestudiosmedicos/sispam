/**
 * SISPAM - Escáner Profesional de Documentos: Worker de detección de bordes en tiempo real.
 * Corre el pipeline de OpenCV.js fuera del hilo principal para no congelar la UI.
 * El pipeline en sí vive en scanner_detect.js (compartido con el hilo principal, que lo
 * usa como fallback cuando no hay soporte de Worker/OffscreenCanvas).
 */

// OpenCV.js se sirve desde el propio proyecto (assets/js/vendor/), no desde un CDN:
// la URL de CDN que se usaba antes devolvía 404 (el archivo está en dist/ dentro de ese
// paquete), por lo que OpenCV nunca llegaba a cargar. Alojarlo localmente además permite
// que el escáner funcione sin conexión a internet.
const OPENCV_URL = 'vendor/opencv.js'; // relativo a la ubicación de este worker

let cvReadyPromise = null;

function loadDependencies() {
    // Ojo: NO cachear una promesa ya rechazada. Si el primer intento falla (CDN lento o
    // caído momentáneamente), hay que permitir reintentar en el siguiente frame; cachear
    // el rechazo dejaba la detección muerta para siempre hasta recargar la página.
    if (cvReadyPromise) return cvReadyPromise;

    cvReadyPromise = new Promise((resolve, reject) => {
        try {
            importScripts(OPENCV_URL);
            importScripts('scanner_detect.js'); // relativo a la ubicación de este worker
        } catch (err) {
            reject(err);
            return;
        }

        const started = Date.now();
        const check = () => {
            if (typeof cv !== 'undefined' && cv.Mat && self.SISPAM_Scanner) {
                resolve();
            } else if (Date.now() - started > 60000) {
                reject(new Error('OpenCV.js no terminó de inicializar en el worker (timeout 60s)'));
            } else {
                setTimeout(check, 30);
            }
        };
        check();
    }).catch((err) => {
        cvReadyPromise = null; // permitir reintento en el próximo frame
        throw err;
    });

    return cvReadyPromise;
}

let cachedCanvas = null;
let cachedCtx = null;

self.onmessage = async (e) => {
    const msg = e.data;
    if (!msg || msg.type !== 'frame') return;

    try {
        await loadDependencies();

        if (!cachedCanvas || cachedCanvas.width !== msg.frameW || cachedCanvas.height !== msg.frameH) {
            cachedCanvas = new OffscreenCanvas(msg.frameW, msg.frameH);
            cachedCtx = cachedCanvas.getContext('2d', { willReadFrequently: true });
        }

        cachedCtx.drawImage(msg.bitmap, 0, 0, msg.frameW, msg.frameH);
        msg.bitmap.close();

        const imageData = cachedCtx.getImageData(0, 0, msg.frameW, msg.frameH);
        const result = self.SISPAM_Scanner.detectDocumentQuad(
            imageData, msg.frameW, msg.frameH, {
                minAreaRatio: msg.minAreaRatio,
                docType: msg.docType,        // restringe la proporción aceptable (Bloque B)
                debug: !!msg.debug
            }
        );

        const payload = {
            type: 'result',
            corners: result.corners,
            bestAreaRatio: result.bestAreaRatio,
            contourCount: result.contourCount,
            pass: result.pass,
            method: result.method,
            score: result.score,
            metrics: result.metrics,
            rejectStats: result.rejectStats
        };

        // El mapa de bordes solo viaja en modo depuración, y se TRANSFIERE (no se copia)
        // para no duplicar ~170KB por frame entre worker e hilo principal.
        const transfer = [];
        if (msg.debug) {
            payload.debugCandidates = result.debugCandidates;
            payload.edgeW = result.edgeW;
            payload.edgeH = result.edgeH;
            if (result.edgeMap) {
                payload.edgeMap = result.edgeMap;
                transfer.push(result.edgeMap.buffer);
            }
        }

        self.postMessage(payload, transfer);
    } catch (err) {
        if (msg.bitmap && typeof msg.bitmap.close === 'function') {
            try { msg.bitmap.close(); } catch (_) {}
        }
        self.postMessage({ type: 'error', error: String((err && err.message) || err) });
    }
};
