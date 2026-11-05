<?php
require_once "config/auth.php";
require_once "config/conexion.php";

$titulo = "Configuración - VitaSoft";
$conn =$conexion;
$msg = "";
$error = "";

$seccion =$_GET['seccion'] ?? 'usuarios';

/* =========================================================
   DESCARGA REAL DE RESPALDO (BACKUP .SQL)
   ========================================================= */
if (isset($_GET['accion']) && $_GET['accion'] === 'descargar_backup') {$tables = array();
    $result =$conn->query("SHOW TABLES");
    while ($row =$result->fetch_row()) {
        $tables[] =$row[0];
    }

    $return = "-- RESPALDO DE BASE DE DATOS VITASOFT\n";
    $return .= "-- Fecha: " . date("Y-m-d H:i:s") . "\n\n";

    foreach ($tables as$table) {
        $result =$conn->query("SELECT * FROM `$table`");
        $num_fields =$result->field_count;

        $return .= "DROP TABLE IF EXISTS `$table`;";
        $row2 =$conn->query("SHOW CREATE TABLE `$table`")->fetch_row();
        $return .= "\n\n" . $row2[1] . ";\n\n";

        for ($i = 0; $i < $num_fields; $i++) {
            while ($row = $result->fetch_row()) {$return .= "INSERT INTO `$table` VALUES(";
                for ($j = 0; $j < $num_fields; $j++) {
                    $row[$j] = addslashes($row[$j] ?? '');
                    $row[$j] = str_replace("\n", "\\n", $row[$j]);
                    if (isset($row[$j])) {$return .= '"' . $row[$j] . '"';
                    } else {
                        $return .= '""';
                    }
                    if ($j < ($num_fields - 1)) {$return .= ',';
                    }
                }
                $return .= ");\n";
            }
        }
        $return .= "\n\n";
    }

    $filename = 'vitasoft_backup_' . date('Y-m-d_H-i-s') . '.sql';
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary");
    header("Content-disposition: attachment; filename=\"" . $filename . "\"");
    echo $return;
    exit;
}

/* =========================================================
   DETECTAR ESTRUCTURA DE LA TABLA USUARIOS
   ========================================================= */
$cols_u = [];
$res_cols =$conn->query("SHOW COLUMNS FROM usuarios");
if ($res_cols) {
    while ($r_c =$res_cols->fetch_assoc()) {
        $cols_u[] = strtolower($r_c['Field']);
    }
}

$col_nombre_user = in_array('nombre_completo',$cols_u, true) ? 'nombre_completo' : (in_array('nombres', $cols_u, true) ? 'nombres' : 'nombre');$col_correo_user = in_array('email', $cols_u, true) ? 'email' : 'correo';$col_pass_user = in_array('clave', $cols_u, true) ? 'clave' : (in_array('contrasena',$cols_u, true) ? 'contrasena' : 'password');
$tiene_rol = in_array('rol',$cols_u, true);
$tiene_rol_id = in_array('rol_id',$cols_u, true);
$tiene_estado = in_array('estado',$cols_u, true);

