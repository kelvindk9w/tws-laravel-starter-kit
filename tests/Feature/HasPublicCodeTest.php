<?php

declare(strict_types=1);

use App\Core\Identifiers\HasPublicCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Testes do código público legível (ADR-010): PREFIXO-XXXXXX, sem ambiguidade,
// unicidade garantida por constraint UNIQUE + retry de colisão.

class PublicCodeProbe extends Model
{
    use HasPublicCode;

    protected const PUBLIC_CODE_PREFIX = 'TST';

    protected $table = 'public_code_probes';

    protected $fillable = ['codigo_publico'];

    public $timestamps = false;
}

beforeEach(function () {
    Schema::create('public_code_probes', function (Blueprint $table) {
        $table->id();
        $table->string('codigo_publico')->unique();
    });
});

it('gera código no formato PREFIXO-XXXXXX com alfabeto sem ambiguidade', function () {
    $probe = PublicCodeProbe::createWithPublicCodeRetry([]);

    expect($probe->codigo_publico)->toMatch('/^TST-[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{6}$/');
});

it('tenta de novo quando o código colide com a constraint UNIQUE', function () {
    // Força colisão: fixa o primeiro código e gera o mesmo no segundo insert.
    $codigo = 'TST-ABC234';
    PublicCodeProbe::create(['codigo_publico' => $codigo]);

    // Pré-atribui o código duplicado; o retry deve regenerar e persistir.
    $probe = PublicCodeProbe::createWithPublicCodeRetry(['codigo_publico' => $codigo]);

    expect($probe->codigo_publico)
        ->not->toBe($codigo)
        ->toMatch('/^TST-[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{6}$/');
});
