// Trophées 3D maison, en WebGL brut (aucune dépendance). Chaque carte de la vitrine du
// hero affiche une coupe propre a sa competition, construite a la main : Ligue des
// Champions (coupe argentee a grandes anses), Ligue 1 (Hexagoal : sphere doree sur lames
// et socle hexagonal), Coupe de France (coupe argentee ornee a couvercle), Trophee des
// Champions (trophee argente evase). Ce sont des representations STYLISEES reconnaissables,
// pas des reproductions exactes. Le SVG rendu en SSR reste le repli sans JavaScript ni WebGL.
//
// La rotation respecte prefers-reduced-motion. Une seule boucle d'animation pilote toutes
// les coupes et se met en pause quand aucune n'est visible a l'ecran.

// ----------------------------------------------------------------------------------------
// Accumulateur de maillage : concatene des sous-maillages (positions, normales, indices).
// ----------------------------------------------------------------------------------------
function Mesh() {
  return { pos: [], nrm: [], idx: [] };
}

function addRing(mesh, ringPos, ringNrm, prevBase) {
  // Relie deux anneaux consecutifs (prevBase -> nouvel anneau) par des quads.
  const base = mesh.pos.length / 3;
  for (let k = 0; k < ringPos.length; k++) {
    mesh.pos.push(ringPos[k][0], ringPos[k][1], ringPos[k][2]);
    mesh.nrm.push(ringNrm[k][0], ringNrm[k][1], ringNrm[k][2]);
  }
  if (prevBase >= 0) {
    const n = ringPos.length;
    for (let k = 0; k < n - 1; k++) {
      const a = prevBase + k;
      const b = a + 1;
      const c = base + k;
      const d = c + 1;
      mesh.idx.push(a, c, b, b, c, d);
    }
  }
  return base;
}

// Surface de revolution d'un profil [ [r, y], ... ] (bas vers haut) autour de l'axe Y.
function revolution(mesh, profile, radial) {
  const n = profile.length;
  const norm2 = profile.map((p, i) => {
    const prev = profile[Math.max(0, i - 1)];
    const next = profile[Math.min(n - 1, i + 1)];
    const dr = next[0] - prev[0];
    const dy = next[1] - prev[1];
    const len = Math.hypot(dy, dr) || 1;
    return [dy / len, -dr / len];
  });
  let prev = -1;
  for (let i = 0; i < n; i++) {
    const [r, y] = profile[i];
    const [nr, ny] = norm2[i];
    const rp = [];
    const rn = [];
    for (let j = 0; j <= radial; j++) {
      const t = (j / radial) * Math.PI * 2;
      const c = Math.cos(t);
      const s = Math.sin(t);
      rp.push([r * c, y, r * s]);
      rn.push([nr * c, ny, nr * s]);
    }
    prev = addRing(mesh, rp, rn, prev);
  }
}

// Sphere (pour le Hexagoal et les finials) : revolution d'un demi-cercle.
function sphere(mesh, radius, cy, lat, radial) {
  const profile = [];
  for (let i = 0; i <= lat; i++) {
    const a = -Math.PI / 2 + (i / lat) * Math.PI;
    profile.push([Math.cos(a) * radius, cy + Math.sin(a) * radius]);
  }
  revolution(mesh, profile, radial);
}

// Tube a section circulaire suivi le long d'un chemin plan (dans le plan XY, z = 0).
// Sert aux anses. Le repere de section utilise B = axe Z fixe (chemin plan).
function tube(mesh, pathPts, ringR, ringSeg) {
  const m = pathPts.length;
  let prev = -1;
  for (let i = 0; i < m; i++) {
    const p = pathPts[i];
    const a = pathPts[Math.max(0, i - 1)];
    const b = pathPts[Math.min(m - 1, i + 1)];
    let tx = b[0] - a[0];
    let ty = b[1] - a[1];
    const tl = Math.hypot(tx, ty) || 1;
    tx /= tl;
    ty /= tl;
    // N perpendiculaire a T dans le plan XY, B = axe Z.
    const nx = -ty;
    const ny = tx;
    const rp = [];
    const rn = [];
    for (let j = 0; j <= ringSeg; j++) {
      const t = (j / ringSeg) * Math.PI * 2;
      const c = Math.cos(t);
      const s = Math.sin(t);
      const ox = nx * c * ringR;
      const oy = ny * c * ringR;
      const oz = s * ringR;
      rp.push([p[0] + ox, p[1] + oy, oz]);
      rn.push([nx * c, ny * c, s]);
    }
    prev = addRing(mesh, rp, rn, prev);
  }
}

