/* =========================================================
   OG IMAGE LAB — interactive logic
   - Canvas-based variant rendering
   - Per-platform crop simulation
   - Safe-area / danger-zone overlays
   - PNG export
   ========================================================= */

// ------- Theme toggle -------
// (function () {
//   const t = document.querySelector('[data-theme-toggle]');
//   const r = document.documentElement;
//   let mode = matchMedia('(prefers-color-scheme:dark)').matches ? 'dark' : 'light';
//   r.setAttribute('data-theme', mode);
//   function setIcon() {
//     t.innerHTML =
//       mode === 'dark'
//         ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>'
//         : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
//     t.setAttribute('aria-label', `Switch to ${mode === 'dark' ? 'light' : 'dark'} mode`);
//   }
//   setIcon();
//   t.addEventListener('click', () => {
//     mode = mode === 'dark' ? 'light' : 'dark';
//     r.setAttribute('data-theme', mode);
//     setIcon();
//     // Re-render previews (placeholder colors depend on theme)
//     rerenderAll();
//   });
// })();

// ------- Variants -------
const VARIANTS = [
  {
    id: 'og-standard',
    name: 'OG Standard',
    w: 1200,
    h: 630,
    ratio: '1.91:1',
    note: 'The universal Open Graph default. Works for Facebook, LinkedIn link previews, Slack and Discord unfurls.',
  },
  {
    id: 'square',
    name: 'Square',
    w: 1200,
    h: 1200,
    ratio: '1:1',
    note: 'Strong on LinkedIn mobile feed and Instagram. Will be center-cropped to 1.91:1 on link unfurls.',
  },
  {
    id: 'twitter-16-9',
    name: 'X / 16:9',
    w: 1200,
    h: 675,
    ratio: '16:9',
    note: 'Recommended for X (Twitter) summary_large_image cards. Slightly shorter than OG standard.',
  },
  {
    id: 'twitter-hires',
    name: 'X HD 16:9',
    w: 1600,
    h: 900,
    ratio: '16:9',
    note: 'Higher-res 16:9 for X — ideal when image carries fine detail or text.',
  },
  {
    id: 'portrait',
    name: 'Portrait',
    w: 1080,
    h: 1350,
    ratio: '4:5',
    note: 'Mobile-first. Strong on Instagram & LinkedIn vertical posts. Most platforms will crop top/bottom on link previews.',
  },
  {
    id: 'banner',
    name: 'X Header',
    w: 1500,
    h: 500,
    ratio: '3:1',
    note: 'X profile header / Twitter Spaces cover. Not for link previews.',
  },
];

// Platform crop behavior — describes the crop applied when an image
// in the source variant is rendered into the platform's preview slot.
const PLATFORM_TARGETS = {
  facebook: { ratio: 1.91 / 1, label: 'Facebook · 1.91:1' },
  linkedin: { ratio: 1.91 / 1, label: 'LinkedIn · 1.91:1' },
  x: { ratio: 16 / 9, label: 'X · 16:9' },
  slack: { ratio: 1.91 / 1, label: 'Slack · 1.91:1' },
  imessage: { ratio: 1, label: 'iMessage · 1:1' },
  discord: { ratio: 1.91 / 1, label: 'Discord · 1.91:1' },
};

// ------- DOM refs -------
const masterCanvas = document.getElementById('masterCanvas');
const masterCtx = masterCanvas.getContext('2d');
const masterDimLabel = document.getElementById('masterDimLabel');
const variantGrid = document.querySelector('.variant-grid');
const variantMeta = document.getElementById('variantMeta');
const srcMeta = document.getElementById('srcMeta');

const tgSafe = document.getElementById('tgSafe');
const tgCrop = document.getElementById('tgCrop');
const tgGrid = document.getElementById('tgGrid');
const tgCenter = document.getElementById('tgCenter');
const tgLabels = document.getElementById('tgLabels');

const dropzone = document.getElementById('dropzone');
const fileInput = document.getElementById('fileInput');
const urlInput = document.getElementById('urlInput');
const urlLoadBtn = document.getElementById('urlLoadBtn');
const exportBtn = document.getElementById('exportBtn');

