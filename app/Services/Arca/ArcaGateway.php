<?php

namespace App\Services\Arca;

use App\Models\Invoice;

/**
 * Contrato de integración con ARCA (ex AFIP). La facturación no conoce detalles
 * de los web services: sólo usa esta interfaz. Implementaciones:
 *  - SimulationGateway: sin conexión, para pruebas y capacitación.
 *  - WsfeGateway: WSAA + WSFEv1 (homologación o producción).
 */
interface ArcaGateway
{
    public function mode(): string;

    /** Solicita CAE para el comprobante. Nunca lanza por rechazo de ARCA: lo informa en ArcaResult. */
    public function authorize(Invoice $invoice): ArcaResult;

    /** Último número autorizado para punto de venta y tipo de comprobante. */
    public function lastAuthorizedNumber(int $pointOfSale, int $voucherType): int;

    /** Verifica credenciales y conectividad (no emite comprobantes). */
    public function testConnection(): ArcaResult;
}
