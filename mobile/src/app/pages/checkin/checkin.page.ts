import { Component, ElementRef, ViewChild, computed, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import {
  IonContent,
  IonButton,
  IonInput,
  IonIcon,
  IonSpinner,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
  personCircleOutline,
  locationOutline,
  cameraOutline,
  checkmarkCircle,
  closeCircle,
  alertCircleOutline,
  arrowBackOutline,
  refreshOutline,
  logOutOutline,
  navigateOutline,
  timeOutline,
  idCardOutline,
  fingerPrintOutline,
  moonOutline,
  sunnyOutline,
} from 'ionicons/icons';

import { MobileApiService } from '../../services/mobile-api.service';
import { LocationService, LocationPermissionState } from '../../services/location.service';
import { FaceService } from '../../services/face.service';
import { ThemeService } from '../../services/theme.service';
import { CheckinResult, EmployeeLookupResult, fullEmployeeName } from '../../models/employee.model';

type Step =
  | 'identify'
  | 'confirm'
  | 'enrollCapture'
  | 'enrollVerify'
  | 'homeVerify'
  | 'homeCapture'
  | 'homeSuccess'
  | 'location'
  | 'camera'
  | 'success';

@Component({
  selector: 'app-checkin',
  standalone: true,
  imports: [CommonModule, FormsModule, IonContent, IonButton, IonInput, IonIcon, IonSpinner],
  templateUrl: './checkin.page.html',
  styleUrl: './checkin.page.scss',
})
export class CheckinPage {
  @ViewChild('videoEl') videoEl?: ElementRef<HTMLVideoElement>;
  @ViewChild('canvasEl') canvasEl?: ElementRef<HTMLCanvasElement>;

  step = signal<Step>('identify');

  // Paso 1: identificar
  query = signal('');
  lookupLoading = signal(false);
  lookupError = signal('');
  employee = signal<EmployeeLookupResult | null>(null);

  // Paso 2b (solo primera vez sin rostro enrolado): auto-enrolamiento en la app.
  // Reusa el mismo token/endpoints de la liga web de 72h (ver EnrollTokenController):
  // captura + verificacion, igual de estricto que si un admin le hubiera mandado la liga.
  enrollSubmitting = signal(false);
  enrollError = signal('');
  enrollMismatch = signal(false); // true = la 2a captura no hizo match contra la 1a

  // Home Office: el empleado (ya enrolado) fija su ubicacion personal una sola vez, con
  // margen de 100m para checar su entrada. Requiere verificacion facial + GPS. Si se
  // muda, un admin la desbloquea desde el panel y el empleado la vuelve a capturar aqui.
  homeSubmitting = signal(false);
  homeError = signal('');
  homeLocked = signal(false); // true = ya tiene HO fija, debe pedir a RH que la desbloquee
  homeResult = signal<{ home_lat: string; home_lng: string; home_radius_meters: number } | null>(null);
  private homeDescriptor: number[] | null = null;

  // Paso 3: ubicacion
  locationState = signal<LocationPermissionState>('prompt');
  locationChecking = signal(false);
  coords = signal<{ lat: number; lng: number; accuracy: number | null } | null>(null);

  // Paso 4: camara + resultado
  submitError = signal('');
  faceMismatch = signal(false); // true = el rostro no hizo match (a diferencia de un error de red/servidor)
  submitting = signal(false);
  result = signal<CheckinResult | null>(null);

  fullName = computed(() => (this.employee() ? fullEmployeeName(this.employee()!) : ''));
  photoUrl = computed(() => this.api.photoUrl(this.employee()?.photo_path ?? null));

  faceStage;
  faceDetected;
  faceError;

  constructor(
    private api: MobileApiService,
    private location: LocationService,
    private face: FaceService,
    public themeSvc: ThemeService,
  ) {
    this.faceStage = this.face.stage;
    this.faceDetected = this.face.faceDetected;
    this.faceError = this.face.errorMsg;

    addIcons({
      personCircleOutline,
      locationOutline,
      cameraOutline,
      checkmarkCircle,
      closeCircle,
      alertCircleOutline,
      arrowBackOutline,
      refreshOutline,
      logOutOutline,
      navigateOutline,
      timeOutline,
      idCardOutline,
      fingerPrintOutline,
      moonOutline,
      sunnyOutline,
    });
  }

  // ---------------------------------------------------------------
  // Paso 1: identificar por numero de empleado / CURP / RFC
  // ---------------------------------------------------------------
  async onLookup() {
    const q = this.query().trim();
    if (!q) return;

    this.lookupLoading.set(true);
    this.lookupError.set('');

    try {
      const emp = await this.api.lookup(q);
      this.employee.set(emp);
      this.step.set('confirm');
    } catch (e: any) {
      this.lookupError.set(
        e?.error?.message || 'No encontramos a nadie con ese dato. Revisa e intenta de nuevo.',
      );
    } finally {
      this.lookupLoading.set(false);
    }
  }

  notMe() {
    this.employee.set(null);
    this.query.set('');
    this.lookupError.set('');
    this.step.set('identify');
  }

  // ---------------------------------------------------------------
  // Paso 2 -> 3: confirmar identidad, pasar a checar ubicacion
  // (si es la primera vez del empleado y no tiene rostro enrolado, primero se
  // manda a enrolar dentro de la misma app -- ver goToEnrollCapture())
  // ---------------------------------------------------------------
  async confirmAndContinue() {
    const emp = this.employee();
    if (emp && !emp.has_face_enrolled) {
      await this.goToEnrollCapture();
      return;
    }
    this.step.set('location');
    await this.checkLocation();
  }

  // ---------------------------------------------------------------
  // Paso 2b: auto-enrolamiento (solo primera vez, sin admin de por medio).
  // Mismo criterio que la liga web: captura ("save") + segunda captura en vivo
  // que se compara contra la primera ("verify"). Si algo falla se puede
  // reintentar sin salir de la app.
  // ---------------------------------------------------------------
  async goToEnrollCapture() {
    this.step.set('enrollCapture');
    this.enrollError.set('');
    this.enrollMismatch.set(false);
    setTimeout(() => {
      if (this.videoEl && this.canvasEl) {
        this.face.start(this.videoEl.nativeElement, this.canvasEl.nativeElement);
      }
    }, 0);
  }

  retryEnroll() {
    this.enrollError.set('');
    this.face.resume();
  }

  async captureForEnroll() {
    const emp = this.employee();
    const token = emp?.enroll_token;
    if (!emp || !token) {
      this.enrollError.set('Perdimos tu sesion de enrolamiento. Vuelve a intentar desde el inicio.');
      return;
    }

    const descriptor = await this.face.captureDescriptor();
    if (!descriptor) {
      this.enrollMismatch.set(false);
      this.enrollError.set('No detectamos tu rostro. Centra tu cara en el circulo e intenta de nuevo.');
      return;
    }

    this.enrollSubmitting.set(true);
    this.enrollError.set('');
    this.enrollMismatch.set(false);

    try {
      await this.api.enrollSave(token, Array.from(descriptor));
      this.face.cleanup();
      this.step.set('enrollVerify');
      setTimeout(() => {
        if (this.videoEl && this.canvasEl) {
          this.face.start(this.videoEl.nativeElement, this.canvasEl.nativeElement);
        }
      }, 0);
    } catch (e: any) {
      this.enrollError.set(e?.error?.message || 'No pudimos guardar tu rostro. Intenta de nuevo.');
      this.face.resume();
    } finally {
      this.enrollSubmitting.set(false);
    }
  }

  async captureForVerify() {
    const emp = this.employee();
    const token = emp?.enroll_token;
    if (!emp || !token) {
      this.enrollError.set('Perdimos tu sesion de enrolamiento. Vuelve a intentar desde el inicio.');
      return;
    }

    const descriptor = await this.face.captureDescriptor();
    if (!descriptor) {
      this.enrollMismatch.set(false);
      this.enrollError.set('No detectamos tu rostro. Centra tu cara en el circulo e intenta de nuevo.');
      return;
    }

    this.enrollSubmitting.set(true);
    this.enrollError.set('');
    this.enrollMismatch.set(false);

    try {
      await this.api.enrollVerify(token, Array.from(descriptor));
      this.face.cleanup();
      // Ya quedo enrolado y verificado en el backend -- lo reflejamos localmente y
      // seguimos derecho al flujo normal de checada (ubicacion -> camara), sin que el
      // empleado tenga que volver a buscarse ni confirmar de nuevo.
      this.employee.set({ ...emp, has_face_enrolled: true, enroll_token: null });
      this.step.set('location');
      await this.checkLocation();
    } catch (e: any) {
      const isMismatch = e?.error?.errors?.face_distance !== undefined;
      this.enrollMismatch.set(isMismatch);
      this.enrollError.set(
        isMismatch
          ? 'No pudimos confirmar que eres tu.'
          : e?.error?.message || 'No pudimos verificar tu rostro. Intenta de nuevo.',
      );
      this.face.resume();
    } finally {
      this.enrollSubmitting.set(false);
    }
  }

  // ---------------------------------------------------------------
  // Home Office: fijar (o re-fijar, tras desbloqueo de un admin) la ubicacion personal
  // del empleado. Requiere verificacion facial (una captura) + GPS. Se activa desde un
  // boton aparte en el paso "confirm" (solo si ya tiene rostro enrolado).
  // ---------------------------------------------------------------
  async goToHomeLocation() {
    this.step.set('homeVerify');
    this.homeError.set('');
    this.homeLocked.set(false);
    this.homeResult.set(null);
    this.homeDescriptor = null;
    setTimeout(() => {
      if (this.videoEl && this.canvasEl) {
        this.face.start(this.videoEl.nativeElement, this.canvasEl.nativeElement);
      }
    }, 0);
  }

  async captureForHomeVerify() {
    const descriptor = await this.face.captureDescriptor();
    if (!descriptor) {
      this.homeError.set('No detectamos tu rostro. Centra tu cara en el circulo e intenta de nuevo.');
      return;
    }

    this.homeDescriptor = Array.from(descriptor);
    this.face.cleanup();
    this.step.set('homeCapture');
    await this.captureHomeGps();
  }

  async captureHomeGps() {
    const emp = this.employee();
    if (!emp || !this.homeDescriptor) {
      this.homeError.set('Perdimos tu verificacion. Vuelve a intentar desde el inicio.');
      this.step.set('confirm');
      return;
    }

    this.homeSubmitting.set(true);
    this.homeError.set('');
    this.homeLocked.set(false);

    let state = await this.location.checkPermission();
    if (state === 'prompt') {
      state = await this.location.requestPermission();
    }
    if (state !== 'granted') {
      this.homeSubmitting.set(false);
      this.homeError.set(
        'No podemos guardar tu ubicacion sin acceso a tu GPS. Activa el permiso de ubicacion en los ajustes de tu telefono.',
      );
      return;
    }

    const pos = await this.location.getCurrentPosition();
    if (!pos) {
      this.homeSubmitting.set(false);
      this.homeError.set('No pudimos leer tu GPS. Revisa que este activado e intenta de nuevo.');
      return;
    }

    try {
      const res = await this.api.homeLocation({
        employee_id: emp.id,
        descriptor: this.homeDescriptor,
        latitude: pos.coords.latitude,
        longitude: pos.coords.longitude,
      });
      this.homeResult.set(res);
      this.step.set('homeSuccess');
    } catch (e: any) {
      const reason = e?.error?.errors?.reason;
      this.homeLocked.set(reason === 'locked');
      this.homeError.set(
        e?.error?.message || 'No pudimos guardar tu ubicacion. Intenta de nuevo.',
      );
    } finally {
      this.homeSubmitting.set(false);
    }
  }

  retryHomeCapture() {
    this.homeError.set('');
    this.captureHomeGps();
  }

  backToConfirmFromHome() {
    this.face.cleanup();
    this.homeError.set('');
    this.homeLocked.set(false);
    this.homeDescriptor = null;
    this.step.set('confirm');
  }

  async checkLocation() {
    this.locationChecking.set(true);
    let state = await this.location.checkPermission();

    if (state === 'prompt') {
      state = await this.location.requestPermission();
    }
    this.locationState.set(state);

    if (state === 'granted') {
      const pos = await this.location.getCurrentPosition();
      if (pos) {
        this.coords.set({
          lat: pos.coords.latitude,
          lng: pos.coords.longitude,
          accuracy: pos.coords.accuracy ?? null,
        });
        this.locationChecking.set(false);
        await this.goToCamera();
        return;
      }
      // Permiso concedido pero no se pudo leer el GPS (apagado, sin señal, etc).
      this.locationState.set('unavailable');
    }

    this.locationChecking.set(false);
  }

  // ---------------------------------------------------------------
  // Paso 4: camara + deteccion facial en vivo
  // ---------------------------------------------------------------
  async goToCamera() {
    this.step.set('camera');
    this.submitError.set('');
    setTimeout(() => {
      if (this.videoEl && this.canvasEl) {
        this.face.start(this.videoEl.nativeElement, this.canvasEl.nativeElement);
      }
    }, 0);
  }

  retryCamera() {
    this.submitError.set('');
    this.face.resume();
  }

  async register() {
    // Defensa en profundidad: si por lo que sea no hay coords, no se manda nada.
    const c = this.coords();
    const emp = this.employee();
    if (!c || !emp) {
      this.submitError.set('Perdimos tu ubicacion. Vuelve a intentar desde el inicio.');
      return;
    }

    const descriptor = await this.face.captureDescriptor();
    if (!descriptor) {
      this.faceMismatch.set(false);
      this.submitError.set('No detectamos tu rostro. Centra tu cara en el circulo e intenta de nuevo.');
      return;
    }

    // OJO: aqui NUNCA cambiamos "step" mientras se manda la checada -- si saliera del
    // paso 'camera', Angular destruye el <video>/<canvas> (por el *ngIf) y al volver
    // se crean unos nuevos que ya no tienen la transmision de la camara enganchada
    // (se queda en negro y no se puede reintentar sin recargar toda la app). Por eso
    // usamos "submitting" como bandera aparte, sin tocar el step, hasta que si haya
    // exito (ahi ya no importa, se pasa a la pantalla de "success").
    this.submitting.set(true);
    this.submitError.set('');
    this.faceMismatch.set(false);

    try {
      const res = await this.api.checkin({
        employee_id: emp.id,
        descriptor: Array.from(descriptor),
        latitude: c.lat,
        longitude: c.lng,
        accuracy_meters: c.accuracy,
      });
      this.face.cleanup();
      this.result.set(res);
      this.step.set('success');
    } catch (e: any) {
      // El backend distingue "no hizo match" (manda face_distance) de otros errores
      // (red, servidor, validacion) -- para el primer caso damos un mensaje mas util
      // con tips concretos, en vez de solo repetir el texto generico del backend.
      const isFaceMismatch = e?.error?.errors?.face_distance !== undefined;
      this.faceMismatch.set(isFaceMismatch);
      this.submitError.set(
        isFaceMismatch
          ? 'No pudimos confirmar que eres tu.'
          : e?.error?.message || 'No pudimos registrar tu asistencia. Intenta de nuevo.',
      );
      this.face.resume();
    } finally {
      this.submitting.set(false);
    }
  }

  startOver() {
    this.face.cleanup();
    this.employee.set(null);
    this.query.set('');
    this.coords.set(null);
    this.result.set(null);
    this.submitError.set('');
    this.lookupError.set('');
    this.enrollError.set('');
    this.enrollMismatch.set(false);
    this.homeError.set('');
    this.homeLocked.set(false);
    this.homeResult.set(null);
    this.homeDescriptor = null;
    this.step.set('identify');
  }
}