// ------- State -------
const state = {
  variantId: 'og-standard',
  image: null, // HTMLImageElement
  imageLabel: null,
};

// ------- Variant UI -------
function renderVariantButtons() {
  variantGrid.innerHTML = '';
  VARIANTS.forEach((v) => {
    const btn = document.createElement('button');
    btn.className = 'variant-btn';
    btn.type = 'button';
    btn.setAttribute('aria-pressed', state.variantId === v.id ? 'true' : 'false');
    btn.dataset.id = v.id;
    // Compute shape proportions (capped to a small icon area)
    const maxSide = 56;
    const ratio = v.w / v.h;
    let sw, sh;
    if (ratio >= 1) {
      sw = maxSide;
      sh = Math.max(14, Math.round(maxSide / ratio));
    } else {
      sh = maxSide;
      sw = Math.max(14, Math.round(maxSide * ratio));
    }
    btn.innerHTML = `
      <span class="vb-shape" style="width:${sw}px;height:${sh}px"></span>
      <span class="vb-label">
        <span class="vb-name">${v.name}</span>
        <span class="vb-dim">${v.w}×${v.h} · ${v.ratio}</span>
      </span>
    `;
    btn.addEventListener('click', () => {
      state.variantId = v.id;
      renderVariantButtons();
      rerenderAll();
    });
    variantGrid.appendChild(btn);
  });
  const v = activeVariant();
  variantMeta.textContent = v.note;
}

function activeVariant() {
  return VARIANTS.find((x) => x.id === state.variantId) || VARIANTS[0];
}

// ------- Sample images (programmatic, no external assets) -------
function makeSampleCanvas(kind, w = 1600, h = 1600) {
  const c = document.createElement('canvas');
  c.width = w;
  c.height = h;
  const ctx = c.getContext('2d');

  if (kind === 'gradient') {
    const g = ctx.createLinearGradient(0, 0, w, h);
    g.addColorStop(0, '#0f766e');
    g.addColorStop(0.55, '#7c3aed');
    g.addColorStop(1, '#f97316');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, w, h);
    // Soft orbs
    ctx.globalCompositeOperation = 'lighter';
    for (let i = 0; i < 6; i++) {
      const rg = ctx.createRadialGradient(
        Math.random() * w,
        Math.random() * h,
        20,
        Math.random() * w,
        Math.random() * h,
        500 + Math.random() * 300,
      );
      rg.addColorStop(0, 'rgba(255,255,255,0.25)');
      rg.addColorStop(1, 'rgba(255,255,255,0)');
      ctx.fillStyle = rg;
      ctx.fillRect(0, 0, w, h);
    }
    ctx.globalCompositeOperation = 'source-over';
    // Center mark
    ctx.fillStyle = 'rgba(255,255,255,0.92)';
    ctx.font = '700 120px Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('Demo Image', w / 2, h / 2);
    ctx.font = '500 50px Inter, sans-serif';
    ctx.fillStyle = 'rgba(255,255,255,0.7)';
    ctx.fillText(`${w} × ${h}`, w / 2, h / 2 + 100);
  } else if (kind === 'text') {
    // Dense text-heavy demo (intentionally too edge-heavy)
    ctx.fillStyle = '#0c1f1d';
    ctx.fillRect(0, 0, w, h);
    ctx.fillStyle = '#2dd4bf';
    ctx.font = '800 220px Inter, sans-serif';
    ctx.textAlign = 'left';
    ctx.textBaseline = 'top';
    ctx.fillText('UNFURL', 60, 80);
    ctx.fillText('YOUR', 60, 320);
    ctx.fillText('LINKS', 60, 560);
    ctx.fillStyle = '#fef3c7';
    ctx.font = '600 60px Inter, sans-serif';
    ctx.fillText('Test how every platform crops this →', 60, h - 180);
    ctx.fillText('Edge text is risky.', 60, h - 100);
    // small badge in corner that WILL get cropped on mobile
    ctx.fillStyle = '#dc2626';
    ctx.fillRect(w - 240, h - 240, 200, 200);
    ctx.fillStyle = 'white';
    ctx.font = '700 36px Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('NEW!', w - 140, h - 140);
  } else if (kind === 'photo') {
    // Procedural "photo" — sky + horizon + sun
    const sky = ctx.createLinearGradient(0, 0, 0, h);
    sky.addColorStop(0, '#fb923c');
    sky.addColorStop(0.4, '#fbbf24');
    sky.addColorStop(0.65, '#fde68a');
    sky.addColorStop(1, '#1e3a8a');
    ctx.fillStyle = sky;
    ctx.fillRect(0, 0, w, h);
    // Sun
    const sun = ctx.createRadialGradient(w * 0.5, h * 0.45, 20, w * 0.5, h * 0.45, 280);
    sun.addColorStop(0, '#fff7ed');
    sun.addColorStop(0.4, '#fdba74');
    sun.addColorStop(1, 'rgba(251, 146, 60, 0)');
    ctx.fillStyle = sun;
    ctx.fillRect(0, 0, w, h);
    // Mountains
    ctx.fillStyle = '#1e293b';
    ctx.beginPath();
    ctx.moveTo(0, h * 0.7);
    ctx.lineTo(w * 0.2, h * 0.55);
    ctx.lineTo(w * 0.4, h * 0.65);
    ctx.lineTo(w * 0.6, h * 0.5);
    ctx.lineTo(w * 0.8, h * 0.6);
    ctx.lineTo(w, h * 0.55);
    ctx.lineTo(w, h);
    ctx.lineTo(0, h);
    ctx.closePath();
    ctx.fill();
    // Foreground
    ctx.fillStyle = '#0f172a';
    ctx.fillRect(0, h * 0.78, w, h * 0.22);
  }
  return c;
}

