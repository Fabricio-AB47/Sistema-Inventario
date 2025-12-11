<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
$user = $_SESSION['user'];

require_once __DIR__ . '/../config/db.php';

function monthsDiffCeil(string $startDate, string $endDate): int
{
    try {
        $start = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($endDate);
    } catch (Throwable $e) {
        return 0;
    }

    if ($start > $end) {
        [$start, $end] = [$end, $start];
    }

    $diff = $start->diff($end);
    $months = ($diff->y * 12) + $diff->m;

    // Contar un mes adicional cuando hay días sobrantes para no perder depreciación parcial.
    if ($diff->d > 0) {
        $months++;
    }

    return $months;
}

$tab = $_GET['tab'] ?? 'equipo';
if (!in_array($tab, ['equipo', 'mueble'], true)) {
    $tab = 'equipo';
}

$errorMsgEq = '';
$errorMsgMb = '';
$equipos = [];
$depAnualRows = [];
$muebles = [];
$depMbRows = [];

try {
    $pdo = db();
    $equipos = $pdo->query("
        SELECT id_equipo, marca, modelo, num_serie, total, tiempo_vida_util, fecha_adquisicion
        FROM equipo
        ORDER BY fecha_adquisicion DESC
    ")->fetchAll();

    $depAnualRows = $pdo->query("
        SELECT id_equipo, anio, total_dep_anual, fecha_calculo
        FROM dep_anual
        ORDER BY id_equipo, anio
    ")->fetchAll();
} catch (Throwable $e) {
    $errorMsgEq = 'No se pudo cargar la información de depreciación de equipos.';
}

try {
    $pdo = $pdo ?? db();
    $muebles = $pdo->query("
        SELECT id_mueble_enseres, marca, modelo, total_muebles_enseres AS total, tiempo_vida_util, fecha_adquision
        FROM muebles_enseres
        ORDER BY fecha_adquision DESC
    ")->fetchAll();

    $depMbRows = $pdo->query("
        SELECT id_mueble_enseres, anio, mes, valor_dep_mes, fecha_dep
        FROM depreciacion_muebles_enseres
        ORDER BY id_mueble_enseres, anio, mes
    ")->fetchAll();
} catch (Throwable $e) {
    $errorMsgMb = 'No se pudo cargar la información de depreciación de muebles.';
}

$depPorEquipo = [];
foreach ($depAnualRows as $row) {
    $id = $row['id_equipo'];
    if (!isset($depPorEquipo[$id])) {
        $depPorEquipo[$id] = ['total' => 0.0, 'rows' => []];
    }
    $depPorEquipo[$id]['total'] += (float)$row['total_dep_anual'];
    $depPorEquipo[$id]['rows'][] = $row;
}

$depPorMueble = [];
foreach ($depMbRows as $row) {
    $id = $row['id_mueble_enseres'];
    if (!isset($depPorMueble[$id])) {
        $depPorMueble[$id] = ['total' => 0.0, 'rows' => []];
    }
    $depPorMueble[$id]['total'] += (float)$row['valor_dep_mes'];
    $depPorMueble[$id]['rows'][] = $row;
}

$now = new DateTimeImmutable('now');
$totalesEq = [
    'dep_mensual' => 0.0,
    'dep_anual' => 0.0,
    'dep_acumulada' => 0.0,
    'valor_pendiente' => 0.0,
    'valor_inicial' => 0.0,
];

$totalesMb = [
    'dep_mensual' => 0.0,
    'dep_anual' => 0.0,
    'dep_acumulada' => 0.0,
    'valor_pendiente' => 0.0,
    'valor_inicial' => 0.0,
];

foreach ($equipos as &$eq) {
    $total = (float)($eq['total'] ?? 0);
    $vidaAnos = max(1, (int)($eq['tiempo_vida_util'] ?? 1));
    $vidaMeses = $vidaAnos * 12;
    // Depreciación anual = total / vida útil (años); mensual = resultado / 12.
    $depAnualCalc = $vidaAnos > 0 ? $total / $vidaAnos : 0.0;
    $depMensual = $depAnualCalc / 12;
    $mesesDesdeCompra = monthsDiffCeil((string)$eq['fecha_adquisicion'], $now->format('Y-m-d'));
    $mesesDepreciados = min($vidaMeses, $mesesDesdeCompra);
    $depAcumuladaCalc = $depMensual * $mesesDepreciados;
    $valorPendiente = max(0, $total - $depAcumuladaCalc);
    $depRegistrada = $depPorEquipo[$eq['id_equipo']]['total'] ?? 0.0;
    $porcentajeVida = $vidaMeses > 0 ? min(100, ($mesesDepreciados / $vidaMeses) * 100) : 0;

    $eq['dep_mensual'] = round($depMensual, 2);
    $eq['dep_anual'] = round($depAnualCalc, 2);
    $eq['dep_acumulada'] = round($depAcumuladaCalc, 2);
    $eq['valor_pendiente'] = round($valorPendiente, 2);
    $eq['dep_registrada'] = round($depRegistrada, 2);
    $eq['vida_meses'] = $vidaMeses;
    $eq['meses_depreciados'] = $mesesDepreciados;
    $eq['porcentaje_vida'] = round($porcentajeVida, 1);
    $eq['dep_rows'] = $depPorEquipo[$eq['id_equipo']]['rows'] ?? [];

    $totalesEq['dep_mensual'] += $eq['dep_mensual'];
    $totalesEq['dep_anual'] += $eq['dep_anual'];
    $totalesEq['dep_acumulada'] += $eq['dep_acumulada'];
    $totalesEq['valor_pendiente'] += $eq['valor_pendiente'];
    $totalesEq['valor_inicial'] += $total;
}
unset($eq);

foreach ($muebles as &$mb) {
    $total = (float)($mb['total'] ?? 0);
    $vidaAnos = max(1, (int)($mb['tiempo_vida_util'] ?? 1));
    $vidaMeses = $vidaAnos * 12;
    $depAnualCalc = $vidaAnos > 0 ? $total / $vidaAnos : 0.0;
    $depMensual = $depAnualCalc / 12;
    $mesesDesdeCompra = monthsDiffCeil((string)$mb['fecha_adquision'], $now->format('Y-m-d'));
    $mesesDepreciados = min($vidaMeses, $mesesDesdeCompra);
    $depAcumuladaCalc = $depMensual * $mesesDepreciados;
    $valorPendiente = max(0, $total - $depAcumuladaCalc);
    $depRegistrada = $depPorMueble[$mb['id_mueble_enseres']]['total'] ?? 0.0;
    $porcentajeVida = $vidaMeses > 0 ? min(100, ($mesesDepreciados / $vidaMeses) * 100) : 0;

    $mb['dep_mensual'] = round($depMensual, 2);
    $mb['dep_anual'] = round($depAnualCalc, 2);
    $mb['dep_acumulada'] = round($depAcumuladaCalc, 2);
    $mb['valor_pendiente'] = round($valorPendiente, 2);
    $mb['dep_registrada'] = round($depRegistrada, 2);
    $mb['vida_meses'] = $vidaMeses;
    $mb['meses_depreciados'] = $mesesDepreciados;
    $mb['porcentaje_vida'] = round($porcentajeVida, 1);
    $mb['dep_rows'] = $depPorMueble[$mb['id_mueble_enseres']]['rows'] ?? [];

    $totalesMb['dep_mensual'] += $mb['dep_mensual'];
    $totalesMb['dep_anual'] += $mb['dep_anual'];
    $totalesMb['dep_acumulada'] += $mb['dep_acumulada'];
    $totalesMb['valor_pendiente'] += $mb['valor_pendiente'];
    $totalesMb['valor_inicial'] += $total;
}
unset($mb);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Depreciación de activos</title>
  <link rel="stylesheet" href="assets/css/main.css">
  <style>.hidden{display:none;}</style>
</head>
<body>
  <div class="app-shell">
    <?php include __DIR__ . '/navbar.php'; ?>

    <section class="hero">
      <div class="hero-header">
        <div class="pill">Depreciación</div>
        <div>
          <h1>Calculo por activo</h1>
          <p>Formula: (total / vida útil en años) = depreciación anual; / 12 = depreciación mensual. Se usa la fecha de adquisición como inicio.</p>
        </div>
      </div>
    </section>

    <div class="layout" style="grid-template-columns: 1fr;">
      <div class="card">
        <h2>Selecciona tipo</h2>
        <div class="actions" style="justify-content:flex-start; gap:10px; margin-bottom:12px;">
          <button type="button" class="btn-secondary" data-target="equipo">Depreciación tecnología</button>
          <button type="button" class="btn-secondary" data-target="mueble">Depreciación muebles</button>
        </div>

        <div id="panel-equipo" class="<?php echo $tab === 'mueble' ? 'hidden' : ''; ?>">
          <h3>Equipos</h3>
          <p class="card-subtitle">
            <?php echo $errorMsgEq ? htmlspecialchars($errorMsgEq, ENT_QUOTES, 'UTF-8') : 'Depreciación calculada contra registros dep_anual.'; ?>
          </p>
          <div class="meta">
            <div class="badge">Equipos: <strong><?php echo count($equipos); ?></strong></div>
            <div class="badge">Dep. mensual total: <strong><?php echo number_format($totalesEq['dep_mensual'], 2); ?></strong></div>
            <div class="badge">Dep. anual total: <strong><?php echo number_format($totalesEq['dep_anual'], 2); ?></strong></div>
            <div class="badge">Dep. acumulada: <strong><?php echo number_format($totalesEq['dep_acumulada'], 2); ?></strong></div>
            <div class="badge">Valor pendiente: <strong><?php echo number_format($totalesEq['valor_pendiente'], 2); ?></strong></div>
            <div class="badge muted">Valor inicial: <strong><?php echo number_format($totalesEq['valor_inicial'], 2); ?></strong></div>
          </div>

          <div class="table-scroll" style="margin-top:14px;">
            <table>
              <thead>
                <tr>
                  <th>Equipo</th>
                  <th>Fecha adq</th>
                  <th>Vida util (meses)</th>
                  <th>Dep. mensual</th>
                  <th>Dep. anual</th>
                  <th>Dep. acumulada</th>
                  <th>Dep. registrada</th>
                  <th>Valor pendiente</th>
                  <th>Meses depreciados</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$equipos): ?>
                  <tr><td colspan="9" class="empty">Sin equipos para calcular</td></tr>
                <?php else: ?>
                  <?php foreach ($equipos as $eq): ?>
                    <tr>
                      <td>
                        <div><strong><?php echo htmlspecialchars($eq['id_equipo'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                        <div class="muted">
                          <?php echo htmlspecialchars(trim(($eq['marca'] ?? '') . ' ' . ($eq['modelo'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                          <?php echo htmlspecialchars(' / ' . ($eq['num_serie'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                      </td>
                      <td><?php echo htmlspecialchars(substr((string)$eq['fecha_adquisicion'], 0, 10), ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo (int)$eq['vida_meses']; ?> (<?php echo htmlspecialchars($eq['porcentaje_vida'] . '%', ENT_QUOTES, 'UTF-8'); ?>)</td>
                      <td><?php echo number_format((float)$eq['dep_mensual'], 2); ?></td>
                      <td><?php echo number_format((float)$eq['dep_anual'], 2); ?></td>
                      <td><?php echo number_format((float)$eq['dep_acumulada'], 2); ?></td>
                      <td>
                        <?php echo number_format((float)$eq['dep_registrada'], 2); ?>
                        <?php if (!empty($eq['dep_rows'])): ?>
                          <div class="muted" style="font-size:12px;">
                            <?php foreach ($eq['dep_rows'] as $row): ?>
                              <div>
                                <?php echo (int)$row['anio']; ?>: <?php echo number_format((float)$row['total_dep_anual'], 2); ?>
                                (<?php echo htmlspecialchars(substr((string)$row['fecha_calculo'], 0, 10), ENT_QUOTES, 'UTF-8'); ?>)
                              </div>
                            <?php endforeach; ?>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td><?php echo number_format((float)$eq['valor_pendiente'], 2); ?></td>
                      <td><?php echo (int)$eq['meses_depreciados']; ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div id="panel-mueble" class="<?php echo $tab === 'equipo' ? 'hidden' : ''; ?>">
          <h3>Muebles y enseres</h3>
          <p class="card-subtitle">
            <?php echo $errorMsgMb ? htmlspecialchars($errorMsgMb, ENT_QUOTES, 'UTF-8') : 'Depreciación calculada contra registros depreciacion_muebles_enseres.'; ?>
          </p>
          <div class="meta">
            <div class="badge">Muebles/Enseres: <strong><?php echo count($muebles); ?></strong></div>
            <div class="badge">Dep. mensual total: <strong><?php echo number_format($totalesMb['dep_mensual'], 2); ?></strong></div>
            <div class="badge">Dep. anual total: <strong><?php echo number_format($totalesMb['dep_anual'], 2); ?></strong></div>
            <div class="badge">Dep. acumulada: <strong><?php echo number_format($totalesMb['dep_acumulada'], 2); ?></strong></div>
            <div class="badge">Valor pendiente: <strong><?php echo number_format($totalesMb['valor_pendiente'], 2); ?></strong></div>
            <div class="badge muted">Valor inicial: <strong><?php echo number_format($totalesMb['valor_inicial'], 2); ?></strong></div>
          </div>

          <div class="table-scroll" style="margin-top:14px;">
            <table>
              <thead>
                <tr>
                  <th>Mueble/Enser</th>
                  <th>Fecha adq</th>
                  <th>Vida util (meses)</th>
                  <th>Dep. mensual</th>
                  <th>Dep. anual</th>
                  <th>Dep. acumulada</th>
                  <th>Dep. registrada</th>
                  <th>Valor pendiente</th>
                  <th>Meses depreciados</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$muebles): ?>
                  <tr><td colspan="9" class="empty">Sin muebles/enseres para calcular</td></tr>
                <?php else: ?>
                  <?php foreach ($muebles as $mb): ?>
                    <tr>
                      <td>
                        <div><strong><?php echo htmlspecialchars($mb['id_mueble_enseres'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                        <div class="muted">
                          <?php echo htmlspecialchars(trim(($mb['marca'] ?? '') . ' ' . ($mb['modelo'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                      </td>
                      <td><?php echo htmlspecialchars(substr((string)$mb['fecha_adquision'], 0, 10), ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo (int)$mb['vida_meses']; ?> (<?php echo htmlspecialchars($mb['porcentaje_vida'] . '%', ENT_QUOTES, 'UTF-8'); ?>)</td>
                      <td><?php echo number_format((float)$mb['dep_mensual'], 2); ?></td>
                      <td><?php echo number_format((float)$mb['dep_anual'], 2); ?></td>
                      <td><?php echo number_format((float)$mb['dep_acumulada'], 2); ?></td>
                      <td>
                        <?php echo number_format((float)$mb['dep_registrada'], 2); ?>
                        <?php if (!empty($mb['dep_rows'])): ?>
                          <div class="muted" style="font-size:12px;">
                            <?php foreach ($mb['dep_rows'] as $row): ?>
                              <div>
                                <?php echo (int)$row['anio']; ?>/<?php echo (int)$row['mes']; ?>:
                                <?php echo number_format((float)$row['valor_dep_mes'], 2); ?>
                                (<?php echo htmlspecialchars(substr((string)$row['fecha_dep'], 0, 10), ENT_QUOTES, 'UTF-8'); ?>)
                              </div>
                            <?php endforeach; ?>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td><?php echo number_format((float)$mb['valor_pendiente'], 2); ?></td>
                      <td><?php echo (int)$mb['meses_depreciados']; ?></td>
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
    const buttons = document.querySelectorAll('.actions button[data-target]');
    const panelEq = document.getElementById('panel-equipo');
    const panelMb = document.getElementById('panel-mueble');
    buttons.forEach(btn => {
      btn.addEventListener('click', () => {
        const t = btn.getAttribute('data-target');
        if (t === 'mueble') {
          panelEq.classList.add('hidden');
          panelMb.classList.remove('hidden');
        } else {
          panelMb.classList.add('hidden');
          panelEq.classList.remove('hidden');
        }
      });
    });
  </script>
</body>
</html>
