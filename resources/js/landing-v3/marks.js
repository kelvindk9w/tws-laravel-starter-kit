// Marcas das tecnologias — RASTERIZAÇÃO, não desenho.
//
// As marcas são desenhadas UMA vez, em SVG inline, no Blade
// (resources/views/landing-v3/tech-mark.blade.php): é o que a página mostra
// quando não há WebGL. O 3D não redesenha nada — ele LEVANTA o mesmo SVG do
// DOM e o transforma em textura. Uma fonte de verdade: mexer no traço do
// Blade muda o fallback e o objeto 3D juntos.

/**
 * Serializa um <svg> do documento em textura de canvas.
 *
 * @param {SVGElement} svg  o próprio elemento da página
 * @param {number} size     lado da textura em px (potência de 2)
 * @param {string} color    cor do traço (a marca é monoline)
 * @returns {Promise<HTMLCanvasElement>}
 */
export async function markToCanvas(svg, size, color) {
    const clone = svg.cloneNode(true);
    clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    clone.setAttribute('width', String(size));
    clone.setAttribute('height', String(size));
    clone.removeAttribute('class');
    clone.setAttribute('stroke', color);
    clone.setAttribute('fill', 'none');

    // `currentColor` não existe fora do documento: resolvido acima no <svg>.
    for (const node of clone.querySelectorAll('[stroke="currentColor"]')) {
        node.setAttribute('stroke', color);
    }

    const source = new XMLSerializer().serializeToString(clone);
    const image = new Image();
    image.src = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(source)}`;
    await image.decode();

    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const context = canvas.getContext('2d');
    // Margem interna: a marca não pode encostar na borda da face de vidro.
    const pad = size * 0.18;
    context.drawImage(image, pad, pad, size - pad * 2, size - pad * 2);

    return canvas;
}
