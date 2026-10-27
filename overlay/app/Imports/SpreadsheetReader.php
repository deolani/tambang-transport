<?php

namespace App\Imports;

/**
 * Membaca file .xlsx atau .csv menjadi array baris asosiatif:
 *   [ ['line' => 2, 'data' => ['kode_unit' => 'DT 1501', ...]], ... ]
 * Header baris pertama dinormalisasi: "Kode Unit *" -> "kode_unit".
 */
class SpreadsheetReader
{
    public const MAX_ROWS = 5000;

    public static function normalizeHeader($h): string
    {
        $h = strtolower(trim((string) $h));
        $h = preg_replace('/\(.*?\)/', '', $h);           // buang "(ton)" dll
        $h = preg_replace('/[^a-z0-9]+/', '_', $h);
        $h = trim($h, '_');

        return $h;
    }

    /** @return array<int, array{line:int, data:array<string,mixed>}> */
    public static function read(string $path, ?string $originalName = null): array
    {
        $ext = strtolower(pathinfo($originalName ?: $path, PATHINFO_EXTENSION));

        $raw = match ($ext) {
            'xlsx' => static::readXlsx($path),
            'csv', 'txt' => static::readCsv($path),
            default => throw new ImportRowException('Format file tidak didukung. Gunakan .xlsx atau .csv'),
        };

        if (count($raw) < 2) {
            throw new ImportRowException('File kosong atau hanya berisi header.');
        }

        $headers = array_map([static::class, 'normalizeHeader'], array_shift($raw));
        $rows = [];
        foreach ($raw as $i => $cells) {
            $line = $i + 2;
            $assoc = [];
            foreach ($headers as $c => $key) {
                if ($key === '') {
                    continue;
                }
                $v = $cells[$c] ?? null;
                $assoc[$key] = is_string($v) ? trim($v) : $v;
            }
            // lewati baris yang benar-benar kosong
            if (collect($assoc)->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
                continue;
            }
            $rows[] = ['line' => $line, 'data' => $assoc];
            if (count($rows) > self::MAX_ROWS) {
                throw new ImportRowException('Maksimal '.self::MAX_ROWS.' baris per file. Pecah file menjadi beberapa bagian.');
            }
        }

        return $rows;
    }

    protected static function readCsv(string $path): array
    {
        $fh = fopen($path, 'r');
        if (! $fh) {
            throw new ImportRowException('File tidak bisa dibuka.');
        }
        $first = (string) fgets($fh);
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first); // buang BOM
        // Excel Indonesia biasanya memakai titik koma
        $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        if (substr_count($first, "\t") > substr_count($first, $delimiter)) {
            $delimiter = "\t";
        }
        rewind($fh);

        $rows = [];
        $isFirst = true;
        while (($cells = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
            if ($isFirst && isset($cells[0])) {
                $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cells[0]);
                $isFirst = false;
            }
            if ($cells === [null]) {
                continue;
            }
            $rows[] = $cells;
        }
        fclose($fh);

        return $rows;
    }

    protected static function readXlsx(string $path): array
    {
        if (! class_exists(\OpenSpout\Reader\XLSX\Reader::class)) {
            throw new ImportRowException('Pembaca Excel belum terpasang. Jalankan: composer require openspout/openspout, atau simpan file sebagai CSV.');
        }

        $reader = new \OpenSpout\Reader\XLSX\Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            break; // hanya sheet pertama
        }
        $reader->close();

        return $rows;
    }
}