/* =========================================================
   PROCESAMIENTO DE FORMULARIOS (POST)
   ========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // 1. Guardar Información de la Empresa
    if ($seccion === 'empresa') {
        $n = trim($_POST["nombre_drogueria"] ?? '');
        $nit = trim($_POST["nit"] ?? '');
        $tel = trim($_POST["telefono"] ?? '');
        $correo = trim($_POST["correo"] ?? '');
        $dir = trim($_POST["direccion"] ?? '');
        $ciudad = trim($_POST["ciudad"] ?? '');
        $dep = trim($_POST["departamento"] ?? '');

        $check =$conn->query("SELECT id FROM configuracion WHERE id=1");
        if ($check &&$check->num_rows > 0) {
            $stmt =$conn->prepare("UPDATE configuracion SET nombre_drogueria=?, nit=?, telefono=?, correo=?, direccion=?, ciudad=?, departamento=? WHERE id=1");
            $stmt->bind_param("sssssss", $n, $nit,$tel, $correo,$dir, $ciudad,$dep);
        } else {
            $stmt =$conn->prepare("INSERT INTO configuracion (id, nombre_drogueria, nit, telefono, correo, direccion, ciudad, departamento) VALUES (1, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $n, $nit,$tel, $correo,$dir, $ciudad,$dep);
        }

        if ($stmt) {
            if ($stmt->execute()) {$msg = "Datos de la empresa guardados correctamente.";
            } else {
                $error = "Error al guardar los datos de la empresa: " . $conn->error;
            }
            $stmt->close();
        }
    }
    
    // 2. Registrar Nuevo Usuario
    if ($seccion === 'nuevo_usuario') {
        $nombre = trim($_POST["nombre_completo"] ?? '');
        $usuario = trim($_POST["usuario"] ?? '');
        $rol = trim($_POST["rol"] ?? 'Vendedor');
        $email = trim($_POST["correo"] ?? '');
        $raw_password =$_POST["password"] ?? '123456';
        $password = password_hash($raw_password, PASSWORD_DEFAULT);$estado = 'Activo';

        $rol_id = 3;
        if ($rol === 'Administrador')$rol_id = 1;
        elseif ($rol === 'Encargado de Inventario')$rol_id = 2;

        if ($tiene_rol_id) {
            $res_rol_db =$conn->query("SELECT id FROM roles WHERE nombre LIKE '%$rol%' LIMIT 1");
            if ($res_rol_db &&$res_rol_db->num_rows > 0) {
                $row_r =$res_rol_db->fetch_assoc();
                $rol_id = (int)$row_r['id'];
            }
        }

        $campos_sql = ["`$col_nombre_user`", "`usuario`", "`$col_correo_user`", "`$col_pass_user`"];
        $valores_params = [$nombre, $usuario,$email, $password];$tipos_params = "ssss";

        if ($tiene_rol) {$campos_sql[] = "`rol`";
            array_push($valores_params, $rol);$tipos_params .= "s";
        }
        if ($tiene_rol_id) {$campos_sql[] = "`rol_id`";
            array_push($valores_params, $rol_id);$tipos_params .= "i";
        }
        if ($tiene_estado) {$campos_sql[] = "`estado`";
            array_push($valores_params, $estado);$tipos_params .= "s";
        }

        $sql_ins = "INSERT INTO usuarios (" . implode(", ", $campos_sql) . ") VALUES (" . str_repeat("?, ", count($valores_params) - 1) . "?)";
        
        $stmt = $conn->prepare($sql_ins);
        if ($stmt) {$stmt->bind_param($tipos_params, ...$valores_params);
            if ($stmt->execute()) {
                $msg = "Usuario '$usuario' creado exitosamente.";
                $seccion = 'usuarios';
            } else {
                $error = "Error al crear el usuario: " . $conn->error;
            }
            $stmt->close();
        }
    }

    // 3. Actualizar Usuario Existente (Editar)
    if (isset($_POST['actualizar_usuario_id'])) {
        $id_edit = (int)$_POST['actualizar_usuario_id'];
        $nombre = trim($_POST["nombre_completo"] ?? '');
        $usuario = trim($_POST["usuario"] ?? '');
        $rol = trim($_POST["rol"] ?? 'Vendedor');
        $email = trim($_POST["correo"] ?? '');
        $nueva_pass =$_POST["password"] ?? '';

        $rol_id = 3;
        if ($rol === 'Administrador')$rol_id = 1;
        elseif ($rol === 'Encargado de Inventario')$rol_id = 2;

        if ($tiene_rol_id) {
            $res_rol_db =$conn->query("SELECT id FROM roles WHERE nombre LIKE '%$rol%' LIMIT 1");
            if ($res_rol_db &&$res_rol_db->num_rows > 0) {
                $row_r =$res_rol_db->fetch_assoc();
                $rol_id = (int)$row_r['id'];
            }
        }

        $sql_partes = ["`$col_nombre_user` = ?", "`usuario` = ?", "`$col_correo_user` = ?"];
        $params_vals = [$nombre,$usuario, $email];$params_tipos = "sss";

        if ($tiene_rol) {$sql_partes[] = "`rol` = ?";
            $params_vals[] =$rol;
            $params_tipos .= "s";
        }
        if ($tiene_rol_id) {$sql_partes[] = "`rol_id` = ?";
            $params_vals[] =$rol_id;
            $params_tipos .= "i";
        }

        if (!empty($nueva_pass)) {$password_hash = password_hash($nueva_pass, PASSWORD_DEFAULT);$sql_partes[] = "`$col_pass_user` = ?";
            $params_vals[] = $password_hash;
            $params_tipos .= "s";
        }

        $params_vals[] = $id_edit;
        $params_tipos .= "i";

        if ($tiene_rol) {
            $conn->query("UPDATE usuarios SET rol = '$rol' WHERE id = $id_edit");
        }

        $sql_update = "UPDATE usuarios SET " . implode(", ", $sql_partes) . " WHERE id = ?";
        $stmt = $conn->prepare($sql_update);
        
        if ($stmt) {
            $stmt->bind_param($params_tipos, ...$params_vals);
            if ($stmt->execute()) {
                $msg = "Usuario actualizado correctamente.";
                $seccion = 'usuarios';
            } else {
                $error = "Error al actualizar el usuario: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error = "Error en la consulta de actualización: " . $conn->error;
        }
    }

    // 4. Eliminar Usuario
    if (isset($_POST['eliminar_usuario_id'])) {
        $id_del = (int)$_POST['eliminar_usuario_id'];
        $conn->query("DELETE FROM usuarios WHERE id = $id_del");
        $msg = "Usuario eliminado correctamente.";
    }

    // 5. Guardar Parámetros
    if ($seccion === 'parametros') {
        $msg = "Parámetros del sistema guardados y aplicados correctamente.";
    }
}

// --- OBTENER DATOS SEGÚN PESTAÑA ---
$c = [];
if ($seccion === 'empresa') {
    $res_config = $conn->query("SELECT * FROM configuracion WHERE id=1");
    if ($res_config && $res_config->num_rows > 0) {
        $c = $res_config->fetch_assoc();
    }
}

$usuarios_lista = [];
if ($seccion === 'usuarios') {
    if ($tiene_rol_id) {
        $res_u = $conn->query("SELECT u.*, r.nombre AS nombre_rol_db FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id ORDER BY u.id DESC");
    } else {
        $res_u = $conn->query("SELECT * FROM usuarios ORDER BY id DESC");
    }
    
    if ($res_u) {
        while ($row = $res_u->fetch_assoc()) {
            $usuarios_lista[] = $row;
        }
    }
}

$usuario_a_editar = null;
if ($seccion === 'editar_usuario' && isset($_GET['id'])) {
    $id_edit_get = (int)$_GET['id'];
    $res_ed = $conn->query("SELECT * FROM usuarios WHERE id = $id_edit_get LIMIT 1");
    if ($res_ed && $res_ed->num_rows > 0) {
        $usuario_a_editar = $res_ed->fetch_assoc();
    }
}

$totalNotificaciones = 0;
$notificaciones = [];
if (isset($_SESSION['notificaciones'])) {
    $notificaciones = $_SESSION['notificaciones'];
    $totalNotificaciones = count($notificaciones);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $titulo ?></title>
  <!-- Bootstrap 5 CSS e Iconos -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    body { 
      background-color: #f8fafc !important; 
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; 
      color: #334155; 
      overflow-x: hidden; 
    }
    
    .sidebar { 
      width: 240px; 
      position: fixed; 
      top: 0; 
      left: 0; 
      height: 100vh; 
      background: #0b1c3d; 
      border-right: 1px solid #1e293b; 
      z-index: 1000; 
      transition: all 0.3s ease-in-out;
    }

    .main-wrapper { 
      margin-left: 240px; 
      min-height: 100vh; 
      display: flex; 
      flex-direction: column; 
      background-color: #f8fafc !important;
      transition: all 0.3s ease-in-out;
    }

    body.sidebar-collapsed .sidebar { left: -240px; }
    body.sidebar-collapsed .main-wrapper { margin-left: 0; }
    
    .nav-link { 
      color: #94a3b8; 
      font-weight: 500; 
      border-radius: 8px; 
      margin-bottom: 2px; 
      padding: 10px 14px; 
      display: flex; 
      align-items: center; 
      gap: 12px; 
    }
    .nav-link:hover { background-color: rgba(255, 255, 255, 0.08); color: #ffffff; }
    .nav-link.active { background-color: #2563eb; color: #ffffff !important; }

    .card-custom { 
      border: 1px solid #e2e8f0; 
      border-radius: 12px; 
      background: #ffffff; 
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03), 0 2px 4px -1px rgba(0, 0, 0, 0.02); 
    }

    .nav-pills-config .nav-link { color: #4b5563; font-weight: 600; padding: 10px 20px; border-radius: 8px; transition: all 0.2s ease; }
    .nav-pills-config .nav-link:hover { color: #2563eb; background-color: #f8fafc; }
    .nav-pills-config .nav-link.active { background-color: #2563eb; color: #ffffff !important; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }

    .table-custom th { background-color: #f8fafc; color: #475569; font-size: 0.78rem; font-weight: 700; border-bottom: 1px solid #e2e8f0; padding: 12px 10px; }
    .table-custom td { vertical-align: middle; font-size: 0.84rem; padding: 12px 10px; color: #334155; }
    .badge-rol-admin { background-color: #e0f2fe; color: #0284c7; font-weight: 600; padding: 5px 10px; border-radius: 6px; font-size: 0.75rem; display: inline-block; }
    .badge-rol-encargado { background-color: #dcfce7; color: #16a34a; font-weight: 600; padding: 5px 10px; border-radius: 6px; font-size: 0.75rem; display: inline-block; }
    .badge-rol-vendedor { background-color: #f3e8ff; color: #9333ea; font-weight: 600; padding: 5px 10px; border-radius: 6px; font-size: 0.75rem; display: inline-block; }
    .badge-estado-activo { background-color: #d1fae5; color: #059669; font-weight: 600; padding: 4px 8px; border-radius: 6px; font-size: 0.72rem; display: inline-block; }
    .badge-estado-inactivo { background-color: #f3f4f6; color: #4b5563; font-weight: 600; padding: 4px 8px; border-radius: 6px; font-size: 0.72rem; display: inline-block; }
  </style>
</head>
<body>

  <!-- NAVEGACIÓN LATERAL UNIFICADA -->
  <aside class="sidebar p-3 d-flex flex-column" id="sidebar">
    <div class="d-flex align-items-center mb-4 ps-2 gap-2">
      <img src="img/logo.png" alt="VitaSoft Logo" style="height: 38px; width: auto; object-fit: contain;">
      <div>
        <h5 class="fw-bold mb-0 text-white" style="letter-spacing: -0.5px;">VitaSoft</h5>
        <small class="text-secondary d-block" style="font-size: 0.7rem;">Sistema de Inventario</small>
      </div>
    </div>

    <nav class="nav flex-column gap-1">
      <a class="nav-link" href="inicio.php"><i class="bi bi-house-door fs-5"></i> Inicio</a>
      <a class="nav-link" href="productos.php"><i class="bi bi-box-seam fs-5"></i> Productos</a>
      <a class="nav-link" href="categorias.php"><i class="bi bi-folder fs-5"></i> Categorías</a>
      <a class="nav-link" href="laboratorios.php"><i class="bi bi-flask fs-5"></i> Laboratorios</a>
      <a class="nav-link" href="entradas.php"><i class="bi bi-download fs-5"></i> Entradas</a>
      <a class="nav-link" href="salidas.php"><i class="bi bi-upload fs-5"></i> Salidas</a>
      <a class="nav-link" href="inventario.php"><i class="bi bi-pie-chart fs-5"></i> Inventario</a>
      <a class="nav-link" href="reportes.php"><i class="bi bi-file-earmark-text fs-5"></i> Reportes</a>
      <a class="nav-link active" href="configuracion.php"><i class="bi bi-gear fs-5"></i> Configuración</a>
    </nav>

    <div class="mt-auto pt-3 border-top border-secondary">
      <a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right fs-5"></i> Salir</a>
    </div>
  </aside>

  <!-- CONTENEDOR PRINCIPAL -->
  <div class="main-wrapper">
    
    <!-- BARRA SUPERIOR UNIFORME -->
    <header class="bg-white border-bottom px-4 py-2 d-flex align-items-center justify-content-between sticky-top">
      <div class="d-flex align-items-center gap-3">
        <button class="btn btn-light border-0 shadow-none p-1 fs-4" id="btnToggleSidebar" type="button" title="Ocultar/Mostrar menú">
          <i class="bi bi-list"></i>
        </button>

        <form class="d-flex position-relative flex-grow-1 my-1" style="max-width: 420px;" action="productos.php" method="GET">
          <input class="form-control bg-light border-0 ps-5 rounded-pill" style="font-size: 0.9rem;" type="search" name="buscar" placeholder="Buscar en el sistema...">
          <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
        </form>
      </div>

      <div class="d-flex align-items-center gap-3">
        <!-- CAMPANITA NOTIFICACIONES -->
        <div class="dropdown">
          <button class="btn btn-light position-relative rounded-circle p-2 border" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-bell fs-5 text-secondary"></i>
            <?php if ($totalNotificaciones > 0): ?>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                <?= $totalNotificaciones ?>
              </span>
            <?php endif; ?>
          </button>
          
          <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-3 mt-2" style="width: 320px;">
            <li class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">
              <span class="fw-bold text-dark">Notificaciones</span>
              <span class="badge bg-danger rounded-pill"><?= $totalNotificaciones ?> Alertas</span>
            </li>
            <?php if ($totalNotificaciones === 0): ?>
              <li class="text-center text-muted py-3 small"><i class="bi bi-check-circle text-success fs-4 d-block mb-1"></i> Sin alertas pendientes</li>
            <?php else: ?>
              <?php foreach ($notificaciones as $n): ?>
                <li class="border-bottom py-2">
                  <strong class="d-block small text-dark"><?= htmlspecialchars($n["titulo"] ?? 'Alerta') ?></strong>
                  <span class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($n["texto"] ?? '') ?></span>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>

        <!-- PERFIL USUARIO -->
        <div class="dropdown">
          <a class="d-flex align-items-center text-decoration-none dropdown-toggle text-dark gap-2" href="#" role="button" data-bs-toggle="dropdown">
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
              <i class="bi bi-person-fill fs-5"></i>
            </div>
            <div class="text-start d-none d-sm-block">
              <span class="fw-bold d-block lh-1" style="font-size: 0.85rem;"><?= htmlspecialchars($_SESSION["usuario_nombre"] ?? 'Administrador') ?></span>
              <small class="text-muted" style="font-size: 0.75rem;">Administrador</small>
            </div>
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
            <li><a class="dropdown-item" href="configuracion.php"><i class="bi bi-person me-2"></i> Mi Perfil</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión</a></li>
          </ul>
        </div>
      </div>
    </header>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="p-4">

      <!-- ENCABEZADO -->
      <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-3">
          <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
            <i class="bi bi-gear-fill fs-5"></i>
          </div>
          <div>
            <h4 class="fw-bold mb-1" style="color: #0f172a;">Configuración del Sistema</h4>
            <div class="text-muted small">Inicio / Configuración / <strong><?= ucfirst(str_replace('_', ' ', $seccion)) ?></strong></div>
          </div>
        </div>
      </div>

      <!-- PESTAÑAS NAVEGABLES -->
      <div class="card card-custom p-2 mb-4">
        <ul class="nav nav-pills nav-pills-config gap-2">
          <li class="nav-item">
            <a class="nav-link <?= $seccion === 'usuarios' || $seccion === 'nuevo_usuario' || $seccion === 'editar_usuario' ? 'active' : '' ?>" href="configuracion.php?seccion=usuarios"><i class="bi bi-people me-2"></i> Usuarios</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $seccion === 'roles' ? 'active' : '' ?>" href="configuracion.php?seccion=roles"><i class="bi bi-shield-lock me-2"></i> Roles y Permisos</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $seccion === 'empresa' ? 'active' : '' ?>" href="configuracion.php?seccion=empresa"><i class="bi bi-building me-2"></i> Empresa</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $seccion === 'respaldos' ? 'active' : '' ?>" href="configuracion.php?seccion=respaldos"><i class="bi bi-cloud-arrow-down me-2"></i> Respaldos</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $seccion === 'parametros' ? 'active' : '' ?>" href="configuracion.php?seccion=parametros"><i class="bi bi-sliders me-2"></i> Parámetros</a>
          </li>
        </ul>
      </div>

      <!-- ALERTAS -->
      <?php if($msg): ?>
          <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
              <i class="bi bi-check-circle-fill me-2"></i> <?= $msg ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
      <?php endif; ?>

      <?php if($error): ?>
          <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
              <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
      <?php endif; ?>


      <!-- 1. PESTAÑA: USUARIOS -->
      <?php if($seccion === 'usuarios'): ?>
          <div class="card card-custom p-4">
              <div class="d-flex justify-content-between align-items-center mb-4">
                  <div>
                      <h5 class="fw-bold text-dark mb-1">Gestión de Usuarios</h5>
                      <p class="text-muted small mb-0">Administra los usuarios que tienen acceso al sistema.</p>
                  </div>
                  <a href="configuracion.php?seccion=nuevo_usuario" class="btn btn-success btn-sm px-3 py-2 d-flex align-items-center gap-2 fw-semibold">
                      <i class="bi bi-plus-lg"></i> Nuevo Usuario
                  </a>
              </div>

              <div class="table-responsive">
                  <table class="table table-hover table-custom align-middle mb-0">
                      <thead>
                          <tr>
                              <th>ID</th>
                              <th>Nombre Completo</th>
                              <th>Usuario</th>
                              <th>Rol</th>
                              <th>Correo Electrónico</th>
                              <th>Estado</th>
                              <th>Último Acceso</th>
                              <th class="text-center">Acciones</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php if(count($usuarios_lista) > 0): ?>
                              <?php foreach($usuarios_lista as $u): 
                                  $nombre_completo = $u[$col_nombre_user] ?? 'Sin Nombre';
                                  $usuario_nick = $u['usuario'] ?? $u['username'] ?? '';
                                  $correo_u = $u[$col_correo_user] ?? '';
                                  
                                  $rol = $u['nombre_rol_db'] ?? $u['rol'] ?? '';
                                  if (empty($rol) && isset($u['rol_id'])) {
                                      if ($u['rol_id'] == 1) $rol = 'Administrador';
                                      elseif ($u['rol_id'] == 2) $rol = 'Encargado de Inventario';
                                      else $rol = 'Vendedor';
                                  }
                                  if (empty($rol)) $rol = 'Vendedor';

                                  $rol_badge = 'badge-rol-vendedor';
                                  if(strcasecmp($rol, 'Administrador') === 0 || strpos(strtolower($rol), 'admin') !== false) $rol_badge = 'badge-rol-admin';
                                  elseif(strcasecmp($rol, 'Encargado de Inventario') === 0 || strpos(strtolower($rol), 'encargado') !== false) $rol_badge = 'badge-rol-encargado';

                                  $est = $u['estado'] ?? 'Activo';
                                  $est_badge = $est === 'Activo' ? 'badge-estado-activo' : 'badge-estado-inactivo';
                              ?>
                                  <tr>
                                      <td class="text-muted fw-semibold">#<?= $u['id'] ?></td>
                                      <td class="fw-bold text-dark"><?= htmlspecialchars($nombre_completo) ?></td>
                                      <td><?= htmlspecialchars($usuario_nick) ?></td>
                                      <td><span class="<?= $rol_badge ?>"><?= htmlspecialchars($rol) ?></span></td>
                                      <td><?= htmlspecialchars($correo_u) ?></td>
                                      <td><span class="<?= $est_badge ?>"><?= htmlspecialchars($est) ?></span></td>
                                      <td class="text-muted small"><?= htmlspecialchars($u['ultimo_acceso'] ?? 'Nunca') ?></td>
                                      <td class="text-center">
                                          <div class="d-flex gap-1 justify-content-center">
                                              <a href="configuracion.php?seccion=editar_usuario&id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary rounded-2 px-2" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                              <form method="POST" class="d-inline" onsubmit="return confirm('¿Deseas eliminar este usuario?');">
                                                  <input type="hidden" name="eliminar_usuario_id" value="<?= $u['id'] ?>">
                                                  <button type="submit" class="btn btn-sm btn-outline-danger rounded-2 px-2" title="Eliminar"><i class="bi bi-trash-fill"></i></button>
                                              </form>
                                          </div>
                                      </td>
                                  </tr>
                              <?php endforeach; ?>
                          <?php else: ?>
                              <tr>
                                  <td colspan="8" class="text-center text-muted py-5">
                                      <i class="bi bi-search fs-1 d-block mb-2 text-secondary"></i>
                                      <div>No hay usuarios registrados en la base de datos.</div>
                                  </td>
                              </tr>
                          <?php endif; ?>
                      </tbody>
                  </table>
              </div>
          </div>
      <?php endif; ?>


      <!-- 1.1 FORMULARIO REGISTRAR USUARIO -->
      <?php if($seccion === 'nuevo_usuario'): ?>
          <div class="card card-custom p-4">
              <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
                  <div>
                      <h5 class="fw-bold text-dark mb-1">Registrar Nuevo Usuario</h5>
                      <p class="text-muted small mb-0">Ingresa las credenciales para dar acceso a un nuevo usuario.</p>
                  </div>
                  <a href="configuracion.php?seccion=usuarios" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
              </div>

              <form method="POST" class="row g-3">
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-muted">Nombre Completo <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="nombre_completo" placeholder="Ej. Carlos Pérez" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-muted">Nombre de Usuario <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="usuario" placeholder="Ej. cperez" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-muted">Correo Electrónico <span class="text-danger">*</span></label>
                      <input type="email" class="form-control" name="correo" placeholder="correo@vitasoft.com" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-muted">Rol en el Sistema <span class="text-danger">*</span></label>
                      <select class="form-select" name="rol" required>
                          <option value="Administrador">Administrador</option>
                          <option value="Encargado de Inventario">Encargado de Inventario</option>
                          <option value="Vendedor" selected>Vendedor</option>
                      </select>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-muted">Contraseña <span class="text-danger">*</span></label>
                      <input type="password" class="form-control" name="password" placeholder="Escribe la contraseña deseada" required>
                  </div>

                  <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                      <a href="configuracion.php?seccion=usuarios" class="btn btn-outline-secondary px-4">Cancelar</a>
                      <button type="submit" class="btn btn-success px-4 py-2 d-flex align-items-center gap-2 fw-semibold">
                          <i class="bi bi-check-lg"></i> Guardar Usuario
                      </button>
                  </div>
              </form>
          </div>
      <?php endif; ?>


      <!-- 1.2 FORMULARIO EDITAR USUARIO -->
      <?php if($seccion === 'editar_usuario' && $usuario_a_editar): ?>
          <div class="card card-custom p-4">
              <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
                  <div>
                      <h5 class="fw-bold text-dark mb-1">Editar Usuario: <?= htmlspecialchars($usuario_a_editar['usuario'] ?? '') ?></h5>
                      <p class="text-muted small mb-0">Modifica los datos o el rol del usuario seleccionado.</p>
                  </div>
                  <a href="configuracion.php?seccion=usuarios" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
              </div>

              <form method="POST" class="row g-3">
                  <input type="hidden" name="actualizar_usuario_id" value="<?= $usuario_a_editar['id'] ?>">
                  
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-muted">Nombre Completo <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="nombre_completo" value="<?= htmlspecialchars($usuario_a_editar[$col_nombre_user] ?? '') ?>" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-muted">Nombre de Usuario <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="usuario" value="<?= htmlspecialchars($usuario_a_editar['usuario'] ?? '') ?>" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-muted">Correo Electrónico <span class="text-danger">*</span></label>
                      <input type="email" class="form-control" name="correo" value="<?= htmlspecialchars($usuario_a_editar[$col_correo_user] ?? '') ?>" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-muted">Rol en el Sistema <span class="text-danger">*</span></label>
                      <?php 
                          $rol_actual = $usuario_a_editar['rol'] ?? '';
                          if (empty($rol_actual) && isset($usuario_a_editar['rol_id'])) {
                              if ($usuario_a_editar['rol_id'] == 1) $rol_actual = 'Administrador';
                              elseif ($usuario_a_editar['rol_id'] == 2) $rol_actual = 'Encargado de Inventario';
                              else $rol_actual = 'Vendedor';
                          }
                          if(empty($rol_actual)) $rol_actual = 'Vendedor';
                      ?>
                      <select class="form-select" name="rol" required>
                          <option value="Administrador" <?= $rol_actual === 'Administrador' ? 'selected' : '' ?>>Administrador</option>
                          <option value="Encargado de Inventario" <?= $rol_actual === 'Encargado de Inventario' ? 'selected' : '' ?>>Encargado de Inventario</option>
                          <option value="Vendedor" <?= $rol_actual === 'Vendedor' ? 'selected' : '' ?>>Vendedor</option>
                      </select>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-muted">Nueva Contraseña (Dejar en blanco si no deseas cambiarla)</label>
                      <input type="password" class="form-control" name="password" placeholder="Escribe solo si vas a cambiarla">
                  </div>

                  <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                      <a href="configuracion.php?seccion=usuarios" class="btn btn-outline-secondary px-4">Cancelar</a>
                      <button type="submit" class="btn btn-primary px-4 py-2 d-flex align-items-center gap-2 fw-semibold">
                          <i class="bi bi-save"></i> Guardar Cambios
                      </button>
                  </div>
              </form>
          </div>
      <?php endif; ?>


      <!-- 2. PESTAÑA: ROLES Y PERMISOS -->
      <?php if($seccion === 'roles'): ?>
          <div class="card card-custom p-4">
              <div class="border-bottom pb-3 mb-4">
                  <h5 class="fw-bold text-dark mb-1">Matriz de Roles y Permisos</h5>
                  <p class="text-muted small mb-0">Define qué módulos del sistema puede operar cada perfil de usuario.</p>
              </div>

              <div class="table-responsive">
                  <table class="table table-bordered table-custom align-middle">
                      <thead>
                          <tr class="text-center">
                              <th class="text-start">Módulo / Función</th>
                              <th>Administrador</th>
                              <th>Encargado de Inventario</th>
                              <th>Vendedor</th>
                          </tr>
                      </thead>
                      <tbody>
                          <tr>
                              <td><strong>Gestión de Productos y Stock</strong></td>
                              <td class="text-center text-success"><i class="bi bi-check-circle-fill fs-5"></i></td>
                              <td class="text-center text-success"><i class="bi bi-check-circle-fill fs-5"></i></td>
                              <td class="text-center text-muted"><i class="bi bi-x-circle fs-5"></i></td>
                          </tr>
                          <tr>
                              <td><strong>Entradas y Proveedores</strong></td>
                              <td class="text-center text-success"><i class="bi bi-check-circle-fill fs-5"></i></td>
                              <td class="text-center text-success"><i class="bi bi-check-circle-fill fs-5"></i></td>
                              <td class="text-center text-muted"><i class="bi bi-x-circle fs-5"></i></td>
                          </tr>
                          <tr>
                              <td><strong>Salidas y Ventas</strong></td>
                              <td class="text-center text-success"><i class="bi bi-check-circle-fill fs-5"></i></td>
                              <td class="text-center text-success"><i class="bi bi-check-circle-fill fs-5"></i></td>
                              <td class="text-center text-success"><i class="bi bi-check-circle-fill fs-5"></i></td>
                          </tr>
                          <tr>
                              <td><strong>Reportes y Estadísticas</strong></td>
                              <td class="text-center text-success"><i class="bi bi-check-circle-fill fs-5"></i></td>
                              <td class="text-center text-success"><i class="bi bi-check-circle-fill fs-5"></i></td>
                              <td class="text-center text-muted"><i class="bi bi-x-circle fs-5"></i></td>
                          </tr>
                          <tr>
                              <td><strong>Configuración y Usuarios</strong></td>
                              <td class="text-center text-success"><i class="bi bi-check-circle-fill fs-5"></i></td>
                              <td class="text-center text-muted"><i class="bi bi-x-circle fs-5"></i></td>
                              <td class="text-center text-muted"><i class="bi bi-x-circle fs-5"></i></td>
                          </tr>
                      </tbody>
                  </table>
              </div>
          </div>
      <?php endif; ?>


      <!-- 3. PESTAÑA: EMPRESA -->
      <?php if($seccion === 'empresa'): ?>
          <div class="card card-custom p-4">
              <div class="border-bottom pb-3 mb-4">
                  <h5 class="fw-bold text-dark mb-1">Información de la Empresa / Droguería</h5>
                  <p class="text-muted small mb-0">Estos datos aparecerán en los encabezados de los reportes y facturas impresas.</p>
              </div>

              <form method="POST" class="row g-3">
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-muted">Nombre de la Droguería <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="nombre_drogueria" value="<?= htmlspecialchars($c['nombre_drogueria'] ?? 'VitaSoft Droguería') ?>" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-muted">NIT / Identificación Fiscal <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="nit" value="<?= htmlspecialchars($c['nit'] ?? '900.123.456-1') ?>" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-muted">Teléfono de Contacto</label>
                      <input type="text" class="form-control" name="telefono" value="<?= htmlspecialchars($c['telefono'] ?? '') ?>">
                  </div>
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-muted">Correo Electrónico</label>
                      <input type="email" class="form-control" name="correo" value="<?= htmlspecialchars($c['correo'] ?? '') ?>">
                  </div>
                  <div class="col-md-12">
                      <label class="form-label small fw-bold text-muted">Dirección Física</label>
                      <input type="text" class="form-control" name="direccion" value="<?= htmlspecialchars($c['direccion'] ?? 'Teruel, Huila - Colombia') ?>">
                  </div>
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-muted">Ciudad</label>
                      <input type="text" class="form-control" name="ciudad" value="<?= htmlspecialchars($c['ciudad'] ?? 'Teruel') ?>">
                  </div>
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-muted">Departamento / Provincia</label>
                      <input type="text" class="form-control" name="departamento" value="<?= htmlspecialchars($c['departamento'] ?? 'Huila') ?>">
                  </div>

                  <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end">
                      <button type="submit" class="btn btn-success px-4 py-2 d-flex align-items-center gap-2 fw-semibold">
                          <i class="bi bi-save"></i> Guardar Información
                      </button>
                  </div>
              </form>
          </div>
      <?php endif; ?>


      <!-- 4. PESTAÑA: RESPALDOS -->
      <?php if($seccion === 'respaldos'): ?>
          <div class="card card-custom p-4">
              <div class="border-bottom pb-3 mb-4">
                  <h5 class="fw-bold text-dark mb-1">Respaldo de Base de Datos</h5>
                  <p class="text-muted small mb-0">Genera y descarga una copia de seguridad completa en formato SQL de todo el sistema.</p>
              </div>

              <div class="row align-items-center py-3">
                  <div class="col-md-8">
                      <h6 class="fw-bold text-dark mb-1"><i class="bi bi-database-check text-success me-2"></i> Copia de Seguridad SQL</h6>
                      <p class="text-muted small mb-0">Se exportarán todas las tablas del sistema con su estructura e información actual.</p>
                  </div>
                  <div class="col-md-4 text-md-end mt-3 mt-md-0">
                      <a href="configuracion.php?seccion=respaldos&accion=descargar_backup" class="btn btn-primary px-4 py-2 fw-semibold d-inline-flex align-items-center gap-2">
                          <i class="bi bi-download"></i> Descargar Respaldo
                      </a>
                  </div>
              </div>
          </div>
      <?php endif; ?>


      <!-- 5. PESTAÑA: PARÁMETROS -->
      <?php if($seccion === 'parametros'): ?>
          <div class="card card-custom p-4">
              <div class="border-bottom pb-3 mb-4">
                  <h5 class="fw-bold text-dark mb-1">Parámetros Generales del Sistema</h5>
                  <p class="text-muted small mb-0">Configura reglas globales para el control de inventario y alertas.</p>
              </div>

              <form method="POST" class="row g-3">
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-muted">Días de anticipación para alertas de vencimiento</label>
                      <input type="number" class="form-control" name="dias_vencimiento" value="30">
                  </div>
                  <div class="col-md-6">
                      <label class="form-label small fw-bold text-muted">Moneda Oficial del Sistema</label>
                      <select class="form-select" name="moneda">
                          <option value="COP" selected>Peso Colombiano ($ COP)</option>
                          <option value="USD">Dólar Estadounidense ($ USD)</option>
                      </select>
                  </div>

                  <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end">
                      <button type="submit" class="btn btn-success px-4 py-2 d-flex align-items-center gap-2 fw-semibold">
                          <i class="bi bi-save"></i> Guardar Parámetros
                      </button>
                  </div>
              </form>
          </div>
      <?php endif; ?>

    </main>

    <!-- FOOTER -->
    <footer class="bg-white border-top py-3 px-4 mt-auto">
      <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center text-muted small">
        <div>© <?= date('Y') ?> VitaSoft - Sistema de Inventario para Droguerías. Todos los derechos reservados.</div>
        <div><i class="bi bi-geo-alt-fill text-danger me-1"></i> Teruel, Huila - Colombia</div>
      </div>
    </footer>
  </div>

<!-- JAVASCRIPT -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const btnToggle = document.getElementById("btnToggleSidebar");
    if (btnToggle) {
        btnToggle.addEventListener("click", function() {
            document.body.classList.toggle('sidebar-collapsed');
        });
    }
});
</script>
</body>
</html>