// Echantillonne une courbe Catmull-Rom passant par des points de controle 2D.
function catmull(ctrl, perSeg) {
  const pts = [];
  const P = (i) => ctrl[Math.max(0, Math.min(ctrl.length - 1, i))];
  for (let i = 0; i < ctrl.length - 1; i++) {
    const p0 = P(i - 1);
    const p1 = P(i);
    const p2 = P(i + 1);
    const p3 = P(i + 2);
    for (let s = 0; s < perSeg; s++) {
      const t = s / perSeg;
      const t2 = t * t;
      const t3 = t2 * t;
      const f = (a, b, c, d) =>
        0.5 * ((2 * b) + (-a + c) * t + (2 * a - 5 * b + 4 * c - d) * t2 + (-a + 3 * b - 3 * c + d) * t3);
      pts.push([f(p0[0], p1[0], p2[0], p3[0]), f(p0[1], p1[1], p2[1], p3[1])]);
    }
  }
  pts.push(ctrl[ctrl.length - 1]);
  return pts;
}

// Lame (boite fine) transformee : inclinaison autour de Z puis rotation autour de Y.
function blade(mesh, hx, hy, hz, tiltZ, rotY, tx, ty, tz) {
  const cz = Math.cos(tiltZ);
  const sz = Math.sin(tiltZ);
  const cy = Math.cos(rotY);
  const sy = Math.sin(rotY);
  const tf = (x, y, z) => {
    // Rz
    let x1 = x * cz - y * sz;
    let y1 = x * sz + y * cz;
    let z1 = z;
    // Ry
    const x2 = x1 * cy + z1 * sy;
    const z2 = -x1 * sy + z1 * cy;
    return [x2 + tx, y1 + ty, z2 + tz];
  };
  const corners = [
    [-hx, -hy, -hz], [hx, -hy, -hz], [hx, hy, -hz], [-hx, hy, -hz],
    [-hx, -hy, hz], [hx, -hy, hz], [hx, hy, hz], [-hx, hy, hz],
  ].map((c) => tf(c[0], c[1], c[2]));
  const faces = [
    [0, 1, 2, 3, [0, 0, -1]], [5, 4, 7, 6, [0, 0, 1]],
    [4, 0, 3, 7, [-1, 0, 0]], [1, 5, 6, 2, [1, 0, 0]],
    [3, 2, 6, 7, [0, 1, 0]], [4, 5, 1, 0, [0, -1, 0]],
  ];
  for (const f of faces) {
    const nrmRaw = f[4];
    // Rz puis Ry sur la normale (rotation seule).
    let nx = nrmRaw[0] * cz - nrmRaw[1] * sz;
    let nyv = nrmRaw[0] * sz + nrmRaw[1] * cz;
    let nz = nrmRaw[2];
    const nx2 = nx * cy + nz * sy;
    const nz2 = -nx * sy + nz * cy;
    const base = mesh.pos.length / 3;
    for (const ci of [f[0], f[1], f[2], f[3]]) {
      mesh.pos.push(corners[ci][0], corners[ci][1], corners[ci][2]);
      mesh.nrm.push(nx2, nyv, nz2);
    }
    mesh.idx.push(base, base + 1, base + 2, base, base + 2, base + 3);
  }
}

