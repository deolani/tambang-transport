<?php

namespace App\Imports;

/**
 * Membuat file template (.xlsx bila OpenSpout tersedia, selain itu .csv).
 * Baris 1 = header (kolom wajib diberi tanda *), baris 2 = contoh.
 */
class TemplateWriter
{
    /** @param class-string<BaseImporter> $importer */
    public static function download(string $importer)
    {
        $headers = [];
        $example = [];
        foreach ($importer::columns() as [$label, $required, $ex]) {
            $headers[] = $label.($required ? ' *' : '');
            $example[] = $ex;
        }
        $base = 'template-'.$importer::key();

        if (class_exists(\OpenSpout\Writer\XLSX\Writer::class)) {
            $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tpl-'.uniqid().'.xlsx';
            $writer = new \OpenSpout\Writer\XLSX\Writer;
            $writer->openToFile($tmp);
            $bold = (new \OpenSpout\Common\Entity\Style\Style)->setFontBold();
            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($headers, $bold));
            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($example));
            $writer->close();

            return response()->download($tmp, $base.'.xlsx')->deleteFileAfterSend();
        }

        return response()->streamDownload(function () use ($headers, $example) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');
            fputcsv($out, $example, ';');
            fclose($out);
        }, $base.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
