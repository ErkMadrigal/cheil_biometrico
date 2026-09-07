import { Injectable, signal } from '@angular/core';

const MODEL_URL = 'models';

export type FaceStage = 'idle' | 'loadingModels' | 'requestingCamera' | 'scanning' | 'error';

let modelsPromise: Promise<typeof import('@vladmandic/face-api')> | null = null;

/**
 * Carga face-api.js (TensorFlow.js) SOLO cuando el flujo de checada llega a la pantalla
 * de camara (import dinamico, ~1.3MB), y encapsula "prender camara + detectar rostro en
 * vivo + sacar su descriptor de 128 numeros". Misma logica que useFaceCamera.js del web
 * panel, portada a Angular con signals en vez de refs de Vue.
 */
function loadModels() {
  if (!modelsPromise) {
    modelsPromise = import('@vladmandic/face-api').then(async (faceapi) => {
      await Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
        faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
        faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
      ]);
      return faceapi;
    });
  }
  return modelsPromise;
}

@Injectable({ providedIn: 'root' })
export class FaceService {
  readonly stage = signal<FaceStage>('idle');
  readonly faceDetected = signal(false);
  readonly errorMsg = signal('');

  private stream: MediaStream | null = null;
  private loopId: number | null = null;
  private stopped = true;
  private faceapi: typeof import('@vladmandic/face-api') | null = null;
  private video: HTMLVideoElement | null = null;
  private canvas: HTMLCanvasElement | null = null;

  private detectorOptions() {
    return new this.faceapi!.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 });
  }

  async start(video: HTMLVideoElement, canvas: HTMLCanvasElement | null): Promise<boolean> {
    this.video = video;
    this.canvas = canvas;
    this.errorMsg.set('');
    this.faceDetected.set(false);
    this.stage.set('loadingModels');

    try {
      this.faceapi = await loadModels();
    } catch {
      this.stage.set('error');
      this.errorMsg.set('No se pudieron cargar los modelos de reconocimiento facial.');
      return false;
    }

    this.stage.set('requestingCamera');
    try {
      this.stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'user', width: 480, height: 480 },
      });
    } catch {
      this.stage.set('error');
      this.errorMsg.set('No se pudo acceder a la camara. Revisa los permisos de la app.');
      return false;
    }

    video.srcObject = this.stream;
    await video.play();

    this.stage.set('scanning');
    this.stopped = false;
    this.runDetectLoop();
    return true;
  }

  private async runDetectLoop() {
    if (this.stopped || !this.video || this.stage() !== 'scanning' || !this.faceapi) return;

    const video = this.video;
    const canvas = this.canvas;

    if (video.readyState >= 2) {
      try {
        // Sin argumento: usa faceLandmark68Net (el que si cargamos en loadModels()).
        // Pasar "true" pide el modelo "tiny" de landmarks, que nunca se carga, y
        // tronaba cada frame matando el loop desde el primer intento.
        const result = await this.faceapi.detectSingleFace(video, this.detectorOptions()).withFaceLandmarks();

        if (canvas) {
          const ctx = canvas.getContext('2d');
          canvas.width = video.videoWidth;
          canvas.height = video.videoHeight;
          if (ctx) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            if (result) {
              this.faceDetected.set(true);
              const box = result.detection.box;
              ctx.strokeStyle = '#10b981';
              ctx.lineWidth = 4;
              ctx.beginPath();
              ctx.roundRect(box.x, box.y, box.width, box.height, 16);
              ctx.stroke();
            } else {
              this.faceDetected.set(false);
            }
          }
        } else {
          this.faceDetected.set(!!result);
        }
      } catch {
        // No dejamos que un frame fallido mate el loop completo.
        this.faceDetected.set(false);
      }
    }

    this.loopId = requestAnimationFrame(() => this.runDetectLoop());
  }

  /** Congela el loop y calcula el descriptor final. Regresa null si no hay rostro en ese instante. */
  async captureDescriptor(): Promise<Float32Array | null> {
    if (!this.video || !this.faceDetected() || !this.faceapi) return null;

    const result = await this.faceapi
      .detectSingleFace(this.video, this.detectorOptions())
      .withFaceLandmarks()
      .withFaceDescriptor();

    if (!result) return null;

    this.stopLoop();
    return result.descriptor;
  }

  resume() {
    if (this.stage() !== 'scanning') this.stage.set('scanning');
    this.stopped = false;
    this.runDetectLoop();
  }

  private stopLoop() {
    this.stopped = true;
    if (this.loopId) cancelAnimationFrame(this.loopId);
    this.loopId = null;
  }

  stopCamera() {
    if (this.stream) {
      this.stream.getTracks().forEach((t) => t.stop());
      this.stream = null;
    }
  }

  cleanup() {
    this.stopLoop();
    this.stopCamera();
    this.stage.set('idle');
    this.faceDetected.set(false);
  }
}
