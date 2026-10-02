<?php

return [
    // Versión del sistema (se actualiza en cada release; ver CHANGELOG.md).
    'version' => '1.5.0',

    // Identificador de esta instalación (para licencias y soporte).
    'installation_id' => env('GALPON_INSTALLATION_ID', 'local-dev'),

    // Panel General del proveedor. En TU servidor central: GALPON_CENTRAL_MODE=true.
    // En cada empaque: GALPON_CENTRAL_URL (dirección del Panel General) y GALPON_LICENSE_KEY (su clave).
    // Cada empaque envía sólo totales de uso (sin datos de personas ni de clientes) y sus pedidos de soporte.
    'central' => [
        'mode' => (bool) env('GALPON_CENTRAL_MODE', false),
        'url' => env('GALPON_CENTRAL_URL'),
        'key' => env('GALPON_LICENSE_KEY'),
        'timeout' => (int) env('GALPON_CENTRAL_TIMEOUT', 10),
    ],

    // Soporte del proveedor del sistema.
    'support_email' => env('GALPON_SUPPORT_EMAIL', 'soporte@example.com'),
    'support_phone' => env('GALPON_SUPPORT_PHONE', ''),

    // Ruta a mysqldump/mysql para backups (en XAMPP: C:\xampp\mysql\bin).
    'mysql_bin_path' => env('GALPON_MYSQL_BIN', ''),

    // Integración ARCA (ex AFIP). Las credenciales NUNCA van en el código: sólo en .env.
    'arca' => [
        'cuit' => env('ARCA_CUIT'),
        'certificate_path' => env('ARCA_CERT_PATH'),
        'private_key_path' => env('ARCA_KEY_PATH'),
        'private_key_passphrase' => env('ARCA_KEY_PASSPHRASE'),
        'wsaa_homologation' => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms',
        'wsaa_production' => 'https://wsaa.afip.gov.ar/ws/services/LoginCms',
        'wsfe_homologation' => 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx',
        'wsfe_production' => 'https://servicios1.afip.gov.ar/wsfev1/service.asmx',
    ],

    // Dispositivos externos (balanzas, impresoras de etiquetas).
    'devices' => [
        'scale_driver' => env('GALPON_SCALE_DRIVER', 'manual'), // manual | api
        'label_printer' => env('GALPON_LABEL_PRINTER', 'browser'), // browser | zpl
    ],

    // WhatsApp: integración opcional, nunca obligatoria.
    'whatsapp' => [
        'driver' => env('GALPON_WHATSAPP_DRIVER', 'log'), // log | cloud_api
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    ],
];
