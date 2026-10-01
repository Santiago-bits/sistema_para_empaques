<?php

namespace App\Services\Arca;

use App\Exceptions\BusinessException;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use SimpleXMLElement;
use Throwable;

/**
 * Integración real con ARCA: WSAA (autenticación) + WSFEv1 (factura electrónica).
 *
 * - El certificado (.crt) y la clave privada (.key) se leen de rutas definidas en .env
 *   (ARCA_CERT_PATH / ARCA_KEY_PATH). Nunca se guardan en la base ni se muestran.
 * - El ticket de acceso (token/sign) se cachea hasta su vencimiento (12 h).
 * - Las llamadas SOAP se arman a mano porque el servidor no requiere ext-soap.
 *
 * IMPORTANTE: validar SIEMPRE en homologación con el certificado de testing de ARCA
 * antes de pasar a producción. Especificación: Manual del desarrollador WSFEv1 (RG 4291 /
 * RG 5616 — CondicionIVAReceptorId obligatorio).
 */
class WsfeGateway implements ArcaGateway
{
    private const NS_WSFE = 'http://ar.gov.afip.dif.FEV1/';

    private const NS_WSAA = 'http://wsaa.view.sua.dvadac.desein.afip.gov';

    /** Alícuotas de IVA de ARCA (código Id). */
    private const VAT_IDS = ['0' => 3, '10.5' => 4, '21' => 5, '27' => 6, '5' => 8, '2.5' => 9];

    /** Condición IVA del receptor (RG 5616). */
    private const RECEIVER_CONDITION = ['RI' => 1, 'EX' => 4, 'CF' => 5, 'MT' => 6];

