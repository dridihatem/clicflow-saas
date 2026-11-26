import * as THREE from 'three';

const ORANGE = 0xea580c;
const AMBER = 0xfbbf24;
const LIGHT = 0xfdba74;
const DARK = 0x120c08;

function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function createNeuralGraph(nodeCount = 48) {
    const nodes = [];
    const radius = 4.2;

    for (let i = 0; i < nodeCount; i++) {
        const phi = Math.acos(-1 + (2 * i) / nodeCount);
        const theta = Math.sqrt(nodeCount * Math.PI) * phi;
        const jitter = 0.35 + Math.random() * 0.55;
        const r = radius * (0.55 + Math.random() * 0.55) * jitter;

        nodes.push({
            position: new THREE.Vector3(
                r * Math.cos(theta) * Math.sin(phi),
                r * Math.sin(theta) * Math.sin(phi) * 0.85,
                r * Math.cos(phi),
            ),
            pulse: Math.random() * Math.PI * 2,
            speed: 0.6 + Math.random() * 1.4,
            size: 0.035 + Math.random() * 0.05,
        });
    }

    const links = [];
    for (let i = 0; i < nodes.length; i++) {
        const distances = [];
        for (let j = 0; j < nodes.length; j++) {
            if (i === j) continue;
            distances.push({ j, d: nodes[i].position.distanceTo(nodes[j].position) });
        }
        distances.sort((a, b) => a.d - b.d);
        for (const n of distances.slice(0, 3)) {
            if (n.d < 2.8) {
                const key = i < n.j ? `${i}-${n.j}` : `${n.j}-${i}`;
                if (!links.find((l) => l.key === key)) {
                    links.push({ key, a: i, b: n.j });
                }
            }
        }
    }

    return { nodes, links };
}

