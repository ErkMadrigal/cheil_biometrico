const MODEL_URL = '/models'

let readyPromise = null

/**
 * Carga face-api.js (dinamicamente, ~1.3MB con TensorFlow.js incluido) y sus 3
 * modelos SOLO cuando de verdad se necesitan (al abrir el modal de enrolamiento),
 * para no engordar el chunk de la lista de empleados con una libreria de ML pesada.
 *
 * - tinyFaceDetector: encuentra la cara en el frame
 * - faceLandmark68: ubica los puntos de referencia (ojos, nariz, boca...)
 * - faceRecognitionNet: calcula el descriptor/embedding de 128 numeros
 *
 * @returns {Promise<typeof import('@vladmandic/face-api')>}
 */
export function useFaceModels() {
  if (!readyPromise) {
    readyPromise = import('@vladmandic/face-api').then(async (faceapi) => {
      await Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
        faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
        faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
      ])
      return faceapi
    })
  }
  return readyPromise
}