// Prisme hexagonal (marches du socle Hexagoal).
function hexPrism(mesh, radius, y0, y1) {
  const top = [];
  const bot = [];
  for (let i = 0; i < 6; i++) {
    const a = (i / 6) * Math.PI * 2 + Math.PI / 6;
    top.push([Math.cos(a) * radius, y1, Math.sin(a) * radius]);
    bot.push([Math.cos(a) * radius, y0, Math.sin(a) * radius]);
  }
  // Faces laterales.
  for (let i = 0; i < 6; i++) {
    const j = (i + 1) % 6;
    const a = (i / 6) * Math.PI * 2 + Math.PI / 6 + Math.PI / 6;
    const nx = Math.cos(a);
    const nz = Math.sin(a);
    const base = mesh.pos.length / 3;
    const quad = [bot[i], bot[j], top[j], top[i]];
    for (const v of quad) {
      mesh.pos.push(v[0], v[1], v[2]);
      mesh.nrm.push(nx, 0, nz);
    }
    mesh.idx.push(base, base + 1, base + 2, base, base + 2, base + 3);
  }
  // Chapeaux haut/bas.
  const cap = (ring, ny, yy, flip) => {
    const centerBase = mesh.pos.length / 3;
    mesh.pos.push(0, yy, 0);
    mesh.nrm.push(0, ny, 0);
    const start = mesh.pos.length / 3;
    for (const v of ring) {
      mesh.pos.push(v[0], v[1], v[2]);
      mesh.nrm.push(0, ny, 0);
    }
    for (let i = 0; i < 6; i++) {
      const a = start + i;
      const b = start + ((i + 1) % 6);
      if (flip) {
        mesh.idx.push(centerBase, b, a);
      } else {
        mesh.idx.push(centerBase, a, b);
      }
    }
  };
  cap(top, 1, y1, false);
  cap(bot, -1, y0, true);
}

// ----------------------------------------------------------------------------------------
// Modeles de trophees. Chacun renvoie { mesh, color, warm } (warm : 1 dore, 0 argente).
// ----------------------------------------------------------------------------------------
function modelUCL() {
  // Ligue des Champions : corps argente bulbeux, large col evase, deux grandes anses.
  const mesh = Mesh();
  const body = [
    [0.00, 0.00], [0.40, 0.00], [0.40, 0.05], [0.28, 0.09], [0.28, 0.13],
    [0.10, 0.18], [0.085, 0.30], [0.30, 0.42], [0.42, 0.60], [0.44, 0.74],
    [0.40, 0.92], [0.31, 1.08], [0.30, 1.18], [0.44, 1.42], [0.50, 1.52],
    [0.47, 1.55], [0.40, 1.50], [0.20, 1.46], [0.00, 1.44],
  ];
  revolution(mesh, body, 48);
  // Anses "big ears" : une grande boucle de chaque cote, dans le plan XY.
  const earR = [
    [0.44, 1.46], [0.78, 1.44], [0.98, 1.14], [0.92, 0.78], [0.66, 0.60], [0.42, 0.66],
  ];
  tube(mesh, catmull(earR, 8), 0.045, 12);
  tube(mesh, catmull(earR.map((p) => [-p[0], p[1]]), 8), 0.045, 12);
  return { mesh, color: [0.82, 0.84, 0.9], warm: 0 };
}

function modelL1() {
  // Ligue 1 (Hexagoal) : sphere doree, trois lames inclinees, socle hexagonal a marches.
  const mesh = Mesh();
  sphere(mesh, 0.36, 1.18, 20, 32);
  for (let i = 0; i < 3; i++) {
    const rot = (i / 3) * Math.PI * 2;
    blade(mesh, 0.05, 0.62, 0.14, 0.22, rot, 0.22, 0.62, 0);
  }
  hexPrism(mesh, 0.40, 0.00, 0.12);
  hexPrism(mesh, 0.52, -0.12, 0.00);
  return { mesh, color: [0.86, 0.66, 0.22], warm: 1 };
}