export function initMarketingHeroThree(mount) {
    if (!mount || mount.dataset.threeReady === '1') {
        return { destroy() {} };
    }
    mount.dataset.threeReady = '1';

    const reduced = prefersReducedMotion();
    const { nodes, links } = createNeuralGraph(reduced ? 28 : 56);

    const scene = new THREE.Scene();
    scene.fog = new THREE.FogExp2(DARK, 0.085);

    const camera = new THREE.PerspectiveCamera(48, 1, 0.1, 100);
    camera.position.set(0, 0.35, 9.2);

    const renderer = new THREE.WebGLRenderer({
        antialias: !reduced,
        alpha: true,
        powerPreference: 'high-performance',
    });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, reduced ? 1.25 : 2));
    renderer.setClearColor(0x000000, 0);
    renderer.domElement.className = 'cf-hero-canvas';
    renderer.domElement.setAttribute('aria-hidden', 'true');
    mount.appendChild(renderer.domElement);

    const root = new THREE.Group();
    scene.add(root);

    // Ambient AI core
    const coreGeo = new THREE.IcosahedronGeometry(0.85, 2);
    const coreMat = new THREE.MeshBasicMaterial({
        color: ORANGE,
        transparent: true,
        opacity: 0.22,
        wireframe: true,
    });
    const coreWire = new THREE.Mesh(coreGeo, coreMat);
    root.add(coreWire);

    const coreGlowGeo = new THREE.SphereGeometry(0.55, 32, 32);
    const coreGlowMat = new THREE.MeshBasicMaterial({
        color: AMBER,
        transparent: true,
        opacity: 0.55,
    });
    const coreGlow = new THREE.Mesh(coreGlowGeo, coreGlowMat);
    root.add(coreGlow);

    const coreRing = new THREE.Mesh(
        new THREE.TorusGeometry(1.15, 0.02, 12, 80),
        new THREE.MeshBasicMaterial({ color: LIGHT, transparent: true, opacity: 0.55 }),
    );
    coreRing.rotation.x = Math.PI / 2.4;
    root.add(coreRing);

    const coreRing2 = new THREE.Mesh(
        new THREE.TorusGeometry(1.45, 0.015, 12, 90),
        new THREE.MeshBasicMaterial({ color: ORANGE, transparent: true, opacity: 0.35 }),
    );
    coreRing2.rotation.x = Math.PI / 1.7;
    coreRing2.rotation.y = 0.4;
    root.add(coreRing2);

    // Neural nodes
    const nodeGeo = new THREE.SphereGeometry(1, 10, 10);
    const nodeMat = new THREE.MeshBasicMaterial({ color: LIGHT, transparent: true, opacity: 0.9 });
    const nodeMesh = new THREE.InstancedMesh(nodeGeo, nodeMat, nodes.length);
    const dummy = new THREE.Object3D();
    const nodeColor = new THREE.Color();
    nodes.forEach((node, i) => {
        dummy.position.copy(node.position);
        dummy.scale.setScalar(node.size);
        dummy.updateMatrix();
        nodeMesh.setMatrixAt(i, dummy.matrix);
        nodeColor.setHex(i % 3 === 0 ? AMBER : ORANGE);
        nodeMesh.setColorAt(i, nodeColor);
    });
    nodeMesh.instanceMatrix.needsUpdate = true;
    if (nodeMesh.instanceColor) nodeMesh.instanceColor.needsUpdate = true;
    root.add(nodeMesh);

    // Links
    const linkPositions = [];
    links.forEach((link) => {
        const a = nodes[link.a].position;
        const b = nodes[link.b].position;
        linkPositions.push(a.x, a.y, a.z, b.x, b.y, b.z);
    });
    const linkGeo = new THREE.BufferGeometry();
    linkGeo.setAttribute('position', new THREE.Float32BufferAttribute(linkPositions, 3));
    const linkMat = new THREE.LineBasicMaterial({
        color: ORANGE,
        transparent: true,
        opacity: 0.22,
    });
    const linkLines = new THREE.LineSegments(linkGeo, linkMat);
    root.add(linkLines);

    // Signal particles along links
    const signalCount = reduced ? 24 : 72;
    const signalGeo = new THREE.BufferGeometry();
    const signalPos = new Float32Array(signalCount * 3);
    const signalMeta = [];
    for (let i = 0; i < signalCount; i++) {
        const link = links[i % links.length];
        signalMeta.push({
            a: nodes[link.a].position,
            b: nodes[link.b].position,
            t: Math.random(),
            speed: 0.15 + Math.random() * 0.45,
        });
        signalPos[i * 3] = 0;
        signalPos[i * 3 + 1] = 0;
        signalPos[i * 3 + 2] = 0;
    }
    signalGeo.setAttribute('position', new THREE.BufferAttribute(signalPos, 3));
    const signals = new THREE.Points(
        signalGeo,
        new THREE.PointsMaterial({
            color: AMBER,
            size: reduced ? 0.06 : 0.08,
            transparent: true,
            opacity: 0.95,
            depthWrite: false,
            sizeAttenuation: true,
        }),
    );
    root.add(signals);

    // Floating star dust
    const dustCount = reduced ? 120 : 320;
    const dustGeo = new THREE.BufferGeometry();
    const dustArr = new Float32Array(dustCount * 3);
    for (let i = 0; i < dustCount; i++) {
        dustArr[i * 3] = (Math.random() - 0.5) * 18;
        dustArr[i * 3 + 1] = (Math.random() - 0.5) * 12;
        dustArr[i * 3 + 2] = (Math.random() - 0.5) * 14;
    }
    dustGeo.setAttribute('position', new THREE.BufferAttribute(dustArr, 3));
    const dust = new THREE.Points(
        dustGeo,
        new THREE.PointsMaterial({
            color: 0xffedd5,
            size: 0.025,
            transparent: true,
            opacity: 0.35,
            depthWrite: false,
        }),
    );
    scene.add(dust);

    // Soft lights for depth
    scene.add(new THREE.AmbientLight(0xffffff, 0.35));
    const key = new THREE.PointLight(ORANGE, 28, 20);
    key.position.set(2, 1.5, 4);
    scene.add(key);
    const fill = new THREE.PointLight(AMBER, 12, 18);
    fill.position.set(-3, -1, 2);
    scene.add(fill);

    let raf = 0;
    let running = true;
    let pointerX = 0;
    let pointerY = 0;
    const clock = new THREE.Clock();

    const resize = () => {
        const { clientWidth: w, clientHeight: h } = mount;
        if (!w || !h) return;
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
        renderer.setSize(w, h, false);
    };

    const onPointer = (event) => {
        const rect = mount.getBoundingClientRect();
        pointerX = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        pointerY = ((event.clientY - rect.top) / rect.height) * 2 - 1;
    };

    const animate = () => {
        if (!running) return;
        raf = requestAnimationFrame(animate);
        const t = clock.getElapsedTime();
        const motion = reduced ? 0.25 : 1;

        root.rotation.y = t * 0.12 * motion + pointerX * 0.25;
        root.rotation.x = Math.sin(t * 0.2) * 0.08 * motion - pointerY * 0.15;
        coreWire.rotation.y = -t * 0.35 * motion;
        coreWire.rotation.x = t * 0.18 * motion;
        coreGlow.scale.setScalar(1 + Math.sin(t * 2.2) * 0.08 * motion);
        coreRing.rotation.z = t * 0.55 * motion;
        coreRing2.rotation.z = -t * 0.35 * motion;

        nodes.forEach((node, i) => {
            const breathe = 1 + Math.sin(t * node.speed + node.pulse) * 0.22 * motion;
            dummy.position.copy(node.position);
            dummy.position.multiplyScalar(1 + Math.sin(t * 0.4 + node.pulse) * 0.03 * motion);
            dummy.scale.setScalar(node.size * breathe);
            dummy.updateMatrix();
            nodeMesh.setMatrixAt(i, dummy.matrix);
        });
        nodeMesh.instanceMatrix.needsUpdate = true;

        const pos = signals.geometry.attributes.position.array;
        for (let i = 0; i < signalCount; i++) {
            const s = signalMeta[i];
            s.t = (s.t + s.speed * 0.01 * motion) % 1;
            pos[i * 3] = s.a.x + (s.b.x - s.a.x) * s.t;
            pos[i * 3 + 1] = s.a.y + (s.b.y - s.a.y) * s.t;
            pos[i * 3 + 2] = s.a.z + (s.b.z - s.a.z) * s.t;
        }
        signals.geometry.attributes.position.needsUpdate = true;

        dust.rotation.y = t * 0.02 * motion;
        camera.position.x += (pointerX * 0.6 - camera.position.x) * 0.04;
        camera.position.y += (-pointerY * 0.35 + 0.35 - camera.position.y) * 0.04;
        camera.lookAt(0, 0, 0);

        renderer.render(scene, camera);
    };

    const ro = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(resize) : null;
    resize();
    ro?.observe(mount);
    window.addEventListener('pointermove', onPointer, { passive: true });

    const onVisibility = () => {
        if (document.hidden) {
            running = false;
            cancelAnimationFrame(raf);
        } else if (!running) {
            running = true;
            clock.start();
            animate();
        }
    };
    document.addEventListener('visibilitychange', onVisibility);

    animate();

    return {
        destroy() {
            running = false;
            cancelAnimationFrame(raf);
            ro?.disconnect();
            window.removeEventListener('pointermove', onPointer);
            document.removeEventListener('visibilitychange', onVisibility);
            renderer.dispose();
            coreGeo.dispose();
            coreMat.dispose();
            coreGlowGeo.dispose();
            coreGlowMat.dispose();
            nodeGeo.dispose();
            nodeMat.dispose();
            linkGeo.dispose();
            linkMat.dispose();
            signalGeo.dispose();
            signals.material.dispose();
            dustGeo.dispose();
            dust.material.dispose();
            if (renderer.domElement.parentNode === mount) {
                mount.removeChild(renderer.domElement);
            }
            delete mount.dataset.threeReady;
        },
    };
}

export function bootMarketingHeroThree() {
    const mount = document.querySelector('[data-cf-hero-three]');
    if (!mount) return;
    initMarketingHeroThree(mount);
}
