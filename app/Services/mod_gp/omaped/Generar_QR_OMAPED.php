<?php
/**
 * Generador QR completamente independiente para OMAPED
 * NO usa Generar_QR - Implementación completa desde cero
 * Nivel H (High) de corrección de errores para mejor legibilidad con logo
 */

class Generar_QR_OMAPED
{
    /**
     * Convierte una imagen a truecolor si no lo es
     * @param resource $image Recurso de imagen GD
     * @return resource Imagen en truecolor
     */
    private static function ensureTruecolor($image) {
        if (imageistruecolor($image)) {
            return $image;
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $truecolor = imagecreatetruecolor($width, $height);
        imagealphablending($truecolor, false);
        imagesavealpha($truecolor, true);
        $transparent = imagecolorallocatealpha($truecolor, 0, 0, 0, 127);
        imagefill($truecolor, 0, 0, $transparent);
        imagealphablending($truecolor, true);
        imagecopy($truecolor, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);
        return $truecolor;
    }
    
    /**
     * Crea una imagen truecolor con transparencia
     * @param int $width Ancho
     * @param int $height Alto
     * @return resource Imagen GD
     */
    private static function createTransparentImage($width, $height) {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
        imagealphablending($image, true);
        return $image;
    }
    
    /**
     * Genera un código QR PNG optimizado para OMAPED con logo
     * 
     * @param string $qrcode_data_string Datos a codificar (URL)
     * @param string $qrcode_expe_string Nombre del archivo sin extensión
     * @param int $qrcode_module_size Tamaño del módulo (recomendado: 4-5)
     * @return bool true si se generó correctamente
     */
    public static function Gene_QR_png($qrcode_data_string, $qrcode_expe_string, $qrcode_module_size = '4') {
        // Verificar que las constantes estén definidas
        if (!defined('__DOCROOT__') || !defined('__SUBDIRECTORY__')) {
            throw new \Exception("Constantes __DOCROOT__ o __SUBDIRECTORY__ no definidas");
        }
        
        $path = __DOCROOT__ . __SUBDIRECTORY__ . "/Negocio/qr_img/data";
        $image_path = __DOCROOT__ . __SUBDIRECTORY__ . "/Negocio/qr_img/image";
        
        // Verificar que los directorios existan
        if (!is_dir($path) || !is_dir($image_path)) {
            throw new \Exception("Directorio no existe: " . (!is_dir($path) ? $path : $image_path));
        }
        
        $version_ul = 40;
        
        // NIVEL H (High) de corrección de errores - permite hasta ~30% de errores
        // Esto es esencial cuando se incrusta un logo en el centro
        $qrcode_error_correct = 'H';
        $qrcode_version = null; // Auto-select version
        $qrcode_image_type = 'png';
        
        $qrcode_module_size = max(1, (int)$qrcode_module_size);
        
        $qrcode_data_string = rawurldecode($qrcode_data_string);
        $data_length = strlen($qrcode_data_string);
        if ($data_length <= 0) {
            throw new \Exception("Datos vacíos para generar QR");
        }
        
        $data_counter = 0;
        $data_bits = array();
        $data_value = array();
        
        // Determinar modo de codificación
        if (preg_match("/[^0-9]/", $qrcode_data_string) != 0) {
            if (preg_match("/[^0-9A-Z \$\*\%\+\-\.\/\:]/", $qrcode_data_string) != 0) {
                // Modo 8-bit byte
                $codeword_num_plus = array(0,0,0,0,0,0,0,0,0,0,8,8,8,8,8,8,8,8,8,8,8,8,8,8,8,8,8,8,8,8,8,8,8,8,8,8,8,8,8,8,8);
                $data_value[$data_counter] = 4;
                $data_bits[$data_counter] = 4;
                $data_counter++;
                $data_value[$data_counter] = $data_length;
                $data_bits[$data_counter] = 8;
                $codeword_num_counter_value = $data_counter;
                $data_counter++;
                $i = 0;
                while ($i < $data_length) {
                    $data_value[$data_counter] = ord(substr($qrcode_data_string, $i, 1));
                    $data_bits[$data_counter] = 8;
                    $data_counter++;
                    $i++;
                }
            } else {
                // Modo alfanumérico
                $codeword_num_plus = array(0,0,0,0,0,0,0,0,0,0,2,2,2,2,2,2,2,2,2,2,2,2,2,2,2,2,2,2,4,4,4,4,4,4,4,4,4,4,4,4,4);
                $data_value[$data_counter] = 2;
                $data_bits[$data_counter] = 4;
                $data_counter++;
                $data_value[$data_counter] = $data_length;
                $data_bits[$data_counter] = 9;
                $codeword_num_counter_value = $data_counter;
                $alphanumeric_character_hash = array("0"=>0,"1"=>1,"2"=>2,"3"=>3,"4"=>4,"5"=>5,"6"=>6,"7"=>7,"8"=>8,"9"=>9,"A"=>10,"B"=>11,"C"=>12,"D"=>13,"E"=>14,"F"=>15,"G"=>16,"H"=>17,"I"=>18,"J"=>19,"K"=>20,"L"=>21,"M"=>22,"N"=>23,"O"=>24,"P"=>25,"Q"=>26,"R"=>27,"S"=>28,"T"=>29,"U"=>30,"V"=>31,"W"=>32,"X"=>33,"Y"=>34,"Z"=>35," "=>36,"$"=>37,"%"=>38,"*"=>39,"+"=>40,"-"=>41,"."=>42,"/"=>43,":"=>44);
                $i = 0;
                $data_counter++;
                while ($i < $data_length) {
                    if (($i % 2) == 0) {
                        $data_value[$data_counter] = $alphanumeric_character_hash[substr($qrcode_data_string, $i, 1)];
                        $data_bits[$data_counter] = 6;
                    } else {
                        $data_value[$data_counter] = $data_value[$data_counter] * 45 + $alphanumeric_character_hash[substr($qrcode_data_string, $i, 1)];
                        $data_bits[$data_counter] = 11;
                        $data_counter++;
                    }
                    $i++;
                }
            }
        } else {
            // Modo numérico
            $codeword_num_plus = array(0,0,0,0,0,0,0,0,0,0,2,2,2,2,2,2,2,2,2,2,2,2,2,2,2,2,2,4,4,4,4,4,4,4,4,4,4,4,4,4,4);
            $data_value[$data_counter] = 1;
            $data_counter++;
            $data_value[$data_counter] = $data_length;
            $data_bits[$data_counter] = 10;
            $codeword_num_counter_value = $data_counter;
            $i = 0;
            $data_counter++;
            while ($i < $data_length) {
                if (($i % 3) == 0) {
                    $data_value[$data_counter] = substr($qrcode_data_string, $i, 1);
                    $data_bits[$data_counter] = 4;
                } else {
                    $data_value[$data_counter] = $data_value[$data_counter] * 10 + substr($qrcode_data_string, $i, 1);
                    if (($i % 3) == 1) {
                        $data_bits[$data_counter] = 7;
                    } else {
                        $data_bits[$data_counter] = 10;
                        $data_counter++;
                    }
                }
                $i++;
            }
        }
        
        if (@$data_bits[$data_counter] > 0) {
            $data_counter++;
        }
        
        $i = 0;
        $total_data_bits = 0;
        while ($i < $data_counter) {
            $total_data_bits += $data_bits[$i];
            $i++;
        }
        
        // Mapeo de niveles de corrección de errores
        $ecc_character_hash = array("L"=>"1", "l"=>"1", "M"=>"0", "m"=>"0", "Q"=>"3", "q"=>"3", "H"=>"2", "h"=>"2");
        $ec = isset($ecc_character_hash[$qrcode_error_correct]) ? $ecc_character_hash[$qrcode_error_correct] : 2; // H = 2
        
        // Array de bits máximos por versión y nivel ECC (4 niveles x 40 versiones)
        $max_data_bits_array = array(
            // Nivel L (ec=1)
            0,128,224,352,512,688,864,992,1232,1456,1728,2032,2320,2672,2920,3320,3624,4056,4504,5016,5352,5712,6256,6880,7312,8000,8496,9024,9544,10136,10984,11640,12328,13048,13800,14496,15312,15936,16816,17728,18672,
            // Nivel M (ec=0)
            152,272,440,640,864,1088,1248,1552,1856,2192,2592,2960,3424,3688,4184,4712,5176,5768,6360,6888,7456,8048,8752,9392,10208,10960,11744,12248,13048,13880,14744,15640,16568,17528,18448,19472,20528,21616,22496,23648,
            // Nivel Q (ec=3)
            72,128,208,288,368,480,528,688,800,976,1120,1264,1440,1576,1784,2024,2264,2504,2728,3080,3248,3536,3712,4112,4304,4768,5024,5288,5608,5960,6344,6760,7208,7688,7888,8432,8768,9136,9776,10208,
            // Nivel H (ec=2) - USAMOS ESTE
            104,176,272,384,496,608,704,880,1056,1232,1440,1648,1952,2088,2360,2600,2936,3176,3560,3880,4096,4544,4912,5312,5744,6032,6464,6968,7288,7880,8264,8920,9368,9848,10288,10832,11408,12016,12656,13328
        );
        
        // Auto-seleccionar versión
        if (!$qrcode_version) {
            $i = 1 + 40 * $ec; // Índice inicial para nivel H (ec=2): 1 + 40*2 = 81
            $j = $i + 39; // Índice final: 81 + 39 = 120
            $qrcode_version = 1;
            while ($i <= $j) {
                if (($max_data_bits_array[$i]) >= $total_data_bits + $codeword_num_plus[$qrcode_version]) {
                    $max_data_bits = $max_data_bits_array[$i];
                    break;
                }
                $i++;
                $qrcode_version++;
            }
        } else {
            $max_data_bits = $max_data_bits_array[$qrcode_version + 40 * $ec];
        }
        
        if ($qrcode_version > $version_ul) {
            throw new \Exception("Versión QR demasiado grande: " . $qrcode_version);
        }
        
        $total_data_bits += $codeword_num_plus[$qrcode_version];
        $data_bits[$codeword_num_counter_value] += $codeword_num_plus[$qrcode_version];
        
        $max_codewords_array = array(0,26,44,70,100,134,172,196,242,292,346,404,466,532,581,655,733,815,901,991,1085,1156,1258,1364,1474,1588,1706,1828,1921,2051,2185,2323,2465,2611,2761,2876,3034,3196,3362,3532,3706);
        $max_codewords = $max_codewords_array[$qrcode_version];
        $max_modules_1side = 17 + ($qrcode_version << 2);
        
        $matrix_remain_bit = array(0,0,7,7,7,7,7,0,0,0,0,0,0,0,3,3,3,3,3,3,3,4,4,4,4,4,4,4,3,3,3,3,3,3,3,0,0,0,0,0,0);
        
        // Leer archivos de datos ECC
        $byte_num = $matrix_remain_bit[$qrcode_version] + ($max_codewords << 3);
        $filename = $path . "/qrv" . $qrcode_version . "_" . $ec . ".dat";
        
        if (!file_exists($filename)) {
            throw new \Exception("Archivo de datos ECC no encontrado: " . $filename);
        }
        
        $fp1 = fopen($filename, "rb");
        if (!$fp1) {
            throw new \Exception("No se pudo abrir archivo ECC: " . $filename);
        }
        
        $matx = fread($fp1, $byte_num);
        $maty = fread($fp1, $byte_num);
        $masks = fread($fp1, $byte_num);
        $fi_x = fread($fp1, 15);
        $fi_y = fread($fp1, 15);
        $rs_ecc_codewords = ord(fread($fp1, 1));
        $rso = fread($fp1, 128);
        fclose($fp1);
        
        // Leer archivo de código Reed-Solomon
        $filename_rs = $path . "/rsc" . $rs_ecc_codewords . ".dat";
        if (!file_exists($filename_rs)) {
            throw new \Exception("Archivo RS no encontrado: " . $filename_rs);
        }
        
        $fp0 = fopen($filename_rs, "rb");
        if (!$fp0) {
            throw new \Exception("No se pudo abrir archivo RS: " . $filename_rs);
        }
        
        $rs_cal_table_array = array();
        for ($i = 0; $i < 256; $i++) {
            $rs_cal_table_array[$i] = fread($fp0, $rs_ecc_codewords);
        }
        fclose($fp0);
        
        // Set terminator
        if ($total_data_bits <= $max_data_bits - 4) {
            $data_value[$data_counter] = 0;
            $data_bits[$data_counter] = 4;
        } else {
            if ($total_data_bits < $max_data_bits) {
                $data_value[$data_counter] = 0;
                $data_bits[$data_counter] = $max_data_bits - $total_data_bits;
            } else {
                if ($total_data_bits > $max_data_bits) {
                    throw new \Exception("Overflow error - total_data_bits=$total_data_bits, max_data_bits=$max_data_bits");
                }
            }
        }
        
        // Convertir datos a codewords (simplificado - necesitaría la lógica completa)
        // Por ahora, usaré una aproximación más simple copiando la lógica esencial
        
        // Leer el resto del código de qr_img.php sería muy extenso
        // Voy a usar un enfoque híbrido: generar la matriz del QR usando los datos ECC
        // y luego renderizar la imagen
        
        // Desempaquetar matrices
        $matrix_x_array = unpack("C*", $matx);
        $matrix_y_array = unpack("C*", $maty);
        $mask_array = unpack("C*", $masks);
        $rs_block_order = unpack("C*", $rso);
        
        $format_information_x2 = unpack("C*", $fi_x);
        $format_information_y2 = unpack("C*", $fi_y);
        
        $format_information_x1 = array(0,1,2,3,4,5,7,8,8,8,8,8,8,8,8);
        $format_information_y1 = array(8,8,8,8,8,8,8,8,7,5,4,3,2,1,0);
        
        $max_data_codewords = ($max_data_bits >> 3);
        
        // Convertir datos a codewords (buffer) - copiado exactamente del original
        $i = 0;
        $codewords_counter = 0;
        $codewords[0] = 0; // Inicializar el primer elemento
        $remaining_bits = 8;
        
        while ($i <= $data_counter) {
            $buffer = @$data_value[$i];
            $buffer_bits = @$data_bits[$i];
            
            $flag = 1;
            while ($flag) {
                if ($remaining_bits > $buffer_bits) {
                    $codewords[$codewords_counter] = ((@$codewords[$codewords_counter] << $buffer_bits) | $buffer);
                    $remaining_bits -= $buffer_bits;
                    $flag = 0;
                } else {
                    $buffer_bits -= $remaining_bits;
                    $codewords[$codewords_counter] = (($codewords[$codewords_counter] << $remaining_bits) | ($buffer >> $buffer_bits));
                    
                    if ($buffer_bits == 0) {
                        $flag = 0;
                    } else {
                        $buffer = ($buffer & ((1 << $buffer_bits) - 1));
                        $flag = 1;
                    }
                    
                    $codewords_counter++;
                    if ($codewords_counter < $max_data_codewords - 1) {
                        $codewords[$codewords_counter] = 0;
                    }
                    $remaining_bits = 8;
                }
            }
            $i++;
        }
        
        if ($remaining_bits != 8) {
            $codewords[$codewords_counter] = $codewords[$codewords_counter] << $remaining_bits;
        } else {
            $codewords_counter--;
        }
        
        // Set padding character
        if ($codewords_counter < $max_data_codewords - 1) {
            $flag = 1;
            while ($codewords_counter < $max_data_codewords - 1) {
                $codewords_counter++;
                if ($flag == 1) {
                    $codewords[$codewords_counter] = 236;
                } else {
                    $codewords[$codewords_counter] = 17;
                }
                $flag = $flag * (-1);
            }
        }
        
        // RS-ECC prepare
        $i = 0;
        $j = 0;
        $rs_block_number = 0;
        $rs_temp = array();
        $rs_temp[0] = "";
        while ($i < $max_data_codewords) {
            $rs_temp[$rs_block_number] .= chr($codewords[$i]);
            $j++;
            if ($j >= $rs_block_order[$rs_block_number + 1] - $rs_ecc_codewords) {
                $j = 0;
                $rs_block_number++;
                $rs_temp[$rs_block_number] = "";
            }
            $i++;
        }
        
        // RS-ECC main (copiado exactamente del original)
        $rs_block_number = 0;
        $rs_block_order_num = count($rs_block_order);
        
        while ($rs_block_number < $rs_block_order_num) {
            $rs_codewords = $rs_block_order[$rs_block_number + 1];
            $rs_data_codewords = $rs_codewords - $rs_ecc_codewords;
            $rstemp = $rs_temp[$rs_block_number] . str_repeat(chr(0), $rs_ecc_codewords);
            $padding_data = str_repeat(chr(0), $rs_data_codewords);
            $j = $rs_data_codewords;
            while ($j > 0) {
                $first = ord(substr($rstemp, 0, 1));
                if ($first) {
                    $left_chr = substr($rstemp, 1);
                    $cal = $rs_cal_table_array[$first] . $padding_data;
                    $rstemp = $left_chr ^ $cal;
                } else {
                    $rstemp = substr($rstemp, 1);
                }
                $j--;
            }
            // Agregar los codewords ECC al array de codewords (no reemplazar)
            $codewords = array_merge($codewords, unpack("C*", $rstemp));
            $rs_block_number++;
        }
        
        // Flash matrix (inicializar matriz)
        $matrix_content = array_fill(0, $max_modules_1side, array_fill(0, $max_modules_1side, 0));
        
        // Attach data (colocar datos en matriz)
        $i = 0;
        while ($i < $max_codewords) {
            if (!isset($codewords[$i])) {
                throw new \Exception("Codeword index $i no existe. max_codewords=$max_codewords, count(codewords)=" . count($codewords));
            }
            $codeword_i = $codewords[$i];
            $j = 8;
            while ($j >= 1) {
                $codeword_bits_number = ($i << 3) + $j;
                if (!isset($matrix_x_array[$codeword_bits_number]) || !isset($matrix_y_array[$codeword_bits_number]) || !isset($mask_array[$codeword_bits_number])) {
                    throw new \Exception("Índice $codeword_bits_number no existe en arrays de matriz. i=$i, j=$j, max_codewords=$max_codewords");
                }
                $matrix_content[$matrix_x_array[$codeword_bits_number]][$matrix_y_array[$codeword_bits_number]] = ((255 * ($codeword_i & 1)) ^ $mask_array[$codeword_bits_number]);
                $codeword_i = $codeword_i >> 1;
                $j--;
            }
            $i++;
        }
        
        $matrix_remain = $matrix_remain_bit[$qrcode_version];
        while ($matrix_remain) {
            $remain_bit_temp = $matrix_remain + ($max_codewords << 3);
            if (!isset($matrix_x_array[$remain_bit_temp]) || !isset($matrix_y_array[$remain_bit_temp]) || !isset($mask_array[$remain_bit_temp])) {
                throw new \Exception("Índice $remain_bit_temp no existe en arrays de matriz para matrix_remain. matrix_remain=$matrix_remain, max_codewords=$max_codewords");
            }
            $matrix_content[$matrix_x_array[$remain_bit_temp]][$matrix_y_array[$remain_bit_temp]] = (255 ^ $mask_array[$remain_bit_temp]);
            $matrix_remain--;
        }
        
        // Mask select (seleccionar mejor máscara)
        $min_demerit_score = 0;
        $hor_master = "";
        $ver_master = "";
        for ($k = 0; $k < $max_modules_1side; $k++) {
            for ($l = 0; $l < $max_modules_1side; $l++) {
                $hor_master .= chr($matrix_content[$l][$k]);
                $ver_master .= chr($matrix_content[$k][$l]);
            }
        }
        
        $all_matrix = $max_modules_1side * $max_modules_1side;
        for ($i = 0; $i < 8; $i++) {
            $demerit_n1 = 0;
            $ptn_temp = array();
            $bit = 1 << $i;
            $bit_r = (~$bit) & 255;
            $bit_mask = str_repeat(chr($bit), $all_matrix);
            $hor = $hor_master & $bit_mask;
            $ver = $ver_master & $bit_mask;
            
            $ver_shift1 = $ver . str_repeat(chr(170), $max_modules_1side);
            $ver_shift2 = str_repeat(chr(170), $max_modules_1side) . $ver;
            $ver_shift1_0 = $ver . str_repeat(chr(0), $max_modules_1side);
            $ver_shift2_0 = str_repeat(chr(0), $max_modules_1side) . $ver;
            $ver_or = chunk_split(~($ver_shift1 | $ver_shift2), $max_modules_1side, chr(170));
            $ver_and = chunk_split(~($ver_shift1_0 & $ver_shift2_0), $max_modules_1side, chr(170));
            
            $hor = chunk_split(~$hor, $max_modules_1side, chr(170));
            $ver = chunk_split(~$ver, $max_modules_1side, chr(170));
            $hor = $hor . chr(170) . $ver;
            
            $n1_search = "/" . str_repeat(chr(255), 5) . "+|" . str_repeat(chr($bit_r), 5) . "+/";
            $n3_search = chr($bit_r) . chr(255) . chr($bit_r) . chr($bit_r) . chr($bit_r) . chr(255) . chr($bit_r);
            
            $demerit_n3 = substr_count($hor, $n3_search) * 40;
            $demerit_n4 = floor(abs(((100 * (substr_count($ver, chr($bit_r)) / ($byte_num))) - 50) / 5)) * 10;
            
            $n2_search1 = "/" . chr($bit_r) . chr($bit_r) . "+/";
            $n2_search2 = "/" . chr(255) . chr(255) . "+/";
            $demerit_n2 = 0;
            preg_match_all($n2_search1, $ver_and, $ptn_temp);
            foreach ($ptn_temp[0] as $str_temp) {
                $demerit_n2 += (strlen($str_temp) - 1);
            }
            $ptn_temp = array();
            preg_match_all($n2_search2, $ver_or, $ptn_temp);
            foreach ($ptn_temp[0] as $str_temp) {
                $demerit_n2 += (strlen($str_temp) - 1);
            }
            $demerit_n2 *= 3;
            
            $ptn_temp = array();
            preg_match_all($n1_search, $hor, $ptn_temp);
            foreach ($ptn_temp[0] as $str_temp) {
                $demerit_n1 += (strlen($str_temp) - 2);
            }
            
            $demerit_score = $demerit_n1 + $demerit_n2 + $demerit_n3 + $demerit_n4;
            
            if ($demerit_score <= $min_demerit_score || $i == 0) {
                $mask_number = $i;
                $min_demerit_score = $demerit_score;
            }
        }
        
        $mask_content = 1 << $mask_number;
        
        // Format information
        $format_information_value = (($ec << 3) | $mask_number);
        $format_information_array = array(
            "101010000010010","101000100100101","101111001111100","101101101001011",
            "100010111111001","100000011001110","100111110010111","100101010100000",
            "111011111000100","111001011110011","111110110101010","111100010011101",
            "110011000101111","110001100011000","110110001000001","110100101110110",
            "001011010001001","001001110111110","001110011100111","001100111010000",
            "000011101100010","000001001010101","000110100001100","000100000111011",
            "011010101011111","011000001101000","011111100110001","011101000000110",
            "010010010110100","010000110000011","010111011011010","010101111101101"
        );
        
        for ($i = 0; $i < 15; $i++) {
            $content = (int)substr($format_information_array[$format_information_value], $i, 1);
            $matrix_content[$format_information_x1[$i]][$format_information_y1[$i]] = $content * 255;
            $matrix_content[$format_information_x2[$i + 1]][$format_information_y2[$i + 1]] = $content * 255;
        }
        
        // Crear imagen
        $mib = $max_modules_1side + 8;
        $qrcode_image_size = $mib * $qrcode_module_size;
        if ($qrcode_image_size > 1480) {
            throw new \Exception("Imagen QR demasiado grande: " . $qrcode_image_size);
        }
        
        // Usar imagecreatetruecolor para soportar colores completos (no solo 256 colores)
        $output_image = imagecreatetruecolor($qrcode_image_size, $qrcode_image_size);
        
        // Rellenar fondo blanco
        $blanco = imagecolorallocate($output_image, 255, 255, 255);
        imagefill($output_image, 0, 0, $blanco);
        
        $image_path_file = $image_path . "/qrv" . $qrcode_version . ".png";
        
        if (!file_exists($image_path_file)) {
            imagedestroy($output_image);
            throw new \Exception("Archivo de imagen base no encontrado: " . $image_path_file);
        }
        
        $base_image = self::ensureTruecolor(ImageCreateFromPNG($image_path_file));
        
        $col[1] = imagecolorallocate($base_image, 0, 0, 0);
        $col[0] = imagecolorallocate($base_image, 255, 255, 255);
        
        $mxe = 4 + $max_modules_1side;
        for ($i = 4, $ii = 0; $i < $mxe; $i++, $ii++) {
            for ($j = 4, $jj = 0; $j < $mxe; $j++, $jj++) {
                if ($matrix_content[$ii][$jj] & $mask_content) {
                    imagesetpixel($base_image, $i, $j, $col[1]);
                }
            }
        }
        
        // Usar imagecopyresampled en lugar de ImageCopyResized para mejor calidad
        imagecopyresampled($output_image, $base_image, 0, 0, 0, 0, $qrcode_image_size, $qrcode_image_size, $mib, $mib);
        imagedestroy($base_image);
        
        $rutaPngFinal = __DOCROOT__ . __SUBDIRECTORY__ . "/Presentacion/genera_dcmnto/tempQR/" . $qrcode_expe_string . ".png";
        
        // Trabajar directamente con $output_image (evitar guardar y recargar)
        $destino = $output_image;
        
        // Cargar y procesar el logo con máxima calidad
        $rutaLogo = __DOCROOT__ . __SUBDIRECTORY__ . "/Presentacion/asset/image/logos/logoon.png";
        
        if (file_exists($rutaLogo)) {
            $origen = @imagecreatefrompng($rutaLogo);
            if ($origen) {
                $qrSize = imagesx($destino);
                $logoW = imagesx($origen);
                $logoH = imagesy($origen);
                
                // Tamaño del logo: máximo 25% del QR para mejor visibilidad y menos borrosidad
                $logoMaxSize = (int)($qrSize * 0.25);
                
                // Mantener tamaño original si cabe, sino redimensionar manteniendo proporción
                if ($logoW <= $logoMaxSize && $logoH <= $logoMaxSize) {
                    $logoWidth = $logoW;
                    $logoHeight = $logoH;
                    $necesitaRedimensionar = false;
                } else {
                    $aspectRatio = $logoW / $logoH;
                    if ($logoW > $logoH) {
                        $logoWidth = $logoMaxSize;
                        $logoHeight = (int)($logoMaxSize / $aspectRatio);
                    } else {
                        $logoHeight = $logoMaxSize;
                        $logoWidth = (int)($logoMaxSize * $aspectRatio);
                    }
                    $necesitaRedimensionar = true;
                }
                
                // Centrar el logo
                $x = (int)(($qrSize - $logoWidth) / 2);
                $y = (int)(($qrSize - $logoHeight) / 2);
                
                // Si necesita redimensionar, usar algoritmo de alta calidad con múltiples pasos
                if ($necesitaRedimensionar) {
                    $logoResized = self::createTransparentImage($logoWidth, $logoHeight);
                    
                    // Redimensionamiento de alta calidad: usar múltiples pasos intermedios para mejor calidad
                    $reductionRatio = min($logoW / $logoWidth, $logoH / $logoHeight);
                    
                    // Si la reducción es muy grande (>4x), usar múltiples pasos intermedios
                    if ($reductionRatio > 4) {
                        // Calcular número de pasos: uno cada vez que se reduce a la mitad
                        $numPasos = (int)ceil(log($reductionRatio, 2));
                        $pasoActual = $origen;
                        $anchoActual = $logoW;
                        $altoActual = $logoH;
                        
                        for ($paso = 1; $paso < $numPasos; $paso++) {
                            $anchoSiguiente = max((int)($anchoActual / 2), $logoWidth);
                            $altoSiguiente = max((int)($altoActual / 2), $logoHeight);
                            
                            // Mantener proporción
                            $aspectRatioActual = $anchoActual / $altoActual;
                            if ($anchoSiguiente / $altoSiguiente > $aspectRatioActual) {
                                $altoSiguiente = (int)($anchoSiguiente / $aspectRatioActual);
                            } else {
                                $anchoSiguiente = (int)($altoSiguiente * $aspectRatioActual);
                            }
                            
                            $intermediate = self::createTransparentImage($anchoSiguiente, $altoSiguiente);
                            
                            imagecopyresampled($intermediate, $pasoActual, 0, 0, 0, 0, $anchoSiguiente, $altoSiguiente, $anchoActual, $altoActual);
                            
                            if ($pasoActual !== $origen) {
                                imagedestroy($pasoActual);
                            }
                            
                            $pasoActual = $intermediate;
                            $anchoActual = $anchoSiguiente;
                            $altoActual = $altoSiguiente;
                        }
                        
                        // Último paso: redimensionar desde el último intermedio al tamaño final
                        imagecopyresampled($logoResized, $pasoActual, 0, 0, 0, 0, $logoWidth, $logoHeight, $anchoActual, $altoActual);
                        
                        if ($pasoActual !== $origen) {
                            imagedestroy($pasoActual);
                        }
                    } elseif ($reductionRatio > 2) {
                        // Si la reducción es mayor a 2x, usar un paso intermedio
                        $intermediateW = (int)($logoW / sqrt($reductionRatio));
                        $intermediateH = (int)($logoH / sqrt($reductionRatio));
                        $intermediate = self::createTransparentImage($intermediateW, $intermediateH);
                        // Primer paso: redimensionar a tamaño intermedio
                        imagecopyresampled($intermediate, $origen, 0, 0, 0, 0, $intermediateW, $intermediateH, $logoW, $logoH);
                        // Segundo paso: redimensionar desde intermedio a tamaño final
                        imagecopyresampled($logoResized, $intermediate, 0, 0, 0, 0, $logoWidth, $logoHeight, $intermediateW, $intermediateH);
                        imagedestroy($intermediate);
                    } else {
                        // Redimensionar directamente si la reducción es pequeña
                        imagecopyresampled($logoResized, $origen, 0, 0, 0, 0, $logoWidth, $logoHeight, $logoW, $logoH);
                    }
                    $logoFinal = $logoResized;
                } else {
                    // Usar logo original sin redimensionar para máxima nitidez
                    $logoFinal = $origen;
                }
                
                // Agregar fondo blanco detrás del logo
                $blancoLogo = imagecolorallocate($destino, 255, 255, 255);
                $padding = 8;
                imagefilledrectangle($destino, $x - $padding, $y - $padding, 
                    $x + $logoWidth + $padding, $y + $logoHeight + $padding, $blancoLogo);
                
                // Copiar el logo preservando colores y transparencia
                imagealphablending($destino, true);
                imagesavealpha($destino, true);
                // Usar imagecopyresampled para preservar mejor los colores (aunque sea mismo tamaño)
                imagecopyresampled($destino, $logoFinal, $x, $y, 0, 0, $logoWidth, $logoHeight, imagesx($logoFinal), imagesy($logoFinal));
                
                // Limpiar memoria
                if ($necesitaRedimensionar) {
                    imagedestroy($logoResized);
                }
                imagedestroy($origen);
            }
        }
        
        // Asegurar que el directorio existe antes de guardar
        $dirPng = dirname($rutaPngFinal);
        if (!is_dir($dirPng) && !@mkdir($dirPng, 0775, true) && !is_dir($dirPng)) {
            imagedestroy($destino);
            throw new \Exception("No se pudo crear directorio: " . $dirPng);
        }
        
        // Guardar el PNG final
        $guardado = @imagepng($destino, $rutaPngFinal, 9);
        imagedestroy($destino);
        
        if (!$guardado || !file_exists($rutaPngFinal)) {
            throw new \Exception("No se pudo guardar el PNG. Ruta: " . $rutaPngFinal);
        }
        
        return true;
    }
}
