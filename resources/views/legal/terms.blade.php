@php
    $p = $provider;
    $holder = $p['name'].($p['cuit'] ? ', CUIT '.$p['cuit'] : '');
    $court = $p['jurisdiction'] ?: 'los tribunales ordinarios con competencia en el domicilio del Titular';
@endphp
<x-legal-page title="Términos y condiciones de uso" :provider="$provider" :version="$version">
    <p>
        Estos términos regulan el uso del sistema de gestión para galpones de empaque que ofrece <strong>{{ $holder }}</strong>
        (en adelante, «el Titular») en {{ $p['url'] }} y en las demás direcciones o instalaciones donde se brinde. Al crear
        una cuenta, ingresar o usar el sistema, el galpón o empresa que lo contrata (en adelante, «el Cliente») y cada persona
        que lo usa en su nombre («los Usuarios») aceptan estos términos y la <a class="link" href="{{ route('legal.privacy') }}">Política de privacidad</a>.
    </p>

    <h2>1. El servicio</h2>
    <p>
        El sistema es un software en línea para registrar la operación de un galpón de empaque: ingresos de fruta, lotes,
        cajones, pallets, producción, calidad, cargas, remitos, facturación, DTV-e, tesorería, reportes y demás módulos que
        el Titular habilite. Cada galpón trabaja con sus propios datos, usuarios y configuración, separados de los de otros
        clientes. El Titular puede mejorar, cambiar o dejar de ofrecer funciones, procurando avisar con anticipación razonable
        cuando el cambio afecte el uso habitual.
    </p>

    <h2>2. Cuentas, usuarios y seguridad</h2>
    <ul>
        <li>El Cliente designa un administrador, que crea los usuarios de su galpón y decide qué puede hacer cada uno.</li>
        <li>Cada usuario es personal. Las contraseñas no se comparten y deben mantenerse en reserva. Todo lo que se haga con una cuenta se considera hecho por su titular y queda registrado en el historial de cambios.</li>
        <li>El Cliente avisa de inmediato al Titular si sospecha que alguien entró sin permiso, y da de baja a las personas que dejan de trabajar con él.</li>
    </ul>

    <h2>3. Uso permitido</h2>
    <p>El Cliente y los Usuarios se comprometen a no:</p>
    <ul>
        <li>usar el sistema para actividades ilegales o para cargar información falsa con el fin de engañar a terceros u organismos;</li>
        <li>intentar acceder a datos de otros clientes, saltear permisos, probar vulnerabilidades sin autorización escrita del Titular, o sobrecargar el servicio;</li>
        <li>copiar, descompilar, revender, sublicenciar o poner el sistema a disposición de terceros ajenos al galpón sin acuerdo escrito.</li>
    </ul>
    <p>El Titular puede suspender una cuenta que incumpla estas reglas, avisando al Cliente salvo urgencia de seguridad.</p>

    <h2>4. Datos del Cliente</h2>
    <ul>
        <li>Los datos que el Cliente carga (productores, clientes, choferes, operación, facturas, etc.) son <strong>del Cliente</strong>. El Titular los guarda y procesa sólo para prestar el servicio, según la Política de privacidad.</li>
        <li>El Cliente es responsable de tener derecho a cargar los datos personales de terceros (empleados, productores, choferes) y de informarles lo necesario según la Ley 25.326.</li>
        <li>El Cliente puede exportar su información en cualquier momento desde el propio sistema (Excel/CSV).</li>
        <li>El Titular hace copias de seguridad periódicas, pero recomienda al Cliente exportar y guardar por su cuenta la información que considere crítica.</li>
    </ul>

    <h2>5. Documentos fiscales, sanitarios y de trazabilidad</h2>
    <p>
        El sistema ayuda a preparar y registrar facturas electrónicas de ARCA, remitos, etiquetas, DTV-e de SENASA y otros
        documentos. Es una herramienta: <strong>el Cliente es responsable de revisar los datos que carga y de los documentos que
        emite</strong>, de tener sus propios certificados y habilitaciones, y de cumplir con las normas fiscales, sanitarias y
        comerciales que le correspondan. El sistema no reemplaza el asesoramiento de un contador, abogado o profesional
        habilitado. Los movimientos que se hacen directamente en ARCA u otros organismos se rigen por las reglas de esos organismos.
    </p>

    <h2>6. Disponibilidad y soporte</h2>
    <p>
        El Titular pone su mejor esfuerzo para que el sistema funcione en forma continua y segura, pero no garantiza que esté
        libre de interrupciones o errores: puede haber cortes por mantenimiento, actualizaciones, fallas del proveedor de
        alojamiento, de internet o de servicios de terceros (por ejemplo, ARCA). Los mantenimientos programados se intentan
        hacer fuera del horario de trabajo. El soporte se brinda por los canales que indique el Titular
        @if ($p['email'])(correo <a class="link" href="mailto:{{ $p['email'] }}">{{ $p['email'] }}</a> y la sección «Soporte» del sistema)@else(la sección «Soporte» del sistema)@endif
        en días y horarios hábiles, salvo otro acuerdo.
    </p>

    <h2>7. Precio, pago y suspensión</h2>
    <ul>
        <li>El servicio se paga según el plan o la propuesta acordada con el Cliente (abono mensual, puesta en marcha u otros conceptos).</li>
        <li>Los precios pueden actualizarse avisando con al menos 30 días de anticipación. Si el Cliente no está de acuerdo, puede terminar el servicio antes de que rija el nuevo precio.</li>
        <li>Si el pago se atrasa, el Titular avisa al Cliente y, si pasados 15 días no se regulariza, puede suspender el acceso. Durante la suspensión los datos se conservan y el acceso vuelve al regularizar el pago.</li>
    </ul>

    <h2>8. Propiedad intelectual</h2>
    <p>
        El software, su diseño, código, marca y documentación son del Titular. Mientras el servicio esté vigente y pago, el
        Cliente recibe una licencia de uso no exclusiva e intransferible, limitada a su galpón o empresa. Las sugerencias que
        el Cliente haga pueden incorporarse al sistema sin que eso genere derechos a su favor.
    </p>

    <h2>9. Responsabilidad</h2>
    <p>
        En la medida que lo permita la ley, el Titular no responde por lucro cesante, pérdida de negocios, multas o daños
        indirectos derivados del uso o de la imposibilidad de usar el sistema, ni por errores en los datos cargados por el
        Cliente o los Usuarios. La responsabilidad total del Titular por cualquier reclamo queda limitada al importe que el
        Cliente haya pagado por el servicio en los 12 meses anteriores al hecho. Estas limitaciones no se aplican en caso de
        dolo o culpa grave del Titular.
    </p>

    <h2>10. Duración y fin del servicio</h2>
    <ul>
        <li>El servicio dura mientras el Cliente lo siga usando y pagando. Cualquiera de las partes puede terminarlo avisando con 30 días de anticipación.</li>
        <li>Al terminar, el Cliente tiene <strong>30 días</strong> para exportar su información. Pasado ese plazo, el Titular puede borrarla definitivamente, salvo lo que deba conservar por obligación legal.</li>
    </ul>

    <h2>11. Cambios en estos términos</h2>
    <p>
        El Titular puede actualizar estos términos. Los cambios importantes se avisan dentro del sistema y el administrador
        de cada galpón los acepta en su siguiente ingreso. La versión vigente siempre está publicada en esta página.
    </p>

    <h2>12. Ley aplicable y jurisdicción</h2>
    <p>
        Estos términos se rigen por las leyes de la República Argentina. Ante cualquier diferencia, las partes intentarán
        primero resolverla de buena fe; de no lograrlo, se someten a {{ $court }}, con renuncia a cualquier otro fuero,
        salvo que una norma de orden público disponga otra cosa.
    </p>

    <h2>13. Contacto</h2>
    <p>
        {{ $holder }}@if ($p['address']), con domicilio en {{ $p['address'] }}@endif.
        @if ($p['email'])Consultas: <a class="link" href="mailto:{{ $p['email'] }}">{{ $p['email'] }}</a>.@endif
    </p>
</x-legal-page>