function modelCdF() {
  // Coupe de France : coupe argentee ornee, couvercle en dome, petit finial, deux anses.
  const mesh = Mesh();
  const body = [
    [0.00, 0.00], [0.38, 0.00], [0.38, 0.05], [0.26, 0.08], [0.26, 0.13],
    [0.10, 0.17], [0.09, 0.30], [0.13, 0.40], [0.34, 0.52], [0.43, 0.72],
    [0.42, 0.90], [0.34, 1.02], [0.40, 1.08], [0.40, 1.14], [0.30, 1.28],
    [0.15, 1.37], [0.07, 1.42], [0.00, 1.44],
  ];
  revolution(mesh, body, 44);
  sphere(mesh, 0.06, 1.52, 12, 18);
  // Deux anses classiques, plus petites, du haut du ventre.
  const h = [[0.40, 1.02], [0.60, 0.98], [0.66, 0.80], [0.50, 0.66], [0.40, 0.72]];
  tube(mesh, catmull(h, 7), 0.03, 10);
  tube(mesh, catmull(h.map((p) => [-p[0], p[1]]), 7), 0.03, 10);
  return { mesh, color: [0.82, 0.84, 0.9], warm: 0 };
}

function modelTdC() {
  // Trophee des Champions : trophee argente moderne, taille pincee et sommet largement evase.
  const mesh = Mesh();
  const body = [
    [0.00, 0.00], [0.36, 0.00], [0.36, 0.06], [0.31, 0.11], [0.22, 0.20],
    [0.14, 0.34], [0.12, 0.46], [0.16, 0.60], [0.28, 0.80], [0.42, 1.02],
    [0.54, 1.26], [0.60, 1.44], [0.61, 1.52], [0.54, 1.49], [0.40, 1.44],
    [0.00, 1.40],
  ];
  revolution(mesh, body, 48);
  return { mesh, color: [0.82, 0.85, 0.9], warm: 0 };
}

const MODELS = { ucl: modelUCL, l1: modelL1, cdf: modelCdF, tdc: modelTdC };

// Centre le maillage en Y et l'echelle pour tenir dans le cadre (hauteur et largeur).
function normalize(mesh) {
  let minY = Infinity;
  let maxY = -Infinity;
  let maxR = 0;
  for (let i = 0; i < mesh.pos.length; i += 3) {
    const x = mesh.pos[i];
    const y = mesh.pos[i + 1];
    const z = mesh.pos[i + 2];
    if (y < minY) minY = y;
    if (y > maxY) maxY = y;
    const r = Math.hypot(x, z);
    if (r > maxR) maxR = r;
  }
  const cy = (minY + maxY) / 2;
  const h = maxY - minY || 1;
  let scale = 1.62 / h;
  if (maxR * scale > 0.92) {
    scale = 0.92 / maxR;
  }
  for (let i = 0; i < mesh.pos.length; i += 3) {
    mesh.pos[i] *= scale;
    mesh.pos[i + 1] = (mesh.pos[i + 1] - cy) * scale;
    mesh.pos[i + 2] *= scale;
  }
}

// ----------------------------------------------------------------------------------------
// WebGL
// ----------------------------------------------------------------------------------------
const VERT_SRC = `
  attribute vec3 aPos;
  attribute vec3 aNormal;
  uniform mat4 uProj;
  uniform mat4 uMV;
  uniform mat3 uNormalMat;
  varying vec3 vN;
  varying vec3 vView;
  void main() {
    vec4 mv = uMV * vec4(aPos, 1.0);
    vView = mv.xyz;
    vN = uNormalMat * aNormal;
    gl_Position = uProj * mv;
  }
`;

