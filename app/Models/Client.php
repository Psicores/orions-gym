<?php

namespace App\Models;

use App\Enums\ClientStatusEnum;
use App\Enums\ConsentOriginEnum;
use App\Enums\MembershipStatusEnum;
use App\Enums\PaymentMethodEnum;
use Carbon\CarbonInterface;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use libphonenumber\PhoneNumberUtil;
use Propaganistas\LaravelPhone\PhoneNumber;

#[Fillable([
    'dni',
    'nombre',
    'apellido',
    'fecha_nacimiento',
    'cel',
    'email',
    'foto_path',
    'certificado_salud_path',
    'observaciones_dolencias',
    'acepta_avisos_whatsapp',
    'acepta_novedades',
    'estado',
    'fecha_vencimiento',
])]
// La contraseña y el token de "recordarme" nunca salen al convertir el cliente a array o JSON.
#[Hidden(['password', 'remember_token'])]
// Extiende Authenticatable (y no Model a secas) porque el socio inicia sesión en su portal con el guard "client".
class Client extends Authenticatable
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, SoftDeletes;

    // Minutos durante los que un segundo escaneo se toma como el mismo ingreso y no descuenta otro crédito.
    public const int ATTENDANCE_REPEAT_MINUTES = 10;

    // País de los celulares (código ISO): define cómo se interpretan la característica, el 0 y el 15.
    public const string CEL_COUNTRY = 'AR';

    /** Permisos de aviso => columna con la fecha en que se dieron. */
    public const array CONSENTS = [
        'acepta_avisos_whatsapp' => 'acepta_avisos_whatsapp_at',
        'acepta_novedades' => 'acepta_novedades_at',
    ];

    /**
     * Registra automáticamente cuándo y desde dónde cambian los permisos de aviso.
     *
     * Precondiciones:
     * - Ninguna: Eloquent lo llama una vez al cargar el modelo.
     *
     * Postcondiciones:
     * - Al guardar un cliente con un permiso que cambió: si quedó en true, su fecha pasa a ser
     *   ahora; si quedó en false, la fecha queda null.
     * - Si cambió algún permiso y no se indicó el origen, se anota que vino del panel (es el
     *   único lugar que los cambia sin indicarlo; el portal pasa el suyo, ver updateConsents()).
     * - Si ningún permiso cambió, no toca ni las fechas ni el origen.
     */
    protected static function booted(): void
    {
        // Antes de cada guardado (alta o modificación).
        static::saving(function (Client $client): void {
            // Si cambió alguno de los dos permisos.
            $changed = false;

            // Revisa cada permiso.
            foreach (self::CONSENTS as $consent => $grantedAt) {
                // Solo los que cambiaron (en un alta, los que se marcaron).
                if ($client->isDirty($consent) && ($client->exists || $client->{$consent})) {
                    // Fecha del permiso, o null si se retiró.
                    $client->{$grantedAt} = $client->{$consent} ? now() : null;
                    // Hay que anotar el origen.
                    $changed = true;
                }
            }

            // Sin origen explícito, el cambio vino del panel.
            if ($changed && ! $client->isDirty('consentimiento_origen')) {
                // Lo cargó el personal.
                $client->consentimiento_origen = ConsentOriginEnum::AltaPanel;
            }
        });
    }

    /**
     * Define cómo se convierten las columnas al leerlas y escribirlas desde el modelo.
     *
     * Precondiciones:
     * - Las columnas fecha_nacimiento y fecha_vencimiento guardan fechas (Y-m-d) o null.
     * - La columna estado guarda uno de los valores de ClientStatusEnum
     *   (entrenando, en_pausa, activo_temporal, inactivo).
     *
     * Postcondiciones:
     * - $client->fecha_nacimiento y $client->fecha_vencimiento devuelven un objeto Carbon
     *   (o null si la columna está vacía).
     * - $client->estado devuelve un caso de ClientStatusEnum, y al asignarle un caso del enum
     *   se guarda su valor de texto.
     * - Si la base tiene un estado que no existe en el enum, leerlo lanza un ValueError.
     * - Al asignar $client->password un texto plano, se guarda su hash; null se guarda como null.
     * - $client->observaciones_dolencias se lee y se asigna como texto plano, pero en la base
     *   queda cifrado (null sigue siendo null). Por eso no se puede buscar por ese campo con SQL.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // Devuelve el mapa "columna => tipo" que Eloquent aplica automáticamente.
        return [
            // La fecha de nacimiento se maneja como fecha (Carbon) en lugar de texto.
            'fecha_nacimiento' => 'date',
            // Permisos de aviso como verdadero o falso, con la fecha en que se dieron.
            'acepta_avisos_whatsapp' => 'boolean',
            'acepta_avisos_whatsapp_at' => 'datetime',
            'acepta_novedades' => 'boolean',
            'acepta_novedades_at' => 'datetime',
            // Desde dónde se registró el último cambio de esos permisos.
            'consentimiento_origen' => ConsentOriginEnum::class,
            // El estado se maneja como el enum ClientStatusEnum en lugar de texto suelto.
            'estado' => ClientStatusEnum::class,
            // La fecha de vencimiento de la cuota se maneja como fecha (Carbon).
            'fecha_vencimiento' => 'date',
            // La contraseña del portal se guarda siempre hasheada (null = todavía usa su DNI).
            'password' => 'hashed',
            // Las observaciones de salud se guardan cifradas con la clave de la aplicación (APP_KEY):
            // en la base y en sus backups no se leen; el modelo las cifra y descifra solo.
            'observaciones_dolencias' => 'encrypted',
        ];
    }

    /**
     * Deja solo los dígitos de un DNI, para comparar sin importar puntos ni espacios.
     *
     * Precondiciones:
     * - Ninguna: acepta cualquier texto.
     *
     * Postcondiciones:
     * - Devuelve el texto sin nada que no sea un dígito ("30.123.456" => "30123456").
     * - No modifica nada.
     *
     * @param  string  $dni  DNI tal como lo escribió alguien.
     * @return string Solo los dígitos (puede quedar vacío).
     */
    public static function normalizeDni(string $dni): string
    {
        // Borra todo lo que no sea un dígito.
        return preg_replace('/\D+/', '', $dni);
    }

    /**
     * Lleva un celular argentino, escrito como sea, al formato internacional único que usa
     * WhatsApp (E.164): "280 15 412-3456", "02804123456" y "2804123456" dan "+5492804123456".
     *
     * Precondiciones:
     * - Ninguna: acepta cualquier texto o null.
     *
     * Postcondiciones:
     * - Si es un celular argentino válido, devuelve "+549" seguido de la característica y el
     *   número, sin el 0 ni el 15.
     * - Un número de 10 dígitos sin 15 (por ejemplo "1123456789") se toma como celular: el campo
     *   es "Celular" y así lo escribe mucha gente.
     * - Si está vacío, no es un número, le faltan o sobran dígitos, no tiene característica o es
     *   de otro país, devuelve null.
     * - No modifica nada.
     *
     * @param  string|null  $cel  Celular tal como lo escribió alguien.
     * @return string|null El celular en formato E.164, o null si no es válido.
     */
    public static function normalizeCel(?string $cel): ?string
    {
        // Sin nada escrito no hay celular.
        if (blank($cel)) {
            return null;
        }

        // Lo interpreta como un número de Argentina (saca el 0 y el 15 de la característica).
        $number = new PhoneNumber($cel, self::CEL_COUNTRY);

        // Tiene que ser un número posible y de Argentina.
        if (! $number->isValid() || ! $number->isOfCountry(self::CEL_COUNTRY)) {
            return null;
        }

        // Número nacional, sin el +54 (por ejemplo "2804123456" o "92804123456").
        $national = PhoneNumberUtil::getInstance()->getNationalSignificantNumber($number->toLibPhoneObject());

        // Sin el 9 de celular, la librería lo toma como fijo: se lo agrega.
        if (! str_starts_with($national, '9')) {
            // Mismo número, marcado como celular.
            $number = new PhoneNumber('+549'.$national);
        }

        // Si con el 9 sigue siendo un celular válido, lo devuelve en formato internacional.
        return $number->isValid() && $number->isOfType('mobile') ? $number->formatE164() : null;
    }

    /**
     * Indica si el celular guardado ya está en el formato único (ver normalizeCel()).
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve true si cel es un celular argentino en formato E.164.
     * - Devuelve false si está vacío o si quedó un valor viejo que no se pudo normalizar.
     * - No modifica nada.
     *
     * @return bool true si se le puede escribir por WhatsApp.
     */
    public function hasValidCel(): bool
    {
        // Es válido si normalizarlo no lo cambia.
        return filled($this->cel) && self::normalizeCel($this->cel) === $this->cel;
    }

    /**
     * Celular para mostrar en pantalla, legible: "+5492804123456" da "+54 9 280 412-3456".
     *
     * Precondiciones:
     * - Ninguna: acepta cualquier texto o null.
     *
     * Postcondiciones:
     * - Si es un celular argentino válido, lo devuelve con espacios y guion.
     * - Si no se puede interpretar (un valor viejo mal cargado), lo devuelve tal cual.
     * - Si está vacío, devuelve null. No modifica nada.
     *
     * @param  string|null  $cel  Celular guardado o escrito.
     * @return string|null Celular legible, o null.
     */
    public static function formatCel(?string $cel): ?string
    {
        // Sin celular no hay nada que mostrar.
        if (blank($cel)) {
            return null;
        }

        // Formato único, si es un celular válido.
        $normalized = self::normalizeCel($cel);

        // Solo un celular válido se puede formatear; el resto se muestra como está.
        return $normalized !== null ? (new PhoneNumber($normalized))->formatInternational() : $cel;
    }

    /**
     * Normaliza el celular cada vez que se asigna, venga de donde venga (panel, portal,
     * datos de ejemplo).
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Un celular argentino válido se guarda en formato E.164 (ver normalizeCel()).
     * - Un texto vacío se guarda como null.
     * - Un valor que no se puede normalizar se guarda tal cual, sin espacios en las puntas, para
     *   no perder el dato (los formularios ya lo rechazan con la regla ArgentineMobile).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function cel(): Attribute
    {
        // Solo cambia la escritura; la lectura devuelve lo guardado.
        return Attribute::make(
            // Normalizado, o el texto original si no se pudo.
            set: fn (?string $value): ?string => blank($value) ? null : (self::normalizeCel($value) ?? trim($value)),
        );
    }

    /**
     * Busca al cliente que quiere entrar al portal, por DNI o por email.
     *
     * Precondiciones:
     * - $usuario es lo que la persona escribió en el campo "DNI o email" del login.
     *
     * Postcondiciones:
     * - Si $usuario tiene una arroba, devuelve el cliente con ese email (sin distinguir mayúsculas).
     * - Si no, devuelve el cliente con ese DNI, escrito con o sin puntos.
     * - Devuelve null si no hay ninguno o si el cliente está dado de baja.
     * - No modifica nada.
     *
     * @param  string  $usuario  DNI o email.
     * @return self|null El cliente encontrado, o null.
     */
    public static function findForLogin(string $usuario): ?self
    {
        // Saca espacios de las puntas.
        $usuario = trim($usuario);

        // Con arroba es un email.
        if (str_contains($usuario, '@')) {
            // Busca por email en minúsculas (los dados de baja quedan afuera por el borrado lógico).
            return static::query()->whereRaw('lower(email) = ?', [mb_strtolower($usuario)])->first();
        }

        // Sin arroba es un DNI: lo busca tal cual y también solo con sus dígitos.
        return static::query()->whereIn('dni', array_filter([$usuario, static::normalizeDni($usuario)]))->first();
    }

    /**
     * Cambia los dos permisos de aviso del cliente y anota desde dónde se cambiaron.
     *
     * Precondiciones:
     * - El cliente existe en la base.
     *
     * Postcondiciones:
     * - acepta_avisos_whatsapp y acepta_novedades quedan con los valores recibidos.
     * - El permiso que cambió queda con la fecha de ahora (si se dio) o sin fecha (si se retiró);
     *   el que no cambió conserva la suya (ver booted()).
     * - Si alguno cambió, consentimiento_origen queda en $origin. Si ninguno cambió, no se
     *   modifica nada.
     *
     * @param  bool  $whatsappNotices  Acepta avisos de su cuenta por WhatsApp.
     * @param  bool  $news  Acepta promociones, eventos y novedades.
     * @param  ConsentOriginEnum  $origin  Desde dónde se hace el cambio.
     */
    public function updateConsents(bool $whatsappNotices, bool $news, ConsentOriginEnum $origin): void
    {
        // Asigna los dos permisos.
        $this->acepta_avisos_whatsapp = $whatsappNotices;
        $this->acepta_novedades = $news;

        // Sin cambios no hay nada que guardar ni que registrar.
        if (! $this->isDirty(array_keys(self::CONSENTS))) {
            return;
        }

        // Anota el origen; las fechas las completa booted() al guardar.
        $this->consentimiento_origen = $origin;
        $this->save();
    }

    /**
     * Resume un permiso de aviso para mostrarlo en la ficha del cliente.
     *
     * Precondiciones:
     * - $consent es una clave de CONSENTS.
     *
     * Postcondiciones:
     * - Si el permiso está dado: "Sí, desde el dd/mm/aaaa", más el origen del último cambio si
     *   está anotado (por ejemplo "· registrado en el portal").
     * - Si no: "No". No modifica nada.
     *
     * @param  string  $consent  Permiso a resumir.
     * @return string Texto para la ficha.
     */
    public function consentSummary(string $consent): string
    {
        // Sin permiso, un "No" alcanza.
        if (! $this->{$consent}) {
            return 'No';
        }

        // Fecha en que se dio (puede faltar en datos viejos).
        $since = $this->{self::CONSENTS[$consent]}?->format('d/m/Y');
        // Desde dónde se registró el último cambio.
        $origin = $this->consentimiento_origen?->getLabel();

        // "Sí, desde el 22/11/2026 · registrado en el portal".
        return 'Sí'.($since ? ', desde el '.$since : '').($origin ? ' · registrado en '.$origin : '');
    }

    /**
     * Indica si el cliente ya eligió una contraseña propia para el portal.
     *
     * Precondiciones:
     * - Ninguna.
     *
     * Postcondiciones:
     * - Devuelve false mientras la columna password esté vacía: en ese caso su contraseña es su DNI.
     * - Devuelve true desde que la cambió.
     * - No modifica nada.
     *
     * @return bool true si tiene contraseña propia.
     */
    public function hasCustomPassword(): bool
    {
        // Hay contraseña propia cuando la columna tiene un hash guardado.
        return filled($this->password);
    }

    /**
     * Verifica la contraseña que escribió el cliente para entrar al portal.
     *
     * Precondiciones:
     * - $password es el texto plano que escribió.
     *
     * Postcondiciones:
     * - Si el cliente todavía no eligió contraseña, devuelve true solo si $password es su DNI
     *   (escrito con o sin puntos).
     * - Si ya la eligió, devuelve true solo si $password coincide con la guardada.
     * - No modifica nada.
     *
     * @param  string  $password  Contraseña en texto plano.
     * @return bool true si es correcta.
     */
    public function passwordMatches(string $password): bool
    {
        // Con contraseña propia, compara contra el hash guardado.
        if ($this->hasCustomPassword()) {
            // Hash::check hace la comparación segura.
            return Hash::check($password, $this->password);
        }

        // Dígitos del DNI del cliente.
        $dni = static::normalizeDni($this->dni);

        // Sin contraseña propia, la contraseña es el DNI; un DNI vacío nunca habilita el ingreso.
        return $dni !== '' && hash_equals($dni, static::normalizeDni($password));
    }

    /**
     * Huella de la contraseña vigente, para reconocer las sesiones abiertas con una anterior.
     *
     * Precondiciones:
     * - La aplicación tiene su clave (APP_KEY) configurada.
     *
     * Postcondiciones:
     * - Devuelve un texto que cambia cada vez que cambia la contraseña del cliente (también al
     *   elegir la primera o al restablecerla) y que no permite deducirla.
     * - No modifica nada.
     *
     * @return string Huella de 64 caracteres hexadecimales.
     */
    public function passwordStamp(): string
    {
        // Firma el hash guardado (vacío si todavía usa el DNI) con la clave de la aplicación.
        return hash_hmac('sha256', (string) $this->password, (string) config('app.key'));
    }

    /**
     * Restablece la contraseña del portal: el cliente vuelve a entrar con su DNI y tiene que
     * elegir una contraseña nueva. Es lo que hace el personal cuando un socio la olvidó.
     *
     * Precondiciones:
     * - El cliente existe en la base.
     *
     * Postcondiciones:
     * - La columna password queda vacía: hasCustomPassword() devuelve false, la contraseña
     *   vuelve a ser el DNI y el portal lo obliga a elegir una nueva en el próximo ingreso.
     * - La contraseña anterior deja de servir.
     * - El token de "recordarme" cambia y la huella de la contraseña (passwordStamp()) también:
     *   los dispositivos recordados y las sesiones que estaban abiertas tienen que volver a ingresar.
     * - No cambia ningún otro dato del cliente.
     */
    public function resetPassword(): void
    {
        // Carga los dos cambios saltando la lista de campos asignables (password no está en ella).
        $this->forceFill([
            // Sin contraseña propia: vuelve a valer el DNI.
            'password' => null,
            // Token nuevo: invalida las cookies de "recordarme" ya emitidas.
            'remember_token' => Str::random(60),
        ]);

        // Guarda los cambios.
        $this->save();
    }

    /**
     * Membresías (contratos con un plan) que tuvo y tiene el cliente.
     *
     * Precondiciones:
     * - Existe la tabla memberships con la columna client_id.
     *
     * Postcondiciones:
     * - Devuelve la relación "un cliente tiene muchas membresías", sin ejecutar la consulta.
     * - $client->memberships devuelve una colección (vacía si no tiene ninguna).
     * - No modifica datos.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        // Busca en memberships las filas cuyo client_id es el id de este cliente.
        return $this->hasMany(Membership::class);
    }

    /**
     * Pagos que hizo el cliente, de cualquiera de sus membresías.
     *
     * Precondiciones:
     * - Existe la tabla payments con la columna client_id.
     *
     * Postcondiciones:
     * - Devuelve la relación "un cliente tiene muchos pagos", sin ejecutar la consulta.
     * - $client->payments devuelve una colección (vacía si nunca pagó).
     * - No modifica datos.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        // Busca en payments las filas cuyo client_id es el id de este cliente.
        return $this->hasMany(Payment::class);
    }

    /**
     * Anuncios que el cliente ya cerró (leyó) en su portal.
     *
     * Precondiciones:
     * - Existe la tabla announcement_client con announcement_id, client_id y read_at.
     *
     * Postcondiciones:
     * - Devuelve la relación "un cliente cerró muchos anuncios", sin ejecutar la consulta.
     * - Cada anuncio trae en ->pivot->read_at cuándo lo cerró.
     * - No modifica datos.
     *
     * @return BelongsToMany<Announcement, $this>
     */
    public function announcements(): BelongsToMany
    {
        // Une clientes y anuncios a través de announcement_client, con la fecha de lectura.
        return $this->belongsToMany(Announcement::class)->withPivot('read_at');
    }

    /**
     * Marca un anuncio como cerrado (leído) por el cliente: deja de aparecerle en su portal.
     *
     * Precondiciones:
     * - El cliente y $announcement existen en la base.
     *
     * Postcondiciones:
     * - Queda una sola marca del cliente para ese anuncio en announcement_client, con read_at =
     *   el momento en que lo cerró por primera vez. Cerrarlo de nuevo no cambia nada.
     * - No modifica el anuncio: los demás socios lo siguen viendo.
     *
     * @param  Announcement  $announcement  Anuncio que cierra.
     */
    public function dismissAnnouncement(Announcement $announcement): void
    {
        // Agrega la marca solo si todavía no existe (no pisa la fecha de la primera vez).
        $this->announcements()->syncWithoutDetaching([
            // Anuncio => datos de la marca.
            $announcement->getKey() => ['read_at' => now()],
        ]);
    }

    /**
     * Asistencias (ingresos al gimnasio) del cliente.
     *
     * Precondiciones:
     * - Existe la tabla attendances con la columna client_id.
     *
     * Postcondiciones:
     * - Devuelve la relación "un cliente tiene muchas asistencias", sin ejecutar la consulta.
     * - $client->attendances devuelve una colección (vacía si nunca ingresó).
     * - No modifica datos.
     *
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        // Busca en attendances las filas cuyo client_id es el id de este cliente.
        return $this->hasMany(Attendance::class);
    }

    /**
     * Registra un ingreso del cliente al gimnasio y le descuenta un crédito.
     *
     * Lo usa el portal del socio cuando escanea el QR de recepción (ver App\Livewire\MemberScan).
     *
     * Precondiciones:
     * - El cliente existe en la base.
     * - Tiene una membresía vigente (ver currentMembership()) en un estado que permite entrar:
     *   Entrenando o Activo flex.
     *
     * Postcondiciones:
     * - Si en los últimos ATTENDANCE_REPEAT_MINUTES minutos ya registró un ingreso, devuelve ese
     *   mismo y no descuenta nada: escanear dos veces seguidas no cuesta dos créditos.
     * - Si no, crea y devuelve una Attendance de ahora, con la membresía vigente, y le resta 1 a
     *   sus creditos_disponibles. El resultado puede ser negativo: entra igual y queda debiendo.
     * - La asistencia y el descuento se guardan juntos: si uno falla, no queda ninguno.
     * - Si alguna precondición no se cumple, lanza la excepción y no guarda nada.
     * - Para saber si el ingreso es nuevo o repetido: $attendance->wasRecentlyCreated.
     *
     * @return Attendance La asistencia registrada (o la reciente, si era un ingreso repetido).
     *
     * @throws InvalidArgumentException Si no tiene membresía vigente o su estado no permite entrar.
     */
    public function registerAttendance(): Attendance
    {
        // Todo en una transacción: o quedan la asistencia y el descuento, o nada.
        return DB::transaction(function (): Attendance {
            // Membresía vigente, bloqueada hasta terminar para que dos escaneos a la vez no se pisen.
            $membership = Membership::query()
                ->whereKey($this->currentMembership()->value('memberships.id'))
                ->lockForUpdate()
                ->first();

            // Sin membresía abierta no hay con qué entrar.
            if ($membership === null) {
                // Corta sin guardar nada.
                throw new InvalidArgumentException('No tenés una membresía vigente. Pasá por recepción.');
            }

            // Una membresía en pausa (cuota impaga) no deja entrar.
            if (! $membership->estado->allowsGymAccess()) {
                // Corta sin guardar nada.
                throw new InvalidArgumentException('Cuota vencida. Pasá por recepción.');
            }

            // Ingreso registrado hace instantes, si lo hay.
            $recent = $this->attendances()
                ->where('fecha_hora', '>=', now()->subMinutes(self::ATTENDANCE_REPEAT_MINUTES))
                ->latest('fecha_hora')
                ->first();

            // Es el mismo ingreso escaneado de nuevo: no se cobra otra vez.
            if ($recent !== null) {
                // Devuelve el que ya estaba.
                return $recent;
            }

            // Descuenta un crédito directo en la base (puede quedar en negativo).
            $membership->decrement('creditos_disponibles');

            // Guarda la asistencia con la membresía que pagó el crédito.
            return $this->attendances()->create([
                // Membresía usada.
                'membership_id' => $membership->id,
                // Momento del ingreso.
                'fecha_hora' => now(),
            ]);
        });
    }

    /**
     * Membresía vigente del cliente: la abierta (no vencida) que empezó más recientemente.
     *
     * Solo sirve para mostrar (ficha del cliente, acciones rápidas); no cambia cómo se cobra
     * ni cómo se calculan los estados.
     *
     * Precondiciones:
     * - Existe la tabla memberships con la columna client_id.
     *
     * Postcondiciones:
     * - Devuelve la relación "un cliente tiene una membresía vigente", sin ejecutar la consulta.
     * - $client->currentMembership es la membresía no vencida con fecha_inicio más reciente
     *   (si empatan, la creada último), o null si no tiene ninguna abierta.
     * - No modifica datos.
     *
     * @return HasOne<Membership, $this>
     */
    public function currentMembership(): HasOne
    {
        // Entre las membresías del cliente, se queda con una sola.
        return $this->hasOne(Membership::class)->ofMany(
            // Criterio: la fecha de inicio más alta y, si empatan, el id más alto.
            ['fecha_inicio' => 'max', 'id' => 'max'],
            // Solo compiten las abiertas (todas menos las vencidas).
            fn (Builder $query) => $query->where('estado', '!=', MembershipStatusEnum::Vencida),
        );
    }

    /**
     * Último pago que hizo el cliente.
     *
     * Precondiciones:
     * - Existe la tabla payments con la columna client_id.
     *
     * Postcondiciones:
     * - Devuelve la relación "un cliente tiene un último pago", sin ejecutar la consulta.
     * - $client->latestPayment es el pago con fecha_pago más reciente, o null si nunca pagó.
     * - No modifica datos.
     *
     * @return HasOne<Payment, $this>
     */
    public function latestPayment(): HasOne
    {
        // Entre los pagos del cliente, se queda con el de fecha de pago más reciente.
        return $this->hasOne(Payment::class)->latestOfMany('fecha_pago');
    }

    /**
     * Registra un pago del cliente imputado a una de sus membresías.
     *
     * Es el único punto de entrada para guardar pagos: hoy lo usa la acción "Registrar pago"
     * del panel (cobro manual), y el día que se sume una pasarela (por ejemplo Mercado Pago)
     * su webhook debería llamar a este mismo método con un nuevo caso de PaymentMethodEnum,
     * así las validaciones quedan en un solo lugar.
     *
     * Precondiciones:
     * - El cliente existe en la base.
     * - $membership pertenece a este cliente y su estado admite pagos (no está vencida).
     * - $monto es un número mayor a 0 con hasta 2 decimales (por ejemplo "25000.50").
     * - $registradoPor es el usuario del staff que cobró (en el panel, el usuario logueado).
     *
     * Postcondiciones:
     * - Se creó y devolvió un Payment con este cliente, la membresía, el monto, el medio,
     *   fecha_pago = ahora y registrado_por_user_id = $registradoPor->id.
     * - El cliente queda en estado Entrenando (cuota al día), tanto en la base como en esta
     *   instancia del modelo.
     * - Si la membresía había terminado su ciclo (En pausa, Activo flex o con la fecha pasada;
     *   ver Membership::hasCompletedCycle()), el pago inicia el mes nuevo desde hoy: queda
     *   Entrenando, con un mes de vigencia y los créditos del plan (ver Membership::renewCycle()).
     *   $membership queda actualizada.
     * - Si la membresía sigue en su ciclo (Entrenando y sin vencer), el pago no le cambia el
     *   estado, las fechas ni los créditos.
     * - El pago, la renovación y el cambio de estado se guardan juntos: si uno falla, no queda ninguno.
     * - Si alguna precondición sobre la membresía o el monto no se cumple, lanza la excepción
     *   y no guarda nada.
     *
     * @param  Membership  $membership  Membresía a la que se imputa el pago.
     * @param  string  $monto  Monto cobrado, como texto decimal para no perder centavos.
     * @param  PaymentMethodEnum  $metodoPago  Medio con el que pagó.
     * @param  User  $registradoPor  Usuario que cobró.
     * @return Payment El pago recién guardado.
     *
     * @throws InvalidArgumentException Si la membresía es de otro cliente, está vencida o el monto no es positivo.
     */
    public function registerPayment(Membership $membership, string $monto, PaymentMethodEnum $metodoPago, User $registradoPor): Payment
    {
        // Evita imputar el pago a la membresía de otra persona.
        if ($membership->client_id !== $this->id) {
            // Corta sin guardar nada.
            throw new InvalidArgumentException('La membresía no pertenece a este cliente.');
        }

        // Evita cobrar sobre una membresía cerrada (vencida).
        if (! $membership->estado->acceptsPayments()) {
            // Corta sin guardar nada.
            throw new InvalidArgumentException('La membresía está vencida y no admite pagos.');
        }

        // Evita montos vacíos, cero, negativos o que no sean números.
        if (! is_numeric($monto) || (float) $monto <= 0) {
            // Corta sin guardar nada.
            throw new InvalidArgumentException('El monto debe ser mayor a cero.');
        }

        // Guarda el pago y el nuevo estado en una transacción: o se guardan los dos o ninguno.
        return DB::transaction(function () use ($membership, $monto, $metodoPago, $registradoPor): Payment {
            // Crea el pago ya vinculado a este cliente (client_id lo completa la relación).
            $payment = $this->payments()->create([
                // Membresía a la que se imputa.
                'membership_id' => $membership->id,
                // Monto cobrado; el cast decimal:2 lo guarda con 2 decimales.
                'monto' => $monto,
                // Medio de pago; el cast del enum guarda su valor de texto.
                'metodo_pago' => $metodoPago,
                // Momento del cobro: ahora.
                'fecha_pago' => now(),
                // Quién cobró, para la rendición de caja.
                'registrado_por_user_id' => $registradoPor->id,
            ]);

            // Si la membresía ya había terminado su mes, este pago arranca el mes nuevo desde hoy.
            if ($membership->hasCompletedCycle()) {
                // Entrenando, un mes de vigencia y créditos del plan.
                $membership->renewCycle(today());
            }

            // Si pagó, la cuota queda al día: pasa el cliente a Entrenando y lo guarda.
            $this->update(['estado' => ClientStatusEnum::Entrenando]);
            // Relee el cliente: la renovación pudo cambiarle la fecha de vencimiento.
            $this->refresh();

            // Devuelve el pago creado.
            return $payment;
        });
    }

    /**
     * Le crea al cliente una membresía que arranca habilitada y lo deja en Entrenando.
     *
     * Lo usa el paso "Crear membresía" que aparece apenas se da de alta un cliente en el panel,
     * que a continuación registra el pago con registerPayment().
     *
     * Precondiciones:
     * - El cliente existe en la base.
     * - $plan existe y no está dado de baja.
     * - $fechaVencimiento es igual o posterior a $fechaInicio.
     *
     * Postcondiciones:
     * - Se creó y devolvió una Membership de este cliente con ese plan y esas fechas, en estado
     *   Entrenando y con los créditos que otorga el plan.
     * - El cliente queda en estado Entrenando y con fecha_vencimiento recalculada, tanto en la
     *   base como en esta instancia del modelo.
     * - No registra ningún pago: eso lo hace quien llama.
     * - La membresía y el cambio de estado se guardan juntos: si uno falla, no queda ninguno.
     * - Si alguna precondición sobre el plan o las fechas no se cumple, lanza la excepción y no
     *   guarda nada.
     *
     * @param  Plan  $plan  Plan que contrata.
     * @param  CarbonInterface  $fechaInicio  Primer día de la membresía.
     * @param  CarbonInterface  $fechaVencimiento  Último día de la membresía.
     * @return Membership La membresía recién guardada.
     *
     * @throws InvalidArgumentException Si el plan está dado de baja o el vencimiento es anterior al inicio.
     */
    public function startMembership(Plan $plan, CarbonInterface $fechaInicio, CarbonInterface $fechaVencimiento): Membership
    {
        // Evita vender un plan que ya no se ofrece.
        if ($plan->trashed()) {
            // Corta sin guardar nada.
            throw new InvalidArgumentException('El plan está dado de baja.');
        }

        // Evita una membresía que vence antes de empezar.
        if ($fechaVencimiento->isBefore($fechaInicio)) {
            // Corta sin guardar nada.
            throw new InvalidArgumentException('El vencimiento no puede ser anterior al inicio.');
        }

        // Guarda la membresía y el nuevo estado en una transacción: o se guardan los dos o ninguno.
        return DB::transaction(function () use ($plan, $fechaInicio, $fechaVencimiento): Membership {
            // Crea la membresía ya vinculada a este cliente (client_id lo completa la relación).
            $membership = $this->memberships()->create([
                // Plan contratado; los créditos los copia el modelo desde el plan.
                'plan_id' => $plan->id,
                // Primer día.
                'fecha_inicio' => $fechaInicio->toDateString(),
                // Último día.
                'fecha_vencimiento' => $fechaVencimiento->toDateString(),
                // Arranca habilitada para entrar.
                'estado' => MembershipStatusEnum::Entrenando,
            ]);

            // Con membresía cargada, el cliente pasa a Entrenando.
            $this->update(['estado' => ClientStatusEnum::Entrenando]);
            // Relee el cliente: al guardarse la membresía se recalculó su fecha de vencimiento.
            $this->refresh();

            // Devuelve la membresía creada.
            return $membership;
        });
    }

    /**
     * Recalcula la fecha de vencimiento del cliente a partir de sus membresías.
     *
     * Precondiciones:
     * - El cliente existe en la base.
     *
     * Postcondiciones:
     * - fecha_vencimiento = la fecha de vencimiento más lejana entre sus membresías abiertas
     *   (todas menos las Vencidas).
     * - Si no tiene membresías abiertas, toma la más lejana entre todas (la última que tuvo).
     * - Si no tiene ninguna membresía, queda vacía (null).
     * - El valor queda guardado en la base y en esta instancia. Si no cambió, no escribe nada.
     * - No cambia el estado del cliente.
     */
    public function syncFechaVencimiento(): void
    {
        // Vencimiento más lejano entre las membresías abiertas (no vencidas).
        $fechaVencimiento = $this->memberships()
            // Excluye las membresías cerradas.
            ->where('estado', '!=', MembershipStatusEnum::Vencida)
            // Toma la fecha máxima (null si no hay ninguna).
            ->max('fecha_vencimiento')
            // Si no hay abiertas, usa la fecha máxima entre todas (null si no tiene membresías).
            ?? $this->memberships()->max('fecha_vencimiento');

        // Guarda la fecha (update no escribe si el valor es el mismo).
        $this->update(['fecha_vencimiento' => $fechaVencimiento]);
    }
}
