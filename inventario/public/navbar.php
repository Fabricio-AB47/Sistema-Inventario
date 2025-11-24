<?php
if (!isset($user)) {
    return;
}
$isAdmin = !empty($user['can_manage']);
$displayName = strtoupper(trim($user['name'] ?? $user['email'] ?? ''));
$displayRole = strtoupper(trim($user['tipo_nombre'] ?? ''));
?>
<div class="topbar">
  <div class="topbar-user">
    <span class="badge">Sesión: <strong><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></strong></span>
    <span class="badge">Rol: <strong><?php echo htmlspecialchars($displayRole, ENT_QUOTES, 'UTF-8'); ?></strong></span>
  </div>
  <div class="topbar-links">
    <a class="btn-secondary" href="dashboard.php">Dashboard</a>
    <?php if ($isAdmin): ?>
      <a class="btn-secondary" href="asignacion.php">Asignacion</a>
      <a class="btn-secondary" href="dashboard-autoridades.php">Dash Autoridades</a>
      <a class="btn-secondary" href="documentos-asignacion.php">Docs Asignación</a>
      <a class="btn-secondary" href="admin-usuarios.php">Usuarios</a>
      <a class="btn-secondary" href="admin-equipos.php">Equipos</a>
    <?php endif; ?>
    <form action="logout.php" method="post" style="display:inline;">
      <button class="btn" type="submit">Cerrar sesion</button>
    </form>
  </div>
</div>
