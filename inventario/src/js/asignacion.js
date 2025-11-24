/* global html2canvas, jspdf */
const btnGuardarMov = document.getElementById('asig-guardar');
const selEquipo = document.getElementById('asig-equipo');
const selUsuario = document.getElementById('asig-usuario');
const inpObs = document.getElementById('asig-obs');
const chkRevision = document.getElementById('asig-revision');
const msgBox = document.getElementById('asig-msg');
const detEquipo = document.getElementById('det-equipo');
const detUsuario = document.getElementById('det-usuario');
const detObs = document.getElementById('det-obs');
const spanRecibe = document.getElementById('asig-recibe-name');
const meta = document.getElementById('asig-meta');
const tplCargoLive = document.getElementById('tpl-cargo');
const usuarioRolBadge = document.getElementById('asig-usuario-rol');
const estadoActivoBadge = document.getElementById('asig-estado-activo');
const usuarioCorreoBadge = document.getElementById('asig-usuario-correo');
const sigEntregaCanvas = document.getElementById('asig-sig-entrega');
const sigRecibeCanvas = document.getElementById('asig-sig-recibe');
const modal = document.getElementById('asig-modal');
const modalBody = document.getElementById('asig-modal-body');
const modalTitle = document.getElementById('asig-modal-title');
const modalOk = document.getElementById('asig-modal-ok');
const modalCancel = document.getElementById('asig-modal-cancel');

function showMsg(text, tone = 'muted') {
  if (msgBox) {
    msgBox.textContent = text;
    msgBox.className = `card-subtitle ${tone}`;
  }
}

function isCanvasBlank(canvasId) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return true;
  const ctx = canvas.getContext('2d');
  const pixelBuffer = new Uint32Array(ctx.getImageData(0, 0, canvas.width, canvas.height).data.buffer);
  return !pixelBuffer.some((color) => color !== 0);
}

function updateDetails() {
  const eqOpt = selEquipo?.options[selEquipo.selectedIndex];
  const usOpt = selUsuario?.options[selUsuario.selectedIndex];
  const obs = inpObs.value.trim();
  const rol = usOpt?.dataset?.rol || '';
  const area = rol;

  const ramTxt = eqOpt?.dataset?.ram ? `RAM: ${eqOpt.dataset.ram} GB` : '';
  const discoTxt = eqOpt?.dataset?.disco ? `Disco: ${eqOpt.dataset.disco} GB` : '';
  const eqInfo = eqOpt?.value
    ? `${eqOpt.dataset.modelo || ''} ${eqOpt.dataset.procesador || ''} ${ramTxt} ${discoTxt} / Serie: ${eqOpt.dataset.serie || ''}`
        .replace(/\s+/g, ' ')
        .trim()
    : 'Equipo: -';
  const usInfo = usOpt?.value ? `${usOpt.dataset.nombre || usOpt.text}` : 'Usuario: -';

  if (detEquipo) detEquipo.textContent = eqOpt?.value ? eqInfo : 'Equipo: -';
  if (detUsuario) detUsuario.textContent = usOpt?.value ? usInfo : 'Usuario: -';
  if (detObs) detObs.textContent = obs ? `Observaciones: ${obs}` : 'Observaciones: -';
  if (spanRecibe) spanRecibe.textContent = usOpt?.dataset?.nombre || 'Nombre y firma';
  if (tplCargoLive) tplCargoLive.textContent = rol || '__________________________';
  const tplAreaLive = document.getElementById('tpl-area');
  if (tplAreaLive) tplAreaLive.textContent = area || '__________________________';
  if (usuarioRolBadge) {
    usuarioRolBadge.textContent = rol ? `Rol: ${rol}` : 'Rol: -';
  }
  if (usuarioCorreoBadge) {
    const correo = usOpt?.dataset?.correo || '';
    usuarioCorreoBadge.textContent = correo ? `Correo: ${correo}` : 'Correo: -';
  }
  if (estadoActivoBadge) {
    const estadoTxt = eqOpt?.dataset?.estado || '';
    const estadoId = parseInt(eqOpt?.dataset?.estadoId || eqOpt?.dataset?.estado_id || '0', 10);
    estadoActivoBadge.textContent = estadoTxt ? `Estado: ${estadoTxt}` : 'Estado: -';
    estadoActivoBadge.classList.remove('chip', 'success', 'warning', 'info');
    estadoActivoBadge.classList.add('chip');
    if (estadoId === 2) {
      estadoActivoBadge.classList.add('success');
    } else if (estadoId === 1 || estadoId === 5) {
      estadoActivoBadge.classList.add('warning');
    } else {
      estadoActivoBadge.classList.add('info');
    }
  }
}

async function ensureHtml2Canvas() {
  if (window.html2canvas) return;
  await new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
    script.onload = resolve;
    script.onerror = () => reject(new Error('No se pudo cargar html2canvas'));
    document.head.appendChild(script);
  });
}

