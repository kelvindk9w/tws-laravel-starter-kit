// Objetos 3D com MATERIAL DE VERDADE (vidro: transmissão, refração,
// clearcoat) flutuando sobre o céu. Carregado por import() dinâmico — o
// Three.js só chega ao navegador de quem vai realmente vê-lo.
//
// DECISÕES
// - Ambiente PROCEDURAL: o environment map é gerado em runtime a partir das
//   MESMAS variáveis CSS do céu (--sky-top/mid/low). O vidro reflete a
//   página; trocar o tema (claro ↔ escuro) reilumina os objetos sem asset
//   nenhum. Nenhuma imagem de CDN — a CSP do kit não permitiria.
// - Marca como decalque: um plano fino à frente da face, com a textura
//   levantada do SVG que a própria página já desenha (marks.js).
// - Física leve, não simulação: cada objeto tem fase, amplitude e velocidade
//   próprias. Não há solver — há três senoides e um alvo de mouse.
// - Só renderiza quando está VISÍVEL (IntersectionObserver) e para quando a
//   aba sai de foco. Um canvas girando fora de vista é bateria queimada.

import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/examples/jsm/geometries/RoundedBoxGeometry.js';
import { markToCanvas } from './marks.js';

const TAU = Math.PI * 2;

function readSkyColors(root) {
    const styles = getComputedStyle(root);
    const read = (name, fallback) => (styles.getPropertyValue(name).trim() || fallback);

    return {
        top: read('--sky-top', '#cfe4f7'),
        mid: read('--sky-mid', '#e7f1fb'),
        low: read('--sky-low', '#fbfdff'),
        ink: read('--sky-ink', '#25303f'),
    };
}

// Céu equirretangular desenhado no canvas 2D: é o que o vidro vai refletir.
// O sol (mancha clara no alto) existe para o material ter um DESTAQUE — sem
// um ponto quente, vidro fica plástico fosco.
function environmentTexture(colors, renderer) {
    const canvas = document.createElement('canvas');
    canvas.width = 512;
    canvas.height = 256;
    const context = canvas.getContext('2d');

    const gradient = context.createLinearGradient(0, 0, 0, canvas.height);
    gradient.addColorStop(0, colors.top);
    gradient.addColorStop(0.55, colors.mid);
    gradient.addColorStop(1, colors.low);
    context.fillStyle = gradient;
    context.fillRect(0, 0, canvas.width, canvas.height);

    const sun = context.createRadialGradient(150, 46, 4, 150, 46, 120);
    sun.addColorStop(0, 'rgba(255,255,255,0.95)');
    sun.addColorStop(1, 'rgba(255,255,255,0)');
    context.fillStyle = sun;
    context.fillRect(0, 0, canvas.width, canvas.height);

    // Nuvens: as mesmas manchas moles do CSS, para o reflexo ter movimento
    // de textura em vez de um degradê liso.
    context.globalAlpha = 0.5;
    for (let i = 0; i < 7; i += 1) {
        const x = (i * 97) % canvas.width;
        const y = 90 + ((i * 53) % 120);
        const cloud = context.createRadialGradient(x, y, 2, x, y, 70);
        cloud.addColorStop(0, 'rgba(255,255,255,0.8)');
        cloud.addColorStop(1, 'rgba(255,255,255,0)');
        context.fillStyle = cloud;
        context.fillRect(x - 80, y - 80, 160, 160);
    }
    context.globalAlpha = 1;

    const texture = new THREE.CanvasTexture(canvas);
    texture.mapping = THREE.EquirectangularReflectionMapping;
    texture.colorSpace = THREE.SRGBColorSpace;

    const pmrem = new THREE.PMREMGenerator(renderer);
    const environment = pmrem.fromEquirectangular(texture).texture;
    pmrem.dispose();
    texture.dispose();

    return environment;
}

/**
 * Monta a cena de um canvas.
 *
 * @param {HTMLElement} mount   contêiner com [data-sky-3d]
 * @param {Object} options
 * @param {'float'|'arc'} options.layout
 * @param {SVGElement[]} options.marks  os SVGs da própria página
 */
