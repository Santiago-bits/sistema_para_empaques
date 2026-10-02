<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Jobs\GenerateReportExport;
use App\Models\User;
use App\Services\Reports\Format;
use App\Services\Reports\ReportDataset;
use App\Services\Reports\ReportDatasets;
use App\Services\Reports\ReportFilters;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exportación reutilizable de cualquier ReportDataset a XLSX / CSV (openspout,
 * en streaming fila por fila) o PDF (dompdf, con encabezado del galpón y los
 * filtros aplicados).
 *
 * Exportaciones grandes (> reports.queue_threshold filas, 20.000 por defecto)
 * se generan en segundo plano con el Job GenerateReportExport, que guarda el
 * archivo en storage privado (exports/{user_id}/) y notifica al usuario.
 * Cada exportación queda auditada con la acción 'export'.
 */
class ExportService
{
    public const FORMATS = ['xlsx', 'csv', 'pdf'];

    public const MIME = [
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv' => 'text/csv; charset=UTF-8',
        'pdf' => 'application/pdf',
    ];

    /** Disco y carpeta privados donde se guardan las exportaciones en cola. */
    public const DISK = 'local';

    public const DIRECTORY = 'exports';

    public function __construct(
        private readonly ReportDatasets $datasets,
        private readonly AuditService $audit,
    ) {
    }

    public function queueThreshold(): int
    {
        return (int) config('reports.queue_threshold', 20000);
    }

    public function pdfRowLimit(): int
    {
        return (int) config('reports.pdf_row_limit', 3000);
    }

    /**
     * Exporta un reporte. Devuelve la respuesta de descarga, o null si se encoló.
     * La autorización (reports.export_excel / reports.export_pdf) la hace el controlador.
     */
    public function export(User $user, string $report, string $variant, ReportFilters $filters, string $format): ?Response
    {
        if (! in_array($format, self::FORMATS, true)) {
            throw new BusinessException('Formato de exportación no soportado.');
        }

        $dataset = $this->datasets->make($report, $variant, $filters, $user);
        $count = $dataset->count();

        if ($format === 'pdf' && $count > $this->pdfRowLimit()) {
            throw new BusinessException('El reporte tiene '.num($count).' filas: es demasiado grande para PDF (máximo '
                .num($this->pdfRowLimit()).'). Acotá los filtros o exportalo a Excel.');
        }

        $queued = $format !== 'pdf' && $count > $this->queueThreshold();

        $this->audit->log('export', null, null, [
            'report' => $report,
            'variant' => $dataset->variant,
            'format' => $format,
            'rows' => $count,
            'queued' => $queued,
            'filters' => $filters->describe(),
        ], 'Exportación de «'.$dataset->title.'» ('.strtoupper($format).', '.num($count).' filas)');

        if ($queued) {
            GenerateReportExport::dispatch($user->id, $report, $dataset->variant, $filters->toArray(), $format, $this->filename($dataset, $format, true));

            return null;
        }

        return $this->download($dataset, $format, $filters, $user);
    }

    /** Respuesta de descarga inmediata (streaming para XLSX/CSV). */
    public function download(ReportDataset $dataset, string $format, ReportFilters $filters, ?User $user = null): Response
    {
        $filename = $this->filename($dataset, $format);

        if ($format === 'pdf') {
            return response($this->pdf($dataset, $filters, $user)->output(), 200, [
                'Content-Type' => self::MIME['pdf'],
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        return response()->streamDownload(function () use ($dataset, $format) {
            $this->writeSpreadsheet($dataset, $format, 'php://output');
        }, $filename, ['Content-Type' => self::MIME[$format]]);
    }

    /** Genera el archivo en el disco privado (usado por el Job en cola). */
    public function store(ReportDataset $dataset, string $format, ReportFilters $filters, string $path, ?User $user = null): void
    {
        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory(dirname($path));
        $absolute = $disk->path($path);

        if ($format === 'pdf') {
            $this->pdf($dataset, $filters, $user)->save($absolute);

            return;
        }

        $this->writeSpreadsheet($dataset, $format, $absolute);
    }

    public function writeSpreadsheet(ReportDataset $dataset, string $format, string $target): int
    {
        $writer = $format === 'csv' ? new CsvWriter() : new XlsxWriter();
        $writer->openToFile($target);

        $bold = (new Style())->setFontBold();
        $writer->addRow(Row::fromValues($dataset->headings(), $format === 'xlsx' ? $bold : null));

        $written = 0;
        foreach ($dataset->rows() as $row) {
            $writer->addRow(Row::fromValues($this->values($dataset, $row, $format === 'csv')));
            $written++;
        }
        if ($dataset->totals !== []) {
            $writer->addRow(Row::fromValues($this->values($dataset, $dataset->totals, $format === 'csv'), $format === 'xlsx' ? $bold : null));
        }

        $writer->close();

        return $written;
    }

    /** @return list<mixed> */
    private function values(ReportDataset $dataset, array $row, bool $csv = false): array
    {
        $values = [];
        foreach ($dataset->columns as $key => $column) {
            $value = Format::cell($row[$key] ?? null, $column['type']);
            $values[] = $csv ? self::neutralizeFormula($value) : $value;
        }

        return $values;
    }

    /**
     * Evita la inyección de fórmulas al abrir un CSV en Excel: un texto que empieza con
     * = + - @ tabulación o retorno se antepone con un apóstrofo (Excel lo muestra como texto).
     */
    public static function neutralizeFormula(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value)) {
            return "'".$value;
        }

        return $value;
    }

    private function pdf(ReportDataset $dataset, ReportFilters $filters, ?User $user): \Barryvdh\DomPDF\PDF
    {
        $view = $dataset->key === 'executive' ? 'reports.pdf-executive' : 'reports.pdf';
        $data = [
            'dataset' => $dataset,
            'filters' => $filters->describe(),
            'company' => [
                'name' => setting('company.name'),
                'cuit' => setting('company.cuit'),
                'address' => setting('company.address'),
                'phone' => setting('company.phone'),
            ],
            'generatedBy' => $user?->full_name,
            'generatedAt' => now(),
        ];
        if ($dataset->key === 'executive') {
            $data['sections'] = $this->datasets->executiveSections($filters, $user);
        }

        return Pdf::loadView($view, $data)->setPaper('a4', count($dataset->columns) > 6 ? 'landscape' : 'portrait');
    }

    public function filename(ReportDataset $dataset, string $format, bool $unique = false): string
    {
        $name = Str::slug($dataset->title).'-'.now()->format('Ymd-His');
        if ($unique) {
            $name .= '-'.Str::lower(Str::random(8));
        }

        return $name.'.'.$format;
    }

    /** Ruta relativa (en el disco privado) de un archivo exportado por un usuario. */
    public static function pathFor(int $userId, string $file): string
    {
        return self::DIRECTORY.'/'.$userId.'/'.$file;
    }
}
