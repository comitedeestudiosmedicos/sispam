/**
 * Sintetizador de Voz para Turnero TV (Web Speech API + Chime Sonoro Ding-Dong)
 * Optimizado para llamado por Nombre Completo con Selección de Voz Natural y Clara.
 */

class TurneroSpeech {
    constructor() {
        this.synth = window.speechSynthesis || null;
        this.voices = [];
        this.audioCtx = null;
        this.initVoices();
    }

    initVoices() {
        if (!this.synth) return;
        this.voices = this.synth.getVoices();
        if (this.synth.onvoiceschanged !== undefined) {
            this.synth.onvoiceschanged = () => {
                this.voices = this.synth.getVoices();
            };
        }
    }

    selectBestVoice() {
        if (!this.voices || this.voices.length === 0) {
            this.voices = this.synth.getVoices();
        }

        // Criterios de prioridad para seleccionar la voz en español más fluida y natural (menos robótica)
        const filters = [
            v => v.name.toLowerCase().includes('google') && v.lang.startsWith('es'),
            v => (v.name.toLowerCase().includes('natural') || v.name.toLowerCase().includes('neural')) && v.lang.startsWith('es'),
            v => v.name.includes('Sabina') || v.name.includes('Dalia') || v.name.includes('Helena') || v.name.includes('Laura') || v.name.includes('Jorge') || v.name.includes('Alonso') || v.name.includes('Salome'),
            v => v.lang === 'es-CO',
            v => v.lang.startsWith('es-MX') || v.lang.startsWith('es-US') || v.lang.startsWith('es-ES'),
            v => v.lang.startsWith('es')
        ];

        for (const filter of filters) {
            const voice = this.voices.find(filter);
            if (voice) return voice;
        }
        return null;
    }

    playChime() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!this.audioCtx) {
                this.audioCtx = new AudioContext();
            }
            if (this.audioCtx.state === 'suspended') {
                this.audioCtx.resume();
            }

            const now = this.audioCtx.currentTime;

            // Nota 1: G5 (783.99 Hz)
            const osc1 = this.audioCtx.createOscillator();
            const gain1 = this.audioCtx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(783.99, now);
            gain1.gain.setValueAtTime(0.25, now);
            gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
            osc1.connect(gain1);
            gain1.connect(this.audioCtx.destination);
            osc1.start(now);
            osc1.stop(now + 0.6);

            // Nota 2: E5 (659.25 Hz) - Chime Clásico "Ding-Dong"
            const osc2 = this.audioCtx.createOscillator();
            const gain2 = this.audioCtx.createGain();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(659.25, now + 0.35);
            gain2.gain.setValueAtTime(0.25, now + 0.35);
            gain2.gain.exponentialRampToValueAtTime(0.001, now + 1.2);
            osc2.connect(gain2);
            gain2.connect(this.audioCtx.destination);
            osc2.start(now + 0.35);
            osc2.stop(now + 1.2);

        } catch (e) {
            console.warn("AudioContext no iniciado:", e);
        }
    }

    normalizarTextoParaVoz(texto) {
        if (!texto) return '';

        let limpio = texto.toString().trim().toLowerCase();

        // Eliminar caracteres especiales excepto letras en español, números y espacios
        limpio = limpio.replace(/[^a-záéíóúñ0-9\s]/gi, ' ');

        // Ajuste fonético especializado para motores TTS que se traban o deletrean la palabra PREFERENCIAL:
        // Reemplazar la ortografía por la pronunciación fonética "preferensial" o "prefe rencial" 
        // para forzar a cualquier sintetizador (SAPI5, Android, Google, Apple) a pronunciarla fluida y continua.
        limpio = limpio.replace(/\bpreferencial\b/gi, 'preferensial');
        limpio = limpio.replace(/\bmodulo\b/gi, 'módulo');
        limpio = limpio.replace(/\bventanilla\b/gi, 'ventanilla');

        // Limpiar espacios múltiples sobrantes
        limpio = limpio.replace(/\s+/g, ' ').trim();

        return limpio;
    }

    speak(nombrePaciente, modulo) {
        // 1. Reproducir el timbre Ding-Dong
        this.playChime();

        if (!this.synth) return;

        // Normalizar paciente y módulo a minúsculas y fonética adaptada
        const pacienteNom = this.normalizarTextoParaVoz(nombrePaciente);
        const moduloNom = this.normalizarTextoParaVoz(modulo);

        // Formatear la frase de llamado
        const text = `Atención. Paciente ${pacienteNom}, favor pasar a ${moduloNom}`;

        // Cancelar avisos anteriores en cola
        this.synth.cancel();

        setTimeout(() => {
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'es-CO';
            utterance.rate = 0.85;   // Velocidad pausada, natural e inteligible
            utterance.pitch = 1.0;   // Tono humano natural

            const bestVoice = this.selectBestVoice();
            if (bestVoice) {
                utterance.voice = bestVoice;
            }

            this.synth.speak(utterance);
        }, 750);
    }
}

window.turneroSpeech = new TurneroSpeech();