    public function __construct(private readonly string $mode)
    {
        if (! in_array($mode, ['homologation', 'production'], true)) {
            throw new \InvalidArgumentException('Modo ARCA inválido para WSFE.');
        }
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function authorize(Invoice $invoice): ArcaResult
    {
        $invoice->loadMissing('client', 'items');
        $auth = $this->auth();
        $number = $this->lastAuthorizedNumber((int) $invoice->point_of_sale, (int) $invoice->voucher_type) + 1;
        $detail = $this->detail($invoice, $number);

        $body = '<ar:FECAESolicitar><ar:Auth>'.$this->authXml($auth).'</ar:Auth><ar:FeCAEReq>'
            .'<ar:FeCabReq><ar:CantReg>1</ar:CantReg><ar:PtoVta>'.(int) $invoice->point_of_sale.'</ar:PtoVta>'
            .'<ar:CbteTipo>'.(int) $invoice->voucher_type.'</ar:CbteTipo></ar:FeCabReq>'
            .'<ar:FeDetReq><ar:FECAEDetRequest>'.$this->toXml($detail).'</ar:FECAEDetRequest></ar:FeDetReq>'
            .'</ar:FeCAEReq></ar:FECAESolicitar>';

        $request = ['PtoVta' => $invoice->point_of_sale, 'CbteTipo' => $invoice->voucher_type] + $detail;

        try {
            $xml = $this->call('FECAESolicitar', $body);
        } catch (Throwable $e) {
            return ArcaResult::failure('Sin respuesta de ARCA: '.Str::limit($e->getMessage(), 300), $request);
        }

        $result = $xml->xpath('//*[local-name()="FECAESolicitarResult"]')[0] ?? null;
        $det = $result?->xpath('.//*[local-name()="FECAEDetResponse"]')[0] ?? null;
        $errors = $this->messages($result, 'Err');
        $observations = $det ? $this->messages($det, 'Obs') : [];
        $response = ['Resultado' => (string) ($det?->xpath('./*[local-name()="Resultado"]')[0] ?? ''), 'Errores' => $errors, 'Observaciones' => $observations];

        $approved = $response['Resultado'] === 'A';
        if (! $approved) {
            return ArcaResult::failure(implode(' | ', array_merge($errors, $observations)) ?: 'Comprobante rechazado por ARCA.', $request, $response);
        }

        $cae = (string) ($det->xpath('./*[local-name()="CAE"]')[0] ?? '');
        $due = (string) ($det->xpath('./*[local-name()="CAEFchVto"]')[0] ?? '');

        return new ArcaResult(true, $number, $cae, $due ? Carbon::createFromFormat('Ymd', $due)->startOfDay() : null,
            request: $request, response: $response + ['CAE' => $cae, 'CAEFchVto' => $due]);
    }

    public function lastAuthorizedNumber(int $pointOfSale, int $voucherType): int
    {
        $body = '<ar:FECompUltimoAutorizado><ar:Auth>'.$this->authXml($this->auth()).'</ar:Auth>'
            .'<ar:PtoVta>'.$pointOfSale.'</ar:PtoVta><ar:CbteTipo>'.$voucherType.'</ar:CbteTipo></ar:FECompUltimoAutorizado>';
        $xml = $this->call('FECompUltimoAutorizado', $body);
        $errors = $this->messages($xml, 'Err');
        if ($errors) {
            throw new BusinessException('ARCA: '.implode(' | ', $errors));
        }

        return (int) ($xml->xpath('//*[local-name()="CbteNro"]')[0] ?? 0);
    }

    public function testConnection(): ArcaResult
    {
        try {
            $xml = $this->call('FEDummy', '<ar:FEDummy/>');
            $status = [
                'AppServer' => (string) ($xml->xpath('//*[local-name()="AppServer"]')[0] ?? ''),
                'DbServer' => (string) ($xml->xpath('//*[local-name()="DbServer"]')[0] ?? ''),
                'AuthServer' => (string) ($xml->xpath('//*[local-name()="AuthServer"]')[0] ?? ''),
            ];
            $this->auth();

            return new ArcaResult(true, operation: 'FEDummy', response: $status);
        } catch (Throwable $e) {
            return ArcaResult::failure(Str::limit($e->getMessage(), 300), operation: 'FEDummy');
        }
    }

    /** Detalle FECAEDetRequest según el comprobante. */
    private function detail(Invoice $invoice, int $number): array
    {
        /** @var Client $client */
        $client = $invoice->client;
        $cuit = preg_replace('/\D/', '', (string) $client->cuit);
        [$docType, $docNumber] = match (true) {
            strlen($cuit) === 11 => [80, $cuit],
            ! empty($client->dni) => [96, preg_replace('/\D/', '', (string) $client->dni)],
            default => [99, '0'],
        };

        $isC = in_array((int) $invoice->voucher_type, [11, 13], true);
        $detail = [
            'Concepto' => 1, // Productos
            'DocTipo' => $docType,
            'DocNro' => $docNumber,
            'CbteDesde' => $number,
            'CbteHasta' => $number,
            'CbteFch' => $invoice->issued_on->format('Ymd'),
            'ImpTotal' => $this->amount($invoice->total_amount),
            'ImpTotConc' => '0.00',
            'ImpNeto' => $this->amount($isC ? $invoice->total_amount : $invoice->net_amount),
            'ImpOpEx' => '0.00',
            'ImpTrib' => '0.00',
            'ImpIVA' => $this->amount($isC ? 0 : $invoice->vat_amount),
            'MonId' => $invoice->currency === 'USD' ? 'DOL' : 'PES',
            'MonCotiz' => number_format((float) $invoice->exchange_rate, 6, '.', ''),
            'CondicionIVAReceptorId' => self::RECEIVER_CONDITION[$client->tax_condition] ?? 5,
        ];

        if (! $isC) {
            $groups = [];
            foreach ($invoice->items as $item) {
                $rate = rtrim(rtrim(number_format((float) $item->vat_rate, 2, '.', ''), '0'), '.');
                $groups[$rate]['base'] = ($groups[$rate]['base'] ?? 0) + (float) $item->subtotal;
                $groups[$rate]['tax'] = ($groups[$rate]['tax'] ?? 0) + round((float) $item->subtotal * (float) $item->vat_rate / 100, 2);
            }
            $detail['Iva'] = array_map(fn ($rate, $g) => [
                'AlicIva' => ['Id' => self::VAT_IDS[$rate] ?? 5, 'BaseImp' => $this->amount($g['base']), 'Importe' => $this->amount($g['tax'])],
            ], array_keys($groups), $groups);
        }

        return $detail;
    }

    /** Ticket de acceso WSAA (token + sign), cacheado hasta su vencimiento. */
    private function auth(): array
    {
        $config = config('galpon.arca');
        $cuit = preg_replace('/\D/', '', (string) (setting('arca.cuit') ?: $config['cuit']));
        if (strlen($cuit) !== 11) {
            throw new BusinessException('Falta configurar el CUIT emisor de ARCA (Configuración → ARCA o ARCA_CUIT en .env).');
        }

        $cacheKey = 'arca:ta:'.$this->mode.':'.$cuit;
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        foreach (['certificate_path' => 'ARCA_CERT_PATH', 'private_key_path' => 'ARCA_KEY_PATH'] as $key => $env) {
            if (empty($config[$key]) || ! is_readable($config[$key])) {
                throw new BusinessException("Falta el certificado de ARCA: configurá {$env} en el archivo .env del servidor.");
            }
        }

        $now = now('UTC');
        $tra = '<?xml version="1.0" encoding="UTF-8"?><loginTicketRequest version="1.0"><header>'
            .'<uniqueId>'.$now->getTimestamp().'</uniqueId>'
            .'<generationTime>'.$now->copy()->subMinutes(10)->format('c').'</generationTime>'
            .'<expirationTime>'.$now->copy()->addMinutes(10)->format('c').'</expirationTime>'
            .'</header><service>wsfe</service></loginTicketRequest>';

        $in = tempnam(sys_get_temp_dir(), 'tra');
        $out = tempnam(sys_get_temp_dir(), 'cms');
        try {
            file_put_contents($in, $tra);
            $key = $config['private_key_passphrase'] ? [file_get_contents($config['private_key_path']), $config['private_key_passphrase']] : file_get_contents($config['private_key_path']);
            if (! openssl_pkcs7_sign($in, $out, file_get_contents($config['certificate_path']), $key, [], PKCS7_BINARY | PKCS7_NOATTR)) {
                throw new BusinessException('No se pudo firmar el ticket de acceso de ARCA (verificá certificado y clave).');
            }
            $cms = $this->extractCms((string) file_get_contents($out));
        } finally {
            @unlink($in);
            @unlink($out);
        }

        $endpoint = $config['wsaa_'.$this->mode];
        $envelope = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:wsaa="'.self::NS_WSAA.'">'
            .'<soapenv:Header/><soapenv:Body><wsaa:loginCms><wsaa:in0>'.$cms.'</wsaa:in0></wsaa:loginCms></soapenv:Body></soapenv:Envelope>';
        $response = Http::timeout(30)->withHeaders(['Content-Type' => 'text/xml; charset=utf-8', 'SOAPAction' => '""'])
            ->withBody($envelope, 'text/xml')->post($endpoint);

        $xml = $this->parse($response->body());
        $fault = (string) ($xml->xpath('//*[local-name()="faultstring"]')[0] ?? '');
        if ($fault !== '') {
            throw new BusinessException('ARCA (WSAA): '.$fault);
        }
        $ticket = $this->parse(html_entity_decode((string) ($xml->xpath('//*[local-name()="loginCmsReturn"]')[0] ?? '')));
        $auth = [
            'token' => (string) ($ticket->xpath('//token')[0] ?? ''),
            'sign' => (string) ($ticket->xpath('//sign')[0] ?? ''),
            'cuit' => $cuit,
        ];
        $expires = Carbon::parse((string) ($ticket->xpath('//expirationTime')[0] ?? now()->addHours(11)));
        Cache::put($cacheKey, $auth, $expires->subMinutes(5));

        return $auth;
    }

    private function extractCms(string $smime): string
    {
        $parts = preg_split("/\r?\n\r?\n/", $smime, 2);
        $body = $parts[1] ?? $smime;

        return preg_replace('/\s+/', '', preg_replace('/-----.*-----/', '', $body));
    }

    private function call(string $operation, string $body): SimpleXMLElement
    {
        $endpoint = config('galpon.arca.wsfe_'.$this->mode);
        $envelope = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ar="'.self::NS_WSFE.'">'
            .'<soapenv:Header/><soapenv:Body>'.$body.'</soapenv:Body></soapenv:Envelope>';

        $response = Http::timeout(45)->withHeaders([
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => self::NS_WSFE.$operation,
        ])->withBody($envelope, 'text/xml')->post($endpoint);

        if (! $response->successful() && $response->status() !== 500) {
            throw new BusinessException("ARCA respondió HTTP {$response->status()} en {$operation}.");
        }

        return $this->parse($response->body());
    }

    private function parse(string $xml): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        try {
            // LIBXML_NONET: sin acceso a red al parsear (protección XXE).
            $parsed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            if ($parsed === false) {
                throw new BusinessException('Respuesta de ARCA ilegible.');
            }

            return $parsed;
        } finally {
            libxml_use_internal_errors($previous);
        }
    }

    /** @return list<string> */
    private function messages(?SimpleXMLElement $node, string $tag): array
    {
        if (! $node) {
            return [];
        }

        return array_map(function (SimpleXMLElement $m) {
            $code = (string) ($m->xpath('./*[local-name()="Code"]')[0] ?? '');
            $msg = (string) ($m->xpath('./*[local-name()="Msg"]')[0] ?? '');

            return trim($code.' '.$msg);
        }, $node->xpath('.//*[local-name()="'.$tag.'"]') ?: []);
    }

    private function authXml(array $auth): string
    {
        return '<ar:Token>'.e($auth['token']).'</ar:Token><ar:Sign>'.e($auth['sign']).'</ar:Sign><ar:Cuit>'.e($auth['cuit']).'</ar:Cuit>';
    }

    /** Serializa el detalle a XML con prefijo ar: (escapando valores). */
    private function toXml(array $data): string
    {
        $xml = '';
        foreach ($data as $key => $value) {
            if (is_int($key)) {
                $xml .= $this->toXml($value);
                continue;
            }
            $xml .= '<ar:'.$key.'>'.(is_array($value) ? $this->toXml($value) : e((string) $value)).'</ar:'.$key.'>';
        }

        return $xml;
    }

    private function amount(float|string|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