const FRAG_SRC = `
  precision mediump float;
  varying vec3 vN;
  varying vec3 vView;
  uniform vec3 uColor;
  uniform float uWarm;      // 1.0 dore, 0.0 argente
  void main() {
    vec3 N = normalize(vN);
    vec3 V = normalize(-vView);
    if (dot(N, V) < 0.0) N = -N;
    vec3 L1 = normalize(vec3(-0.42, 0.82, 0.62));
    vec3 L2 = normalize(vec3(0.5, 0.25, 0.55));
    vec3 sky = mix(vec3(0.66, 0.76, 0.95), vec3(1.0, 0.92, 0.68), uWarm);
    vec3 ground = mix(vec3(0.18, 0.2, 0.26), vec3(0.28, 0.2, 0.08), uWarm);
    vec3 specCol = mix(vec3(0.95, 0.97, 1.0), vec3(1.0, 0.9, 0.6), uWarm);
    float d1 = max(dot(N, L1), 0.0);
    float d2 = max(dot(N, L2), 0.0);
    vec3 H = normalize(L1 + V);
    float spec = pow(max(dot(N, H), 0.0), 42.0);
    float fres = pow(1.0 - max(dot(N, V), 0.0), 3.0);
    vec3 hemi = mix(ground, sky, N.y * 0.5 + 0.5);
    vec3 col = uColor * (hemi * 0.55 + d1 * 0.68 + d2 * 0.22)
             + specCol * spec * 0.85
             + sky * fres * 0.30;
    gl_FragColor = vec4(col, 1.0);
  }
`;

function perspective(fovy, aspect, near, far) {
  const f = 1 / Math.tan(fovy / 2);
  const nf = 1 / (near - far);
  return [f / aspect, 0, 0, 0, 0, f, 0, 0, 0, 0, (far + near) * nf, -1, 0, 0, 2 * far * near * nf, 0];
}
function modelView(a, d) {
  const c = Math.cos(a);
  const s = Math.sin(a);
  return [c, 0, -s, 0, 0, 1, 0, 0, s, 0, c, 0, 0, 0, -d, 1];
}
function normalMat(a) {
  const c = Math.cos(a);
  const s = Math.sin(a);
  return [c, 0, -s, 0, 1, 0, s, 0, c];
}

function compile(gl, type, src) {
  const sh = gl.createShader(type);
  gl.shaderSource(sh, src);
  gl.compileShader(sh);
  if (!gl.getShaderParameter(sh, gl.COMPILE_STATUS)) {
    gl.deleteShader(sh);
    return null;
  }
  return sh;
}

