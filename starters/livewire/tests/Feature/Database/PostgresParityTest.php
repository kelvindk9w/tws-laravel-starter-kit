<?php

declare(strict_types=1);

use App\Core\Uploads\Models\Upload;
use App\Demo\Catalog\Models\Product;
use App\Demo\Filament\Resources\FormSubmissions\Pages\ListFormSubmissions;
use App\Demo\Filament\Resources\Products\Pages\ListProducts;
use App\Demo\Showcase\Models\FormSubmission;
use App\Filament\Resources\ApiKeys\Pages\ListApiKeys;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\RequestLogs\Pages\ListRequestLogs;
use App\Filament\Resources\Uploads\Pages\ListUploads;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Livewire\ApiKeys\Index as ApiKeysIndex;
use App\Livewire\Projects\Index as ProjectsIndex;
use App\Models\User;
use Livewire\Livewire;
use Twstec\Kit\Accounts\ApiKeys\Services\ApiKeyService;
use Twstec\Kit\Accounts\Tenancy\Models\Project;
use Twstec\Kit\Foundation\Logging\Enums\RequestLogStatus;
use Twstec\Kit\Foundation\Logging\Models\RequestLog;

// =============================================================================
// PARIDADE COM O POSTGRESQL (banco de produção).
//
// Tudo aqui passava no SQLite e quebrava — ou se comportava diferente — no
// PostgreSQL. O SQLite é tolerante: aceita qualquer texto numa coluna `uuid`
// e faz LIKE sem diferenciar maiúsculas. O PostgreSQL tem `uuid` NATIVO (texto
// que não é uuid = erro de sintaxe, a consulta inteira cai com 500) e LIKE que
// diferencia maiúsculas.
//
// Estes testes rodam nos dois bancos (o CI roda a suíte no PostgreSQL); em
// SQLite eles passam de qualquer jeito, o que importa é o resultado no pgsql.
// =============================================================================

const UUID_MALFORMADO = 'nao-e-um-uuid';

// -----------------------------------------------------------------------------
// uuid malformado vindo de fora = 404, nunca erro de banco
// -----------------------------------------------------------------------------

describe('uuid malformado na URL do /admin', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    });

    it('responde 404 em :dataset', function (string $caminho) {
        $this->get($caminho)->assertNotFound();
    })->with([
        'detalhe de usuário' => '/admin/users/'.UUID_MALFORMADO,
        'edição de usuário' => '/admin/users/'.UUID_MALFORMADO.'/edit',
        'detalhe de envio de formulário' => '/admin/form-submissions/'.UUID_MALFORMADO,
        'edição de produto' => '/admin/products/'.UUID_MALFORMADO.'/edit',
        'detalhe de log de requisição' => '/admin/request-logs/'.UUID_MALFORMADO,
    ]);
});

describe('uuid malformado na API', function () {
    beforeEach(function () {
        $user = User::factory()->create();
        ['api_key' => $key, 'secret_key' => $secret] = criarChave($user);
        $this->headers = headersApi($key, $secret);
    });

    it('responde 404 no envelope padrão em :dataset', function (string $metodo, string $caminho) {
        assertErroApi($this->json($metodo, $caminho, ['name' => 'X', 'project_uuids' => []], $this->headers), 404, 'not_found');
    })->with([
        'detalhe de projeto' => ['GET', '/api/v1/projects/'.UUID_MALFORMADO],
        'edição de projeto' => ['PUT', '/api/v1/projects/'.UUID_MALFORMADO],
        'remoção de projeto' => ['DELETE', '/api/v1/projects/'.UUID_MALFORMADO],
        'revogação de chave' => ['DELETE', '/api/v1/api-keys/'.UUID_MALFORMADO],
        'vínculo de projetos da chave' => ['PUT', '/api/v1/api-keys/'.UUID_MALFORMADO.'/projects'],
    ]);

    it('o service trata lista de projetos com uuid malformado como projeto inexistente', function () {
        $user = User::factory()->create();

        app(ApiKeyService::class)->resolveProjectIds($user, [UUID_MALFORMADO]);
    })->throws(InvalidArgumentException::class, 'projects');
});

describe('uuid malformado nas ações do painel (Livewire)', function () {
    it('projetos: 404 uniforme', function () {
        Livewire::actingAs(User::factory()->create())
            ->test(ProjectsIndex::class)
            ->call('startDelete', UUID_MALFORMADO)
            ->assertNotFound();
    });

    it('chaves de API: 404 uniforme', function () {
        Livewire::actingAs(User::factory()->withTransactionPassword()->create())
            ->test(ApiKeysIndex::class)
            ->call('startRevoke', UUID_MALFORMADO)
            ->assertNotFound();
    });
});

// -----------------------------------------------------------------------------
// Filtros e busca do /admin
// -----------------------------------------------------------------------------

function logDeRequisicao(string $endpoint, ?string $tenant = null): RequestLog
{
    return RequestLog::query()->create([
        'correlation_id' => (string) str()->uuid7(),
        'tenant_uuid' => $tenant,
        'method' => 'GET',
        'endpoint' => $endpoint,
        'status' => RequestLogStatus::Concluida,
    ]);
}

