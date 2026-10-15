<?php

declare(strict_types=1);

use App\Contracts\Services\BdCoreInterface;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['database.connections.pgsql'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'']]);
    DB::purge('pgsql');
    $this->bdCore = app(BdCoreInterface::class);
});

test('puede obtener la conexion principal pgsql', function () {
    $conexion = $this->bdCore->obtenerConexion('pgsql');
    expect($conexion)->toBeInstanceOf(\Illuminate\Database\Connection::class);
});

test('puede ejecutar consultas select de lectura', function () {
    $resultados = $this->bdCore->consultar('pgsql', 'SELECT 1 as num');

    expect($resultados)->toBeArray()
        ->and($resultados)->toHaveCount(1)
        ->and($resultados[0]['num'])->toEqual(1);
});

test('bloquea consultas que no son select', function () {
    expect(fn() => $this->bdCore->consultar('pgsql', 'INSERT INTO tabla (col) VALUES (1)'))
        ->toThrow(\InvalidArgumentException::class, 'Operación no permitida');

    expect(fn() => $this->bdCore->consultar('pgsql', 'DELETE FROM tabla'))
        ->toThrow(\InvalidArgumentException::class, 'Operación no permitida');

    expect(fn() => $this->bdCore->consultar('pgsql', 'UPDATE tabla SET col = 1'))
        ->toThrow(\InvalidArgumentException::class, 'Operación no permitida');
});

test('sanitiza cadenas de texto no utf8 a utf8', function () {
    // "Canci\xf3n" representa la palabra "Canción" en codificación Windows-1252 / ISO-8859-1
    $winString = "Canci\xf3n";

    $sanitized = $this->bdCore->sanitizarUtf8($winString);

    expect(mb_check_encoding($sanitized, 'UTF-8'))->toBeTrue()
        ->and($sanitized)->toEqual('Canción');
});

test('retorna cadena vacia en sanitizacion de nulos', function () {
    expect($this->bdCore->sanitizarUtf8(null))->toBe('');
});
