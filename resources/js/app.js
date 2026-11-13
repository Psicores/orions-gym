/*
 * Componente Alpine "escanerQr": abre la cámara del celular dentro de la página y lee el QR de
 * recepción. Lo usa la pantalla "Escanear QR" del portal del socio
 * (resources/views/livewire/member-scan.blade.php).
 *
 * Alpine viene incluido en Livewire; acá solo se registra el componente cuando Alpine arranca.
 * La librería html5-qrcode se descarga recién al tocar "Escanear", para no cargarla en el resto
 * del sitio.
 */
const registrarEscaner = () => {
    window.Alpine.data('escanerQr', () => ({
        // inactivo | iniciando | escaneando | procesando
        estado: 'inactivo',
        // Mensaje si no se pudo abrir la cámara.
        error: null,
        // "environment" = cámara trasera; "user" = cámara frontal.
        camara: 'environment',
        // Instancia de Html5Qrcode mientras la cámara está abierta.
        lector: null,
        // Contexto de audio para el sonido de confirmación.
        audio: null,

        // Abre la cámara (el navegador pide permiso la primera vez) y empieza a buscar un QR.
        async iniciar() {
            this.error = null;
            this.estado = 'iniciando';

            // El audio se habilita acá porque el navegador solo lo permite a partir de un toque del usuario.
            try {
                this.audio ??= new (window.AudioContext || window.webkitAudioContext)();
            } catch {
                this.audio = null;
            }

            // Espera a que el visor esté visible: la librería necesita medirlo.
            await this.$nextTick();

            try {
                const { Html5Qrcode } = await import('html5-qrcode');

                const lector = new Html5Qrcode('lector-qr');
                this.lector = lector;

                await lector.start(
                    { facingMode: this.camara },
                    { fps: 10, qrbox: { width: 230, height: 230 } },
                    (texto) => this.leido(texto),
                    () => {},
                );

                // Si se canceló mientras la cámara abría, la cierra.
                if (this.estado !== 'iniciando') {
                    this.lector = lector;
                    await this.cerrarCamara();

                    return;
                }

                this.estado = 'escaneando';
            } catch {
                this.lector = null;
                this.estado = 'inactivo';
                this.error = 'No pudimos abrir la cámara. Revisá que el navegador tenga permiso para usarla y probá de nuevo.';
            }
        },

        // Se leyó un código: cierra la cámara y le pide al servidor que registre el ingreso.
        async leido(texto) {
            // La librería puede leer el mismo código varias veces seguidas: solo cuenta la primera.
            if (this.estado !== 'escaneando') {
                return;
            }

            this.estado = 'procesando';
            await this.cerrarCamara();

            try {
                await this.$wire.procesarIngreso(texto);
            } finally {
                this.estado = 'inactivo';
            }
        },

        // Cancela el escaneo.
        async detener() {
            this.estado = 'inactivo';
            await this.cerrarCamara();
        },

        // Apaga la cámara y limpia el visor.
        async cerrarCamara() {
            const lector = this.lector;
            this.lector = null;

            if (!lector) {
                return;
            }

            try {
                await lector.stop();
                lector.clear();
            } catch {
                // La cámara ya estaba cerrada.
            }
        },

        // Alterna entre la cámara trasera y la frontal.
        async cambiarCamara() {
            this.camara = this.camara === 'environment' ? 'user' : 'environment';
            await this.cerrarCamara();
            await this.iniciar();
        },

        // Sonido y vibración: dos tonos que suben si el ingreso se registró, uno grave si se rechazó.
        sonar(exito) {
            navigator.vibrate?.(exito ? 120 : [80, 60, 80]);

            if (!this.audio) {
                return;
            }

            const tonos = exito ? [880, 1320] : [220];

            tonos.forEach((frecuencia, indice) => {
                const inicio = this.audio.currentTime + indice * 0.14;
                const oscilador = this.audio.createOscillator();
                const volumen = this.audio.createGain();

                oscilador.type = exito ? 'sine' : 'square';
                oscilador.frequency.value = frecuencia;
                volumen.gain.setValueAtTime(0.25, inicio);
                volumen.gain.exponentialRampToValueAtTime(0.001, inicio + (exito ? 0.18 : 0.4));
                oscilador.connect(volumen).connect(this.audio.destination);
                oscilador.start(inicio);
                oscilador.stop(inicio + (exito ? 0.2 : 0.42));
            });
        },

        // Alpine lo llama al salir de la pantalla: no deja la cámara encendida.
        destroy() {
            this.cerrarCamara();
        },
    }));
};

// Si Alpine ya está cargado se registra en el momento; si no, cuando arranque.
if (window.Alpine) {
    registrarEscaner();
} else {
    document.addEventListener('alpine:init', registrarEscaner);
}