async function ensureJsPdf() {
  if (window.jspdf && window.jspdf.jsPDF) return;
  await new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
    script.onload = resolve;
    script.onerror = () => reject(new Error('No se pudo cargar jsPDF'));
    document.head.appendChild(script);
  });
}

async function buildPdf() {
  await ensureHtml2Canvas();
  await ensureJsPdf();

  const eqOpt = selEquipo.options[selEquipo.selectedIndex];
  const usOpt = selUsuario.options[selUsuario.selectedIndex];

  const obs = inpObs.value.trim() || 'Ninguna';
  const revision = chkRevision.checked ? 'Revisado' : 'Sin revisar';
  const hoy = new Date();
  const dd = String(hoy.getDate()).padStart(2, '0');
  const mm = String(hoy.getMonth() + 1).padStart(2, '0');
  const yyyy = hoy.getFullYear();
  const fechaStr = `${dd}/${mm}/${yyyy}`;

  const entregaName = meta ? meta.dataset.entrega || '' : '';
  const entregaRol = meta ? meta.dataset.entregaRol || 'ADMINISTRADOR' : 'ADMINISTRADOR';
  const entregaDisplay = entregaRol ? `${entregaName} (${entregaRol})` : entregaName;

  const modelo = eqOpt?.dataset?.modelo || '';
  const marca = eqOpt?.dataset?.marca || '';
  const serie = eqOpt?.dataset?.serie || '';
  const proc = eqOpt?.dataset?.procesador || '';
  const ram = eqOpt?.dataset?.ram || '';
  const disco = eqOpt?.dataset?.disco || '';

  const descParts = [];
  if (modelo) descParts.push(modelo);
  if (proc) descParts.push(proc);
  if (ram) descParts.push(`RAM: ${ram} GB`);
  if (disco) descParts.push(`Disco: ${disco} GB`);
  const desc = descParts.join(' | ');

  const recibeNombre = usOpt?.dataset?.nombre || usOpt?.text || '';
  const rolRecibe = usOpt?.dataset?.rol || '';
  const areaRecibe = rolRecibe || '';

  const tpl = document.getElementById('asig-template');
  document.getElementById('tpl-fecha').textContent = fechaStr;
  document.getElementById('tpl-fecha2').textContent = fechaStr;
  document.getElementById('tpl-entrega').textContent = entregaDisplay || '__________________________';
  document.getElementById('tpl-recibe').textContent = recibeNombre || '__________________________';
  const tplEntNom = document.getElementById('tpl-entrega-nombre');
  if (tplEntNom) tplEntNom.textContent = entregaDisplay || '__________________________';
  const tplRecNom = document.getElementById('tpl-recibe-nombre');
  if (tplRecNom) tplRecNom.textContent = recibeNombre || '__________________________';
  document.getElementById('tpl-desc').textContent = desc || eqOpt?.text || '';
  document.getElementById('tpl-marca').textContent = marca;
  document.getElementById('tpl-ref').textContent = serie || modelo;
  document.getElementById('tpl-estado').textContent = revision;

  const obsDet = [];
  if (obs) obsDet.push(obs);
  if (ram) obsDet.push(`RAM: ${ram} GB`);
  if (disco) obsDet.push(`Disco: ${disco} GB`);
  document.getElementById('tpl-obs').textContent = obsDet.join(' | ') || obs;

  document.getElementById('tpl-cargo').textContent = rolRecibe || '__________________________';
  const tplArea = document.getElementById('tpl-area');
  if (tplArea) tplArea.textContent = areaRecibe || '__________________________';

  const sigEnt = sigEntregaCanvas?.toDataURL('image/png') || '';
  const sigRec = sigRecibeCanvas?.toDataURL('image/png') || '';
  document.getElementById('tpl-sig-entrega').src = sigEnt;
  document.getElementById('tpl-sig-recibe').src = sigRec;

  tpl.style.display = 'block';
  const canvas = await window.html2canvas(tpl, { scale: 2, useCORS: true });
  tpl.style.display = 'none';

  const imgData = canvas.toDataURL('image/png');
  const pdf = new window.jspdf.jsPDF('p', 'mm', 'a4');
  const pageWidth = pdf.internal.pageSize.getWidth();
  const pageHeight = pdf.internal.pageSize.getHeight();
  const imgProps = pdf.getImageProperties(imgData);
  const imgHeight = (imgProps.height * pageWidth) / imgProps.width;
  let heightLeft = imgHeight;
  let position = 0;

  pdf.addImage(imgData, 'PNG', 0, position, pageWidth, imgHeight);
  heightLeft -= pageHeight;
  while (heightLeft > 0) {
    position = heightLeft - imgHeight;
    pdf.addPage();
    pdf.addImage(imgData, 'PNG', 0, position, pageWidth, imgHeight);
    heightLeft -= pageHeight;
  }

  const pdfUri = pdf.output('datauristring');
  return { pdf, pdfUri };
}

