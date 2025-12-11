<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
$user = $_SESSION['user'];
if (empty($user['can_manage'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
$pdo = db();

function redirectActivos(array $params = []): void
{
    $base = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $base . ($params ? '?' . http_build_query($params) : ''));
    exit;
}

$tiposActivo = $pdo->query("SELECT id_tipo_activo, descripcion_tp_activo FROM tipo_activo ORDER BY descripcion_tp_activo")->fetchAll();

// Helper para elegir IDs por nombre
function findTipoActivoId(array $tipos, array $keywords): ?int
{
    foreach ($tipos as $ta) {
        $name = strtolower($ta['descripcion_tp_activo']);
        foreach ($keywords as $kw) {
            if (strpos($name, $kw) !== false) {
                return (int)$ta['id_tipo_activo'];
            }
        }
    }
    return null;
}

$tipoActivoIdTech = findTipoActivoId($tiposActivo, ['tecnolog', 'tech']);
$tipoActivoIdMueble = findTipoActivoId($tiposActivo, ['mueble', 'enser', 'oficina']);

$messageEquipo = '';
$messageMueble = '';

// Combos comunes
// Combos equipos
$tiposEquipo = $pdo->query("SELECT id_tp_equipo AS id_tipo_equipo, descripcion_tp_equipo AS nombre_tipo FROM tipo_equipo ORDER BY descripcion_tp_equipo")->fetchAll();
$estadosEquipo = $pdo->query("SELECT id_estado_equipo, descripcion_estado_equipo FROM estado_equipo ORDER BY descripcion_estado_equipo")->fetchAll();
$estadosActivo = $pdo->query("SELECT id_estado_activo, descripcion_estado_activo FROM estado_activo ORDER BY descripcion_estado_activo")->fetchAll();

// Combos muebles
$tiposMueble = $pdo->query("SELECT id_tp_mueble, descripcion_tp_mueble FROM tipo_mueble ORDER BY descripcion_tp_mueble")->fetchAll();

// Alta equipo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_equipo'])) {
    $tipoEquipo = (int)($_POST['tipo_equipo'] ?? 0);
    $tipoActivoEq = (int)($_POST['tipo_activo'] ?? 0);
    $estadoEquipo = (int)($_POST['estado_equipo'] ?? 0);
    $estadoActivoEq = (int)($_POST['estado_activo'] ?? 0);
    $modelo = trim($_POST['modelo'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $hostname = trim($_POST['hostname'] ?? '');
    $procesador = trim($_POST['procesador'] ?? '');
    $ram = (int)($_POST['ram'] ?? 0);
    $disco = (int)($_POST['disco'] ?? 0);
    $serie = trim($_POST['serie'] ?? '');
    $precio = (float)($_POST['precio'] ?? 0);
    $iva = (float)($_POST['iva'] ?? 0);
    $vidaUtil = (int)($_POST['vida_util'] ?? 36);
    $fechaAdq = $_POST['fecha_adq'] ?? date('Y-m-d');
    $numFactura = trim($_POST['num_factura'] ?? 'N/A');
    $idEquipo = trim($_POST['id_equipo'] ?? '') ?: uniqid('EQ-');

    if ($tipoEquipo <= 0 || $estadoEquipo <= 0 || $modelo === '' || $marca === '' || $serie === '') {
        $messageEquipo = 'Tipo, estado, modelo, marca y serie son obligatorios';
    } else {
        $precioTotal = round($precio + $precio * ($iva / 100), 2);
        $ins = $pdo->prepare("
            INSERT INTO equipo (
                id_equipo, marca, modelo, num_serie, memoria_ram, almacenamiento, hostname, procesador,
                precio, iva, total, tiempo_vida_util, fecha_adquisicion, num_factura,
                id_estado_activo, id_estado_equipo, id_tp_equipo, id_tipo_activo
            ) VALUES (
                :id_equipo, :marca, :modelo, :serie, :ram, :disco, :hostname, :procesador,
                :precio, :iva, :total, :vida_util, :fecha_adq, :num_factura,
                :estado_activo, :estado_equipo, :tipo_equipo, :tipo_activo
            )
        ");
        $ins->execute([
            ':id_equipo' => $idEquipo,
            ':marca' => $marca,
            ':modelo' => $modelo,
            ':serie' => $serie,
            ':ram' => $ram,
            ':disco' => $disco,
            ':hostname' => $hostname,
            ':procesador' => $procesador,
            ':precio' => $precio,
            ':iva' => $iva,
            ':total' => $precioTotal,
            ':vida_util' => $vidaUtil,
            ':fecha_adq' => $fechaAdq,
            ':num_factura' => $numFactura === '' ? 'N/A' : $numFactura,
            ':estado_activo' => $estadoActivoEq > 0 ? $estadoActivoEq : $estadoEquipo,
            ':estado_equipo' => $estadoEquipo,
            ':tipo_equipo' => $tipoEquipo,
            ':tipo_activo' => $tipoActivoEq > 0 ? $tipoActivoEq : 1
        ]);
        redirectActivos(['eq_msg' => 'Equipo creado', 'tab' => 'equipo']);
    }
}

// Alta mueble
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_mueble'])) {
    $tipoMuebleSel = (int)($_POST['tipo_mueble'] ?? 0);
    $tipoActivoMb = (int)($_POST['tipo_activo_mb'] ?? 0);
    $marcaMb = trim($_POST['marca_mb'] ?? '');
    $modeloMb = trim($_POST['modelo_mb'] ?? '');
    $descripcionMb = trim($_POST['descripcion_mb'] ?? '');
    $cantidad = max(1, (int)($_POST['cantidad'] ?? 1));
    $dimensiones = trim($_POST['dimensiones'] ?? '');
    $origen = trim($_POST['origen'] ?? '');
    $proveedor = trim($_POST['proveedor'] ?? '');
    $precioMb = (float)($_POST['precio_mb'] ?? 0);
    $ivaMb = (float)($_POST['iva_mb'] ?? 0);
    $vidaUtilMb = (int)($_POST['vida_util_mb'] ?? 3);
    $fechaAdqMb = $_POST['fecha_adq_mb'] ?? date('Y-m-d');
    $numFacturaMb = trim($_POST['num_factura_mb'] ?? 'N/A');
    $idMueble = trim($_POST['id_mueble'] ?? '') ?: uniqid('MB-');

    if ($tipoMuebleSel <= 0 || $tipoActivoMb <= 0 || $marcaMb === '' || $modeloMb === '') {
        $messageMueble = 'Tipo de mueble, tipo de activo, marca y modelo son obligatorios';
    } else {
        $totalMb = round($precioMb + $precioMb * ($ivaMb / 100), 2);
        $ins = $pdo->prepare("
            INSERT INTO muebles_enseres (
                id_mueble_enseres, id_tp_mueble, fecha_adquision, precio, iva, total_muebles_enseres,
                tiempo_vida_util, num_factura, id_tipo_activo, Cantidad, Dimensiones, OrigenBien, Proveedor,
                marca, modelo, descripcion
            ) VALUES (
                :id_mueble, :id_tp_mueble, :fecha_adq, :precio, :iva, :total,
                :vida_util, :num_factura, :tipo_activo, :cantidad, :dimensiones, :origen, :proveedor,
                :marca, :modelo, :descripcion
            )
        ");
        $ins->execute([
            ':id_mueble' => $idMueble,
            ':id_tp_mueble' => $tipoMuebleSel,
            ':fecha_adq' => $fechaAdqMb,
            ':precio' => $precioMb,
            ':iva' => $ivaMb,
            ':total' => $totalMb,
            ':vida_util' => $vidaUtilMb,
            ':num_factura' => $numFacturaMb === '' ? 'N/A' : $numFacturaMb,
            ':tipo_activo' => $tipoActivoMb,
            ':cantidad' => $cantidad,
            ':dimensiones' => $dimensiones,
            ':origen' => $origen,
            ':proveedor' => $proveedor,
            ':marca' => $marcaMb,
            ':modelo' => $modeloMb,
            ':descripcion' => $descripcionMb
        ]);
        redirectActivos(['mb_msg' => 'Mueble/Enser ingresado', 'tab' => 'mueble']);
    }
}

$tab = $_GET['tab'] ?? (isset($_POST['form_mueble']) ? 'mueble' : 'equipo');

$equipos = $pdo->query("
    SELECT e.id_equipo, e.modelo, e.marca, e.num_serie AS serie, e.hostname, e.memoria_ram, e.almacenamiento, e.procesador,
           e.precio, e.iva, e.total, e.fecha_adquisicion, e.num_factura,
           te.descripcion_tp_equipo AS tipo_equipo,
           es.descripcion_estado_equipo AS estado_equipo,
           ea.descripcion_estado_activo AS estado_activo,
           ta.descripcion_tp_activo AS tipo_activo
    FROM equipo e
    INNER JOIN tipo_equipo te ON te.id_tp_equipo = e.id_tp_equipo
    INNER JOIN estado_equipo es ON es.id_estado_equipo = e.id_estado_equipo
    INNER JOIN estado_activo ea ON ea.id_estado_activo = e.id_estado_activo
    INNER JOIN tipo_activo ta ON ta.id_tipo_activo = e.id_tipo_activo
    ORDER BY e.id_equipo DESC
")->fetchAll();

$muebles = $pdo->query("
    SELECT m.id_mueble_enseres, m.marca, m.modelo, m.descripcion, m.Cantidad AS cantidad, m.Dimensiones AS dimensiones,
           m.OrigenBien AS origen, m.Proveedor AS proveedor, m.total_muebles_enseres AS total,
           m.fecha_adquision, m.tiempo_vida_util, tm.descripcion_tp_mueble AS tipo_mueble,
           ta.descripcion_tp_activo AS tipo_activo
    FROM muebles_enseres m
    INNER JOIN tipo_mueble tm ON tm.id_tp_mueble = m.id_tp_mueble
    INNER JOIN tipo_activo ta ON ta.id_tipo_activo = m.id_tipo_activo
    ORDER BY m.id_mueble_enseres DESC
")->fetchAll();

if (isset($_GET['eq_msg'])) {
    $messageEquipo = $_GET['eq_msg'];
}
if (isset($_GET['mb_msg'])) {
    $messageMueble = $_GET['mb_msg'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administración | Activos</title>
  <link rel="stylesheet" href="assets/css/main.css">
  <style>
    .tab-switch { display:flex; gap:10px; margin:10px 0 6px; flex-wrap:wrap; }
    .tab-switch button { min-width:160px; }
    .hidden { display:none; }
  </style>
</head>
<body>
  <div class="app-shell">
    <?php include __DIR__ . '/navbar.php'; ?>

    <section class="hero">
      <div class="hero-header">
        <div class="pill">Panel administrador</div>
        <div>
          <h1>Activos: equipos y muebles</h1>
          <p>Elige el tipo de activo y completa el formulario correspondiente.</p>
        </div>
      </div>
    </section>

    <div class="layout" style="grid-template-columns: 1fr;">
      <div class="card">
        <h2>Selecciona tipo de activo</h2>
        <div class="tab-switch">
          <?php
          $labelTech = null;
          if ($tipoActivoIdTech !== null) {
              foreach ($tiposActivo as $ta) {
                  if ((int)$ta['id_tipo_activo'] === $tipoActivoIdTech) {
                      $labelTech = $ta['descripcion_tp_activo'];
                      break;
                  }
              }
          }
          $labelMueble = null;
          if ($tipoActivoIdMueble !== null) {
              foreach ($tiposActivo as $ta) {
                  if ((int)$ta['id_tipo_activo'] === $tipoActivoIdMueble) {
                      $labelMueble = $ta['descripcion_tp_activo'];
                      break;
                  }
              }
          }
          ?>
          <button type="button" class="btn-secondary" data-target="equipo" data-activo-id="<?php echo (int)($tipoActivoIdTech ?? 0); ?>">
            <?php echo htmlspecialchars($labelTech ?: 'Tecnologia', ENT_QUOTES, 'UTF-8'); ?>
          </button>
          <button type="button" class="btn-secondary" data-target="mueble" data-activo-id="<?php echo (int)($tipoActivoIdMueble ?? 0); ?>">
            <?php echo htmlspecialchars($labelMueble ?: 'Mueble/Enser', ENT_QUOTES, 'UTF-8'); ?>
          </button>
        </div>

        <div id="panel-equipo" class="<?php echo $tab === 'mueble' ? 'hidden' : ''; ?>">
          <h3>Nuevo equipo</h3>
          <p class="card-subtitle <?php echo $messageEquipo ? '' : 'muted'; ?>">
            <?php echo htmlspecialchars($messageEquipo ?: 'Selecciona tipo/estado y llena los datos.', ENT_QUOTES, 'UTF-8'); ?>
          </p>
          <form method="post">
            <input type="hidden" name="form_equipo" value="1">
            <div style="display:grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap:14px;">
              <label>
                ID Equipo
                <input type="text" name="id_equipo" placeholder="Se autogenera si se deja vacío">
              </label>
              <label>
                Tipo de equipo
                <select name="tipo_equipo" required>
                  <?php foreach ($tiposEquipo as $t): ?>
                    <option value="<?php echo (int)$t['id_tipo_equipo']; ?>"><?php echo htmlspecialchars($t['nombre_tipo'], ENT_QUOTES, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>
                Tipo de activo
                <select name="tipo_activo" id="tipo_activo_eq" required>
                  <?php foreach ($tiposActivo as $ta): ?>
                    <option value="<?php echo (int)$ta['id_tipo_activo']; ?>" <?php echo $tipoActivoIdTech !== null && (int)$ta['id_tipo_activo'] === $tipoActivoIdTech ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($ta['descripcion_tp_activo'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>
                Estado de equipo
                <select name="estado_equipo" required>
                  <?php foreach ($estadosEquipo as $e): ?>
                    <option value="<?php echo (int)$e['id_estado_equipo']; ?>"><?php echo htmlspecialchars($e['descripcion_estado_equipo'], ENT_QUOTES, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>
                Estado de activo
                <select name="estado_activo" required>
                  <?php foreach ($estadosActivo as $ea): ?>
                    <option value="<?php echo (int)$ea['id_estado_activo']; ?>"><?php echo htmlspecialchars($ea['descripcion_estado_activo'], ENT_QUOTES, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>
                Modelo
                <input type="text" name="modelo" required>
              </label>
              <label>
                Marca
                <input type="text" name="marca" required>
              </label>
              <label>
                Procesador
                <input type="text" name="procesador" placeholder="CPU / generación">
              </label>
              <label>
                Hostname
                <input type="text" name="hostname" placeholder="Nombre en red">
              </label>
              <label>
                RAM
                <input type="number" name="ram" min="0" step="1" placeholder="GB">
              </label>
              <label>
                Disco
                <input type="number" name="disco" min="0" step="1" placeholder="GB">
              </label>
              <label>
                Serie
                <input type="text" name="serie" required>
              </label>
              <label>
                Precio
                <input type="number" step="0.01" name="precio" required>
              </label>
              <label>
                IVA (%)
                <input type="number" step="0.01" name="iva" value="15" required>
              </label>
              <label>
                Vida útil (años)
                <input type="number" name="vida_util" value="3" min="1" step="1">
              </label>
              <label>
                Fecha de adquisición
                <input type="date" name="fecha_adq" value="<?php echo date('Y-m-d'); ?>">
              </label>
              <label>
                Nº factura
                <input type="text" name="num_factura" placeholder="N/A">
              </label>
            </div>
            <div class="actions full" style="margin-top:12px;">
              <button type="reset" class="btn-secondary">Limpiar</button>
              <button type="submit" class="btn">Guardar equipo</button>
            </div>
          </form>

          <div class="table-scroll" style="margin-top:14px;">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Modelo</th>
                  <th>Tipo</th>
                  <th>Estado</th>
                  <th>Estado activo</th>
                  <th>Serie</th>
                  <th>Procesador</th>
                  <th>Precio</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$equipos): ?>
                  <tr><td colspan="9" class="empty">Sin equipos</td></tr>
                <?php else: ?>
                  <?php foreach ($equipos as $eq): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($eq['id_equipo'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo htmlspecialchars($eq['modelo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo htmlspecialchars($eq['tipo_equipo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo htmlspecialchars($eq['estado_equipo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo htmlspecialchars($eq['estado_activo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo htmlspecialchars($eq['serie'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo htmlspecialchars($eq['procesador'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo number_format((float)($eq['total'] ?? 0), 2); ?></td>
                      <td>
                        <a class="btn-secondary" href="equipo-editar.php?id=<?php echo urlencode($eq['id_equipo']); ?>">Editar</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div id="panel-mueble" class="<?php echo $tab === 'equipo' ? 'hidden' : ''; ?>">
          <h3>Nuevo mueble/enser</h3>
          <p class="card-subtitle <?php echo $messageMueble ? '' : 'muted'; ?>">
            <?php echo htmlspecialchars($messageMueble ?: 'Completa los datos requeridos.', ENT_QUOTES, 'UTF-8'); ?>
          </p>
          <form method="post">
            <input type="hidden" name="form_mueble" value="1">
            <div style="display:grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap:14px;">
              <label>
                ID Mueble/Enser
                <input type="text" name="id_mueble" placeholder="Se autogenera si se deja vacío">
              </label>
              <label>
                Tipo de mueble
                <select name="tipo_mueble" required>
                  <?php foreach ($tiposMueble as $tm): ?>
                    <option value="<?php echo (int)$tm['id_tp_mueble']; ?>"><?php echo htmlspecialchars($tm['descripcion_tp_mueble'], ENT_QUOTES, 'UTF-8'); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>
                Tipo de activo
                <select name="tipo_activo_mb" id="tipo_activo_mb" required>
                  <?php foreach ($tiposActivo as $ta): ?>
                    <option value="<?php echo (int)$ta['id_tipo_activo']; ?>" <?php echo $tipoActivoIdMueble !== null && (int)$ta['id_tipo_activo'] === $tipoActivoIdMueble ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($ta['descripcion_tp_activo'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>
                Marca
                <input type="text" name="marca_mb" required>
              </label>
              <label>
                Modelo
                <input type="text" name="modelo_mb" required>
              </label>
              <label>
                Descripción
                <textarea name="descripcion_mb" placeholder="Detalle del bien" style="min-height:72px;"></textarea>
              </label>
              <label>
                Cantidad
                <input type="number" name="cantidad" min="1" step="1" value="1">
              </label>
              <label>
                Dimensiones
                <input type="text" name="dimensiones" placeholder="Alto x Ancho x Profundidad">
              </label>
              <label>
                Origen del bien
                <input type="text" name="origen" placeholder="Compra / Donación">
              </label>
              <label>
                Proveedor
                <input type="text" name="proveedor" placeholder="Empresa proveedora">
              </label>
              <label>
                Precio
                <input type="number" step="0.01" name="precio_mb" required>
              </label>
              <label>
                IVA (%)
                <input type="number" step="0.01" name="iva_mb" value="15" required>
              </label>
              <label>
                Vida útil (años)
                <input type="number" name="vida_util_mb" value="3" min="1" step="1">
              </label>
              <label>
                Fecha de adquisición
                <input type="date" name="fecha_adq_mb" value="<?php echo date('Y-m-d'); ?>">
              </label>
              <label>
                Nº factura
                <input type="text" name="num_factura_mb" placeholder="N/A">
              </label>
            </div>
            <div class="actions full" style="margin-top:12px;">
              <button type="reset" class="btn-secondary">Limpiar</button>
              <button type="submit" class="btn">Guardar mueble/enser</button>
            </div>
          </form>

          <div class="table-scroll" style="margin-top:14px;">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Tipo</th>
                  <th>Marca</th>
                  <th>Modelo</th>
                  <th>Descripción</th>
                  <th>Cantidad</th>
                  <th>Origen</th>
                  <th>Total</th>
                  <th>Fecha adq</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$muebles): ?>
                  <tr><td colspan="9" class="empty">Sin muebles registrados</td></tr>
                <?php else: ?>
                  <?php foreach ($muebles as $mb): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($mb['id_mueble_enseres'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo htmlspecialchars($mb['tipo_mueble'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo htmlspecialchars($mb['marca'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo htmlspecialchars($mb['modelo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                      <td class="muted"><?php echo htmlspecialchars($mb['descripcion'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo (int)($mb['cantidad'] ?? 0); ?></td>
                      <td><?php echo htmlspecialchars($mb['origen'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo number_format((float)($mb['total'] ?? 0), 2); ?></td>
                      <td><?php echo htmlspecialchars(substr((string)$mb['fecha_adquision'], 0, 10), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    const switches = document.querySelectorAll('.tab-switch button');
    const panelEq = document.getElementById('panel-equipo');
    const panelMb = document.getElementById('panel-mueble');
    const selectEq = document.getElementById('tipo_activo_eq');
    const selectMb = document.getElementById('tipo_activo_mb');
    switches.forEach(btn => {
      btn.addEventListener('click', () => {
        const target = btn.getAttribute('data-target');
        const idActivo = btn.getAttribute('data-activo-id');
        if (target === 'mueble') {
          panelEq.classList.add('hidden');
          panelMb.classList.remove('hidden');
          if (idActivo && selectMb) {
            selectMb.value = idActivo;
          }
        } else {
          panelMb.classList.add('hidden');
          panelEq.classList.remove('hidden');
          if (idActivo && selectEq) {
            selectEq.value = idActivo;
          }
        }
      });
    });
  </script>
</body>
</html>
