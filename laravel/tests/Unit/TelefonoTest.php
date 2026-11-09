<?php

namespace Tests\Unit;

use App\Rules\TelefonoValido;
use App\Support\Telefono;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Spec 05, CA2. Casos de la sección 10 de la especificación más los bordes.
 * Extiende Tests\TestCase solo para usar Validator con la regla; no toca la base.
 */
class TelefonoTest extends TestCase
{
    /** @return array<string, array{string, string, bool}> entrada, normalizado, ¿válido? */
    public static function casos(): array
    {
        return [
            // Sección 10
            '+502 con espacio y guion' => ['+502 5555-1234', '55551234', true],
            '502 entre paréntesis y puntos' => ['(502) 7761.2345', '77612345', true],
            '502 pegado, 11 dígitos' => ['50255551234', '55551234', true],
            'no se quita el 502' => ['5021 2345', '50212345', true],
            'empieza con 1' => ['1234-5678', '12345678', false],
            'siete dígitos' => ['5555123', '5555123', false],
            // Bordes
            '502 con 10 dígitos' => ['502 5555 123', '5025555123', false],
            '+502 y empieza con 1' => ['+502 1234 5678', '12345678', false],
            'con una letra' => ['5555-12a4', '555512a4', false],
        ];
    }

    #[DataProvider('casos')]
    public function test_normaliza_y_valida(string $entrada, string $normalizado, bool $valido): void
    {
        $this->assertSame($normalizado, Telefono::normalizar($entrada));
        $this->assertSame($valido, Telefono::esValido(Telefono::normalizar($entrada)));
    }

    #[DataProvider('casos')]
    public function test_regla_telefono_valido(string $entrada, string $normalizado, bool $valido): void
    {
        $v = Validator::make(['telefono' => $entrada], ['telefono' => [new TelefonoValido]]);

        $this->assertSame(! $valido, $v->fails());
        if (! $valido) {
            $this->assertSame('Escriba un teléfono de Guatemala de 8 dígitos.', $v->errors()->first('telefono'));
        }
    }

    public function test_regla_rechaza_lo_que_no_es_texto(): void
    {
        $this->assertTrue(Validator::make(['telefono' => 55551234], ['telefono' => [new TelefonoValido]])->fails());
    }
}
