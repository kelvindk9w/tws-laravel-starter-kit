// Entrada de build das IMAGENS da demonstração (as telas do kit mostradas na
// landing e na vitrine). Nenhuma página carrega este arquivo: ele existe para
// o Vite copiar as imagens para public/build e pô-las no manifesto, de onde
// as views as pedem por Vite::asset('vendor/twstec/kit-demo/resources/img/…').
// Sem a demo instalada, a entrada não existe e nenhuma imagem vai para o build.
// O mapa é exportado para o bundler não descartar a importação (uma entrada
// sem uso visível seria removida inteira).
export default import.meta.glob('../img/landing/*.webp', { eager: true, query: '?url', import: 'default' });
