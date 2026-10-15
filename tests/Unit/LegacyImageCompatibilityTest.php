<?php

declare(strict_types=1);

namespace Tests\Unit;

use GdImage;
use ReflectionMethod;
use Tests\TestCase;

class LegacyImageCompatibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once app_path('Services/mod_gp/omaped/Generar_QR_OMAPED.php');
    }

    public function test_conversion_de_paleta_conserva_dimensiones_y_color_en_php_84(): void
    {
        $source = imagecreate(12, 15);
        imagecolorallocate($source, 10, 80, 160);
        $method = new ReflectionMethod(\Generar_QR_OMAPED::class, 'ensureTruecolor');
        $image = $method->invoke(null, $source);
        $this->assertInstanceOf(GdImage::class, $image);
        $this->assertTrue(imageistruecolor($image));
        $this->assertSame(12, imagesx($image));
        $this->assertSame(15, imagesy($image));
        $color = imagecolorsforindex($image, imagecolorat($image, 0, 0));
        $this->assertSame([10, 80, 160], [$color['red'], $color['green'], $color['blue']]);
        imagedestroy($image);
    }

    public function test_imagen_transparente_y_truecolor_no_pierde_alpha(): void
    {
        $method = new ReflectionMethod(\Generar_QR_OMAPED::class, 'createTransparentImage');
        $image = $method->invoke(null, 10, 8);
        $this->assertInstanceOf(GdImage::class, $image);
        $color = imagecolorsforindex($image, imagecolorat($image, 0, 0));
        $this->assertSame(127, $color['alpha']);
        $convert = new ReflectionMethod(\Generar_QR_OMAPED::class, 'ensureTruecolor');
        $this->assertSame($image, $convert->invoke(null, $image));
        imagedestroy($image);
    }
}
