<?php

namespace Tests\Unit;

use App\Rules\NitValido;
use App\Support\Nit;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Spec 05, CA1. Casos de la sección 10 de la especificación más los bordes.
 * Extiende Tests\TestCase solo para usar Validator con la regla; no toca la base.
 */
class NitTest extends TestCase
{
    /** @return array<string, array{string, string, bool}> entrada, normalizado, ¿válido? */
    public static function casos(): array
    {
        return [
            // Sección 10
            'con espacio, guion y k minúscula' => ['452 1817-k', '4521817K', true],
            'con guion y K mayúscula' => ['4521817-K', '4521817K', true],
            'ceros a la izquierda' => ['00012345679', '12345679', true],
            'verificador incorrecto' => ['4521817-1', '45218171', false],
            // Bordes
            'verificador 0' => ['310', '310', true],
            'verificador K' => ['23K', '23K', true],
            'verificador k minúscula' => ['23k', '23K', true],
            'un carácter' => ['1', '1', false],
            'solo un cero' => ['0', '', false],
            'solo ceros' => ['000', '', false],
            'trece caracteres' => ['1234567890123', '1234567890123', false],
            'letra en el cuerpo' => ['12A45679', '12A45679', false],
            'CFX no es CF' => ['CFX', 'CFX', false],
        ];
    }

    #[DataProvider('casos')]
    public function test_normaliza_y_valida(string $entrada, string $normalizado, bool $valido): void
    {
        $this->assertSame($normalizado, Nit::normalizar($entrada));
        $this->assertSame($valido, Nit::esValido(Nit::normalizar($entrada)));
        $this->assertFalse(Nit::esConsumidorFinal($entrada));
    }

    public function test_consumidor_final(): void
    {
        $this->assertTrue(Nit::esConsumidorFinal('cf'));
        $this->assertTrue(Nit::esConsumidorFinal('CF'));
        $this->assertTrue(Nit::esConsumidorFinal('C F'));
        $this->assertSame('CF', Nit::normalizar('cf'));
        $this->assertFalse(Nit::esValido('CF'));
    }

    public function test_lo_valido_cumple_el_check_de_la_base(): void
    {
        foreach (['452 1817-k', '00012345679', '310', '23k'] as $entrada) {
            $this->assertMatchesRegularExpression('/^[1-9][0-9]*[0-9K]$/', Nit::normalizar($entrada));
        }
    }

    public function test_nits_de_la_semilla_son_validos(): void
    {
        foreach (['4521817K', '98765434', '12345679', '76543218'] as $nit) {
            $this->assertTrue(Nit::esValido($nit), $nit);
        }
    }

    #[DataProvider('casos')]
    public function test_regla_nit_valido(string $entrada, string $normalizado, bool $valido): void
    {
        $v = Validator::make(['nit' => $entrada], ['nit' => [new NitValido]]);

        $this->assertSame(! $valido, $v->fails());
        if (! $valido) {
            $this->assertSame('Escriba un NIT válido (con su dígito verificador) o CF.', $v->errors()->first('nit'));
        }
    }

    public function test_regla_acepta_cf_y_rechaza_lo_que_no_es_texto(): void
    {
        foreach (['cf', 'CF', 'C F'] as $cf) {
            $this->assertTrue(Validator::make(['nit' => $cf], ['nit' => [new NitValido]])->passes(), $cf);
        }
        $this->assertTrue(Validator::make(['nit' => ['4521817K']], ['nit' => [new NitValido]])->fails());
    }
}