async function sendEmail(to, pdfUri) {
  if (!to) {
    to = meta?.dataset?.adminmail || '';
  }
  if (!to) throw new Error('No hay correo de destino para enviar el acta');
  const eqOpt = selEquipo.options[selEquipo.selectedIndex];
  const usOpt = selUsuario.options[selUsuario.selectedIndex];
  const obs = inpObs.value.trim() || 'Ninguna';

  const acta = {
    folio: '',
    fecha: new Date().toISOString().slice(0, 10),
    entrega: meta?.dataset?.entrega || '',
    recibe: usOpt?.dataset?.nombre || usOpt?.text || '',
    rol: usOpt?.dataset?.rol || '',
    clausulas: 'Entrega de equipo con firma digital',
    modelo: eqOpt?.dataset?.modelo || '',
    marca: eqOpt?.dataset?.marca || '',
    procesador: eqOpt?.dataset?.procesador || '',
    ram: eqOpt?.dataset?.ram || '',
    disco: eqOpt?.dataset?.disco || '',
    serie: eqOpt?.dataset?.serie || '',
    observaciones: obs
  };

  const resp = await fetch('../api/send-acta.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      to,
      cc: ['maria.yauri@intec.edu.ec'],
      subject: 'Acta de entrega/recepcion de equipo',
      acta,
      firma_entrega: sigEntregaCanvas?.toDataURL('image/png') || '',
      firma_recibe: sigRecibeCanvas?.toDataURL('image/png') || '',
      pdf_base64: pdfUri
    })
  });
  if (!resp.ok) {
    const data = await resp.json().catch(() => ({}));
    throw new Error(data.error || 'No se pudo enviar el correo');
  }
}

async function guardarMovimiento() {
  const id_equipo = selEquipo.value || '';
  const id_usuario = parseInt(selUsuario.value || '0', 10);
  const eqOpt = selEquipo?.options[selEquipo.selectedIndex];
  const estadoId = parseInt(eqOpt?.dataset?.estadoId || eqOpt?.dataset?.estado_id || '0', 10);
  const estadoTxt = eqOpt?.dataset?.estado || '';
  if (!id_equipo || !id_usuario) {
    showMsg('Selecciona equipo y usuario', 'danger');
    return;
  }
  // Alertas segun estado
  if (estadoId === 1) {
    const conf = await showConfirm(`El equipo está ASIGNADO (${estadoTxt}). ¿Deseas reasignarlo?`, 'Confirmar reasignación');
    if (!conf) return;
  } else if (estadoId !== 0 && estadoId !== 2 && estadoId !== 5) {
    const conf = await showConfirm(`El equipo está en estado "${estadoTxt}". ¿Continuar de todos modos?`, 'Confirmar');
    if (!conf) return;
  }
  if (isCanvasBlank('asig-sig-recibe')) {
    showMsg('Dibuja la firma de quien recibe antes de guardar', 'danger');
    return;
  }
  showMsg('Guardando movimiento...', 'muted');
  try {
    const { pdf, pdfUri } = await buildPdf();
    const res = await fetch('../api/movimientos.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        id_equipo,
        id_usuario,
        observaciones: inpObs.value.trim(),
        revision_serie: chkRevision.checked,
        documento_base64: pdfUri
      })
    });
    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      throw new Error(text || 'Respuesta no válida del servidor');
    }
    if (!res.ok) throw new Error(data.error || data.detail || 'No se pudo registrar');

    const usOpt = selUsuario.options[selUsuario.selectedIndex];
    const to = usOpt?.dataset?.correo || '';
    await sendEmail(to, pdfUri);

    pdf.save('movimiento-asignacion.pdf');
    showMsg('Movimiento registrado, documento generado y enviado', 'success');
  } catch (err) {
    showMsg(err.message || 'Error', 'danger');
  }
}

if (btnGuardarMov) btnGuardarMov.addEventListener('click', guardarMovimiento);
if (selEquipo) selEquipo.addEventListener('change', updateDetails);
if (selUsuario) selUsuario.addEventListener('change', updateDetails);
if (inpObs) inpObs.addEventListener('input', updateDetails);
document.addEventListener('DOMContentLoaded', updateDetails);

function showConfirm(message, title = 'Aviso') {
  return new Promise((resolve) => {
    if (!modal || !modalBody || !modalTitle || !modalOk || !modalCancel) {
      resolve(window.confirm(message));
      return;
    }
    modalTitle.textContent = title;
    modalBody.textContent = message;
    modal.classList.remove('hidden');
    const cleanup = () => {
      modal.classList.add('hidden');
      modalOk.onclick = null;
      modalCancel.onclick = null;
    };
    modalOk.onclick = () => {
      cleanup();
      resolve(true);
    };
    modalCancel.onclick = () => {
      cleanup();
      resolve(false);
    };
  });
}
