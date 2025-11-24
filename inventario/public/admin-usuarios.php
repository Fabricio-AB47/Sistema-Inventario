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
$messageUser = '';
$editingUser = null;

function redirectWithUser(array $params): void {
    $base = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $base . '?' . http_build_query($params));
    exit;
}

// Cargar usuario a editar si se solicita
$editId = (int)($_GET['edit_id'] ?? 0);
if ($editId > 0) {
    $stmtEdit = $pdo->prepare("
        SELECT u.id_user AS id_usuario, u.nombre, u.apellidos, u.cedula, u.correo, u.id_tp_perfil
        FROM usuario u
        WHERE u.id_user = :id
    ");
    $stmtEdit->execute([':id' => $editId]);
    $editingUser = $stmtEdit->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_users'])) {
    $editingId = (int)($_POST['edit_id'] ?? 0);
    $nombres = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $cedula = trim($_POST['cedula'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $tipo = (int)($_POST['tipo'] ?? 1);
    $psw = trim($_POST['psw'] ?? '');

    if ($nombres === '' || $apellidos === '' || $correo === '' || ($editingId === 0 && $psw === '')) {
        $messageUser = 'nombres, apellidos, correo y psw son obligatorios';
    } else {
        // Verificar correo duplicado en otros usuarios
        $stmt = $pdo->prepare("SELECT id_user FROM usuario WHERE correo = :correo");
        $stmt->execute([':correo' => $correo]);
        $existing = $stmt->fetchColumn();
        if ($existing && (int)$existing !== $editingId) {
            $messageUser = 'El correo ya existe';
        } else {
            if ($editingId > 0) {
                // Actualizar usuario
                $fields = [
                    'nombre' => $nombres,
                    'apellidos' => $apellidos,
                    'cedula' => $cedula,
                    'correo' => $correo,
                    'id_tp_perfil' => $tipo ?: 1,
                ];
                $setParts = [];
                $params = [':id' => $editingId];
                foreach ($fields as $col => $val) {
                    $setParts[] = "$col = :$col";
                    $params[":$col"] = $val;
                }
                if ($psw !== '') {
                    $setParts[] = "pwd = :pwd";
                    $params[':pwd'] = $psw;
                }
                $sql = "UPDATE usuario SET " . implode(', ', $setParts) . " WHERE id_user = :id";
                $pdo->prepare($sql)->execute($params);
                redirectWithUser(['user_msg' => 'Usuario actualizado']);
            } else {
                // Insertar usuario
                $ins = $pdo->prepare("
                    INSERT INTO usuario (nombre, apellidos, cedula, correo, id_tp_perfil, pwd)
                    VALUES (:nombres, :apellidos, :cedula, :correo, :tipo, :psw)
                ");
                $ins->execute([
                    ':nombres' => $nombres,
                    ':apellidos' => $apellidos,
                    ':cedula' => $cedula,
                    ':correo' => $correo,
                    ':tipo' => $tipo ?: 1,
                    ':psw' => $psw === '' ? 'psw' : $psw,
                ]);
                redirectWithUser(['user_msg' => 'Usuario creado']);
            }
        }
    }
}

$tiposUsuario = $pdo->query("SELECT id_tp_perfil AS id_tipo_usuario, descripcion_perfil AS nombre_tipo FROM tipo_perfil ORDER BY id_tp_perfil")->fetchAll();
$usuarios = $pdo->query("
    SELECT u.id_user AS id_usuario, u.nombre, u.apellidos, u.correo, u.id_tp_perfil, t.descripcion_perfil AS nombre_tipo
    FROM usuario u
    INNER JOIN tipo_perfil t ON t.id_tp_perfil = u.id_tp_perfil
    ORDER BY u.id_user DESC
")->fetchAll();

if (isset($_GET['user_msg'])) {
    $messageUser = $_GET['user_msg'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administración | Usuarios</title>
  <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
  <div class="app-shell">
    <?php include __DIR__ . '/navbar.php'; ?>

    <section class="hero">
      <div class="hero-header">
        <div class="pill">Panel administrador</div>
        <div>
          <h1>Ingreso de usuarios</h1>
          <p>Solo roles Administrador y TI pueden crear usuarios.</p>
        </div>
      </div>
    </section>

    <div class="layout" style="grid-template-columns: 1fr;">
      <div class="card">
        <h2>Nuevo usuario</h2>
        <p class="card-subtitle <?php echo $messageUser ? '' : 'muted'; ?>">
          <?php echo htmlspecialchars($messageUser ?: 'Completa los datos y guarda.', ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <form method="post">
        <input type="hidden" name="form_users" value="1">
        <input type="hidden" name="edit_id" value="<?php echo $editingUser['id_usuario'] ?? 0; ?>">
        <div style="display:grid; grid-template-columns: repeat(auto-fit,minmax(240px,1fr)); gap:14px;">
          <label>
            Nombres
            <input type="text" name="nombres" value="<?php echo htmlspecialchars($editingUser['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
          </label>
          <label>
            Apellidos
            <input type="text" name="apellidos" value="<?php echo htmlspecialchars($editingUser['apellidos'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
          </label>
          <label>
            Cédula
            <input type="text" name="cedula" placeholder="Opcional" value="<?php echo htmlspecialchars($editingUser['cedula'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </label>
          <label>
            Correo
            <input type="email" name="correo" value="<?php echo htmlspecialchars($editingUser['correo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
          </label>
          <label>
            Tipo de usuario
            <select name="tipo">
              <?php foreach ($tiposUsuario as $t): ?>
                <option value="<?php echo (int)$t['id_tipo_usuario']; ?>" <?php echo isset($editingUser['id_tp_perfil']) && (int)$editingUser['id_tp_perfil'] === (int)$t['id_tipo_usuario'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($t['nombre_tipo'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Contraseña
            <input type="text" name="psw" value="">
          </label>
        </div>
        <div class="actions full" style="margin-top:12px;">
          <button type="reset" class="btn-secondary">Limpiar</button>
          <button type="submit" class="btn"><?php echo $editingUser ? 'Actualizar usuario' : 'Guardar usuario'; ?></button>
          <?php if ($editingUser): ?>
            <a class="btn-secondary" href="admin-usuarios.php" style="margin-left:8px;">Cancelar edición</a>
          <?php endif; ?>
        </div>
      </form>

        <div class="table-scroll" style="margin-top:14px;">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Tipo</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$usuarios): ?>
                <tr><td colspan="4" class="empty">Sin usuarios</td></tr>
              <?php else: ?>
                <?php foreach ($usuarios as $u): ?>
                  <tr>
                    <td><?php echo (int)$u['id_usuario']; ?></td>
                    <td><?php echo htmlspecialchars(trim(($u['nombre'] ?? '') . ' ' . ($u['apellidos'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($u['correo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($u['nombre_tipo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                      <button type="button"
                        class="btn-secondary open-edit"
                        data-id="<?php echo (int)$u['id_usuario']; ?>"
                        data-nombre="<?php echo htmlspecialchars($u['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        data-apellidos="<?php echo htmlspecialchars($u['apellidos'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        data-cedula="<?php echo htmlspecialchars($u['cedula'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        data-correo="<?php echo htmlspecialchars($u['correo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        data-perfil="<?php echo (int)($u['id_tp_perfil'] ?? 0); ?>"
                      >Editar</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal de edición -->
  <div id="edit-modal" class="modal hidden">
    <div class="modal__backdrop"></div>
    <div class="modal__content">
      <div class="modal__header">
        <h3>Editar usuario</h3>
        <button type="button" class="btn-secondary" id="edit-close">Cerrar</button>
      </div>
      <form method="post" id="edit-form">
        <input type="hidden" name="form_users" value="1">
        <input type="hidden" name="edit_id" id="edit_id">
        <div style="display:grid; grid-template-columns: repeat(auto-fit,minmax(240px,1fr)); gap:14px;">
          <label>
            Nombres
            <input type="text" name="nombres" id="edit_nombres" required>
          </label>
          <label>
            Apellidos
            <input type="text" name="apellidos" id="edit_apellidos" required>
          </label>
          <label>
            Cédula
            <input type="text" name="cedula" id="edit_cedula" placeholder="Opcional">
          </label>
          <label>
            Correo
            <input type="email" name="correo" id="edit_correo" required>
          </label>
          <label>
            Tipo de usuario
            <select name="tipo" id="edit_tipo">
              <?php foreach ($tiposUsuario as $t): ?>
                <option value="<?php echo (int)$t['id_tipo_usuario']; ?>"><?php echo htmlspecialchars($t['nombre_tipo'], ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Contraseña (deja vacío para no cambiar)
            <input type="text" name="psw" id="edit_psw" placeholder="(sin cambio)">
          </label>
        </div>
        <div class="actions full" style="margin-top:12px;">
          <button type="button" class="btn-secondary" id="edit-cancel">Cancelar</button>
          <button type="submit" class="btn">Actualizar usuario</button>
        </div>
      </form>
    </div>
  </div>

  <style>
    .modal.hidden { display: none; }
    .modal { position: fixed; inset: 0; z-index: 2000; }
    .modal__backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.5); }
    .modal__content { position: relative; margin: 60px auto; max-width: 720px; background: var(--card-bg, #111); border-radius: 10px; padding: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.35); color: var(--text, #f5f5f5); }
    .modal__header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
  </style>

  <script>
    (() => {
      const modal = document.getElementById('edit-modal');
      const closeBtn = document.getElementById('edit-close');
      const cancelBtn = document.getElementById('edit-cancel');
      const form = document.getElementById('edit-form');
      const fields = {
        id: document.getElementById('edit_id'),
        nombres: document.getElementById('edit_nombres'),
        apellidos: document.getElementById('edit_apellidos'),
        cedula: document.getElementById('edit_cedula'),
        correo: document.getElementById('edit_correo'),
        tipo: document.getElementById('edit_tipo'),
        psw: document.getElementById('edit_psw')
      };

      function openModal(data) {
        fields.id.value = data.id || '';
        fields.nombres.value = data.nombres || '';
        fields.apellidos.value = data.apellidos || '';
        fields.cedula.value = data.cedula || '';
        fields.correo.value = data.correo || '';
        fields.tipo.value = data.perfil || '';
        fields.psw.value = '';
        modal.classList.remove('hidden');
      }

      function closeModal() {
        modal.classList.add('hidden');
        form.reset();
      }

      document.querySelectorAll('.open-edit').forEach((btn) => {
        btn.addEventListener('click', () => {
          openModal({
            id: btn.dataset.id,
            nombres: btn.dataset.nombre,
            apellidos: btn.dataset.apellidos,
            cedula: btn.dataset.cedula,
            correo: btn.dataset.correo,
            perfil: btn.dataset.perfil
          });
        });
      });

      closeBtn.addEventListener('click', closeModal);
      cancelBtn.addEventListener('click', closeModal);
    })();
  </script>
</body>
</html>
