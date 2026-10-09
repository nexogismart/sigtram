<?php

namespace App\Services\Core;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use clsTinyButStrong;
use Exception;

class DocumentGeneratorService
{
    /**
     * Genera un documento Word (.docx) borrador utilizando plantillas físicas
     * y reemplazando las etiquetas de variables (sintaxis OpenTBS).
     *
     * @param array $data Datos a reemplazar en el documento.
     * @param string $templatePath Ruta relativa (dentro de storage/app/) a la plantilla.
     * @param string $outputFileName Nombre del archivo de salida.
     * @return string Ruta absoluta del archivo temporal generado.
     */
    public function generarBorradorInterno(array $data, string $templatePath, string $outputFileName): string
    {
        $fullTemplatePath = storage_path('app/' . $templatePath);

        if (!File::exists($fullTemplatePath)) {
            throw new Exception("La plantilla solicitada no existe en la ruta: {$templatePath}");
        }

        // Generar el QR Code con el logo si existe el logo
        $qrPath = storage_path('app/private/temp_qr_' . time() . '_' . uniqid() . '.png');
        File::ensureDirectoryExists(dirname($qrPath));

        $logoPath = public_path('images/logo_qr.png');

        // $data['URL_CONSULTA'] debe venir poblado con la url
        $urlConsulta = $data['URL_CONSULTA'] ?? 'https://sigtram.munipuentepiedra.gob.pe/';

        // Generar QR (usamos chillerlan/php-qrcode nativo con GD)
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'eccLevel'        => EccLevel::H, // Alta corrección de errores para el logo
            'scale'           => 4,
            'outputBase64'    => false,
        ]);

        $qrcode = new QRCode($options);
        $qrcode->render($urlConsulta, $qrPath);

        if (File::exists($logoPath)) {
            // Incrustar el logo manualmente usando GD
            $QR = imagecreatefrompng($qrPath);
            $logo = imagecreatefrompng($logoPath);

            if ($QR !== false && $logo !== false) {
                $QR_width = imagesx($QR);
                $QR_height = imagesy($QR);
                $logo_width = imagesx($logo);
                $logo_height = imagesy($logo);

                // Escalar logo al 30% del tamaño del QR
                $logo_qr_width = $QR_width / 3;
                $scale = $logo_width / $logo_qr_width;
                $logo_qr_height = $logo_height / $scale;

                // Calcular posición central
                $pos_x = ($QR_width - $logo_qr_width) / 2;
                $pos_y = ($QR_height - $logo_qr_height) / 2;

                // Preservar transparencia del logo
                imagealphablending($QR, true);

                imagecopyresampled(
                    $QR,
                    $logo,
                    $pos_x,
                    $pos_y,
                    0,
                    0,
                    $logo_qr_width,
                    $logo_qr_height,
                    $logo_width,
                    $logo_height
                );

                imagepng($QR, $qrPath);
                imagedestroy($QR);
                imagedestroy($logo);
            }
        }

        // OpenTBS falla con rutas absolutas en Windows. La solución es cambiar el CWD 
        // temporalmente a la raíz del proyecto y usar rutas relativas puras.
        // IMAGEN estará en storage/app/private/temp_qr...png
        $data['IMAGEN'] = 'storage/app/private/' . basename($qrPath);

        // Inicializar OpenTBS
        $TBS = new clsTinyButStrong;
        class_exists('clsOpenTBS'); // Forzar la carga de las constantes globales del plugin
        $TBS->Plugin(\TBS_INSTALL, \OPENTBS_PLUGIN);

        // Configurar VarRef para que [var.KEY] busque dentro del arreglo $data en lugar de $GLOBALS
        $TBS->VarRef = &$data;
        $TBS->NoErr = true; // Prevenir que el plugin cancele el documento si falta alguna variable en el template

        // Cargar Plantilla
        $TBS->LoadTemplate($fullTemplatePath, \OPENTBS_ALREADY_UTF8);

        // --- INICIO FIX IMAGENES LEGACY ---
        // Los templates antiguos tienen rutas relativas hardcodeadas en los atributos `from` 
        // de changepic (ej. from='../../genera_dcmnto/tempQR/inter_[val]').
        // Limpiamos ese from para que quede solo from='[val]', y OpenTBS use la ruta absoluta enviada en $data.

        // 1. Limpiar el documento principal (Firma)
        $TBS->Plugin(OPENTBS_SELECT_MAIN);
        $TBS->Source = preg_replace('/from=\'[^\']*\[val\][^\']*\'/', "from='[val]'", $TBS->Source);
        $TBS->Source = preg_replace('/from="[^"]*\[val\][^"]*"/', 'from="[val]"', $TBS->Source);

        // 2. Limpiar headers y footers (QR)
        for ($i = 1; $i <= 3; $i++) {
            if ($TBS->Plugin(OPENTBS_FILEEXISTS, "word/header{$i}.xml")) {
                $TBS->Plugin(OPENTBS_SELECT_FILE, "word/header{$i}.xml");
                $TBS->Source = preg_replace('/from=\'[^\']*\[val\][^\']*\'/', "from='[val]'", $TBS->Source);
                $TBS->Source = preg_replace('/from="[^"]*\[val\][^"]*"/', 'from="[val]"', $TBS->Source);
            }
            if ($TBS->Plugin(OPENTBS_FILEEXISTS, "word/footer{$i}.xml")) {
                $TBS->Plugin(OPENTBS_SELECT_FILE, "word/footer{$i}.xml");
                $TBS->Source = preg_replace('/from=\'[^\']*\[val\][^\']*\'/', "from='[val]'", $TBS->Source);
                $TBS->Source = preg_replace('/from="[^"]*\[val\][^"]*"/', 'from="[val]"', $TBS->Source);
            }
        }
        $TBS->Plugin(OPENTBS_SELECT_MAIN); // Return to main doc
        // --- FIN FIX IMAGENES LEGACY ---

        // Si hay áreas con copia (CC), insertar una fila en la tabla debajo de A:
        $destinatariosCopia = $data['DESTINATARIOS_COPIA'] ?? [];
        if (!empty($destinatariosCopia) && is_array($destinatariosCopia)) {
            $ccRowXml = $this->generarFilaCcXml($destinatariosCopia);
            $pattern = '/(<w:tr\b(?:(?!<w:tr\b).)*?NO_CARGO_DESTINO.*?<\/w:tr>)/s';
            if (preg_match($pattern, $TBS->Source)) {
                $TBS->Source = preg_replace($pattern, '$1' . $ccRowXml, $TBS->Source, 1);
            }
        }

        // Generar archivo de salida temporal
        $tempOutputDir = storage_path('app/private/temp_borradores');
        File::ensureDirectoryExists($tempOutputDir);
        $finalPath = $tempOutputDir . '/' . $outputFileName;

        // 3. Reemplazar firma si existe
        if (isset($data['FIRMA'])) {
            // FIRMA debe ser convertida a ruta relativa desde base_path()
            $firmaPath = storage_path('app/private/firmas/' . $data['FIRMA']);
            $data['FIRMA'] = 'storage/app/private/firmas/' . basename($firmaPath);
        }

        // Cambiar CWD a base_path() temporalmente para engañar a OpenTBS
        $originalCwd = getcwd();
        chdir(base_path());

        // Procesar y guardar el documento
        $TBS->Show(\OPENTBS_FILE, $finalPath);

        // Restaurar CWD original
        chdir($originalCwd);

        // Limpiar el QR temporal
        /*if (File::exists($qrPath)) {
            File::delete($qrPath);
        }*/

        return $finalPath;
    }

    /**
     * Genera la fila XML para insertar "CC : [FUNCIONARIO / CARGO]" en la tabla de destinatarios del Word.
     */
    private function generarFilaCcXml(array $destinatariosCopia): string
    {
        if (empty($destinatariosCopia)) {
            return '';
        }

        $paragraphsXml = '';
        $total = count($destinatariosCopia);

        foreach ($destinatariosCopia as $idx => $dest) {
            $nombre = htmlspecialchars((string) ($dest['nombre'] ?? ''), ENT_XML1, 'UTF-8');
            $cargo = htmlspecialchars((string) ($dest['cargo'] ?? ''), ENT_XML1, 'UTF-8');
            $isNotLast = ($idx < $total - 1);

            // Párrafo 1: Funcionario (si existe)
            if (!empty($nombre)) {
                $nombreSpacing = (empty($cargo) && $isNotLast) ? ' w:after="120"' : '';
                $paragraphsXml .= '<w:p w:rsidR="002C00FC" w:rsidP="0028152E">'
                    . '<w:pPr>'
                    . '<w:pStyle w:val="Contenidodelatabla"/>'
                    . '<w:spacing w:line="100" w:lineRule="atLeast"' . $nombreSpacing . '/>'
                    . '<w:ind w:right="13"/>'
                    . '<w:rPr>'
                    . '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'
                    . '<w:sz w:val="22"/><w:szCs w:val="22"/>'
                    . '</w:rPr>'
                    . '</w:pPr>'
                    . '<w:r>'
                    . '<w:rPr>'
                    . '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'
                    . '<w:sz w:val="22"/><w:szCs w:val="22"/>'
                    . '</w:rPr>'
                    . '<w:t>' . $nombre . '</w:t>'
                    . '</w:r>'
                    . '</w:p>';
            }

            // Párrafo 2: Cargo / Área
            if (!empty($cargo)) {
                // Separar un poco (6pt / 120 dxa) del siguiente destinatario para que no queden pegados
                $cargoSpacing = $isNotLast ? ' w:after="120"' : '';
                $paragraphsXml .= '<w:p w:rsidR="002C00FC" w:rsidP="0028152E">'
                    . '<w:pPr>'
                    . '<w:pStyle w:val="Contenidodelatabla"/>'
                    . '<w:spacing w:line="100" w:lineRule="atLeast"' . $cargoSpacing . '/>'
                    . '<w:ind w:right="13"/>'
                    . '<w:rPr>'
                    . '<w:sz w:val="18"/><w:szCs w:val="18"/>'
                    . '</w:rPr>'
                    . '</w:pPr>'
                    . '<w:r>'
                    . '<w:rPr>'
                    . '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'
                    . '<w:b/><w:bCs/>'
                    . '<w:sz w:val="18"/><w:szCs w:val="18"/>'
                    . '</w:rPr>'
                    . '<w:t>' . $cargo . '</w:t>'
                    . '</w:r>'
                    . '</w:p>';
            }
        }

        return '<w:tr w:rsidR="002C00FC" w:rsidTr="0028152E">'
            . '<w:tc>'
            . '<w:tcPr>'
            . '<w:tcW w:w="1462" w:type="dxa"/>'
            . '<w:tcBorders>'
            . '<w:top w:val="single" w:sz="1" w:space="0" w:color="FFFFFF"/>'
            . '<w:left w:val="single" w:sz="1" w:space="0" w:color="FFFFFF"/>'
            . '<w:bottom w:val="single" w:sz="1" w:space="0" w:color="FFFFFF"/>'
            . '</w:tcBorders>'
            . '<w:shd w:val="clear" w:color="auto" w:fill="auto"/>'
            . '</w:tcPr>'
            . '<w:p w:rsidR="002C00FC" w:rsidP="0028152E">'
            . '<w:pPr>'
            . '<w:pStyle w:val="Contenidodelatabla"/>'
            . '<w:rPr>'
            . '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'
            . '<w:sz w:val="22"/><w:szCs w:val="22"/>'
            . '</w:rPr>'
            . '</w:pPr>'
            . '<w:r>'
            . '<w:rPr>'
            . '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'
            . '<w:b/><w:bCs/>'
            . '<w:sz w:val="22"/><w:szCs w:val="22"/>'
            . '</w:rPr>'
            . '<w:t>CC</w:t>'
            . '</w:r>'
            . '</w:p>'
            . '</w:tc>'
            . '<w:tc>'
            . '<w:tcPr>'
            . '<w:tcW w:w="300" w:type="dxa"/>'
            . '<w:tcBorders>'
            . '<w:top w:val="single" w:sz="1" w:space="0" w:color="FFFFFF"/>'
            . '<w:left w:val="single" w:sz="1" w:space="0" w:color="FFFFFF"/>'
            . '<w:bottom w:val="single" w:sz="1" w:space="0" w:color="FFFFFF"/>'
            . '</w:tcBorders>'
            . '<w:shd w:val="clear" w:color="auto" w:fill="auto"/>'
            . '</w:tcPr>'
            . '<w:p w:rsidR="002C00FC" w:rsidP="0028152E">'
            . '<w:pPr>'
            . '<w:pStyle w:val="Contenidodelatabla"/>'
            . '<w:jc w:val="center"/>'
            . '<w:rPr>'
            . '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'
            . '<w:sz w:val="22"/><w:szCs w:val="22"/>'
            . '</w:rPr>'
            . '</w:pPr>'
            . '<w:r>'
            . '<w:rPr>'
            . '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'
            . '<w:sz w:val="22"/><w:szCs w:val="22"/>'
            . '</w:rPr>'
            . '<w:t>:</w:t>'
            . '</w:r>'
            . '</w:p>'
            . '</w:tc>'
            . '<w:tc>'
            . '<w:tcPr>'
            . '<w:tcW w:w="7338" w:type="dxa"/>'
            . '<w:tcBorders>'
            . '<w:top w:val="single" w:sz="1" w:space="0" w:color="FFFFFF"/>'
            . '<w:left w:val="single" w:sz="1" w:space="0" w:color="FFFFFF"/>'
            . '<w:bottom w:val="single" w:sz="1" w:space="0" w:color="FFFFFF"/>'
            . '<w:right w:val="single" w:sz="1" w:space="0" w:color="FFFFFF"/>'
            . '</w:tcBorders>'
            . '<w:shd w:val="clear" w:color="auto" w:fill="auto"/>'
            . '</w:tcPr>'
            . $paragraphsXml
            . '</w:tc>'
            . '</w:tr>';
    }
}
