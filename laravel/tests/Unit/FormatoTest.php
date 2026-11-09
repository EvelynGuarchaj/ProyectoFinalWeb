<?php

namespace Tests\Unit;

use App\Support\Formato;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Spec 04, CA11. Los mismos ejemplos de la especificación que usa util.js.
 */
class FormatoTest extends TestCase
{
    public function test_quetzales(): void
    {
        $this->assertSame('Q1,200.00', Formato::quetzales('1200.00'));
        $this->assertSame('Q9,250.00', Formato::quetzales(9250));
        $this->assertSame('Q2,512.88', Formato::quetzales('2512.88'));
        $this->assertSame('Q150.00', Formato::quetzales(150.0));
        $this->assertSame('Q0.00', Formato::quetzales(0));
        $this->assertSame('Q0.00', Formato::quetzales(null));
        $this->assertSame('Q99,999,999.99', Formato::quetzales('99999999.99'));
    }

    public function test_fecha_gt_desde_cadena_utc(): void
    {
        // Cruza la medianoche: 03:30 UTC del 1 de noviembre es 21:30 del 31 de octubre en Guatemala.
        $this->assertSame('31 oct 2026, 21:30', Formato::fechaGT('2026-11-01 03:30:00'));
        $this->assertSame('5 oct 2026, 10:00', Formato::fechaGT('2026-10-05 16:00:00'));
        $this->assertSame('1 ene 2026, 00:00', Formato::fechaGT('2026-01-01 06:00:00'));
    }

    public function test_fecha_gt_desde_carbon(): void
    {
        $fecha = CarbonImmutable::parse('2026-11-01 03:30:00', 'UTC');

        $this->assertSame('31 oct 2026, 21:30', Formato::fechaGT($fecha));
    }

    /** Spec 09, paso 1: título del mes en la página Mi equipo. */
    public function test_mes_en_palabras(): void
    {
        $this->assertSame('enero 2026', Formato::mesEnPalabras('2026-01'));
        $this->assertSame('octubre 2026', Formato::mesEnPalabras('2026-10'));
        $this->assertSame('diciembre 2025', Formato::mesEnPalabras('2025-12'));
    }

    public function test_fecha_gt_vacia(): void
    {
        $this->assertSame('—', Formato::fechaGT(null));
        $this->assertSame('—', Formato::fechaGT(''));
    }
}
