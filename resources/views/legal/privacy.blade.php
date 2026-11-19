@php
    $p = $provider;
    $holder = $p['name'].($p['cuit'] ? ', CUIT '.$p['cuit'] : '');
@endphp
<x-legal-page title="Política de privacidad" :provider="$provider" :version="$version">
    <p>
        Esta política explica qué datos personales se tratan en el sistema, para qué y cómo se cuidan, de acuerdo con la
        Ley 25.326 de Protección de los Datos Personales y sus normas complementarias.
    </p>

    <h2>1. Quién es quién</h2>
    <ul>
        <li><strong>Cada galpón (el Cliente)</strong> es el responsable de los datos que carga sobre su gente y su negocio: empleados, embaladores, productores, clientes, choferes, transportistas y operación.</li>
        <li><strong>{{ $holder }} (el Titular)</strong> presta el servicio y trata esos datos sólo por cuenta y según las instrucciones del Cliente, para hacer funcionar el sistema. Además es responsable de los datos de las cuentas de usuario que usa para dar acceso y soporte.</li>
    </ul>

    <h2>2. Qué datos se tratan</h2>
    <ul>
        <li><strong>De los usuarios:</strong> nombre, usuario, DNI, CUIT, correo, teléfono, rol y permisos, contraseña (guardada cifrada, nunca legible), fecha y dirección IP de los ingresos, y el historial de lo que cada uno hace en el sistema.</li>
        <li><strong>Que carga el galpón:</strong> datos de productores, clientes, choferes, transportistas, camiones y empleados (nombre, documento, CUIT, domicilio, contacto), y la información de la operación (lotes, cajones, cargas, remitos, facturas, cobros).</li>
        <li><strong>Técnicos:</strong> registros de errores del sistema, sin contraseñas ni claves.</li>
    </ul>

    <h2>3. Para qué se usan</h2>
    <ul>
        <li>Prestar el servicio: registrar la operación, emitir documentos, generar reportes y mantener la trazabilidad.</li>
        <li>Seguridad: controlar el acceso, detectar usos indebidos y dejar constancia de quién hizo cada cambio.</li>
        <li>Soporte y avisos del servicio (por ejemplo, recuperar una contraseña o responder un ticket).</li>
    </ul>
    <p>Los datos <strong>no se venden ni se usan para publicidad</strong>, y no se cruzan con los de otros galpones.</p>

    <h2>4. Con quién se comparten</h2>
    <ul>
        <li>Con el proveedor de alojamiento del sistema y de envío de correos, que los guardan o transmiten por cuenta del Titular.</li>
        <li>Con organismos como ARCA o SENASA, sólo cuando el propio galpón emite o consulta documentos a través del sistema.</li>
        <li>Con autoridades, cuando una ley u orden judicial lo exija.</li>
    </ul>

    <h2>5. Cómo se cuidan</h2>
    <p>
        Conexión cifrada (HTTPS), contraseñas cifradas, permisos por usuario, separación de los datos de cada galpón, bloqueo
        ante intentos repetidos de ingreso, historial de cambios y copias de seguridad periódicas. Ningún sistema es
        infalible: si ocurriera un incidente que afecte datos personales, el Titular avisará a los clientes afectados.
    </p>

    <h2>6. Cookies</h2>
    <p>
        Sólo se usan las cookies técnicas necesarias para mantener la sesión iniciada y proteger los formularios. No se usan
        cookies de publicidad ni de seguimiento de terceros.
    </p>

    <h2>7. Cuánto tiempo se guardan</h2>
    <p>
        Mientras el servicio esté vigente. Al terminar, el galpón tiene 30 días para exportar su información y después se
        borra, salvo lo que deba conservarse por obligación legal (por ejemplo, datos fiscales). Los registros técnicos de
        errores se limpian solos a los pocos días.
    </p>

    <h2>8. Tus derechos</h2>
    <p>
        Cualquier persona puede pedir acceso a sus datos, su corrección, actualización o supresión. Si los cargó un galpón
        (por ejemplo, un chofer o un empleado), conviene pedirlo primero a ese galpón; también se puede escribir al Titular
        @if ($p['email'])a <a class="link" href="mailto:{{ $p['email'] }}">{{ $p['email'] }}</a>@endif
        y se derivará o resolverá según corresponda. El derecho de acceso es gratuito en intervalos no menores a seis meses,
        salvo interés legítimo (art. 14, inc. 3, Ley 25.326).
    </p>
    <p class="rounded-none border border-stone-200 bg-white p-3 text-sm dark:border-stone-800 dark:bg-stone-900">
        La AGENCIA DE ACCESO A LA INFORMACIÓN PÚBLICA, en su carácter de Órgano de Control de la Ley N° 25.326, tiene la
        atribución de atender las denuncias y reclamos que interpongan quienes resulten afectados en sus derechos por
        incumplimiento de las normas vigentes en materia de protección de datos personales.
    </p>

    <h2>9. Cambios</h2>
    <p>Esta política puede actualizarse. La versión vigente siempre está publicada en esta página.</p>
</x-legal-page>
