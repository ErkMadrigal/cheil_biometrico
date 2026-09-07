import { ref, onBeforeUnmount, nextTick } from 'vue'
import { useFaceModels } from './useFaceModels'

/**
 * Encapsula "prender la camara + detectar un rostro en vivo + sacar su descriptor
 * de 128 dimensiones". Lo usan tanto el enrolamiento (Empleados) como el kiosko
 * de registro biometrico del login, para no duplicar la logica de camara/deteccion.
 *
 * @param {import('vue').Ref<HTMLVideoElement|null>} videoRef
 * @param {import('vue').Ref<HTMLCanvasElement|null>} canvasRef
 */
export function useFaceCamera(videoRef, canvasRef) {
  // idle -> loadingModels -> requestingCamera -> scanning -> error
  const stage = ref('idle')
  const faceDetected = ref(false)
  const errorMsg = ref('')

  let stream = null
  let loopId = null
  let stopped = true
  let faceapi = null

  const detectorOptions = () => new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 })

  async function start() {
    errorMsg.value = ''
    faceDetected.value = false
    stage.value = 'loadingModels'

    try {
      faceapi = await useFaceModels()
    } catch (e) {
      stage.value = 'error'
      errorMsg.value = 'No se pudieron cargar los modelos de reconocimiento facial.'
      return false
    }

    stage.value = 'requestingCamera'
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 480, height: 480 } })
    } catch (e) {
      stage.value = 'error'
      errorMsg.value = 'No se pudo acceder a la camara. Revisa los permisos del navegador.'
      return false
    }

    await nextTick()
    if (!videoRef.value) return false
    videoRef.value.srcObject = stream
    await videoRef.value.play()

    stage.value = 'scanning'
    stopped = false
    runDetectLoop()
    return true
  }

  async function runDetectLoop() {
    if (stopped || !videoRef.value || stage.value !== 'scanning') return

    const video = videoRef.value
    const canvas = canvasRef?.value

    if (video.readyState >= 2) {
      try {
        // Sin argumento: usa faceLandmark68Net (el modelo completo, que si cargamos en
        // useFaceModels.js). Pasar "true" aqui pide el modelo "tiny" de landmarks, que
        // nunca se carga, y tronaba cada frame matando el loop desde el primer intento.
        const result = await faceapi.detectSingleFace(video, detectorOptions()).withFaceLandmarks()

        if (canvas) {
          const ctx = canvas.getContext('2d')
          canvas.width = video.videoWidth
          canvas.height = video.videoHeight
          ctx.clearRect(0, 0, canvas.width, canvas.height)

          if (result) {
            faceDetected.value = true
            const box = result.detection.box
            ctx.strokeStyle = '#34d399'
            ctx.lineWidth = 3
            ctx.beginPath()
            ctx.roundRect(box.x, box.y, box.width, box.height, 12)
            ctx.stroke()
          } else {
            faceDetected.value = false
          }
        }
      } catch (e) {
        // No dejamos que un frame fallido mate el loop completo.
        faceDetected.value = false
      }
    }

    loopId = requestAnimationFrame(runDetectLoop)
  }

  /**
   * Detiene el loop de deteccion en vivo (para congelar el frame) y calcula el
   * descriptor final de 128 numeros. Regresa null si en ese instante no hay rostro.
   */
  async function captureDescriptor() {
    if (!videoRef.value || !faceDetected.value) return null

    const result = await faceapi
      .detectSingleFace(videoRef.value, detectorOptions())
      .withFaceLandmarks()
      .withFaceDescriptor()

    if (!result) return null

    stopLoop()
    return result.descriptor
  }

  /** Reanuda la deteccion en vivo (ej. el usuario quiere volver a intentar). */
  function resume() {
    if (stage.value !== 'scanning') stage.value = 'scanning'
    stopped = false
    runDetectLoop()
  }

  function stopLoop() {
    stopped = true
    if (loopId) cancelAnimationFrame(loopId)
    loopId = null
  }

  function stopCamera() {
    if (stream) {
      stream.getTracks().forEach((t) => t.stop())
      stream = null
    }
  }

  function cleanup() {
    stopLoop()
    stopCamera()
    stage.value = 'idle'
    faceDetected.value = false
  }

  onBeforeUnmount(cleanup)

  return { stage, faceDetected, errorMsg, start, captureDescriptor, resume, cleanup }
}