function createTrophy(canvas, type) {
  const gl = canvas.getContext('webgl', { antialias: true, alpha: true, premultipliedAlpha: false })
    || canvas.getContext('experimental-webgl', { antialias: true, alpha: true });
  if (!gl) {
    return null;
  }
  const vs = compile(gl, gl.VERTEX_SHADER, VERT_SRC);
  const fs = compile(gl, gl.FRAGMENT_SHADER, FRAG_SRC);
  if (!vs || !fs) {
    return null;
  }
  const prog = gl.createProgram();
  gl.attachShader(prog, vs);
  gl.attachShader(prog, fs);
  gl.linkProgram(prog);
  if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) {
    return null;
  }

  const build = MODELS[type] || MODELS.ucl;
  const model = build();
  normalize(model.mesh);
  const positions = new Float32Array(model.mesh.pos);
  const normals = new Float32Array(model.mesh.nrm);
  const useUint32 = model.mesh.pos.length / 3 > 65535;
  let indexType = gl.UNSIGNED_SHORT;
  let indices;
  if (useUint32 && gl.getExtension('OES_element_index_uint')) {
    indices = new Uint32Array(model.mesh.idx);
    indexType = gl.UNSIGNED_INT;
  } else {
    indices = new Uint16Array(model.mesh.idx);
  }

  const posBuf = gl.createBuffer();
  gl.bindBuffer(gl.ARRAY_BUFFER, posBuf);
  gl.bufferData(gl.ARRAY_BUFFER, positions, gl.STATIC_DRAW);
  const normBuf = gl.createBuffer();
  gl.bindBuffer(gl.ARRAY_BUFFER, normBuf);
  gl.bufferData(gl.ARRAY_BUFFER, normals, gl.STATIC_DRAW);
  const idxBuf = gl.createBuffer();
  gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, idxBuf);
  gl.bufferData(gl.ELEMENT_ARRAY_BUFFER, indices, gl.STATIC_DRAW);
  const idxCount = indices.length;

  const aPos = gl.getAttribLocation(prog, 'aPos');
  const aNormal = gl.getAttribLocation(prog, 'aNormal');
  const uProj = gl.getUniformLocation(prog, 'uProj');
  const uMV = gl.getUniformLocation(prog, 'uMV');
  const uNormalMat = gl.getUniformLocation(prog, 'uNormalMat');
  const uColor = gl.getUniformLocation(prog, 'uColor');
  const uWarm = gl.getUniformLocation(prog, 'uWarm');

  gl.enable(gl.DEPTH_TEST);
  gl.clearColor(0, 0, 0, 0);
  let bufW = 0;
  let bufH = 0;

  return function draw(angle) {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const w = Math.round(canvas.clientWidth * dpr);
    const h = Math.round(canvas.clientHeight * dpr);
    if (w === 0 || h === 0) {
      return;
    }
    if (w !== bufW || h !== bufH) {
      canvas.width = w;
      canvas.height = h;
      bufW = w;
      bufH = h;
    }
    gl.viewport(0, 0, w, h);
    gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);
    gl.useProgram(prog);
    gl.uniformMatrix4fv(uProj, false, perspective(0.62, w / h, 0.1, 20));
    gl.uniformMatrix4fv(uMV, false, modelView(angle, 3.3));
    gl.uniformMatrix3fv(uNormalMat, false, normalMat(angle));
    gl.uniform3fv(uColor, model.color);
    gl.uniform1f(uWarm, model.warm);
    gl.bindBuffer(gl.ARRAY_BUFFER, posBuf);
    gl.enableVertexAttribArray(aPos);
    gl.vertexAttribPointer(aPos, 3, gl.FLOAT, false, 0, 0);
    gl.bindBuffer(gl.ARRAY_BUFFER, normBuf);
    gl.enableVertexAttribArray(aNormal);
    gl.vertexAttribPointer(aNormal, 3, gl.FLOAT, false, 0, 0);
    gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, idxBuf);
    gl.drawElements(gl.TRIANGLES, idxCount, indexType, 0);
  };
}

export function initTrophies() {
  // Les cartes rendues par model-viewer (GLB present) portent data-mv : on les ignore,
  // le WebGL maison ne sert que de repli pour les cartes sans GLB.
  const stages = Array.from(document.querySelectorAll('.trophy__stage:not([data-mv])'));
  if (stages.length === 0) {
    return;
  }
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const items = [];
  for (const stage of stages) {
    const canvas = document.createElement('canvas');
    canvas.className = 'trophy__canvas';
    canvas.setAttribute('aria-hidden', 'true');
    stage.appendChild(canvas);
    const draw = createTrophy(canvas, stage.dataset.trophy || 'ucl');
    if (!draw) {
      canvas.remove();
      continue;
    }
    stage.classList.add('is-3d');
    items.push({ draw, angle: 0.5, onScreen: undefined });
  }
  if (items.length === 0) {
    return;
  }

  if (reduce) {
    for (const it of items) {
      it.draw(0.5);
    }
    return;
  }

  let running = false;
  let last = 0;
  function frame(now) {
    const dt = last ? Math.min((now - last) / 1000, 0.05) : 0;
    last = now;
    let any = false;
    for (const it of items) {
      if (it.onScreen === false) {
        continue;
      }
      any = true;
      it.angle += dt * 0.55;
      it.draw(it.angle);
    }
    if (any) {
      requestAnimationFrame(frame);
    } else {
      running = false;
      last = 0;
    }
  }
  function kick() {
    if (!running) {
      running = true;
      requestAnimationFrame(frame);
    }
  }

  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      for (const e of entries) {
        const it = items[Number(e.target.dataset.trophyIndex)];
        if (it) {
          it.onScreen = e.isIntersecting;
        }
      }
      if (items.some((it) => it.onScreen)) {
        kick();
      }
    }, { threshold: 0.01 });
    items.forEach((it, i) => {
      stages[i].dataset.trophyIndex = String(i);
      io.observe(stages[i]);
    });
  }
  kick();
}
