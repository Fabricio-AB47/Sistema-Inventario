function setupSignaturePad(canvasId) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  let drawing = false;
  let lastPoint = null;

  const resizeCanvas = () => {
    const data = canvas.toDataURL();
    canvas.width = canvas.clientWidth;
    canvas.height = canvas.clientHeight;
    const img = new Image();
    img.onload = () => {
      ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
    };
    img.src = data;
  };

  window.addEventListener('resize', resizeCanvas);
  resizeCanvas();

  const getPos = (e) => {
    const rect = canvas.getBoundingClientRect();
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    return {
      x: clientX - rect.left,
      y: clientY - rect.top
    };
  };

  const start = (e) => {
    drawing = true;
    lastPoint = getPos(e);
  };

  const move = (e) => {
    if (!drawing) return;
    const pos = getPos(e);
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#0a52ff'; // azul bolígrafo
    ctx.beginPath();
    ctx.moveTo(lastPoint.x, lastPoint.y);
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
    lastPoint = pos;
  };

  const end = () => {
    drawing = false;
    lastPoint = null;
  };

  // Pointer events para mouse/táctil sin interrupciones
  const startWrapper = (e) => {
    e.preventDefault();
    start(e);
  };
  const moveWrapper = (e) => {
    e.preventDefault();
    move(e);
  };

  canvas.addEventListener('pointerdown', startWrapper);
  canvas.addEventListener('pointermove', moveWrapper);
  canvas.addEventListener('pointerup', end);
  canvas.addEventListener('pointerleave', end);
  canvas.addEventListener('pointercancel', end);

  const clearBtn = document.querySelector(`[data-clear="${canvasId}"]`);
  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
    });
  }
}

document.addEventListener('DOMContentLoaded', () => {
  setupSignaturePad('sig-entrega');
  setupSignaturePad('sig-recibe');
  setupSignaturePad('asig-sig-entrega');
  setupSignaturePad('asig-sig-recibe');
});
