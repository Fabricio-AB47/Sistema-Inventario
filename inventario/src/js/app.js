const API_URL = '../api/inventory.php';

const state = {
  items: []
};

const form = document.getElementById('inventory-form');
const tableBody = document.getElementById('inventory-body');
const filterTipo = document.getElementById('filter-tipo');
const filterEstado = document.getElementById('filter-estado');
const filterTerm = document.getElementById('filter-term');
const messageBox = document.getElementById('message');
const selectEquipo = document.getElementById('id_equipo');
const selectUsuario = document.getElementById('id_usuario');

let equiposCache = [];
let usuariosCache = [];

const statusMap = {
  pendiente: 'Pendiente',
  progreso: 'En progreso',
  cerrado: 'Cerrado'
};

document.addEventListener('DOMContentLoaded', () => {
  loadCatalogs();
  fetchItems();
  bindEvents();
  setDefaultDate();
});

function bindEvents() {
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const payload = getFormPayload();
    if (!payload) {
      return;
    }

    try {
      const res = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (!res.ok) {
        throw new Error('No se pudo guardar el registro');
      }

      const created = await res.json();
      state.items.unshift(created);
      renderTable();
      form.reset();
      showMessage('Registro agregado', 'success');
    } catch (error) {
      showMessage(error.message, 'danger');
    }
  });

  [filterTipo, filterEstado].forEach((filter) => {
    filter.addEventListener('change', () => renderTable());
  });

  filterTerm.addEventListener('input', () => renderTable());

  tableBody.addEventListener('change', (event) => {
    if (event.target.matches('.status-select')) {
      updateStatus(event.target.dataset.id, event.target.value);
    }
  });

  tableBody.addEventListener('click', (event) => {
    if (event.target.matches('.delete-btn')) {
      const id = event.target.dataset.id;
      deleteItem(id);
    }
  });
}

async function fetchItems() {
  try {
    const res = await fetch(API_URL);
    const data = await res.json();
    state.items = Array.isArray(data) ? data.sort(sortByDate) : [];
    renderTable();
  } catch (error) {
    showMessage('No se pudo obtener el inventario', 'danger');
  }
}