describe('filtros dos logs de requisição', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    });

    it('filtro de tenant com texto que não é uuid não encontra nada (e não derruba a tela)', function () {
        $log = logDeRequisicao('/api/v1/projects', (string) str()->uuid7());

        Livewire::test(ListRequestLogs::class)
            ->filterTable('tenant_uuid', ['tenant' => UUID_MALFORMADO])
            ->assertOk()
            ->assertCanNotSeeTableRecords([$log]);
    });

    it('filtro de tenant com uuid válido encontra só as linhas dele', function () {
        $tenant = (string) str()->uuid7();
        $dele = logDeRequisicao('/api/v1/projects', $tenant);
        $outro = logDeRequisicao('/api/v1/projects', (string) str()->uuid7());

        Livewire::test(ListRequestLogs::class)
            ->filterTable('tenant_uuid', ['tenant' => $tenant])
            ->assertCanSeeTableRecords([$dele])
            ->assertCanNotSeeTableRecords([$outro]);
    });

    it('filtro de endpoint não diferencia maiúsculas (igual nos dois bancos)', function () {
        $alvo = logDeRequisicao('/api/v1/Projects/Export');
        $outro = logDeRequisicao('/up');

        Livewire::test(ListRequestLogs::class)
            ->filterTable('endpoint', ['contains' => 'projects/EXPORT'])
            ->assertCanSeeTableRecords([$alvo])
            ->assertCanNotSeeTableRecords([$outro]);
    });
});

describe('busca das tabelas do /admin não diferencia maiúsculas', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create(['is_admin' => true, 'email' => 'admin-da-busca@example.com']);
        $this->actingAs($this->admin);
    });

    it('usuários', function () {
        $alvo = User::factory()->create(['email' => 'pessoa.buscada@example.com']);

        Livewire::test(ListUsers::class)
            ->searchTable('PESSOA.Buscada')
            ->assertCanSeeTableRecords([$alvo])
            ->assertCanNotSeeTableRecords([$this->admin]);
    });

    it('chaves de API', function () {
        $alvo = app(ApiKeyService::class)->create($this->admin, ['name' => 'integração do estoque'])['api_key'];
        $outra = app(ApiKeyService::class)->create($this->admin, ['name' => 'outra coisa'])['api_key'];

        Livewire::test(ListApiKeys::class)
            ->searchTable('ESTOQUE')
            ->assertCanSeeTableRecords([$alvo])
            ->assertCanNotSeeTableRecords([$outra]);
    });

    it('projetos', function () {
        $alvo = Project::createWithPublicCodeRetry(['user_id' => $this->admin->id, 'name' => 'loja virtual']);
        $outro = Project::createWithPublicCodeRetry(['user_id' => $this->admin->id, 'name' => 'outro projeto']);

        Livewire::test(ListProjects::class)
            ->searchTable('Loja VIRTUAL')
            ->assertCanSeeTableRecords([$alvo])
            ->assertCanNotSeeTableRecords([$outro]);
    });

    it('uploads', function () {
        $dados = ['user_id' => $this->admin->id, 'disk' => 'local', 'mime' => 'application/pdf', 'size' => 10, 'sha256' => hash('sha256', 'x')];
        $alvo = Upload::createWithPublicCodeRetry([...$dados, 'path' => 'docs/a.pdf', 'original_name' => 'contrato-assinado.pdf']);
        $outro = Upload::createWithPublicCodeRetry([...$dados, 'path' => 'docs/b.pdf', 'original_name' => 'recibo.pdf']);

        Livewire::test(ListUploads::class)
            ->searchTable('CONTRATO')
            ->assertCanSeeTableRecords([$alvo])
            ->assertCanNotSeeTableRecords([$outro]);
    });

    it('logs de requisição', function () {
        $alvo = logDeRequisicao('/api/v1/relatorios');
        $outro = logDeRequisicao('/up');

        Livewire::test(ListRequestLogs::class)
            ->searchTable('RELATORIOS')
            ->assertCanSeeTableRecords([$alvo])
            ->assertCanNotSeeTableRecords([$outro]);
    });

    it('envios de formulário', function () {
        $alvo = FormSubmission::factory()->create(['nickname' => 'visitante_curioso']);
        $outro = FormSubmission::factory()->create(['nickname' => 'outra_pessoa']);

        Livewire::test(ListFormSubmissions::class)
            ->searchTable('CURIOSO')
            ->assertCanSeeTableRecords([$alvo])
            ->assertCanNotSeeTableRecords([$outro]);
    })->group('demo');

    it('produtos', function () {
        $alvo = Product::factory()->create(['title' => 'cadeira ergonômica']);
        $outro = Product::factory()->create(['title' => 'mesa de jantar']);

        Livewire::test(ListProducts::class)
            ->searchTable('CADEIRA')
            ->assertCanSeeTableRecords([$alvo])
            ->assertCanNotSeeTableRecords([$outro]);
    })->group('demo');
});