function loadSample(kind) {
  const c = makeSampleCanvas(kind);
  const img = new Image();
  img.onload = () => {
    state.image = img;
    state.imageLabel = `sample · ${kind} · ${c.width}×${c.height}`;
    onImageReady();
  };
  img.src = c.toDataURL('image/png');
}

// ------- Image loading -------
function loadFile(file) {
  if (!file || !file.type.startsWith('image/')) {
    flashSrcMeta('Not an image file.', true);
    return;
  }
  const reader = new FileReader();
  reader.onload = (e) => {
    const img = new Image();
    img.onload = () => {
      state.image = img;
      state.imageLabel = `${file.name} · ${img.naturalWidth}×${img.naturalHeight}`;
      onImageReady();
    };
    img.onerror = () => flashSrcMeta('Could not decode image.', true);
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
}

function loadUrl(url) {
  if (!url) return;
  const img = new Image();
  img.crossOrigin = 'anonymous';
  img.onload = () => {
    state.image = img;
    const short = url.length > 50 ? url.slice(0, 47) + '…' : url;
    state.imageLabel = `${short} · ${img.naturalWidth}×${img.naturalHeight}`;
    onImageReady();
  };
  img.onerror = () => {
    flashSrcMeta('Could not load (CORS or 404). Try a direct image URL.', true);
  };
  img.src = url;
}

function flashSrcMeta(msg, isError) {
  srcMeta.innerHTML = `<span style="color:${isError ? 'var(--danger)' : 'inherit'}">${msg}</span>`;
}

function onImageReady() {
  srcMeta.innerHTML = `<span style="color:var(--ok)">●</span> ${state.imageLabel}`;
  rerenderAll();
}

// ------- Master canvas rendering -------
function renderMaster() {
  const v = activeVariant();

  // Cap canvas to a reasonable display size while preserving variant aspect ratio
  const stage = document.getElementById('masterStage');
  const stageW = stage.clientWidth - 48; // padding allowance
  const stageH = Math.min(window.innerHeight * 0.65, 700);
  const ratio = v.w / v.h;

  let displayW, displayH;
  if (stageW / ratio <= stageH) {
    displayW = Math.min(v.w, stageW);
    displayH = displayW / ratio;
  } else {
    displayH = Math.min(v.h, stageH);
    displayW = displayH * ratio;
  }

  const dpr = window.devicePixelRatio || 1;
  masterCanvas.width = displayW * dpr;
  masterCanvas.height = displayH * dpr;
  masterCanvas.style.width = displayW + 'px';
  masterCanvas.style.height = displayH + 'px';
  masterCtx.setTransform(dpr, 0, 0, dpr, 0, 0);

  drawVariant(masterCtx, displayW, displayH, v, {
    safe: tgSafe.checked,
    crop: tgCrop.checked,
    grid: tgGrid.checked,
    center: tgCenter.checked,
    labels: tgLabels.checked,
  });

  masterDimLabel.textContent = `${v.name} · ${v.w} × ${v.h} px · ${v.ratio}`;
}

// Draws the variant onto a 2D context covering [0,0,w,h].
function drawVariant(ctx, w, h, v, overlays) {
  // Background — checkerboard for transparency context if no image
  if (!state.image) {
    drawPlaceholder(ctx, w, h, v);
  } else {
    // Cover-fit the source into the variant frame
    drawImageCover(ctx, state.image, 0, 0, w, h);
  }

  if (overlays.crop) drawMobileCropZones(ctx, w, h, v);
  if (overlays.safe) drawSafeArea(ctx, w, h);
  if (overlays.grid) drawThirds(ctx, w, h);
  if (overlays.center) drawCrosshair(ctx, w, h);
  if (overlays.labels) drawDimLabel(ctx, w, h, v);
}

function drawPlaceholder(ctx, w, h, v) {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  ctx.fillStyle = isDark ? '#1c1f22' : '#f1f1ee';
  ctx.fillRect(0, 0, w, h);
  // Diagonal lines pattern
  ctx.strokeStyle = isDark ? '#2a2e32' : '#e3e3e0';
  ctx.lineWidth = 1;
  for (let i = -h; i < w; i += 24) {
    ctx.beginPath();
    ctx.moveTo(i, h);
    ctx.lineTo(i + h, 0);
    ctx.stroke();
  }
  // Center label
  ctx.fillStyle = isDark ? '#5e615f' : '#a8a8a5';
  ctx.font = '500 14px Inter, sans-serif';
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillText(`${v.w} × ${v.h}`, w / 2, h / 2 - 10);
  ctx.font = '400 12px JetBrains Mono, monospace';
  ctx.fillText('Drop an image to begin', w / 2, h / 2 + 12);
}

// Cover-fit (CSS background-size: cover semantics)
function drawImageCover(ctx, img, dx, dy, dw, dh) {
  const iw = img.naturalWidth || img.width;
  const ih = img.naturalHeight || img.height;
  if (!iw || !ih) return;
  const ir = iw / ih;
  const fr = dw / dh;
  let sx, sy, sw, sh;
  if (ir > fr) {
    // image wider — crop sides
    sh = ih;
    sw = ih * fr;
    sx = (iw - sw) / 2;
    sy = 0;
  } else {
    // image taller — crop top/bottom
    sw = iw;
    sh = iw / fr;
    sx = 0;
    sy = (ih - sh) / 2;
  }
  ctx.save();
  ctx.beginPath();
  ctx.rect(dx, dy, dw, dh);
  ctx.clip();
  ctx.drawImage(img, sx, sy, sw, sh, dx, dy, dw, dh);
  ctx.restore();
}

// Safe area = inner 90% on each axis
function drawSafeArea(ctx, w, h) {
  const inset = 0.05;
  const x = w * inset;
  const y = h * inset;
  const sw = w * (1 - inset * 2);
  const sh = h * (1 - inset * 2);
  ctx.save();
  // Outer dim
  ctx.fillStyle = 'rgba(22, 163, 74, 0.07)';
  ctx.fillRect(0, 0, w, h);
  // Inner clear
  ctx.globalCompositeOperation = 'destination-out';
  ctx.fillRect(x, y, sw, sh);
  ctx.restore();

  // Border
  ctx.save();
  ctx.strokeStyle = 'rgba(22, 163, 74, 0.85)';
  ctx.lineWidth = 1.5;
  ctx.setLineDash([8, 6]);
  ctx.strokeRect(x, y, sw, sh);
  ctx.setLineDash([]);
  // Label
  ctx.fillStyle = 'rgba(22, 163, 74, 0.95)';
  ctx.font = '600 11px JetBrains Mono, monospace';
  ctx.textBaseline = 'top';
  ctx.textAlign = 'left';
  const lbl = ' SAFE ZONE · 90% ';
  const m = ctx.measureText(lbl);
  ctx.fillRect(x, y - 18, m.width + 4, 18);
  ctx.fillStyle = '#fff';
  ctx.fillText(lbl, x + 2, y - 16);
  ctx.restore();
}

// Mobile crop zones — show what gets clipped on the tightest mobile aspect
function drawMobileCropZones(ctx, w, h, v) {
  const sourceRatio = v.w / v.h;
  // Tightest crop common on link previews mobile: 1.91:1 stays as-is for OG;
  // for square sources we draw the 1.91:1 crop window centered.
  // Here we draw the area that survives the *most aggressive* common crop:
  // that's iMessage's 1:1 if the variant is wider, or X's 16:9 if taller.
  const aggressiveRatios = [
    { r: 1, name: 'iMessage 1:1' },
    { r: 16 / 9, name: 'X 16:9' },
    { r: 1.91, name: 'FB / LI 1.91:1' },
  ];
  // Pick the one whose crop *removes the most* relative to the source.
  let worst = null;
  let worstLoss = -1;
  for (const ar of aggressiveRatios) {
    const loss = computeCropLoss(sourceRatio, ar.r);
    if (loss > worstLoss) {
      worstLoss = loss;
      worst = ar;
    }
  }
  if (!worst || worstLoss < 0.001) return;

  // Draw the surviving rect
  const tr = worst.r;
  let cx, cy, cw, ch;
  if (sourceRatio > tr) {
    // sides clipped
    ch = h;
    cw = h * tr;
    cx = (w - cw) / 2;
    cy = 0;
  } else {
    // top/bottom clipped
    cw = w;
    ch = w / tr;
    cx = 0;
    cy = (h - ch) / 2;
  }

  ctx.save();
  // Hatched danger fills outside the surviving rect
  ctx.fillStyle = 'rgba(234, 88, 12, 0.18)';
  ctx.beginPath();
  ctx.rect(0, 0, w, h);
  ctx.rect(cx + cw, cy, -cw, ch); // cut out surviving (using even-odd)
  // Simpler: draw four bands
  ctx.beginPath();
  if (cy > 0) ctx.rect(0, 0, w, cy);
  if (cy + ch < h) ctx.rect(0, cy + ch, w, h - (cy + ch));
  if (cx > 0) ctx.rect(0, cy, cx, ch);
  if (cx + cw < w) ctx.rect(cx + cw, cy, w - (cx + cw), ch);
  ctx.fill();

  // Diagonal hatch on danger
  ctx.strokeStyle = 'rgba(234, 88, 12, 0.5)';
  ctx.lineWidth = 1;
  ctx.beginPath();
  drawHatchClipBand(ctx, 0, 0, w, cy);
  drawHatchClipBand(ctx, 0, cy + ch, w, h - (cy + ch));
  drawHatchClipBand(ctx, 0, cy, cx, ch);
  drawHatchClipBand(ctx, cx + cw, cy, w - (cx + cw), ch);

  // Surviving outline
  ctx.strokeStyle = 'rgba(234, 88, 12, 0.95)';
  ctx.lineWidth = 1.5;
  ctx.setLineDash([4, 4]);
  ctx.strokeRect(cx, cy, cw, ch);
  ctx.setLineDash([]);

  // Label
  const lbl = ` ⚠ Cropped on ${worst.name} `;
  ctx.font = '600 11px JetBrains Mono, monospace';
  ctx.textBaseline = 'bottom';
  ctx.textAlign = 'left';
  const m = ctx.measureText(lbl);
  const lx = cx;
  const ly = cy + ch + 18;
  ctx.fillStyle = 'rgba(234, 88, 12, 0.95)';
  ctx.fillRect(lx, ly - 18, m.width + 4, 18);
  ctx.fillStyle = '#fff';
  ctx.fillText(lbl, lx + 2, ly - 4);
  ctx.restore();
}

function drawHatchClipBand(ctx, x, y, w, h) {
  if (w <= 0 || h <= 0) return;
  ctx.save();
  ctx.beginPath();
  ctx.rect(x, y, w, h);
  ctx.clip();
  for (let i = -h; i < w + h; i += 10) {
    ctx.moveTo(x + i, y + h);
    ctx.lineTo(x + i + h, y);
  }
  ctx.stroke();
  ctx.restore();
}

function computeCropLoss(srcRatio, targetRatio) {
  // returns fraction of area lost when cover-cropping srcRatio frame to targetRatio
  if (srcRatio === targetRatio) return 0;
  const a = srcRatio;
  const b = targetRatio;
  if (a > b) {
    // crop sides — width shrinks from a to b (height = 1)
    return (a - b) / a;
  } else {
    // crop top/bottom — height shrinks from 1/a to 1/b (width = 1)
    return (1 / a - 1 / b) / (1 / a);
  }
}

function drawThirds(ctx, w, h) {
  ctx.save();
  ctx.strokeStyle = 'rgba(15, 118, 110, 0.7)';
  ctx.lineWidth = 1;
  ctx.setLineDash([3, 4]);
  ctx.beginPath();
  for (let i = 1; i < 3; i++) {
    ctx.moveTo((w * i) / 3, 0);
    ctx.lineTo((w * i) / 3, h);
    ctx.moveTo(0, (h * i) / 3);
    ctx.lineTo(w, (h * i) / 3);
  }
  ctx.stroke();
  ctx.restore();
}

function drawCrosshair(ctx, w, h) {
  ctx.save();
  ctx.strokeStyle = 'rgba(15, 118, 110, 0.85)';
  ctx.lineWidth = 1.2;
  ctx.beginPath();
  ctx.moveTo(w / 2, 0);
  ctx.lineTo(w / 2, h);
  ctx.moveTo(0, h / 2);
  ctx.lineTo(w, h / 2);
  ctx.stroke();
  ctx.beginPath();
  ctx.arc(w / 2, h / 2, 14, 0, Math.PI * 2);
  ctx.stroke();
  ctx.restore();
}

function drawDimLabel(ctx, w, h, v) {
  ctx.save();
  ctx.fillStyle = 'rgba(0,0,0,0.65)';
  ctx.font = '600 11px JetBrains Mono, monospace';
  ctx.textAlign = 'right';
  ctx.textBaseline = 'bottom';
  const lbl = ` ${v.w} × ${v.h} · ${v.ratio} `;
  const m = ctx.measureText(lbl);
  ctx.fillRect(w - m.width - 8, h - 22, m.width + 4, 18);
  ctx.fillStyle = '#fff';
  ctx.fillText(lbl, w - 6, h - 8);
  ctx.restore();
}

// ------- Platform preview rendering -------
function renderPlatformPreviews() {
  Object.keys(PLATFORM_TARGETS).forEach((p) => {
    const slot = document.querySelector(`[data-preview="${p}"]`);
    if (!slot) return;
    renderPlatformSlot(slot, PLATFORM_TARGETS[p]);
  });
  // Update FB spec label dynamically
  const fbSpec = document.querySelector('[data-fb-spec]');
  if (fbSpec) fbSpec.textContent = 'Link share · 1.91:1';
}

function renderPlatformSlot(slot, target) {
  // Use intrinsic dimensions from CSS aspect-ratio + width
  const rect = slot.getBoundingClientRect();
  if (rect.width === 0) return;

  // Ensure a canvas exists inside the slot
  let canvas = slot.querySelector('canvas');
  if (!canvas) {
    canvas = document.createElement('canvas');
    slot.innerHTML = '';
    slot.appendChild(canvas);
  }
  const dpr = window.devicePixelRatio || 1;
  const cw = rect.width;
  const ch = rect.height;
  canvas.width = cw * dpr;
  canvas.height = ch * dpr;
  canvas.style.width = cw + 'px';
  canvas.style.height = ch + 'px';
  const ctx = canvas.getContext('2d');
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  ctx.clearRect(0, 0, cw, ch);

  const v = activeVariant();
  // Render the variant first (the way the *master* would crop the original photo
  // into the variant frame), then crop *that* to the platform target.
  // Easier: render the original image cover-fit into the platform's frame,
  // because the user's variant choice represents the master they will publish.
  // But the variant is the file dimensions; the platform crops *the file*.
  // So: build an off-screen variant canvas first, then cover-fit-crop it.

  const variantCanvas = makeVariantCanvas(v);
  // Draw variantCanvas into platform slot using cover-fit
  drawImageCover(ctx, variantCanvas, 0, 0, cw, ch);
}

// Cache an off-screen canvas of the current variant
let _variantCache = null;
function makeVariantCanvas(v) {
  if (_variantCache && _variantCache.id === v.id && _variantCache.img === state.image) {
    return _variantCache.canvas;
  }
  // Render at a reasonable resolution (cap longest side to 1600 for perf)
  const longest = 1600;
  const ratio = v.w / v.h;
  let w, h;
  if (ratio >= 1) {
    w = Math.min(longest, v.w);
    h = w / ratio;
  } else {
    h = Math.min(longest, v.h);
    w = h * ratio;
  }
  const c = document.createElement('canvas');
  c.width = Math.round(w);
  c.height = Math.round(h);
  const ctx = c.getContext('2d');
  if (!state.image) {
    drawPlaceholder(ctx, c.width, c.height, v);
  } else {
    drawImageCover(ctx, state.image, 0, 0, c.width, c.height);
  }
  _variantCache = { id: v.id, img: state.image, canvas: c };
  return c;
}

// ------- Export -------
function exportAnnotated() {
  const v = activeVariant();
  // Render at full variant resolution (cap to 2000 longest)
  const longest = 2000;
  const ratio = v.w / v.h;
  let w, h;
  if (ratio >= 1) {
    w = Math.min(longest, v.w);
    h = w / ratio;
  } else {
    h = Math.min(longest, v.h);
    w = h * ratio;
  }
  const c = document.createElement('canvas');
  c.width = Math.round(w);
  c.height = Math.round(h);
  const ctx = c.getContext('2d');
  drawVariant(ctx, c.width, c.height, v, {
    safe: tgSafe.checked,
    crop: tgCrop.checked,
    grid: tgGrid.checked,
    center: tgCenter.checked,
    labels: tgLabels.checked,
  });
  c.toBlob(
    (blob) => {
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `og-${v.id}-${v.w}x${v.h}.png`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
    },
    'image/png',
  );
}

// ------- Render orchestration -------
function rerenderAll() {
  _variantCache = null; // bust cache when variant or image changes
  renderMaster();
  renderPlatformPreviews();
}

// ------- Wiring -------
function wire() {
  // Drag & drop
  ['dragenter', 'dragover'].forEach((ev) =>
    dropzone.addEventListener(ev, (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropzone.classList.add('dragging');
    }),
  );
  ['dragleave', 'drop'].forEach((ev) =>
    dropzone.addEventListener(ev, (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropzone.classList.remove('dragging');
    }),
  );
  dropzone.addEventListener('drop', (e) => {
    const file = e.dataTransfer?.files?.[0];
    if (file) loadFile(file);
  });
  dropzone.addEventListener('click', () => fileInput.click());
  dropzone.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      fileInput.click();
    }
  });
  fileInput.addEventListener('change', (e) => {
    const f = e.target.files?.[0];
    if (f) loadFile(f);
  });

  // URL
  urlLoadBtn.addEventListener('click', () => loadUrl(urlInput.value.trim()));
  urlInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') loadUrl(urlInput.value.trim());
  });

  // Samples
  document.querySelectorAll('.chip[data-sample]').forEach((b) =>
    b.addEventListener('click', () => loadSample(b.dataset.sample)),
  );

  // Toggles
  [tgSafe, tgCrop, tgGrid, tgCenter, tgLabels].forEach((el) =>
    el.addEventListener('change', rerenderAll),
  );

  // Export
  exportBtn.addEventListener('click', exportAnnotated);

  // Resize
  let rTimer = null;
  window.addEventListener('resize', () => {
    clearTimeout(rTimer);
    rTimer = setTimeout(rerenderAll, 100);
  });
}

// ------- Init -------
renderVariantButtons();
wire();
loadSample('gradient'); // start with a demo so previews aren't empty
