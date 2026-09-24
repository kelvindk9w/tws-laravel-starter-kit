{{-- CTA FINAL — largura total e UM botão.

     A página inteira argumentou que a trilha já está escrita; aqui não há
     escolha a fazer, há um comando a executar. Dois botões no fim seriam
     devolver ao visitante a decisão que a página acabou de tomar por ele.

     O fundo é a tinta cheia nos dois temas: é o único bloco da página que
     não obedece ao tema, porque é o fim da trilha — a última linha do log,
     onde o papel acaba. --}}
<section class="relative overflow-hidden bg-gray-950 px-4 py-24 text-gray-100 sm:py-32" data-lv2-final>
    {{-- O MESMO campo ambiente do herói, atrás — porém mais lento, porque
         aqui a trilha está terminando, não começando. É o que fecha o ciclo:
         a página abre e encerra no mesmo material. --}}
    <canvas class="lv2-ambient lv2-ambient-final" data-lv2-ambient-final aria-hidden="true"></canvas>

    {{-- Fio âmbar que atravessa o bloco: o mesmo traço da espinha,
         terminando a página. --}}
    <span class="absolute inset-x-0 top-0 h-px origin-left bg-(--lv2-amber)" data-lv2-final-rule aria-hidden="true"></span>

    <div class="relative mx-auto max-w-6xl text-center">
        <p class="lv2-pain mx-auto max-w-xl text-gray-400">{{ __('landing_v2.cta.pain') }}</p>

        <h2 data-lv2-lines class="lv2-display mx-auto mt-6 max-w-[34rem] text-[clamp(2.25rem,7vw,4.5rem)] text-white">
            {{ __('landing_v2.cta.heading') }}
        </h2>

        <p class="mx-auto mt-6 max-w-xl text-gray-400">
            {{ __('landing_v2.cta.subtitle') }}
        </p>

        <div class="mt-10">
            <a
                href="{{ platform()->repoUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2.5 rounded-lg bg-white px-6 py-3.5 text-sm font-semibold text-gray-950 transition-colors duration-150 ease-(--ease-out) hover:bg-(--lv2-amber) active:scale-[0.99] motion-reduce:active:scale-100"
            >
                {{ __('landing_v2.cta.button') }}
                <x-ui-icon name="arrow-top-right-on-square" class="h-4 w-4" />
            </a>
        </div>
    </div>
</section>
