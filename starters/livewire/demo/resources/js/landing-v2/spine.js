import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

// =============================================================================
// A ESPINHA — o rastro literal, à esquerda da página.
//
// A tese da página é que o scroll é a linha do tempo. A espinha é onde isso
// deixa de ser metáfora: um fio de 1px que se preenche de âmbar conforme se
// desce, com um MARCADOR por seção e o estado do ciclo de vida ao lado —
// INICIADA no topo, CONCLUÍDA no fim. Ler a página é uma requisição chegando
// ao fim, e a espinha é o log dela.
//
// O marcador da seção em que se está PULSA (é a linha viva); os já passados
// ficam âmbar cheio (já registrados); os que faltam ficam em fio apagado.
//
// O carimbo de tempo NÃO é o relógio do visitante: é uma duração derivada do
// progresso da leitura (00:00:00 no topo, 00:04:00 no fim). Mostrar a hora
// real seria um relógio; o que se quer aqui é a duração da trilha que ele
// acabou de percorrer.
//
// Só existe a partir de 1280px (CSS): abaixo disso não há goteira livre ao
// lado da coluna de conteúdo, e um fio colado no texto é ruído.
// =============================================================================

// Duração fictícia da trilha, do topo ao rodapé.
const TOTAL_SECONDS = 240;

const pad = (value) => String(Math.floor(value)).padStart(2, '0');

export function initSpine(reduced) {
    const stamp = document.querySelector('[data-lv2-stamp]');
    const marks = [...document.querySelectorAll('[data-lv2-mark]')];
    const root = document.documentElement;

    if (!stamp) return;

    // Cada marcador é ancorado na seção que ele representa: a posição no fio
    // é a posição REAL daquela seção no documento, não um palpite em
    // porcentagem que sai do lugar quando o conteúdo muda de tamanho.
    const spine = document.querySelector('.lv2-spine');

    function place() {
        const height = document.documentElement.scrollHeight - window.innerHeight;

        marks.forEach((mark) => {
            const target = document.querySelector(mark.dataset.lv2Mark);

            if (!target || height <= 0) return;

            const top = target.getBoundingClientRect().top + window.scrollY;

            mark.style.top = `${gsap.utils.clamp(0, 100, (top / height) * 100)}%`;
        });

        // Só agora os marcadores aparecem: antes disso estavam todos em
        // top:0 e surgir de lá seria um salto visível (e contabilizado).
        spine?.classList.add('is-placed');
    }

    place();
    ScrollTrigger.addEventListener('refresh', place);
    window.addEventListener('resize', () => requestAnimationFrame(place));

    let current = -1;

    ScrollTrigger.create({
        trigger: document.body,
        start: 'top top',
        end: 'bottom bottom',
        onUpdate: (self) => {
            root.style.setProperty('--lv2-progress', `${(self.progress * 100).toFixed(2)}%`);

            const elapsed = self.progress * TOTAL_SECONDS;

            stamp.textContent = `${pad(elapsed / 3600)}:${pad((elapsed / 60) % 60)}:${pad(elapsed % 60)}`;

            // Qual marcador já foi ultrapassado.
            const scrolled = self.progress * 100;
            let active = -1;

            marks.forEach((mark, index) => {
                const at = Number.parseFloat(mark.style.top) || 0;

                if (scrolled >= at - 1) active = index;
            });

            if (active === current) return;

            current = active;

            marks.forEach((mark, index) => {
                mark.classList.toggle('is-done', index < active);
                mark.classList.toggle('is-live', index === active);
            });
        },
    });

    // A pulsação do marcador vivo é CSS (ver .lv2-mark.is-live), desligada
    // com movimento reduzido: um ponto que pisca sem parar é exatamente o
    // tipo de movimento periférico que essa preferência pede para não ter.
    if (reduced) root.classList.add('lv2-spine-still');
}
