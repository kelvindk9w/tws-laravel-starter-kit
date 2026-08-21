<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Catalog\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Vitrine de produtos da demo (30+ itens variados): preenche a listagem do
 * super admin para demonstrar paginação (10/página) e filtros na URL.
 * Fotos: URLs externas (picsum com seed fixa = imagem estável por produto).
 *
 * Rodada junto com os usuários demo — nunca em produção (DatabaseSeeder).
 */
final class ProductSeeder extends Seeder
{
    /** @var list<array{title: string, price: int, description: string}> */
    private const ITEMS = [
        ['title' => 'Teclado mecânico TWS K87', 'price' => 34990, 'description' => 'Switches hot-swap, keycaps PBT e iluminação por tecla.'],
        ['title' => 'Mouse sem fio TWS M2', 'price' => 15990, 'description' => 'Sensor 26.000 DPI, 90 h de bateria e cliques silenciosos.'],
        ['title' => 'Monitor ultrawide 34"', 'price' => 289990, 'description' => 'Painel IPS 144 Hz, curvatura 1500R e HDR400.'],
        ['title' => 'Cadeira ergonômica Flex', 'price' => 189900, 'description' => 'Apoio lombar dinâmico, mesh respirável e 4 anos de garantia.'],
        ['title' => 'Mesa ajustável Altura+', 'price' => 239900, 'description' => 'Motor duplo silencioso, 4 memórias de altura e 120 kg de carga.'],
        ['title' => 'Headset TWS H7 ANC', 'price' => 49990, 'description' => 'Cancelamento ativo de ruído e microfone destacável.'],
        ['title' => 'Webcam 4K Pro', 'price' => 89990, 'description' => 'Sensor Sony 4K, HDR e correção automática de luz.'],
        ['title' => 'Hub USB-C 10 em 1', 'price' => 32990, 'description' => 'HDMI 4K, ethernet gigabit, leitor SD e 100 W PD.'],
        ['title' => 'Notebook Stand Alumínio', 'price' => 14990, 'description' => 'Alumínio anodizado, ajuste de ângulo e base antiderrapante.'],
        ['title' => 'Luminária de monitor Bar', 'price' => 19990, 'description' => 'Luz assimétrica sem reflexo na tela, temperatura ajustável.'],
        ['title' => 'Teclado low-profile Air', 'price' => 27990, 'description' => 'Perfil baixo, tri-mode (BT/2.4G/USB) e teclas silenciosas.'],
        ['title' => 'Mousepad XXL Costurado', 'price' => 7990, 'description' => '90×40 cm, bordas costuradas e base de borracha natural.'],
        ['title' => 'Dock Thunderbolt 4', 'price' => 129990, 'description' => '3 monitores 4K, 96 W de carga e 2,5 GbE.'],
        ['title' => 'Microfone condensador Studio', 'price' => 64990, 'description' => 'Cápsula de 25 mm, shock mount e filtro pop inclusos.'],
        ['title' => 'Braço articulado de monitor', 'price' => 34900, 'description' => 'Pistão a gás, VESA 75/100 e gestão de cabos.'],
        ['title' => 'SSD NVMe 2 TB Gen4', 'price' => 99990, 'description' => 'Leitura 7.000 MB/s, DRAM cache e 5 anos de garantia.'],
        ['title' => 'Mini PC N100 16 GB', 'price' => 149990, 'description' => 'Silencioso, 16 GB RAM, 512 GB NVMe e Wi-Fi 6.'],
        ['title' => 'Roteador Wi-Fi 6 AX3000', 'price' => 54990, 'description' => 'Dual-band, 4 antenas e QoS para jogos.'],
        ['title' => 'Câmera de segurança 360°', 'price' => 32900, 'description' => 'Visão noturna colorida, detecção de movimento e app.'],
        ['title' => 'Caixa de som Bluetooth 40 W', 'price' => 45990, 'description' => 'Graves profundos, IPX7 e 24 h de bateria.'],
        ['title' => 'Carregador GaN 100 W', 'price' => 24990, 'description' => '2× USB-C + 1× USB-A, carrega notebook e celular juntos.'],
        ['title' => 'Power bank 20.000 mAh', 'price' => 29990, 'description' => 'Carga rápida 65 W e display de porcentagem.'],
        ['title' => 'Smartwatch Fit S2', 'price' => 69990, 'description' => 'GPS integrado, oxímetro e 14 dias de bateria.'],
        ['title' => 'Fone TWS Buds ANC', 'price' => 39990, 'description' => 'ANC híbrido, 6 microfones e modo transparência.'],
        ['title' => 'Kindle-like E-reader 8"', 'price' => 89900, 'description' => 'Tela e-ink 300 dpi, luz quente ajustável e 32 GB.'],
        ['title' => 'Console portátil Retro', 'price' => 54900, 'description' => 'Tela 4" IPS, 5.000 jogos clássicos e HDMI out.'],
        ['title' => 'Teclado numérico mecânico', 'price' => 12990, 'description' => 'Ideal para planilhas, switches silenciosos.'],
        ['title' => 'Suporte de celular magnético', 'price' => 6990, 'description' => 'Ímã N52, giro 360° e base adesiva.'],
        ['title' => 'Organizador de cabos Mesa', 'price' => 4990, 'description' => 'Canaleta de 40 cm com fita dupla-face 3M.'],
        ['title' => 'Ring light 12" com tripé', 'price' => 15900, 'description' => '3 temperaturas de cor e controle remoto Bluetooth.'],
        ['title' => 'Webcam privacy cover (kit 3)', 'price' => 1990, 'description' => 'Ultra fino, deslizante, não arranha a tela.'],
        ['title' => 'Mouse vertical ergonômico', 'price' => 18990, 'description' => 'Reduz tensão no punho, 2.4G + Bluetooth.'],
        ['title' => 'Monitor portátil 15,6"', 'price' => 99900, 'description' => 'USB-C único cabo, IPS Full HD e capa magnética.'],
        ['title' => 'Base refrigerada p/ notebook', 'price' => 13990, 'description' => '2 fans silenciosos e 5 níveis de inclinação.'],
        ['title' => 'Switch KVM 2 PCs 4K', 'price' => 42990, 'description' => 'Alterna teclado, mouse e monitor entre 2 computadores.'],
        ['title' => 'Teclado ergonômico split', 'price' => 59990, 'description' => 'Duas metades independentes, apoio de pulso em gel.'],
    ];

    public function run(): void
    {
        foreach (self::ITEMS as $index => $item) {
            Product::query()->firstOrCreate(
                ['title' => $item['title']],
                [
                    'price' => $item['price'],
                    'description' => $item['description'],
                    'image' => 'https://picsum.photos/seed/produto-'.($index + 1).'/600/400',
                ],
            );
        }
    }
}