export async function createSky3d(mount, { layout, marks }) {
    const root = document.querySelector('.sky') ?? document.documentElement;

    const renderer = new THREE.WebGLRenderer({
        alpha: true,
        antialias: true,
        powerPreference: 'high-performance',
    });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    // Tone mapping NEUTRO, não ACES: o filmic lava justamente o que aqui é
    // informação — a cor da marca de cada tecnologia. Sob um céu azul e
    // claro, o vermelho do Laravel saía rosa.
    renderer.toneMapping = THREE.NeutralToneMapping;
    renderer.toneMappingExposure = 1;
    mount.appendChild(renderer.domElement);

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(38, 1, 0.1, 100);
    camera.position.set(0, 0, 12);

    let colors = readSkyColors(root);
    scene.environment = environmentTexture(colors, renderer);

    // Duas luzes: a chave alta produz a lasca especular no TOPO do cubo (é ela
    // que faz o material ler como esmalte, não como plástico fosco) e uma
    // frontal fraca abre a face onde mora o logo.
    const key = new THREE.DirectionalLight(0xffffff, 1.5);
    key.position.set(2.5, 7, 4);
    scene.add(key);

    const fill = new THREE.DirectionalLight(0xffffff, 0.5);
    fill.position.set(-3, -1, 6);
    scene.add(fill);

    const isDark = () => document.documentElement.classList.contains('dark');

    // Cada tecnologia tem o SEU material, na cor da marca — é o que torna oito
    // objetos distinguíveis a 40px. Esmalte, não vidro: `clearcoat` sobre um
    // corpo opaco. Transmissão aqui lavaria justamente a cor.
    const materials = [];

    function techMaterial(color) {
        const material = new THREE.MeshPhysicalMaterial({
            color: new THREE.Color(color),
            metalness: 0,
            roughness: 0.22,
            clearcoat: 1,
            clearcoatRoughness: 0.06,
            sheen: 0.15,
            sheenColor: new THREE.Color(0xffffff),
            envMapIntensity: 0.35,
            specularIntensity: 1,
        });
        materials.push(material);

        return material;
    }

    // Sombra macia projetada no céu: um disco de gradiente radial atrás e
    // abaixo do objeto. É um sprite, não um shadow map — para quatro a oito
    // objetos, uma passada extra de render custa mais do que ela vale, e o que
    // se quer aqui é PESO, não geometria de sombra.
    const shadowTexture = (() => {
        const canvas = document.createElement('canvas');
        canvas.width = 128;
        canvas.height = 128;
        const context = canvas.getContext('2d');
        const gradient = context.createRadialGradient(64, 64, 2, 64, 64, 62);
        gradient.addColorStop(0, 'rgba(12,24,72,0.5)');
        gradient.addColorStop(0.55, 'rgba(12,24,72,0.22)');
        gradient.addColorStop(1, 'rgba(12,24,72,0)');
        context.fillStyle = gradient;
        context.fillRect(0, 0, 128, 128);

        return new THREE.CanvasTexture(canvas);
    })();

    function applyTheme() {
        const dark = isDark();
        for (const material of materials) {
            material.envMapIntensity = dark ? 0.9 : 0.35;
            material.needsUpdate = true;
        }
        key.intensity = dark ? 1.3 : 1.5;
        renderer.toneMappingExposure = dark ? 1.05 : 1;
    }

    const geometry = new RoundedBoxGeometry(1.6, 1.6, 0.6, 8, 0.44);

    // No herói os objetos moram nas BORDAS (o texto é o assunto); no rodapé
    // eles formam o arco.
    // Pontos normalizados em [-1, 1]: o enquadramento é recalculado a cada
    // resize (o arco do rodapé é largo e baixo; o herói é quase quadrado).
    const anchors =
        layout === 'arc'
            ? marks.map((_, index) => {
                  const t = marks.length === 1 ? 0.5 : index / (marks.length - 1);
                  // Parábola: as pontas do arco sobem, o centro desce.
                  return { nx: (t - 0.5) * 1.76, ny: 0.08 + 0.62 * (2 * (t - 0.5)) ** 2, nz: -Math.abs(t - 0.5) * 1.2 };
              })
            : [
                  { nx: -0.9, ny: 0.42, nz: 0 },
                  { nx: 0.92, ny: 0.24, nz: -1.2 },
                  { nx: -0.94, ny: -0.44, nz: -0.8 },
                  { nx: 0.95, ny: -0.5, nz: 0.4 },
              ];

    const used = marks.slice(0, anchors.length);
    const objects = [];

    for (let index = 0; index < used.length; index += 1) {
        const svg = used[index];
        // A cor vem do CUBO que a própria página desenhou (data-sky-color no
        // Blade): uma fonte de verdade para o fallback e para o 3D.
        const cube = svg.closest('[data-sky-color]');
        const color = cube?.dataset.skyColor ?? '#6470b0';
        // A tinta do decalque também vem do Blade: branco sobre esmalte
        // escuro, quase-preto sobre esmalte claro (ver tech-mark.blade.php).
        const ink = cube?.dataset.skyInk ?? '#ffffff';

        const group = new THREE.Group();
        const box = new THREE.Mesh(geometry, techMaterial(color));
        group.add(box);

        // Sombra: atrás e abaixo, maior que o objeto e bem mais macia.
        const shadow = new THREE.Mesh(
            new THREE.PlaneGeometry(3.4, 3.4),
            new THREE.MeshBasicMaterial({ map: shadowTexture, transparent: true, depthWrite: false, opacity: 0.9 }),
        );
        shadow.position.set(0.1, -1.1, -0.9);
        group.add(shadow);

        // Logo oficial em decalque no meio da face.
        const canvas = await markToCanvas(svg, 256, ink);
        const texture = new THREE.CanvasTexture(canvas);
        texture.colorSpace = THREE.SRGBColorSpace;
        texture.anisotropy = renderer.capabilities.getMaxAnisotropy();

        const decal = new THREE.Mesh(
            new THREE.PlaneGeometry(0.98, 0.98),
            new THREE.MeshBasicMaterial({ map: texture, transparent: true, depthWrite: false, opacity: 0.95 }),
        );
        decal.position.z = 0.305;
        group.add(decal);

        group.scale.setScalar(layout === 'arc' ? 1 : 0.82);
        scene.add(group);

        // Tombo de repouso entre 10° e 20° em dois eixos: um cubo alinhado ao
        // quadro é um quadrado, e a página perde a profundidade que ele tem.
        const tiltX = (0.18 + (index % 3) * 0.045) * (index % 2 === 0 ? -1 : 1);
        const tiltY = (0.2 + (index % 2) * 0.06) * (index % 3 === 0 ? 1 : -1);

        objects.push({
            group,
            anchor: anchors[index],
            base: new THREE.Vector3(),
            phase: (index / used.length) * TAU + index * 0.7,
            bob: layout === 'arc' ? 0.16 : 0.32,
            speed: 0.32 + index * 0.045,
            tilt: layout === 'arc' ? 0.08 : 0.14,
            tiltX,
            tiltY,
            shadow,
            decalTexture: texture,
            decalGeometry: decal.geometry,
            decalMaterial: decal.material,
        });
    }

    applyTheme();

    function resize() {
        const { clientWidth: width, clientHeight: height } = mount;
        if (width === 0 || height === 0) {
            return;
        }
        renderer.setSize(width, height, false);
        camera.aspect = width / height;
        // Enquadramento constante: em telas estreitas a câmera recua para o
        // arco inteiro continuar dentro do quadro.
        camera.position.z = layout === 'arc' ? 6.5 : Math.max(11, 17 - camera.aspect * 2.4);
        camera.updateProjectionMatrix();

        // Quadro visível no plano z=0, em unidades de mundo.
        const halfHeight = Math.tan((camera.fov * Math.PI) / 360) * camera.position.z;
        const halfWidth = halfHeight * camera.aspect;

        for (const item of objects) {
            item.base.set(item.anchor.nx * halfWidth * 0.86, item.anchor.ny * halfHeight, item.anchor.nz);
            item.group.position.copy(item.base);
        }
    }

    // Progresso da CENA (0→1) enviado pelo motion.js enquanto o herói está
    // pinado: é ele que faz os objetos orbitarem em vez de só flutuarem. Sem
    // motion (reduced-motion, sem GSAP) o valor fica em 0 e a flutuação
    // sozinha continua fazendo sentido.
    let scene3dProgress = 0;
    const onScene = (event) => {
        scene3dProgress = event.detail?.progress ?? 0;
    };
    document.addEventListener('sky:scene', onScene);

    // Alvo do ponteiro: paralaxe suave, nunca perseguição direta.
    const pointer = { x: 0, y: 0, tx: 0, ty: 0 };
    function onPointerMove(event) {
        pointer.tx = (event.clientX / window.innerWidth - 0.5) * 2;
        pointer.ty = (event.clientY / window.innerHeight - 0.5) * 2;
    }

    let visible = false;
    let running = false;
    let frame = 0;
    const clock = new THREE.Clock();

    function tick() {
        if (!running) {
            return;
        }
        frame = requestAnimationFrame(tick);

        const time = clock.getElapsedTime();
        pointer.x += (pointer.tx - pointer.x) * 0.045;
        pointer.y += (pointer.ty - pointer.y) * 0.045;

        // Órbita: o raio abre com o progresso da cena e cada objeto percorre
        // meia volta. É o scroll dirigindo a câmera do mundo, não um loop.
        const orbit = layout === 'float' ? scene3dProgress : 0;

        for (const item of objects) {
            const { group, base, phase, bob, speed, tilt } = item;
            const angle = phase + orbit * Math.PI;
            const radius = orbit * 1.5;

            group.position.x = base.x + Math.cos(angle) * radius + Math.sin(time * speed * 0.7 + phase) * 0.18 + pointer.x * 0.35;
            group.position.y = base.y + Math.sin(angle) * radius * 0.6 + Math.sin(time * speed + phase) * bob - pointer.y * 0.25;
            group.position.z = base.z - orbit * 2.4;
            group.rotation.x = item.tiltX + Math.sin(time * speed * 0.8 + phase) * tilt - pointer.y * 0.12 + orbit * 0.5;
            group.rotation.y = item.tiltY + Math.sin(time * speed * 0.6 + phase * 1.3) * tilt + pointer.x * 0.18 + orbit * 1.2;
            group.rotation.z = Math.sin(time * speed * 0.45 + phase) * tilt * 0.4;

            // A sombra é do CHÃO, não do objeto: ela não acompanha o tombo.
            item.shadow.rotation.set(-group.rotation.x, -group.rotation.y, -group.rotation.z);
        }

        renderer.render(scene, camera);
    }

    function start() {
        if (running || !visible || document.hidden) {
            return;
        }
        running = true;
        clock.getDelta();
        frame = requestAnimationFrame(tick);
    }

    function stop() {
        running = false;
        cancelAnimationFrame(frame);
    }

    const observer = new IntersectionObserver(
        ([entry]) => {
            visible = entry.isIntersecting;
            visible ? start() : stop();
        },
        { rootMargin: '120px' },
    );
    observer.observe(mount);

    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(mount);
    resize();

    const onVisibility = () => (document.hidden ? stop() : start());
    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('pointermove', onPointerMove, { passive: true });

    // Troca de tema: o céu muda de cor, e o vidro tem de refletir o novo céu.
    const themeObserver = new MutationObserver(() => {
        colors = readSkyColors(root);
        scene.environment?.dispose();
        scene.environment = environmentTexture(colors, renderer);
        applyTheme();
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

    mount.dataset.skyThreeReady = 'true';

    return function destroy() {
        stop();
        observer.disconnect();
        resizeObserver.disconnect();
        themeObserver.disconnect();
        document.removeEventListener('visibilitychange', onVisibility);
        document.removeEventListener('sky:scene', onScene);
        window.removeEventListener('pointermove', onPointerMove);
        for (const item of objects) {
            item.decalTexture.dispose();
            item.decalGeometry.dispose();
            item.decalMaterial.dispose();
            item.shadow.geometry.dispose();
            item.shadow.material.dispose();
        }
        geometry.dispose();
        shadowTexture.dispose();
        for (const material of materials) {
            material.dispose();
        }
        scene.environment?.dispose();
        renderer.dispose();
        renderer.domElement.remove();
        delete mount.dataset.skyThreeReady;
    };
}
