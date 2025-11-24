/* global html2canvas, jspdf */
const sendBtn = document.getElementById('send-email');
const sendTo = document.getElementById('send-to');
const sendSubject = document.getElementById('send-subject');
const sendStatus = document.getElementById('send-status');

async function generatePdf() {
  const actaNode = document.querySelector('.acta');
  if (!actaNode) throw new Error('No se encontró el acta en la página');

  const canvas = await html2canvas(actaNode, { scale: 2, useCORS: true });
  const imgData = canvas.toDataURL('image/png');
  const pdf = new jspdf.jsPDF('p', 'mm', 'a4');

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

  return pdf.output('datauristring');
}

function collectActaData() {
  const getText = (id) => (document.getElementById(id)?.textContent || '').trim();
  return {
    folio: getText('acta-folio'),
    lugar: getText('acta-lugar'),
    fecha: getText('acta-fecha'),
    entrega: getText('acta-entrega'),
    recibe: getText('acta-recibe'),
    clausulas: getText('acta-clausulas'),
    modelo: getText('acta-modelo'),
    marca: getText('acta-marca'),
    procesador: getText('acta-procesador'),
    ram: getText('acta-ram'),
    disco: getText('acta-disco'),
    serie: getText('acta-serie'),
    cantidad: getText('acta-cantidad'),
    estado_bien: getText('acta-estado-bien'),
    descripcion: getText('acta-descripcion'),
    observaciones: getText('acta-observaciones')
  };
}

async function handleSend() {
  const to = sendTo.value.trim();
  const subject = sendSubject.value.trim() || 'Acta de entrega y recepción';
  if (!to) {
    sendStatus.textContent = 'Escribe un correo de destino';
    return;
  }

  sendStatus.textContent = 'Generando PDF y enviando...';
  sendBtn.disabled = true;

  try {
    const sigEntrega = document.getElementById('sig-entrega')?.toDataURL('image/png') || '';
    const sigRecibe = document.getElementById('sig-recibe')?.toDataURL('image/png') || '';
    const pdfDataUri = await generatePdf();
    const acta = collectActaData();

    const res = await fetch('../api/send-acta.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        to,
        subject,
        acta,
        firma_entrega: sigEntrega,
        firma_recibe: sigRecibe,
        pdf_base64: pdfDataUri
      })
    });
    const data = await res.json();
    if (!res.ok || !data.sent) throw new Error(data.error || 'No se pudo enviar el correo');
    sendStatus.textContent = 'Enviado con éxito';
  } catch (err) {
    sendStatus.textContent = err.message || 'Error al enviar';
  } finally {
    sendBtn.disabled = false;
  }
}

if (sendBtn) {
  sendBtn.addEventListener('click', handleSend);
}
