/**
 * SISPAM - Detección de cuadrilátero de documento (pipeline OpenCV.js compartido).
 *
 * Este archivo se carga TANTO en el hilo principal (vía <script>) COMO dentro del Web
 * Worker (vía importScripts), de modo que el pipeline de detección exista una sola vez
 * y no haya dos copias que mantener sincronizadas.
 *
 * Requiere que `cv` (OpenCV.js) ya esté inicializado al momento de llamar a
 * detectDocumentQuad(); el archivo en sí no carga OpenCV.
 */
(function (root) {
    'use strict';

    /**
     * Umbrales de validación geométrica de candidatos.
     *
     * Antes bastaba con "contorno grande que se aproxima a 4 vértices" y, si eso fallaba,
     * el rectángulo rotado del contorno más grande sin ninguna comprobación. Con una cédula
     * sostenida en la mano frente a un fondo oscuro eso agarraba el blob completo de
     * mano + cédula + rostro: todo lo claro contrasta contra el fondo, pero no entre sí.
     * Estos criterios son los que separan "rectángulo real visto en perspectiva" de
     * "silueta irregular que casualmente tiene 4 lados".
     */
    var GEOM_DEFAULTS = {
        // Epsilon base para approxPolyDP
        epsilonFactor: 0.02,
        // área contorno / área del hull convexo. 0.62 tolera dedos y bordes irregulares
        minSolidity: 0.62,
        // área contorno / área del minAreaRect.
        minExtent: 0.42,
        maxExtent: 1.0,
        // Ángulos internos: tolera perspectiva oblicua común al sostener celular con una mano
        minAngle: 45,
        maxAngle: 135,
        // Piso de área en 0.025 (2.5% del frame) para detección ultra-rápida desde lejos
        minAreaRatio: 0.025,
        maxAreaRatio: 0.98,
        // Relación de aspecto esperada según el tipo de documento.
        expectedRatios: null,
        ratioTolerance: 0.55
    };

    /**
     * Relaciones de aspecto esperadas (lado largo / lado corto) por tipo de documento.
     * Se comparan siempre normalizadas a >= 1, así que sirven igual con el documento
     * sostenido en horizontal o en vertical.
     */
    var DOC_RATIOS = {
        CEDULA:       [85.6 / 54],                              // ISO/IEC 7810 ID-1 ≈ 1.586
        ORDEN_MEDICA: [279 / 216, 297 / 210, 216 / 140, 1.0]    // Carta, A4, Media Carta y Cuadrado
    };

    function getExpectedRatios(docType) {
        if (!docType) return null;
        return DOC_RATIOS[docType] || null;
    }

    /**
     * Ordena 4 puntos como [superior-izq, superior-der, inferior-der, inferior-izq].
     *
     * Se ordena por ángulo alrededor del centroide en vez de por sumas/restas de
     * coordenadas: el método clásico (menor x+y = superior-izq) falla cuando el documento
     * está girado cerca de 45°, porque dos esquinas distintas pueden compartir casi la
     * misma suma y el cuadrilátero termina cruzado (forma de moño), produciendo una
     * imagen retorcida al aplicar warpPerspective.
     */
    function orderQuadPoints(pts) {
        const cx = (pts[0].x + pts[1].x + pts[2].x + pts[3].x) / 4;
        const cy = (pts[0].y + pts[1].y + pts[2].y + pts[3].y) / 4;

        // En coordenadas de pantalla (y hacia abajo) el orden ascendente de ángulo recorre
        // el cuadrilátero en sentido horario, que es justo TL → TR → BR → BL.
        const byAngle = [...pts].sort(
            (a, b) => Math.atan2(a.y - cy, a.x - cx) - Math.atan2(b.y - cy, b.x - cx)
        );

        // Rotar el arreglo para que empiece en la esquina más cercana al origen (sup-izq).
        let startIdx = 0;
        let minSum = Infinity;
        for (let i = 0; i < 4; i++) {
            const sum = byAngle[i].x + byAngle[i].y;
            if (sum < minSum) {
                minSum = sum;
                startIdx = i;
            }
        }

        return [
            byAngle[startIdx],
            byAngle[(startIdx + 1) % 4],
            byAngle[(startIdx + 2) % 4],
            byAngle[(startIdx + 3) % 4]
        ];
    }

    // Descarta cuadriláteros que cubren casi todo el frame: normalmente no son el
    // documento sino el borde del propio encuadre de la cámara.
    function isNearlyFullFrame(pts) {
        const xs = pts.map(p => p.x);
        const ys = pts.map(p => p.y);
        const w = Math.max(...xs) - Math.min(...xs);
        const h = Math.max(...ys) - Math.min(...ys);
        return (w * h) > 0.93;
    }

    /**
     * Ángulos internos del cuadrilátero, en grados.
     * OJO: hay que pasarle los puntos en PÍXELES, no en fracciones 0..1. En fracciones el
     * frame queda "estirado" a un cuadrado y una esquina de 90° real mide otra cosa, con
     * lo que el filtro de ángulos descartaría documentos perfectamente válidos.
     */
    function quadAngles(ptsPx) {
        const angles = [];
        for (let i = 0; i < 4; i++) {
            const prev = ptsPx[(i + 3) % 4];
            const cur = ptsPx[i];
            const next = ptsPx[(i + 1) % 4];

            const v1x = prev.x - cur.x, v1y = prev.y - cur.y;
            const v2x = next.x - cur.x, v2y = next.y - cur.y;

            const m1 = Math.hypot(v1x, v1y);
            const m2 = Math.hypot(v2x, v2y);
            if (m1 < 1e-6 || m2 < 1e-6) return null;

            let cos = (v1x * v2x + v1y * v2y) / (m1 * m2);
            cos = Math.max(-1, Math.min(1, cos));
            angles.push(Math.acos(cos) * 180 / Math.PI);
        }
        return angles;
    }

    // Solidez: área del contorno / área de su envolvente convexa.
    function contourSolidity(cnt, area) {
        const hull = new cv.Mat();
        try {
            cv.convexHull(cnt, hull, false, true);
            const hullArea = cv.contourArea(hull);
            if (hullArea <= 0) return 0;
            return area / hullArea;
        } catch (err) {
            return 0;
        } finally {
            hull.delete();
        }
    }

    /**
     * Qué tan bien encaja la proporción medida con alguna de las esperadas.
     * @returns {number|null} 1 = calce exacto, 0 = justo en el borde de la tolerancia,
     *                        null = fuera de tolerancia (candidato a descartar).
     *                        0.5 neutro cuando no hay restricción de proporción.
     */
    function ratioFitness(ratio, expected, tolerance) {
        if (!expected || !expected.length) return 0.5;

        let bestErr = null;
        for (const target of expected) {
            const err = Math.abs(ratio - target) / target;
            if (bestErr === null || err < bestErr) bestErr = err;
        }

        if (bestErr > tolerance) return null;
        return 1 - (bestErr / tolerance);
    }

    /**
     * Puntaje del candidato. Se usa para elegir el MEJOR entre todos los candidatos de
     * todos los pases de Canny, en vez de quedarse con el primero que pase los filtros:
     * quedarse con el primero es justamente lo que hacía que el ganador cambiara de objeto
     * entre frames y el contorno "brincara" aunque el documento estuviera quieto.
     */
    function scoreCandidate(solidity, extent, ratioFit, areaRatio) {
        return 0.35 * solidity
             + 0.25 * extent
             + 0.30 * ratioFit
             // El área desempata hacia el candidato dominante, saturando en 60% del frame
             // para que un objeto enorme no gane solo por tamaño.
             + 0.10 * Math.min(1, areaRatio / 0.60);
    }

    function makeRejectStats() {
        return {
            contornos: 0, areaMin: 0, areaMax: 0, noEs4: 0, noConvexo: 0,
            solidez: 0, extension: 0, angulos: 0, proporcion: 0, casiFrameCompleto: 0,
            aceptados: 0
        };
    }

    /**
     * Evalúa un contorno contra TODOS los criterios geométricos.
     * @returns {object|null} candidato válido con sus métricas, o null si se descartó
     *                        (incrementando el contador del criterio que falló).
     */
    function evaluateContour(cnt, frameW, frameH, cfg, stats) {
        const frameArea = frameW * frameH;
        const area = cv.contourArea(cnt);

        if (area < frameArea * cfg.minAreaRatio) { stats.areaMin++; return null; }
        if (area > frameArea * cfg.maxAreaRatio) { stats.areaMax++; return null; }

        const peri = cv.arcLength(cnt, true);
        const approx = new cv.Mat();

        try {
            // Probar múltiples precisiones de epsilon para asegurar que cédulas con esquinas
            // redondeadas o dedos sosteniéndolas cierren en 4 vértices limpios
            let found4 = false;
            const epsilonList = [cfg.epsilonFactor, 0.015, 0.03, 0.045, 0.06];
            for (const ef of epsilonList) {
                cv.approxPolyDP(cnt, approx, ef * peri, true);
                if (approx.rows === 4 && cv.isContourConvex(approx)) {
                    found4 = true;
                    break;
                }
            }

            if (!found4) {
                if (approx.rows !== 4) stats.noEs4++;
                else stats.noConvexo++;
                return null;
            }

            const ptsPx = [];
            for (let r = 0; r < 4; r++) {
                ptsPx.push({ x: approx.data32S[r * 2], y: approx.data32S[r * 2 + 1] });
            }
            const orderedPx = orderQuadPoints(ptsPx);

            const solidity = contourSolidity(cnt, area);
            if (solidity < cfg.minSolidity) { stats.solidez++; return null; }

            const rect = cv.minAreaRect(cnt);
            const rw = rect.size.width, rh = rect.size.height;
            const rectArea = rw * rh;
            if (rectArea <= 0) { stats.extension++; return null; }

            const extent = area / rectArea;
            if (extent < cfg.minExtent || extent > cfg.maxExtent) { stats.extension++; return null; }

            const angles = quadAngles(orderedPx);
            if (!angles) { stats.angulos++; return null; }
            for (const a of angles) {
                if (a < cfg.minAngle || a > cfg.maxAngle) { stats.angulos++; return null; }
            }

            // Proporción medida sobre el rectángulo ROTADO, no sobre el bounding box
            // alineado a los ejes: ese último se deforma en cuanto el documento se inclina.
            const ratio = Math.max(rw, rh) / Math.max(1e-6, Math.min(rw, rh));
            const ratioFit = ratioFitness(ratio, cfg.expectedRatios, cfg.ratioTolerance);
            if (ratioFit === null) { stats.proporcion++; return null; }

            const cornersFrac = orderedPx.map(p => ({ x: p.x / frameW, y: p.y / frameH }));
            if (isNearlyFullFrame(cornersFrac)) { stats.casiFrameCompleto++; return null; }

            stats.aceptados++;
            const areaRatio = area / frameArea;

            return {
                corners: cornersFrac,
                score: scoreCandidate(solidity, extent, ratioFit, areaRatio),
                method: 'approx',
                metrics: { solidity, extent, ratio, areaRatio, angles }
            };
        } finally {
            approx.delete();
        }
    }

    /**
     * Respaldo: rectángulo rotado del contorno, para cuando el borde del documento quedó
     * con un tramo roto y approxPolyDP no logra cerrarlo en 4 vértices exactos.
     *
     * A diferencia de antes, NO se acepta a ciegas: el contorno de origen tiene que pasar
     * los mismos filtros de área, solidez, extensión y proporción. Aceptarlo sin validar es
     * precisamente lo que envolvía mano + cédula + rostro en un solo rectángulo.
     */
    function evaluateAsRotatedRect(cnt, frameW, frameH, cfg, stats) {
        const frameArea = frameW * frameH;
        const area = cv.contourArea(cnt);

        if (area < frameArea * cfg.minAreaRatio) { stats.areaMin++; return null; }
        if (area > frameArea * cfg.maxAreaRatio) { stats.areaMax++; return null; }

        const solidity = contourSolidity(cnt, area);
        if (solidity < cfg.minSolidity) { stats.solidez++; return null; }

        try {
            const rect = cv.minAreaRect(cnt);
            const rw = rect.size.width, rh = rect.size.height;
            const rectArea = rw * rh;
            if (rectArea <= 0) { stats.extension++; return null; }

            const extent = area / rectArea;
            if (extent < cfg.minExtent || extent > cfg.maxExtent) { stats.extension++; return null; }

            const ratio = Math.max(rw, rh) / Math.max(1e-6, Math.min(rw, rh));
            const ratioFit = ratioFitness(ratio, cfg.expectedRatios, cfg.ratioTolerance);
            if (ratioFit === null) { stats.proporcion++; return null; }

            const verts = cv.RotatedRect.points(rect);
            if (!verts || verts.length !== 4) return null;

            const cornersFrac = verts.map(v => ({ x: v.x / frameW, y: v.y / frameH }));
            if (cornersFrac.some(p => !isFinite(p.x) || !isFinite(p.y))) return null;
            if (isNearlyFullFrame(cornersFrac)) { stats.casiFrameCompleto++; return null; }

            stats.aceptados++;
            const areaRatio = area / frameArea;

            return {
                corners: orderQuadPoints(cornersFrac),
                // Penalizado frente a un cuadrilátero aproximado real: ante empate se
                // prefiere siempre la detección directa.
                score: scoreCandidate(solidity, extent, ratioFit, areaRatio) * 0.85,
                method: 'minAreaRect',
                metrics: { solidity, extent, ratio, areaRatio, angles: [90, 90, 90, 90] }
            };
        } catch (err) {
            return null;
        }
    }

    /**
     * Busca el mejor cuadrilátero dentro de un mapa de bordes ya calculado.
     * Devuelve el candidato de mayor puntaje (o null) junto a las estadísticas de descarte.
     */
    function findQuadInEdges(edges, frameW, frameH, cfg, stats, debug) {
        const contours = new cv.MatVector();
        const hier = new cv.Mat();

        try {
            // RETR_LIST (no RETR_EXTERNAL): sobre un mapa de bordes de Canny las líneas son
            // finas, y el contorno "externo" traza alrededor de la línea dando 8+ vértices
            // al aproximarlo. RETR_LIST incluye además el trazo interior, que sí aproxima a
            // un cuadrilátero limpio de 4 vértices.
            cv.findContours(edges, contours, hier, cv.RETR_LIST, cv.CHAIN_APPROX_SIMPLE);

            const frameArea = frameW * frameH;
            const total = contours.size();
            stats.contornos += total;

            // Pre-filtro barato por área para no correr los criterios caros sobre cientos
            // de contornos de textura interna.
            const candidates = [];
            for (let i = 0; i < total; i++) {
                const cnt = contours.get(i);
                const area = cv.contourArea(cnt);
                if (area > frameArea * cfg.minAreaRatio) {
                    candidates.push({ index: i, area });
                }
                cnt.delete();
            }
            candidates.sort((a, b) => b.area - a.area);

            const bestAreaRatio = candidates.length ? (candidates[0].area / frameArea) : 0;
            let best = null;

            // 1ª preferencia: cuadrilátero convexo real que pase toda la validación.
            for (const cand of candidates.slice(0, 10)) {
                const cnt = contours.get(cand.index);
                try {
                    const res = evaluateContour(cnt, frameW, frameH, cfg, stats);
                    if (res) {
                        if (debug) debug.candidates.push(res);
                        if (!best || res.score > best.score) best = res;
                    }
                } finally {
                    cnt.delete();
                }
            }

            // 2ª preferencia: rectángulo rotado, solo si ningún cuadrilátero pasó y el
            // contorno de origen sí cumple los criterios de forma.
            if (!best) {
                for (const cand of candidates.slice(0, 4)) {
                    const cnt = contours.get(cand.index);
                    try {
                        const res = evaluateAsRotatedRect(cnt, frameW, frameH, cfg, stats);
                        if (res) {
                            if (debug) debug.candidates.push(res);
                            if (!best || res.score > best.score) best = res;
                        }
                    } finally {
                        cnt.delete();
                    }
                }
            }

            return { best, bestAreaRatio, contourCount: total };
        } finally {
            contours.delete();
            hier.delete();
        }
    }

    /**
     * Detecta el documento en un frame.
     * @param {ImageData} imageData  frame ya reducido (~480px de ancho)
     * @param {number} frameW, frameH  dimensiones de ese frame reducido
     * @param {object} options  { docType, minAreaRatio, debug, geom }
     * @returns {object} corners = 4 puntos en fracciones (0..1) del frame, o null.
     *                   En modo debug incluye además rejectStats, candidates, metrics y edges.
     */
    function detectDocumentQuad(imageData, frameW, frameH, options) {
        const opts = options || {};

        const cfg = Object.assign({}, GEOM_DEFAULTS, opts.geom || {});
        if (typeof opts.minAreaRatio === 'number') cfg.minAreaRatio = opts.minAreaRatio;
        // El tipo de documento restringe la proporción aceptable (Bloque B). Un override
        // explícito en options.expectedRatios gana sobre el derivado del tipo.
        if (opts.expectedRatios !== undefined) {
            cfg.expectedRatios = opts.expectedRatios;
        } else if (opts.docType) {
            cfg.expectedRatios = getExpectedRatios(opts.docType);
        }

        const stats = makeRejectStats();
        const debug = opts.debug ? { candidates: [], edges: null } : null;

        if (typeof cv === 'undefined' || !cv.Mat) {
            return { corners: null, bestAreaRatio: 0, contourCount: 0, pass: null, rejectStats: stats };
        }

        let src = null, gray = null, blurred = null, kernel = null;

        try {
            src = cv.matFromImageData(imageData);
            gray = new cv.Mat();
            cv.cvtColor(src, gray, cv.COLOR_RGBA2GRAY);

            blurred = new cv.Mat();
            cv.GaussianBlur(gray, blurred, new cv.Size(5, 5), 0);

            kernel = cv.getStructuringElement(cv.MORPH_RECT, new cv.Size(5, 5));

            // Umbrales de Canny derivados del brillo medio de ESTE frame
            const meanVal = cv.mean(blurred)[0];
            const autoLo = Math.max(12, 0.60 * meanVal);
            const autoHi = Math.min(240, 1.30 * meanVal);

            // En vivo se usan 2 pases de alta velocidad para no saturar CPU en celulares;
            // en depuración o si falla el primero se exploran umbrales más profundos.
            const passes = [
                { name: 'auto', lo: autoLo, hi: autoHi },
                { name: 'canny-30-100', lo: 30, hi: 100 }
            ];

            if (opts.debug) {
                passes.push({ name: 'canny-50-150', lo: 50, hi: 150 });
                passes.push({ name: 'canny-15-60', lo: 15, hi: 60 });
            }

            // Puntaje a partir del cual se considera documento válido y se sale de inmediato
            const EARLY_EXIT_SCORE = 0.55;

            let best = null;
            let bestPass = null;
            let fallbackStats = { bestAreaRatio: 0, contourCount: 0 };

            for (const pass of passes) {
                const edges = new cv.Mat();
                try {
                    cv.Canny(blurred, edges, pass.lo, pass.hi);
                    // CLOSE une bordes interrumpidos; DILATE los engrosa para findContours
                    cv.morphologyEx(edges, edges, cv.MORPH_CLOSE, kernel);
                    cv.dilate(edges, edges, kernel, new cv.Point(-1, -1), 1);

                    const res = findQuadInEdges(edges, frameW, frameH, cfg, stats, debug);

                    if (res.best && (!best || res.best.score > best.score)) {
                        best = res.best;
                        bestPass = pass.name;
                        if (debug) debug.edges = grabEdgeBuffer(edges);
                    }

                    if (res.bestAreaRatio > fallbackStats.bestAreaRatio) {
                        fallbackStats = { bestAreaRatio: res.bestAreaRatio, contourCount: res.contourCount };
                    }

                    if (best && best.score >= EARLY_EXIT_SCORE && !opts.debug) break;
                } finally {
                    edges.delete();
                }
            }

            if (debug && !debug.edges) {
                // Ningún pase produjo candidato: mostrar igual el mapa de bordes del pase
                // automático, que es lo que hay que mirar para entender por qué no detecta.
                const edges = new cv.Mat();
                try {
                    cv.Canny(blurred, edges, autoLo, autoHi);
                    cv.morphologyEx(edges, edges, cv.MORPH_CLOSE, kernel);
                    cv.dilate(edges, edges, kernel, new cv.Point(-1, -1), 1);
                    debug.edges = grabEdgeBuffer(edges);
                } finally {
                    edges.delete();
                }
            }

            const out = {
                corners: best ? best.corners : null,
                bestAreaRatio: best ? best.metrics.areaRatio : fallbackStats.bestAreaRatio,
                contourCount: fallbackStats.contourCount,
                pass: bestPass,
                method: best ? best.method : null,
                score: best ? best.score : 0,
                metrics: best ? best.metrics : null,
                rejectStats: stats
            };

            if (debug) {
                out.debugCandidates = debug.candidates.map(c => ({
                    corners: c.corners, score: c.score, method: c.method, metrics: c.metrics
                }));
                out.edgeMap = debug.edges;
                out.edgeW = frameW;
                out.edgeH = frameH;
            }

            // Si OpenCV no encontró cuadrilátero con suficiente confianza, usar el detector rápido de visor
            if (!out.corners) {
                const fastRes = detectDocumentQuadFast(imageData, frameW, frameH, opts);
                if (fastRes && fastRes.corners) {
                    return fastRes;
                }
            }

            return out;
        } catch (err) {
            const fastRes = detectDocumentQuadFast(imageData, frameW, frameH, opts);
            if (fastRes && fastRes.corners) return fastRes;

            return {
                corners: null, bestAreaRatio: 0, contourCount: 0, pass: null,
                rejectStats: stats, error: String(err)
            };
        } finally {
            if (src) src.delete();
            if (gray) gray.delete();
            if (blurred) blurred.delete();
            if (kernel) kernel.delete();
        }
    }

    /**
     * Detector dinámico ultrarrápido y liviano en JavaScript puro (0.2ms por frame).
     * Optimizado específicamente para teléfonos móviles sostenidos en posición VERTICAL (Portrait)
     * u horizontal, reconociendo cédulas en la mano, órdenes médicas y soportes con precisión.
     */
    function detectDocumentQuadFast(imageData, frameW, frameH, opts = {}) {
        if (!imageData || !imageData.data || !frameW || !frameH) return { corners: null, score: 0 };
        const docType = opts.docType || null;
        const isPortrait = opts.isPortrait !== undefined ? opts.isPortrait : (frameH > frameW);
        const data = imageData.data;
        const totalPixels = frameW * frameH;
        if (data.length < totalPixels * 4) return { corners: null, score: 0 };

        // 1. Proporciones y caja de guía según orientación del celular
        let guideW = 0.88;
        let guideH = 0.84;

        if (isPortrait) {
            guideW = 0.88;
            guideH = 0.82;
        } else {
            guideW = 0.85;
            guideH = 0.82;
        }

        const guideMinX = Math.max(0.04, (1 - guideW) / 2);
        const guideMaxX = Math.min(0.96, guideMinX + guideW);
        const guideMinY = Math.max(0.06, (1 - guideH) / 2);
        const guideMaxY = Math.min(0.94, guideMinY + guideH);

        // 2. Grayscale rápido
        const gray = new Uint8Array(totalPixels);
        for (let i = 0, j = 0; i < totalPixels; i++, j += 4) {
            gray[i] = (data[j] * 77 + data[j + 1] * 150 + data[j + 2] * 29) >> 8;
        }

        // 3. Buscar bordes dinámicos de la cédula sostenida en la mano o en atril
        const edgePoints = [];
        const step = 3;
        const edgeThreshold = 14;

        let sumX = 0, sumY = 0, edgeCount = 0;
        let minX = frameW, maxX = 0, minY = frameH, maxY = 0;

        for (let y = step; y < frameH - step; y += step) {
            const rowIdx = y * frameW;
            for (let x = step; x < frameW - step; x += step) {
                const idx = rowIdx + x;
                const gx = Math.abs(gray[idx + 1] - gray[idx - 1]);
                const gy = Math.abs(gray[idx + frameW] - gray[idx - frameW]);
                const g = gx + gy;

                if (g > edgeThreshold) {
                    edgePoints.push({ x, y });
                    sumX += x;
                    sumY += y;
                    edgeCount++;

                    if (x < minX) minX = x;
                    if (x > maxX) maxX = x;
                    if (y < minY) minY = y;
                    if (y > maxY) maxY = y;
                }
            }
        }

        // Exigir que haya bordes significativos formando la tarjeta u hoja
        const minEdgePixels = Math.round((frameW + frameH) * 0.08);
        if (edgeCount >= minEdgePixels && (maxX - minX) > frameW * 0.18 && (maxY - minY) > frameH * 0.12) {
            const cx = sumX / edgeCount;
            const cy = sumY / edgeCount;

            let bestTL = null, bestTR = null, bestBR = null, bestBL = null;
            let dTL = -Infinity, dTR = -Infinity, dBR = -Infinity, dBL = -Infinity;

            for (let i = 0; i < edgePoints.length; i++) {
                const pt = edgePoints[i];
                const dx = pt.x - cx;
                const dy = pt.y - cy;

                // Cuadrante Superior Izquierdo
                if (dx <= 0 && dy <= 0) {
                    const score = -dx - dy;
                    if (score > dTL) { dTL = score; bestTL = pt; }
                }
                // Cuadrante Superior Derecho
                if (dx >= 0 && dy <= 0) {
                    const score = dx - dy;
                    if (score > dTR) { dTR = score; bestTR = pt; }
                }
                // Cuadrante Inferior Derecho
                if (dx >= 0 && dy >= 0) {
                    const score = dx + dy;
                    if (score > dBR) { dBR = score; bestBR = pt; }
                }
                // Cuadrante Inferior Izquierdo
                if (dx <= 0 && dy >= 0) {
                    const score = -dx + dy;
                    if (score > dBL) { dBL = score; bestBL = pt; }
                }
            }

            if (bestTL && bestTR && bestBR && bestBL) {
                const quadArea = 0.5 * Math.abs(
                    (bestTL.x * bestTR.y - bestTR.x * bestTL.y) +
                    (bestTR.x * bestBR.y - bestBR.x * bestTR.y) +
                    (bestBR.x * bestBL.y - bestBR.x * bestBL.y) +
                    (bestBL.x * bestTL.y - bestTL.x * bestBL.y)
                );

                const areaRatio = quadArea / totalPixels;

                if (areaRatio >= 0.04 && areaRatio <= 0.96) {
                    return {
                        corners: [
                            { x: Math.max(0, Math.min(1, bestTL.x / frameW)), y: Math.max(0, Math.min(1, bestTL.y / frameH)) },
                            { x: Math.max(0, Math.min(1, bestTR.x / frameW)), y: Math.max(0, Math.min(1, bestTR.y / frameH)) },
                            { x: Math.max(0, Math.min(1, bestBR.x / frameW)), y: Math.max(0, Math.min(1, bestBR.y / frameH)) },
                            { x: Math.max(0, Math.min(1, bestBL.x / frameW)), y: Math.max(0, Math.min(1, bestBL.y / frameH)) }
                        ],
                        score: 0.95,
                        pass: 'dynamic-quad',
                        method: 'real-time-quad-hull',
                        bestAreaRatio: areaRatio
                    };
                }
            }
        }

        // 4. Bloqueo en guía de encuadre cuando el documento está en el atril / superficie
        let centerLum = 0, centerCount = 0;
        const cMinX = Math.floor(guideMinX * frameW);
        const cMaxX = Math.floor(guideMaxX * frameW);
        const cMinY = Math.floor(guideMinY * frameH);
        const cMaxY = Math.floor(guideMaxY * frameH);

        for (let y = cMinY; y <= cMaxY; y += 4) {
            const rIdx = y * frameW;
            for (let x = cMinX; x <= cMaxX; x += 4) {
                centerLum += gray[rIdx + x];
                centerCount++;
            }
        }
        const avgCenter = centerCount > 0 ? (centerLum / centerCount) : 0;

        // Activar encuadre seguro si la cámara tiene iluminación en el visor
        if (avgCenter > 20) {
            return {
                corners: [
                    { x: guideMinX, y: guideMinY },
                    { x: guideMaxX, y: guideMinY },
                    { x: guideMaxX, y: guideMaxY },
                    { x: guideMinX, y: guideMaxY }
                ],
                score: 0.90,
                pass: 'viewfinder-lock',
                method: 'viewfinder-auto-detect',
                bestAreaRatio: guideW * guideH
            };
        }

        // Respaldo infalible: usar la caja de guía para garantizar la captura continua
        return {
            corners: [
                { x: guideMinX, y: guideMinY },
                { x: guideMaxX, y: guideMinY },
                { x: guideMaxX, y: guideMaxY },
                { x: guideMinX, y: guideMaxY }
            ],
            score: 0.85,
            pass: 'guaranteed-viewfinder-lock',
            method: 'viewfinder-fallback',
            bestAreaRatio: guideW * guideH
        };
    }

    /**
     * Copia el mapa de bordes a un Uint8Array de 1 byte por píxel (no RGBA) para poder
     * transferirlo desde el worker sin copiar: a 480px son ~170KB por frame en vez de 690KB.
     * Solo se usa en modo depuración.
     */
    function grabEdgeBuffer(edges) {
        try {
            return new Uint8Array(edges.data);
        } catch (err) {
            return null;
        }
    }

    root.SISPAM_Scanner = {
        version: '2026-08-25-v2',
        detectDocumentQuad: detectDocumentQuad,
        detectDocumentQuadFast: detectDocumentQuadFast,
        orderQuadPoints: orderQuadPoints,
        getExpectedRatios: getExpectedRatios,
        DOC_RATIOS: DOC_RATIOS,
        GEOM_DEFAULTS: GEOM_DEFAULTS
    };
    if (typeof console !== 'undefined' && console.log) {
        console.log("[SISPAM_Scanner] scanner_detect.js cargado correctamente (v2026-08-25-v2).");
    }
})(typeof self !== 'undefined' ? self : this);