async function updateStatus(id, estado) {
  try {
    const res = await fetch(`${API_URL}?id=${encodeURIComponent(id)}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ estado })
    });

    if (!res.ok) {
      throw new Error('No se pudo actualizar el estado');
    }

    const updated = await res.json();
    state.items = state.items.map((item) => (item.id === id ? updated : item));
    renderTable();
    showMessage('Estado actualizado', 'success');
  } catch (error) {
    showMessage(error.message, 'danger');
  }
}

async function deleteItem(id) {
  if (!confirm('¿Eliminar este registro?')) return;

  try {
    const res = await fetch(`${API_URL}?id=${encodeURIComponent(id)}`, {
      method: 'DELETE'
    });

    if (!res.ok) {
      throw new Error('No se pudo eliminar el registro');
    }

    state.items = state.items.filter((item) => item.id !== id);
    renderTable();
    showMessage('Registro eliminado', 'success');
  } catch (error) {
    showMessage(error.message, 'danger');
  }
}

function getFormPayload() {
  const tipo = form.tipo.value;
  const folio = form.folio.value.trim();
  const fecha = form.fecha.value;
  const responsable = form.responsable.value.trim();
  const idEquipo = form.id_equipo.value;
  const idUsuario = form.id_usuario.value;
  const estado = form.estado.value;
  const descripcion = form.descripcion.value.trim();
  const observaciones = form.observaciones.value.trim();

  if (!tipo || !folio || !fecha || !responsable || !idEquipo || !idUsuario) {
    showMessage('Completa los campos obligatorios (folio, fecha, responsable, equipo, usuario)', 'danger');
    return null;
  }

  return {
    tipo,
    folio,
    fecha,
    responsable,
    estado,
    descripcion,
    observaciones,
    id_equipo: idEquipo,
    id_usuario: parseInt(idUsuario, 10)
  };
}

function renderTable() {
  const filtered = getFilteredItems();

  document.getElementById('stats-total').textContent = state.items.length;
  document.getElementById('stats-pendientes').textContent = state.items.filter((i) => i.estado === 'pendiente').length;
  document.getElementById('stats-cerrados').textContent = state.items.filter((i) => i.estado === 'cerrado').length;

  if (!filtered.length) {
    tableBody.innerHTML = `<tr><td colspan="7" class="empty">Sin registros aún</td></tr>`;
    return;
  }

  tableBody.innerHTML = filtered
    .map(
      (item) => `
      <tr>
        <td>
          <div><strong>${escapeHtml(item.folio)}</strong></div>
          <div class="muted">${formatDate(item.fecha || item.created_at)}</div>
        </td>
        <td><span class="pill-compact">${item.tipo}</span></td>
        <td>${escapeHtml(item.responsable || '—')}</td>
        <td>${escapeHtml(resolveUsuario(item.id_usuario))}</td>
        <td>${escapeHtml(resolveEquipo(item.id_equipo))}</td>
        <td>
          <div class="status-pill ${getStatusClass(item.estado)}">${statusMap[item.estado] || item.estado}</div>
          <select class="status-select full" data-id="${item.id}">
            ${Object.entries(statusMap)
              .map(([key, label]) => `<option value="${key}" ${item.estado === key ? 'selected' : ''}>${label}</option>`)
              .join('')}
          </select>
        </td>
        <td>${escapeHtml(item.descripcion || 'Sin detalle')}</td>
        <td>${escapeHtml(item.observaciones || '—')}</td>
        <td>
          <div class="table-actions">
            <button class="btn-secondary delete-btn" data-id="${item.id}">Eliminar</button>
          </div>
        </td>
      </tr>
    `
    )
    .join('');
}

function getFilteredItems() {
  const term = filterTerm.value.trim().toLowerCase();
  const tipo = filterTipo.value;
  const estado = filterEstado.value;

  return state.items.filter((item) => {
    const matchesTipo = tipo === 'todos' || item.tipo === tipo;
    const matchesEstado = estado === 'todos' || item.estado === estado;
    const matchesTerm =
      !term ||
      (item.folio && item.folio.toLowerCase().includes(term)) ||
      (item.responsable && item.responsable.toLowerCase().includes(term)) ||
      (item.descripcion && item.descripcion.toLowerCase().includes(term));

    return matchesTipo && matchesEstado && matchesTerm;
  });
}

function getStatusClass(status) {
  if (status === 'cerrado') return 'status-cerrado';
  if (status === 'progreso') return 'status-progreso';
  return 'status-pendiente';
}

function sortByDate(a, b) {
  const dateA = new Date(a.fecha || a.created_at || 0).getTime();
  const dateB = new Date(b.fecha || b.created_at || 0).getTime();
  return dateB - dateA;
}

function formatDate(date) {
  if (!date) return 'Sin fecha';
  return new Date(date).toLocaleDateString('es-ES');
}

function escapeHtml(text) {
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function showMessage(text, tone = 'muted') {
  if (!messageBox) return;
  messageBox.textContent = text;
  messageBox.className = `card-subtitle ${tone}`;
  setTimeout(() => {
    messageBox.textContent = '';
    messageBox.className = 'card-subtitle muted';
  }, 3200);
}

async function loadCatalogs() {
  try {
    const [equiposRes, usuariosRes] = await Promise.all([
      fetch('../api/equipos.php'),
      fetch('../api/users.php')
    ]);
    const eqData = await equiposRes.json();
    const usData = await usuariosRes.json();
    if (equiposRes.ok) {
      equiposCache = eqData;
      renderOptions(selectEquipo, eqData, 'id_equipo', (row) => `${row.modelo} - ${row.serie}`);
    }
    if (usuariosRes.ok) {
      usuariosCache = usData;
      renderOptions(selectUsuario, usData, 'id_usuario', (row) => `${row.nombres} ${row.apellidos}`);
    }
  } catch (err) {
    showMessage('No se pudieron cargar equipos/usuarios', 'danger');
  }
}

function renderOptions(select, list, valueKey, labelFn) {
  if (!select || !Array.isArray(list)) return;
  select.innerHTML = '<option value=\"\">Seleccione</option>' + list
    .map((item) => `<option value=\"${item[valueKey]}\">${escapeHtml(labelFn(item))}</option>`)
    .join('');
}

function resolveEquipo(id) {
  const found = equiposCache.find((e) => e.id_equipo == id);
  return found ? `${found.modelo} (${found.serie})` : (id ? `Equipo ${id}` : '—');
}

function resolveUsuario(id) {
  const found = usuariosCache.find((u) => u.id_usuario == id);
  return found ? found.nombres + ' ' + found.apellidos : (id ? `Usuario ${id}` : '—');
}
function setDefaultDate() {
  const dateInput = document.getElementById('fecha');
  if (!dateInput) return;
  const today = new Date();
  const yyyy = today.getFullYear();
  const mm = String(today.getMonth() + 1).padStart(2, '0');
  const dd = String(today.getDate()).padStart(2, '0');
  dateInput.value = `${yyyy}-${mm}-${dd}`;
}
