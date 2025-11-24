<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
require_once __DIR__ . '/../config/db.php';
$pdo = db();

$equipos = $pdo->query("
    SELECT e.id_equipo, e.modelo, e.marca, e.num_serie AS serie, e.hostname AS procesador, e.memoria_ram AS ram, e.almacenamiento AS disco,
           te.descripcion_tp_equipo AS tipo,
           ea.id_estado_activo, ea.descripcion_estado_activo AS estado_activo
    FROM equipo e
    INNER JOIN tipo_equipo te ON te.id_tp_equipo = e.id_tp_equipo
    INNER JOIN estado_activo ea ON ea.id_estado_activo = e.id_estado_activo
    ORDER BY e.id_equipo DESC
")->fetchAll();

$usuarios = $pdo->query("
    SELECT u.id_user AS id_usuario, u.nombre AS nombres, u.apellidos, u.correo, t.descripcion_perfil AS nombre_tipo
    FROM usuario u
    INNER JOIN tipo_perfil t ON t.id_tp_perfil = u.id_tp_perfil
    ORDER BY u.nombre
")->fetchAll();

// Definir entregante como el usuario en sesión (admin actual)
$adminEntregaName = trim((string)($user['name'] ?? $user['email'] ?? ''));
if ($adminEntregaName === '') {
    $adminEntregaName = trim(($user['email'] ?? ''));
}
$adminEntregaRol = $user['tipo_nombre'] ?? 'ADMINISTRADOR';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Asignacion de equipo</title>
  <link rel="stylesheet" href="assets/css/main.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" integrity="sha512-BNaLbK5aG3bS5qPXHzveLFb0UBPt16XWZCmhjHn28hSUg+l49VqSWtZ6TxVRGm+e2murJvzN4zXJUGd2at+lJQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" integrity="sha512-/pGS1cnFlvQ4biWi8mUaaaabKD6c2S5+xFd3FsPN6m7kmJ1D2VjhidrVnkC1Q3cf8xafYL4Wk2ox1d5qsI+aug==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
</head>
<body>
  <div class="app-shell">
    <?php include __DIR__ . '/navbar.php'; ?>

    <section class="hero">
      <div class="hero-header">
        <div class="pill">Asignacion</div>
        <div>
          <h1>Asignar equipo a usuario</h1>
          <p>Crea el movimiento y genera un documento con firmas de entrega y recepcion.</p>
        </div>
      </div>
    </section>

      <div class="card">
        <h2>Datos de asignacion</h2>
        <p class="card-subtitle muted" id="asig-msg">Selecciona equipo y usuario, agrega observaciones y firma.</p>
      <div id="asig-meta"
           data-entrega="<?php echo htmlspecialchars($adminEntregaName, ENT_QUOTES, 'UTF-8'); ?>"
           data-entrega-rol="<?php echo htmlspecialchars($adminEntregaRol, ENT_QUOTES, 'UTF-8'); ?>"
           data-adminmail="<?php echo htmlspecialchars($user['correo'] ?? $user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
      <div class="asig-grid">
        <label>
          Equipo
          <div class="input-with-badge">
            <select id="asig-equipo" class="asig-control">
              <option value="">Seleccione equipo</option>
              <?php foreach ($equipos as $eq): ?>
                <option value="<?php echo htmlspecialchars($eq['id_equipo'], ENT_QUOTES, 'UTF-8'); ?>"
                  data-modelo="<?php echo htmlspecialchars($eq['modelo'], ENT_QUOTES, 'UTF-8'); ?>"
                  data-marca="<?php echo htmlspecialchars($eq['marca'], ENT_QUOTES, 'UTF-8'); ?>"
                  data-procesador="<?php echo htmlspecialchars($eq['procesador'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                  data-ram="<?php echo htmlspecialchars($eq['ram'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                  data-disco="<?php echo htmlspecialchars($eq['disco'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                  data-serie="<?php echo htmlspecialchars($eq['serie'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                  data-tipo="<?php echo htmlspecialchars($eq['tipo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                  data-estado-id="<?php echo (int)$eq['id_estado_activo']; ?>"
                  data-estado="<?php echo htmlspecialchars($eq['estado_activo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                >
                  <?php echo htmlspecialchars($eq['modelo'] . ' - ' . $eq['serie'] . ' (' . $eq['tipo'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="chip-row">
              <span class="chip" id="asig-estado-activo">Estado: -</span>
            </div>
          </div>
        </label>
        <label>
          Usuario
          <div class="input-with-badge">
            <select id="asig-usuario" class="asig-control">
              <option value="">Seleccione usuario</option>
              <?php foreach ($usuarios as $u): ?>
                <option value="<?php echo (int)$u['id_usuario']; ?>"
                  data-nombre="<?php echo htmlspecialchars($u['nombres'] . ' ' . $u['apellidos'], ENT_QUOTES, 'UTF-8'); ?>"
                  data-correo="<?php echo htmlspecialchars($u['correo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                  data-rol="<?php echo htmlspecialchars($u['nombre_tipo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars($u['nombres'] . ' ' . $u['apellidos'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="chip-row">
              <span class="chip" id="asig-usuario-rol">Rol: -</span>
              <span class="chip" id="asig-usuario-correo">Correo: -</span>
            </div>
          </div>
        </label>
        <label>
          Observaciones
          <input type="text" id="asig-obs" class="asig-control" placeholder="Opcional">
        </label>
        <label style="display:flex; gap:8px; align-items:center;">
          <input type="checkbox" id="asig-revision" checked> Revisar serie
        </label>
      </div>
      <div class="card-subtitle muted">Firma del entregante y receptor:</div>
      <div class="meta" style="gap:16px; flex-wrap:wrap; margin-bottom:8px;">
        <div id="det-equipo" class="badge" style="flex:1 1 260px;">Equipo: -</div>
        <div id="det-usuario" class="badge" style="flex:1 1 260px;">Usuario: -</div>
        <div id="det-obs" class="badge" style="flex:1 1 200px;">Observaciones: -</div>
      </div>
      <div class="acta__signatures">
        <div class="acta__sig">
          <canvas class="signature-pad" id="asig-sig-entrega"></canvas>
          <div class="sig-actions">
            <button class="btn-secondary" type="button" data-clear="asig-sig-entrega">Limpiar</button>
          </div>
          <div class="acta__sig-line"></div>
          <div><strong>Entrega</strong></div>
          <small id="asig-entrega-name"><?php echo htmlspecialchars($adminEntregaName, ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
        <div class="acta__sig">
          <canvas class="signature-pad" id="asig-sig-recibe"></canvas>
          <div class="sig-actions">
            <button class="btn-secondary" type="button" data-clear="asig-sig-recibe">Limpiar</button>
          </div>
          <div class="acta__sig-line"></div>
          <div><strong>Recibe</strong></div>
          <small id="asig-recibe-name">Nombre y firma</small>
        </div>
      </div>
      <div class="actions" style="margin-top:12px; justify-content:flex-start; gap:10px;">
        <button class="btn" id="asig-guardar">Registrar movimiento</button>
      </div>
    </div>

    <!-- Plantilla oculta para PDF -->
    <style>
      #asig-template, #asig-template * { color: #000 !important; font-family: Arial, sans-serif; }
    </style>
    <div id="asig-template" style="display:none; background:#fff; color:#000; padding:16px; border:1px solid #000; font-family: Arial, sans-serif; font-size:12px; width:800px;">
      <table style="width:100%; border-collapse:collapse; margin-bottom:8px;">
        <tr>
          <td style="width:30%; text-align:left; vertical-align:top;">
            <strong>INSTITUTO SUPERIOR TECNOLOGICO INTEC</strong><br>
            <span style="font-size:10px;">ACTA DE ENTREGA ACTIVOS FIJOS</span>
          </td>
          <td style="width:40%; text-align:center; font-weight:bold;">
            <br>ACTA DE ENTREGA
          </td>
          <td style="width:30%; text-align:right; font-size:10px;">
            Fecha: <span id="tpl-fecha"></span><br>
            Version: 01<br>
            Pagina 1 de 1
          </td>
        </tr>
      </table>

      <table style="width:100%; border-collapse:collapse; margin-bottom:8px;">
        <tr>
          <td style="width:80px; font-weight:bold;">FECHA:</td>
          <td><span id="tpl-fecha2"></span></td>
        </tr>
        <tr>
          <td style="font-weight:bold;">ENTREGA:</td>
          <td><span id="tpl-entrega"></span></td>
        </tr>
        <tr>
          <td style="font-weight:bold;">RECIBE:</td>
          <td><span id="tpl-recibe"></span></td>
        </tr>
      </table>

      <div style="margin:6px 0;">Por medio de la presente, se hace entrega formal del/los siguiente(s) activo(s) fijo(s):</div>

      <table style="width:100%; border-collapse:collapse; margin-bottom:8px;">
        <thead>
          <tr>
            <th style="border:1px solid #000; padding:4px; text-align:center; color:#000;">DESCRIPCION DEL ACTIVO</th>
            <th style="border:1px solid #000; padding:4px; text-align:center; color:#000;">MARCA</th>
            <th style="border:1px solid #000; padding:4px; text-align:center; color:#000;">NUMERO DE SERIE</th>
            <th style="border:1px solid #000; padding:4px; text-align:center; color:#000;">ESTADO</th>
            <th style="border:1px solid #000; padding:4px; text-align:center; color:#000;">OBSERVACIONES</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="border:1px solid #000; padding:4px;" id="tpl-desc"></td>
            <td style="border:1px solid #000; padding:4px;" id="tpl-marca"></td>
            <td style="border:1px solid #000; padding:4px;" id="tpl-ref"></td>
            <td style="border:1px solid #000; padding:4px;" id="tpl-estado"></td>
            <td style="border:1px solid #000; padding:4px;" id="tpl-obs"></td>
          </tr>
        </tbody>
      </table>

      <table style="width:100%; border-collapse:collapse; margin-top:12px;">
        <tr>
          <td style="width:50%; text-align:center; padding:6px;">
            <img id="tpl-sig-entrega" alt="Firma entrega" style="max-width:200px; max-height:80px;"><br>
            <div class="linea-firma" style="border-top:1px solid #000; margin-top:6px;">&nbsp;</div>
            <span>ENTREGA</span><br>
            <small id="tpl-entrega-nombre" style="font-size:10px; color:#000;">__________________________</small>
          </td>
          <td style="width:50%; text-align:center; padding:6px;">
            <img id="tpl-sig-recibe" alt="Firma recibe" style="max-width:200px; max-height:80px;"><br>
            <div class="linea-firma" style="border-top:1px solid #000; margin-top:6px;">&nbsp;</div>
            <span>RECIBE</span><br>
            <small id="tpl-recibe-nombre" style="font-size:10px; color:#000;">__________________________</small>
          </td>
        </tr>
      </table>

      <div class="pie-texto" style="margin-top:10px; font-size:10px; color:#000;">
        <div style="margin-bottom:4px;">Quien esta en el area de: <span id="tpl-cargo">__________________________</span>, como responsable de los activos asignados, adquiere el compromiso de informar oportunamente cualquier novedad (danos, mantenimiento, movimientos o robo).</div>
        <div style="margin-bottom:4px;">En caso de presentarse un dano de fabrica, debera notificar de inmediato al area de Tecnologias de la Informacion para hacer valida la garantia correspondiente.</div>
        <div style="margin-bottom:4px;">Si el equipo resultara danado por mal uso o negligencia del usuario, se tomaran las medidas administrativas que correspondan.</div>
        <div>En caso de robo o perdida, el responsable debera informar de inmediato para que se ejecuten los procedimientos internos y legales pertinentes.</div>
      </div>
    </div>
  </div>

  <!-- Modal de aviso -->
  <div id="asig-modal" class="modal hidden">
    <div class="modal__backdrop"></div>
    <div class="modal__content">
      <div class="modal__header">
        <h3 id="asig-modal-title">Aviso</h3>
      </div>
      <div class="modal__body" id="asig-modal-body">Mensaje</div>
      <div class="modal__actions">
        <button type="button" class="btn-secondary" id="asig-modal-cancel">Cancelar</button>
        <button type="button" class="btn" id="asig-modal-ok">OK</button>
      </div>
    </div>
  </div>

  <style>
    .asig-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit,minmax(240px,1fr));
      gap: 12px;
      margin-bottom: 12px;
    }
    .input-with-badge {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .chip-row {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }
    .chip {
      display: inline-flex;
      align-items: center;
      padding: 5px 10px;
      border-radius: 12px;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.15);
      font-size: 12px;
      color: #f1f1f1;
      pointer-events: none;
    }
    .chip.success { background: rgba(46, 204, 113, 0.15); border-color: rgba(46, 204, 113, 0.4); color: #a6f0c3; }
    .chip.warning { background: rgba(241, 196, 15, 0.15); border-color: rgba(241, 196, 15, 0.4); color: #f7e1a0; }
    .chip.info { background: rgba(52, 152, 219, 0.15); border-color: rgba(52, 152, 219, 0.4); color: #b9ddf8; }
    .asig-control {
      border-radius: 12px;
      padding: 12px;
      background: #0c1b32;
      color: #f6f7fb;
      border: 1px solid rgba(255,255,255,0.1);
      width: 100%;
    }
    .modal.hidden { display: none; }
    .modal { position: fixed; inset: 0; z-index: 3000; }
    .modal__backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.55); }
    .modal__content {
      position: relative;
      margin: 120px auto;
      max-width: 420px;
      background: #121424;
      color: #f1f1f1;
      padding: 18px;
      border-radius: 12px;
      box-shadow: 0 12px 36px rgba(0,0,0,0.4);
    }
    .modal__header { font-size: 18px; font-weight: 600; margin-bottom: 10px; }
    .modal__body { margin-bottom: 12px; line-height: 1.4; }
    .modal__actions { display: flex; justify-content: flex-end; gap: 8px; }
    .acta__signatures {
      display: flex;
      flex-direction: column;
      gap: 24px;
    }
    .acta__signatures--stack {
      display: flex !important;
      flex-direction: column !important;
      grid-template-columns: none !important;
      gap: 24px !important;
      align-items: stretch;
    }
    .acta__signatures--stack .acta__sig {
      width: 100%;
      max-width: none;
      margin: 0 auto;
      padding-top: 12px;
    }
    .acta__signatures--stack .signature-pad {
      width: 100%;
      height: clamp(260px, 60vw, 520px);
    }
    .acta__signatures--stack .acta__sig-line {
      width: 92%;
      max-width: none;
    }
    @media screen and (min-width: 1200px) {
      .acta__signatures {
        display: grid;
        grid-template-columns: repeat(auto-fit,minmax(220px,1fr));
      }
    }
    @media screen and (max-width: 1400px) {
      .acta__signatures {
        display: flex !important;
        flex-direction: column !important;
        grid-template-columns: none !important;
        gap: 22px;
        align-items: stretch;
      }
      .acta__sig {
        padding: 12px 10px 0;
        width: 100%;
        max-width: none;
        margin: 0 auto;
      }
      .signature-pad {
        width: 100%;
        height: clamp(240px, 60vw, 480px);
      }
      .acta__sig-line {
        width: 92%;
        max-width: none;
      }
    }
    @media screen and (max-width: 1400px) and (orientation: landscape) {
      .signature-pad {
        height: clamp(280px, 65vw, 520px);
      }
    }
  </style>

  <script src="assets/js/acta-sign.js"></script>
  <script src="assets/js/asignacion.js"></script>
  <script>
    (function() {
      const sigWrap = document.querySelector('.acta__signatures');
      const applyStack = () => {
        if (!sigWrap) return;
        if (window.innerWidth <= 1400) {
          sigWrap.classList.add('acta__signatures--stack');
        } else {
          sigWrap.classList.remove('acta__signatures--stack');
        }
      };
      applyStack();
      window.addEventListener('resize', applyStack);
    })();
  </script>
</body>
</html>
