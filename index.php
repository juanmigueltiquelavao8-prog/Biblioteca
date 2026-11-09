<?php
date_default_timezone_set('America/Bogota');
session_start();
require_once 'conex/conexion.php';

$mensaje_error = '';
$mensaje_exito = '';

// --- 1. PROCESAR CIERRE DE SESIÓN ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

// --- 2. PROCESAR REGISTRO PÚBLICO (Estudiantes) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registro'])) {
    $documento       = trim($_POST['documento'] ?? '');
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $correo          = trim($_POST['correo'] ?? '');
    $contrasena      = trim($_POST['contrasena'] ?? '');
    $rol             = 'Estudiante'; 

    if (!empty($documento) && !empty($nombre_completo) && !empty($correo) && !empty($contrasena)) {
        $pass_hashed = password_hash($contrasena, PASSWORD_DEFAULT);
        $paz_y_salvo = 1;

        $sql_reg = "INSERT INTO usuarios (documento, nombre_completo, correo, rol, contrasena, paz_y_salvo) 
                    VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_reg = $conexion->prepare($sql_reg);
        $stmt_reg->bind_param("sssssi", $documento, $nombre_completo, $correo, $rol, $pass_hashed, $paz_y_salvo);

        if ($stmt_reg->execute()) {
            $mensaje_exito = "¡Cuenta de estudiante creada con éxito! Ahora puedes ingresar.";
        } else {
            $mensaje_error = "Error al registrarte. Verifica si el correo o documento ya están registrados.";
        }
        $stmt_reg->close();
    } else {
        $mensaje_error = "Por favor complete todos los campos obligatorios.";
    }
}

// --- 3. RECUPERACIÓN DIRECTA: VERIFICAR CORREO Y CAMBIAR CONTRASEÑA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verificar_correo_recuperacion'])) {
    $correo = trim($_POST['correo_recuperacion'] ?? '');

    if (!empty($correo)) {
        $sql_ver = "SELECT id_usuario FROM usuarios WHERE correo = ? LIMIT 1";
        $stmt_ver = $conexion->prepare($sql_ver);
        $stmt_ver->bind_param("s", $correo);
        $stmt_ver->execute();
        $res_ver = $stmt_ver->get_result();

        if ($res_ver->num_rows === 1) {
            // Guardamos el correo en sesión de forma temporal para permitir el cambio inmediato
            $_SESSION['correo_recuperando'] = $correo;
            header("Location: index.php?action=cambiar_pass_directo");
            exit();
        } else {
            $mensaje_error = "No existe ninguna cuenta registrada con ese correo electrónico.";
        }
        $stmt_ver->close();
    } else {
        $mensaje_error = "Por favor ingresa tu correo institucional.";
    }
}

// --- 4. PROCESAR CAMBIO DIRECTO DE CONTRASEÑA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_pass_directo'])) {
    $nueva_pass   = trim($_POST['nueva_contrasena'] ?? '');
    $confirm_pass = trim($_POST['confirmar_contrasena'] ?? '');
    $correo_act   = $_SESSION['correo_recuperando'] ?? '';

    if (empty($nueva_pass) || empty($confirm_pass)) {
        $mensaje_error = "Por favor completa todos los campos.";
    } elseif ($nueva_pass !== $confirm_pass) {
        $mensaje_error = "Las contraseñas no coinciden. Inténtalo de nuevo.";
    } elseif (empty($correo_act)) {
        $mensaje_error = "Sesión de recuperación expirada. Vuelve a ingresar tu correo.";
        header("Location: index.php?action=forgot_pass");
        exit();
    } else {
        $pass_hashed = password_hash($nueva_pass, PASSWORD_DEFAULT);
        $sql_upd = "UPDATE usuarios SET contrasena = ? WHERE correo = ?";
        $stmt_u = $conexion->prepare($sql_upd);
        $stmt_u->bind_param("ss", $pass_hashed, $correo_act);

        if ($stmt_u->execute()) {
            unset($_SESSION['correo_recuperando']);
            $mensaje_exito = "¡Tu contraseña se ha cambiado correctamente! Ya puedes iniciar sesión.";
            // Redirigir al login limpio después de un momento o mostrar éxito
        } else {
            $mensaje_error = "Error al actualizar la contraseña en la base de datos.";
        }
        $stmt_u->close();
    }
}

// --- 5. PROCESAR CREACIÓN DE DOCENTE / USUARIO (EXCLUSIVO ADMINISTRADOR) ---
if (isset($_SESSION['usuario']) && $_SESSION['usuario']['rol'] === 'Administrador' && isset($_POST['crear_usuario_admin'])) {
    $documento       = trim($_POST['documento'] ?? '');
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $correo          = trim($_POST['correo'] ?? '');
    $rol             = trim($_POST['rol_nuevo'] ?? 'Docente');
    $contrasena      = trim($_POST['contrasena'] ?? '');

    if (!empty($documento) && !empty($nombre_completo) && !empty($correo) && !empty($contrasena)) {
        $pass_hashed = password_hash($contrasena, PASSWORD_DEFAULT);
        $paz_y_salvo = 1;

        $sql_adm_u = "INSERT INTO usuarios (documento, nombre_completo, correo, rol, contrasena, paz_y_salvo) 
                      VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_au = $conexion->prepare($sql_adm_u);
        $stmt_au->bind_param("sssssi", $documento, $nombre_completo, $correo, $rol, $pass_hashed, $paz_y_salvo);

        if ($stmt_au->execute()) {
            $mensaje_exito = "¡Usuario ($rol) registrado exitosamente!";
        } else {
            $mensaje_error = "Error al registrar el usuario. Verifique correo o documento.";
        }
        $stmt_au->close();
    } else {
        $mensaje_error = "Por favor complete todos los campos requeridos.";
    }
}

// --- 6. PROCESAR INICIO DE SESIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $correo     = trim($_POST['correo'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');
    $rol        = trim($_POST['rol'] ?? '');

    if (!empty($correo) && !empty($contrasena) && !empty($rol)) {
        $sql_login = "SELECT id_usuario, documento, nombre_completo, correo, rol, contrasena, paz_y_salvo 
                    FROM usuarios 
                    WHERE correo = ? AND rol = ? LIMIT 1";
        
        $stmt_login = $conexion->prepare($sql_login);
        $stmt_login->bind_param("ss", $correo, $rol);
        $stmt_login->execute();
        $res_login = $stmt_login->get_result();

        if ($res_login->num_rows === 1) {
            $user = $res_login->fetch_assoc();
            
            if (password_verify($contrasena, $user['contrasena']) || $contrasena === $user['contrasena']) {
                $_SESSION['usuario'] = [
                    'id_usuario'      => $user['id_usuario'],
                    'nombre_completo' => $user['nombre_completo'],
                    'documento'       => $user['documento'],
                    'rol'             => $user['rol'],
                    'paz_y_salvo'     => $user['paz_y_salvo']
                ];
                header("Location: index.php");
                exit();
            } else {
                $mensaje_error = "Credenciales incorrectas. Verifique su contraseña.";
            }
        } else {
            $mensaje_error = "No se encontró un usuario con ese correo y rol seleccionado.";
        }
        $stmt_login->close();
    } else {
        $mensaje_error = "Por favor complete todos los campos.";
    }
}

// --- 7. PROCESAR CREACIÓN / EDICIÓN DE RECURSO (ADMINISTRADOR) ---
if (isset($_SESSION['usuario']) && $_SESSION['usuario']['rol'] === 'Administrador') {
    
    if (isset($_POST['guardar_nuevo_recurso'])) {
        $codigo          = trim($_POST['codigo_inventario'] ?? '');
        $titulo          = trim($_POST['titulo_nombre'] ?? '');
        $tipo            = trim($_POST['tipo_recurso'] ?? '');
        $autor           = trim($_POST['autor'] ?? '');
        $editorial       = trim($_POST['editorial'] ?? '');
        $anio            = intval($_POST['anio_publicacion'] ?? 0);
        $isbn            = trim($_POST['isbn'] ?? '');
        $caracteristicas = trim($_POST['caracteristicas'] ?? '');
        $estado          = trim($_POST['estado'] ?? 'Disponible');

        if (!empty($codigo) && !empty($titulo) && !empty($tipo)) {
            $sql_ins_rec = "INSERT INTO recursos (codigo_inventario, titulo_nombre, tipo_recurso, autor, editorial, anio_publicacion, isbn, caracteristicas, estado) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_ir = $conexion->prepare($sql_ins_rec);
            $stmt_ir->bind_param("sssssisss", $codigo, $titulo, $tipo, $autor, $editorial, $anio, $isbn, $caracteristicas, $estado);
            
            if ($stmt_ir->execute()) {
                $mensaje_exito = "¡Recurso registrado exitosamente en el catálogo!";
            } else {
                $mensaje_error = "Error al registrar el recurso. Verifique que el código de inventario no esté duplicado.";
            }
            $stmt_ir->close();
        } else {
            $mensaje_error = "Por favor complete los campos obligatorios.";
        }
    }

    if (isset($_POST['actualizar_recurso'])) {
        $id_recurso      = intval($_POST['id_recurso'] ?? 0);
        $codigo          = trim($_POST['codigo_inventario'] ?? '');
        $titulo          = trim($_POST['titulo_nombre'] ?? '');
        $tipo            = trim($_POST['tipo_recurso'] ?? '');
        $autor           = trim($_POST['autor'] ?? '');
        $editorial       = trim($_POST['editorial'] ?? '');
        $anio            = intval($_POST['anio_publicacion'] ?? 0);
        $isbn            = trim($_POST['isbn'] ?? '');
        $caracteristicas = trim($_POST['caracteristicas'] ?? '');
        $estado          = trim($_POST['estado'] ?? 'Disponible');

        if ($id_recurso > 0 && !empty($codigo) && !empty($titulo) && !empty($tipo)) {
            $sql_up_rec = "UPDATE recursos 
                           SET codigo_inventario = ?, titulo_nombre = ?, tipo_recurso = ?, autor = ?, editorial = ?, anio_publicacion = ?, isbn = ?, caracteristicas = ?, estado = ?
                           WHERE id_recurso = ?";
            $stmt_ur = $conexion->prepare($sql_up_rec);
            $stmt_ur->bind_param("sssssisssi", $codigo, $titulo, $tipo, $autor, $editorial, $anio, $isbn, $caracteristicas, $estado, $id_recurso);

            if ($stmt_ur->execute()) {
                $mensaje_exito = "¡Recurso actualizado correctamente!";
            } else {
                $mensaje_error = "Error al actualizar los datos del recurso.";
            }
            $stmt_ur->close();
        }
    }
}

// --- 8. PROCESAR SOLICITUD DE RECURSO CON TIEMPO (HORAS O DÍAS) ---
if (isset($_SESSION['usuario']) && in_array($_SESSION['usuario']['rol'], ['Estudiante', 'Docente']) && isset($_POST['solicitar_recurso'])) {
    $id_usuario    = $_SESSION['usuario']['id_usuario'];
    $rol_usuario   = $_SESSION['usuario']['rol'];
    $id_recurso    = intval($_POST['id_recurso'] ?? 0);
    $unidad_tiempo = trim($_POST['unidad_tiempo'] ?? 'Dias'); 
    $cantidad      = intval($_POST['cantidad_tiempo'] ?? 1);
    $paz_y_salvo   = $_SESSION['usuario']['paz_y_salvo'];

    if (!$paz_y_salvo) {
        $mensaje_error = "No puedes solicitar recursos porque tienes sanciones pendientes o no estás a paz y salvo.";
    } elseif ($cantidad <= 0 || ($unidad_tiempo === 'Horas' && $cantidad > 24) || ($unidad_tiempo === 'Dias' && $cantidad > 60)) {
        $mensaje_error = "Por favor selecciona una cantidad de tiempo válida (máx. 24 horas o 60 días).";
    } elseif ($id_recurso > 0) {
        $sql_check = "SELECT estado, tipo_recurso FROM recursos WHERE id_recurso = ? LIMIT 1";
        $stmt_chk = $conexion->prepare($sql_check);
        $stmt_chk->bind_param("i", $id_recurso);
        $stmt_chk->execute();
        $res_chk = $stmt_chk->get_result();

        if ($res_chk->num_rows === 1) {
            $rec_data = $res_chk->fetch_assoc();
            
            $permitido = ($rol_usuario === 'Docente') || (strpos(strtolower($rec_data['tipo_recurso']), 'libro') !== false);

            if ($permitido && $rec_data['estado'] === 'Disponible') {
                $conexion->begin_transaction();

                try {
                    $fecha_prestamo = date('Y-m-d H:i:s');
                    
                    if ($unidad_tiempo === 'Horas') {
                        $fecha_limite = date('Y-m-d H:i:s', strtotime("+{$cantidad} hours"));
                    } else {
                        $fecha_limite = date('Y-m-d H:i:s', strtotime("+{$cantidad} days"));
                    }

                    $sql_insert = "INSERT INTO prestamos (id_usuario, id_recurso, fecha_prestamo, fecha_limite, tipo_duracion, cantidad_tiempo, estado_prestamo) 
                                   VALUES (?, ?, ?, ?, ?, ?, 'Activo')";
                    $stmt_ins = $conexion->prepare($sql_insert);
                    $stmt_ins->bind_param("iisssi", $id_usuario, $id_recurso, $fecha_prestamo, $fecha_limite, $unidad_tiempo, $cantidad);
                    $stmt_ins->execute();

                    $sql_update = "UPDATE recursos SET estado = 'Prestado' WHERE id_recurso = ?";
                    $stmt_upd = $conexion->prepare($sql_update);
                    $stmt_upd->bind_param("i", $id_recurso);
                    $stmt_upd->execute();

                    $conexion->commit();
                    $txt_unidad = ($unidad_tiempo === 'Horas') ? 'hora(s)' : 'día(s)';
                    $mensaje_exito = "¡Recurso solicitado con éxito por {$cantidad} {$txt_unidad}!";
                } catch (Exception $e) {
                    $conexion->rollback();
                    $mensaje_error = "Error al procesar la solicitud: " . $e->getMessage();
                }
            } else {
                $mensaje_error = "El recurso seleccionado no está disponible o no tienes permisos para solicitarlo.";
            }
        }
        $stmt_chk->close();
    }
}

// --- 9. PROCESAR CANCELACIÓN / DEVOLUCIÓN DE PRÉSTAMO ---
if (isset($_SESSION['usuario']) && in_array($_SESSION['usuario']['rol'], ['Estudiante', 'Docente']) && isset($_POST['cancelar_prestamo'])) {
    $id_usuario = $_SESSION['usuario']['id_usuario'];
    $id_prestamo = intval($_POST['id_prestamo'] ?? 0);

    if ($id_prestamo > 0) {
        $sql_chk_p = "SELECT id_recurso FROM prestamos WHERE id_prestamo = ? AND id_usuario = ? AND estado_prestamo = 'Activo' LIMIT 1";
        $stmt_cp = $conexion->prepare($sql_chk_p);
        $stmt_cp->bind_param("ii", $id_prestamo, $id_usuario);
        $stmt_cp->execute();
        $res_cp = $stmt_cp->get_result();

        if ($res_cp->num_rows === 1) {
            $prestamo_data = $res_cp->fetch_assoc();
            $id_recurso = $prestamo_data['id_recurso'];

            $conexion->begin_transaction();
            try {
                $sql_up_p = "UPDATE prestamos SET estado_prestamo = 'Devuelto' WHERE id_prestamo = ?";
                $stmt_upp = $conexion->prepare($sql_up_p);
                $stmt_upp->bind_param("i", $id_prestamo);
                $stmt_upp->execute();

                $sql_up_r = "UPDATE recursos SET estado = 'Disponible' WHERE id_recurso = ?";
                $stmt_upr = $conexion->prepare($sql_up_r);
                $stmt_upr->bind_param("i", $id_recurso);
                $stmt_upr->execute();

                $conexion->commit();
                $mensaje_exito = "Préstamo devuelto/cancelado con éxito. El recurso ha sido liberado.";
            } catch (Exception $e) {
                $conexion->rollback();
                $mensaje_error = "Error al cancelar el préstamo.";
            }
        }
        $stmt_cp->close();
    }
}

// --- 10. CONSULTAS PARA USUARIOS AUTENTICADOS ---
$resultado_recursos = null;
$resultado_prestamos = null;
$resultado_usuarios  = null;
$recurso_editar     = null;

if (isset($_SESSION['usuario'])) {
    $rol_actual   = $_SESSION['usuario']['rol'];
    $accion       = $_GET['action'] ?? '';

    if ($rol_actual === 'Administrador' && $accion === 'editar_recurso') {
        $id_rec_edit = intval($_GET['id'] ?? 0);
        if ($id_rec_edit > 0) {
            $sql_e = "SELECT * FROM recursos WHERE id_recurso = ? LIMIT 1";
            $stmt_e = $conexion->prepare($sql_e);
            $stmt_e->bind_param("i", $id_rec_edit);
            $stmt_e->execute();
            $recurso_editar = $stmt_e->get_result()->fetch_assoc();
            $stmt_e->close();
        }
    }

    if ($rol_actual === 'Administrador' && $accion === 'gestionar_usuarios') {
        $sql_usr = "SELECT id_usuario, documento, nombre_completo, correo, rol, paz_y_salvo FROM usuarios ORDER BY id_usuario DESC";
        $resultado_usuarios = $conexion->query($sql_usr);
    }

    if (in_array($rol_actual, ['Estudiante', 'Docente']) && in_array($accion, ['mis_prestamos', 'historial_aulas'])) {
        $id_usuario = $_SESSION['usuario']['id_usuario'];
        $sql_pres = "SELECT p.id_prestamo, 
                            DATE_FORMAT(p.fecha_prestamo, '%Y-%m-%d %h:%i %p') AS fecha_prestamo_f,
                            DATE_FORMAT(p.fecha_limite, '%Y-%m-%d %h:%i %p') AS fecha_limite_f,
                            p.fecha_limite AS fecha_limite_raw,
                            p.tipo_duracion, r.codigo_inventario, r.titulo_nombre, r.tipo_recurso
                     FROM prestamos p 
                     INNER JOIN recursos r ON p.id_recurso = r.id_recurso 
                     WHERE p.id_usuario = ? AND p.estado_prestamo = 'Activo' 
                     ORDER BY p.fecha_prestamo DESC";
                     
        $stmt_pres = $conexion->prepare($sql_pres);
        $stmt_pres->bind_param("i", $id_usuario);
        $stmt_pres->execute();
        $resultado_prestamos = $stmt_pres->get_result();

    } elseif (in_array($rol_actual, ['Estudiante', 'Docente']) && $accion === 'proximos_vencimientos') {
        $id_usuario = $_SESSION['usuario']['id_usuario'];
        $sql_pres = "SELECT p.id_prestamo, 
                            DATE_FORMAT(p.fecha_prestamo, '%Y-%m-%d %h:%i %p') AS fecha_prestamo_f,
                            DATE_FORMAT(p.fecha_limite, '%Y-%m-%d %h:%i %p') AS fecha_limite_f,
                            p.fecha_limite AS fecha_limite_raw,
                            p.tipo_duracion, r.codigo_inventario, r.titulo_nombre, r.tipo_recurso
                     FROM prestamos p 
                     INNER JOIN recursos r ON p.id_recurso = r.id_recurso 
                     WHERE p.id_usuario = ? AND p.estado_prestamo = 'Activo' 
                     ORDER BY p.fecha_limite ASC";
                     
        $stmt_pres = $conexion->prepare($sql_pres);
        $stmt_pres->bind_param("i", $id_usuario);
        $stmt_pres->execute();
        $resultado_prestamos = $stmt_pres->get_result();

    } elseif ($rol_actual === 'Administrador' && $accion === 'gestionar_prestamos') {
        $sql_pres = "SELECT p.id_prestamo, 
                            DATE_FORMAT(p.fecha_prestamo, '%Y-%m-%d %h:%i %p') AS fecha_prestamo_f,
                            DATE_FORMAT(p.fecha_limite, '%Y-%m-%d %h:%i %p') AS fecha_limite_f,
                            p.estado_prestamo, p.tipo_duracion,
                            u.nombre_completo, u.correo, r.codigo_inventario, r.titulo_nombre, r.tipo_recurso
                     FROM prestamos p
                     INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
                     INNER JOIN recursos r ON p.id_recurso = r.id_recurso
                     ORDER BY p.id_prestamo DESC";
        $resultado_prestamos = $conexion->query($sql_pres);

    } else {
        $termino      = trim($_GET['buscar'] ?? '');
        $tipo_recurso = trim($_GET['tipo_recurso'] ?? '');

        $sql_rec = "SELECT id_recurso, codigo_inventario, titulo_nombre, tipo_recurso, autor, editorial, anio_publicacion, estado 
                    FROM recursos 
                    WHERE 1=1";
        $params = [];
        $tipos  = "";

        if ($rol_actual === 'Estudiante') {
            $sql_rec .= " AND tipo_recurso LIKE '%Libro%'";
        } else {
            if (!empty($tipo_recurso)) {
                $categoria_limpia = str_replace('_', ' ', $tipo_recurso);
                $sql_rec .= " AND (tipo_recurso LIKE ? OR tipo_recurso LIKE ?)";
                $params[] = "%" . $tipo_recurso . "%";
                $params[] = "%" . $categoria_limpia . "%";
                $tipos   .= "ss";
            }
        }

        if (!empty($termino)) {
            $sql_rec .= " AND (titulo_nombre LIKE ? OR autor LIKE ?)";
            $like_term = "%" . $termino . "%";
            $params[]  = $like_term;
            $params[]  = $like_term;
            $tipos    .= "ss";
        }

        $sql_rec .= " ORDER BY id_recurso DESC";

        $stmt_rec = $conexion->prepare($sql_rec);
        if (!empty($params)) {
            $stmt_rec->bind_param($tipos, ...$params);
        }
        $stmt_rec->execute();
        $resultado_recursos = $stmt_rec->get_result();
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Biblioteca</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --bs-body-bg: #f4f6f9;
            --bs-primary: #4f46e5;
            --bs-primary-rgb: 79, 70, 229;
        }

        [data-bs-theme="dark"] {
            --bs-body-bg: #0f172a;
            --bs-body-color: #f8fafc;
            --bs-tertiary-bg: #1e293b;
        }

        body { min-height: 100vh; display: flex; flex-direction: column; transition: background-color 0.3s ease, color 0.3s ease; }
        .login-wrapper { flex: 1; display: flex; align-items: center; justify-content: center; }
        
        .card-custom { 
            border-radius: 1rem; 
            border: none;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease; 
        }
        .card-custom:hover { transform: translateY(-3px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
        
        .navbar { backdrop-filter: blur(10px); }
        .table-hover tbody tr { transition: background-color 0.2s ease; }
        .btn { border-radius: 0.5rem; transition: all 0.2s ease-in-out; }
        .btn:hover { transform: translateY(-1px); }
        .form-control, .form-select { border-radius: 0.5rem; padding: 0.6rem 1rem; }

        /* CORRECCIÓN PARA LA FLECHA DE LOS SELECTS PEQUEÑOS */
        select.form-select-sm {
            padding-right: 1.8rem !important;
            background-position: right 0.5rem center !important;
        }

        /* CORRECCIÓN DE CONTRASTE EN MODO OSCURO */
        [data-bs-theme="dark"] .table-light {
            background-color: #1e293b !important;
            color: #f8fafc !important;
        }
        [data-bs-theme="dark"] .table {
            color: #f8fafc !important;
        }
        [data-bs-theme="dark"] .btn-light, 
        [data-bs-theme="dark"] .btn-secondary {
            background-color: #334155 !important;
            border-color: #475569 !important;
            color: #94a3b8 !important;
        }
    </style>
</head>
<body>

<?php if (!isset($_SESSION['usuario'])): ?>

    <div class="container login-wrapper py-5">
        <div class="row justify-content-center w-100">
            <div class="col-md-7 col-lg-5 col-xl-4">
                <div class="card card-custom shadow-lg animate__animated animate__zoomIn">
                    <div class="card-body p-4 p-md-5 position-relative">
                        
                        <!-- Botón de Modo Oscuro en Login -->
                        <div class="position-absolute top-0 end-0 p-3">
                            <button id="btnToggleTheme" class="btn btn-outline-secondary btn-sm rounded-circle" title="Cambiar tema">
                                <i class="bi bi-moon-fill" id="themeIcon"></i>
                            </button>
                        </div>

                        <div class="text-center mb-4">
                            <div class="bg-primary text-white rounded-circle d-inline-flex p-3 shadow-sm mb-2 animate__animated animate__pulse animate__infinite">
                                <i class="bi bi-book-half fs-2"></i>
                            </div>
                            <h3 class="fw-bold mt-2">Biblioteca</h3>
                            <p class="text-muted small">
                                <?php 
                                    $action = $_GET['action'] ?? '';
                                    if ($action === 'register') {
                                        echo 'Registro Público (Estudiantes)';
                                    } elseif ($action === 'forgot_pass') {
                                        echo 'Verificar Correo';
                                    } elseif ($action === 'cambiar_pass_directo') {
                                        echo 'Nueva Contraseña';
                                    } else {
                                        echo 'Ingresa tus credenciales';
                                    }
                                ?>
                            </p>
                        </div>

                        <?php if (isset($_GET['action']) && $_GET['action'] === 'register'): ?>
                            <!-- FORMULARIO DE REGISTRO DE ESTUDIANTES -->
                            <form method="POST" action="index.php?action=register">
                                <div class="mb-3">
                                    <label for="documento" class="form-label small fw-semibold text-secondary">Documento de Identidad</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-card-heading"></i></span>
                                        <input type="text" class="form-control" id="documento" name="documento" placeholder="1005123456" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="nombre_completo" class="form-label small fw-semibold text-secondary">Nombre Completo</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" placeholder="Ej: María López" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="correo" class="form-label small fw-semibold text-secondary">Correo Institucional</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                        <input type="email" class="form-control" id="correo" name="correo" placeholder="correo@institucion.edu" required>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="contrasena" class="form-label small fw-semibold text-secondary">Contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input type="password" class="form-control" id="contrasena" name="contrasena" placeholder="********" required>
                                    </div>
                                </div>

                                <button type="submit" name="registro" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm mb-3">
                                    Registrarse como Estudiante
                                </button>

                                <div class="text-center mt-3">
                                    <a href="index.php" class="text-decoration-none small text-primary fw-semibold">
                                        <i class="bi bi-arrow-left"></i> ¿Ya tienes una cuenta? Inicia sesión aquí
                                    </a>
                                </div>
                            </form>

                        <?php elseif (isset($_GET['action']) && $_GET['action'] === 'forgot_pass'): ?>
                            <!-- FORMULARIO 1: INGRESAR CORREO PARA VERIFICAR SI EXISTE -->
                            <form method="POST" action="index.php?action=forgot_pass">
                                <p class="small text-muted mb-3 text-center">
                                    Ingresa tu correo institucional. Si está registrado, podrás cambiar tu contraseña de inmediato.
                                </p>
                                <div class="mb-4">
                                    <label for="correo_recuperacion" class="form-label small fw-semibold text-secondary">Correo Institucional</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                        <input type="email" class="form-control" id="correo_recuperacion" name="correo_recuperacion" placeholder="correo@institucion.edu" required>
                                    </div>
                                </div>

                                <button type="submit" name="verificar_correo_recuperacion" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm mb-3">
                                    Verificar Correo
                                </button>

                                <div class="text-center mt-3">
                                    <a href="index.php" class="text-decoration-none small text-primary fw-semibold">
                                        <i class="bi bi-arrow-left"></i> Volver al Inicio de Sesión
                                    </a>
                                </div>
                            </form>

                        <?php elseif (isset($_GET['action']) && $_GET['action'] === 'cambiar_pass_directo'): ?>
                            <!-- FORMULARIO 2: CAMBIAR LA CONTRASEÑA DIRECTAMENTE -->
                            <form method="POST" action="index.php">
                                <p class="small text-success mb-3 text-center fw-semibold">
                                    <i class="bi bi-check-circle-fill"></i> ¡Correo verificado con éxito! Ingresa tu nueva contraseña.
                                </p>
                                
                                <div class="mb-3">
                                    <label for="nueva_contrasena" class="form-label small fw-semibold text-secondary">Nueva Contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                                        <input type="password" class="form-control" id="nueva_contrasena" name="nueva_contrasena" placeholder="********" required>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="confirmar_contrasena" class="form-label small fw-semibold text-secondary">Confirmar Contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input type="password" class="form-control" id="confirmar_contrasena" name="confirmar_contrasena" placeholder="********" required>
                                    </div>
                                </div>

                                <button type="submit" name="guardar_pass_directo" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm mb-3">
                                    Actualizar Contraseña
                                </button>

                                <div class="text-center mt-3">
                                    <a href="index.php" class="text-decoration-none small text-primary fw-semibold">
                                        <i class="bi bi-arrow-left"></i> Cancelar y Volver
                                    </a>
                                </div>
                            </form>

                        <?php else: ?>
                            <!-- FORMULARIO DE INICIO DE SESIÓN -->
                            <form method="POST" action="index.php">
                                <div class="mb-3">
                                    <label for="rol" class="form-label small fw-semibold text-secondary">Tipo de Usuario</label>
                                    <select class="form-select" id="rol" name="rol" required>
                                        <option value="" disabled selected>Seleccione su rol</option>
                                        <option value="Estudiante">Estudiante</option>
                                        <option value="Docente">Docente</option>
                                        <option value="Administrador">Administrador</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="correo" class="form-label small fw-semibold text-secondary">Correo Institucional</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                        <input type="email" class="form-control" id="correo" name="correo" placeholder="correo@institucion.edu" required>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label for="contrasena" class="form-label small fw-semibold text-secondary">Contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input type="password" class="form-control" id="contrasena" name="contrasena" placeholder="********" required>
                                    </div>
                                </div>

                                <div class="text-center mb-4">
                                    <a href="index.php?action=forgot_pass" class="text-decoration-none small text-muted">
                                        ¿Olvidaste tu contraseña?
                                    </a>
                                </div>

                                <button type="submit" name="login" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm mb-3">
                                    Ingresar al Sistema
                                </button>

                                <div class="text-center border-top pt-3 mt-2">
                                    <a href="index.php?action=register" class="text-decoration-none small text-primary fw-semibold">
                                        ¿No tienes una cuenta? Regístrate aquí
                                    </a>
                                </div>
                            </form>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>

    <?php
    $rol_actual = $_SESSION['usuario']['rol'];
    $nombre     = $_SESSION['usuario']['nombre_completo'];
    $paz_salvo  = $_SESSION['usuario']['paz_y_salvo'];

    $color_rol = match($rol_actual) {
        'Administrador' => 'dark',
        'Docente'       => 'success',
        'Estudiante'    => 'primary',
        default         => 'secondary'
    };
    ?>

    <nav class="navbar navbar-expand-lg navbar-dark bg-<?= $color_rol ?> shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
                <i class="bi bi-book-half"></i> Biblioteca Digital
            </a>
            
            <div class="d-flex align-items-center gap-3">
                <span class="navbar-text text-white small d-none d-md-inline">
                    <i class="bi bi-person-fill"></i> <?= htmlspecialchars($nombre) ?> 
                    <span class="badge bg-light text-dark ms-1"><?= $rol_actual ?></span>
                </span>
                
                <!-- Botón de Modo Oscuro en Navbar -->
                <button id="btnToggleTheme" class="btn btn-outline-light btn-sm rounded-circle" title="Cambiar tema">
                    <i class="bi bi-moon-fill" id="themeIcon"></i>
                </button>

                <a href="index.php?action=logout" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Salir
                </a>
            </div>
        </div>
    </nav>

    <main class="container my-4 flex-grow-1 animate__animated animate__fadeIn">

        <div class="row g-3 mb-4">
            <div class="col-lg-8">
                <div class="card card-custom h-100 p-3">
                    <div class="card-body">
                        <h4 class="fw-bold mb-1">¡Bienvenido(a), <?= htmlspecialchars($nombre) ?>!</h4>
                        <p class="text-muted small mb-3">Panel de control adaptado al perfil: <strong><?= $rol_actual ?></strong>.</p>
                        
                        <?php if ($rol_actual === 'Administrador'): ?>
                            <div class="alert alert-dark py-2 px-3 small mb-0 border-0 shadow-sm">
                                <i class="bi bi-shield-lock-fill me-1"></i> Permisos de Administración: Registro de docentes, creación de recursos y control general de préstamos.
                            </div>
                        <?php elseif ($rol_actual === 'Docente'): ?>
                            <div class="alert alert-success py-2 px-3 small mb-0 border-0 shadow-sm">
                                <i class="bi bi-award-fill me-1"></i> Perfil docente habilitado para reserva de equipos audiovisuales, libros y accesorios por horas o días.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info py-2 px-3 small mb-0 border-0 shadow-sm">
                                <i class="bi bi-info-circle-fill me-1"></i> Estudiante: Acceso exclusivo a libros con flexibilidad de solicitud por horas o días.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-custom h-100 p-3">
                    <div class="card-body d-flex flex-column justify-content-center gap-2">
                        <span class="text-uppercase text-muted fw-bold small">Herramientas</span>
                        <?php if ($rol_actual === 'Administrador'): ?>
                            <a href="index.php?action=nuevo_recurso" class="btn btn-outline-dark btn-sm text-start">
                                <i class="bi bi-plus-circle me-1"></i> Nuevo Recurso
                            </a>
                            <a href="index.php?action=gestionar_usuarios" class="btn btn-outline-dark btn-sm text-start">
                                <i class="bi bi-people-fill me-1"></i> Registrar / Gestionar Docentes
                            </a>
                            <a href="index.php?action=gestionar_prestamos" class="btn btn-outline-dark btn-sm text-start">
                                <i class="bi bi-journal-check me-1"></i> Gestionar Préstamos
                            </a>
                        <?php elseif ($rol_actual === 'Docente'): ?>
                            <a href="index.php?tipo_recurso=Equipo_Audiovisual" class="btn btn-outline-success btn-sm text-start">
                                <i class="bi bi-projector me-1"></i> Solicitar Equipo Audiovisual
                            </a>
                            <a href="index.php?action=historial_aulas" class="btn btn-outline-success btn-sm text-start">
                                <i class="bi bi-clock-history me-1"></i> Mis Préstamos / Historial
                            </a>
                            <a href="index.php?action=proximos_vencimientos" class="btn btn-outline-success btn-sm text-start">
                                <i class="bi bi-calendar-event me-1"></i> Próximos Vencimientos
                            </a>
                        <?php else: ?>
                            <a href="index.php?action=mis_prestamos" class="btn btn-outline-primary btn-sm text-start">
                                <i class="bi bi-bookmark-check me-1"></i> Mis Préstamos Activos
                            </a>
                            <a href="index.php?action=proximos_vencimientos" class="btn btn-outline-primary btn-sm text-start">
                                <i class="bi bi-calendar-event me-1"></i> Próximos Vencimientos
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($rol_actual === 'Administrador' && isset($_GET['action']) && $_GET['action'] === 'gestionar_usuarios'): ?>
            <div class="card card-custom mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-person-plus-fill me-1"></i> Registrar Nuevo Docente / Usuario</h5>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver al Catálogo</a>
                    </div>
                    <form method="POST" action="index.php?action=gestionar_usuarios" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Documento *</label>
                            <input type="text" class="form-control" name="documento" placeholder="1005..." required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">Nombre Completo *</label>
                            <input type="text" class="form-control" name="nombre_completo" placeholder="Prof. Carlos Mendoza" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small text-muted">Correo Institucional *</label>
                            <input type="email" class="form-control" name="correo" placeholder="carlos@institucion.edu" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">Rol Asignado *</label>
                            <select class="form-select" name="rol_nuevo" required>
                                <option value="Docente" selected>Docente</option>
                                <option value="Administrador">Administrador</option>
                                <option value="Estudiante">Estudiante</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">Contraseña *</label>
                            <input type="password" class="form-control" name="contrasena" placeholder="********" required>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" name="crear_usuario_admin" class="btn btn-dark w-100">
                                <i class="bi bi-check-lg me-1"></i> Crear Cuenta Docente
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card card-custom">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Documento</th>
                                    <th>Nombre Completo</th>
                                    <th>Correo</th>
                                    <th>Rol</th>
                                    <th>Paz y Salvo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($resultado_usuarios && $resultado_usuarios->num_rows > 0): ?>
                                    <?php while ($u = $resultado_usuarios->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?= $u['id_usuario'] ?></td>
                                            <td><?= htmlspecialchars($u['documento']) ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($u['nombre_completo']) ?></td>
                                            <td class="text-muted"><?= htmlspecialchars($u['correo']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= match($u['rol']){ 'Administrador'=>'dark', 'Docente'=>'success', default=>'primary' } ?>">
                                                    <?= htmlspecialchars($u['rol']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= ($u['paz_y_salvo']) ? 'success' : 'danger' ?>">
                                                    <?= ($u['paz_y_salvo']) ? 'Paz y Salvo' : 'Con Sanción' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($rol_actual === 'Administrador' && isset($_GET['action']) && $_GET['action'] === 'nuevo_recurso'): ?>
            <div class="card card-custom mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-plus-circle me-1"></i> Registrar Nuevo Recurso</h5>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver al Catálogo</a>
                    </div>
                    <form method="POST" action="index.php" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small text-muted">Código Inventario *</label>
                            <input type="text" class="form-control" name="codigo_inventario" placeholder="Ej: LIB-001" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small text-muted">Título / Nombre del Recurso *</label>
                            <input type="text" class="form-control" name="titulo_nombre" placeholder="Ej: Cien Años de Soledad" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Categoría / Tipo *</label>
                            <select class="form-select" name="tipo_recurso" required>
                                <option value="Libro">Libro</option>
                                <option value="Equipo_Audiovisual">Equipo Audiovisual</option>
                                <option value="Accesorio">Accesorio</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">Autor / Fabricante</label>
                            <input type="text" class="form-control" name="autor" placeholder="Ej: Gabriel García Márquez">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">Editorial / Marca</label>
                            <input type="text" class="form-control" name="editorial" placeholder="Ej: Editorial Sudamericana">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">Año Publicación</label>
                            <input type="number" class="form-control" name="anio_publicacion" placeholder="2020">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">ISBN</label>
                            <input type="text" class="form-control" name="isbn" placeholder="978-0-307...">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small text-muted">Características / Descripción</label>
                            <input type="text" class="form-control" name="caracteristicas" placeholder="Descripción breve...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">Estado Inicial</label>
                            <select class="form-select" name="estado">
                                <option value="Disponible">Disponible</option>
                                <option value="Mantenimiento">Mantenimiento</option>
                                <option value="Dado_Baja">Dado de Baja</option>
                            </select>
                        </div>
                        <div class="col-12 mt-4 text-end">
                            <button type="submit" name="guardar_nuevo_recurso" class="btn btn-dark"><i class="bi bi-save me-1"></i> Guardar Recurso</button>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($rol_actual === 'Administrador' && isset($_GET['action']) && $_GET['action'] === 'editar_recurso' && $recurso_editar): ?>
            <div class="card card-custom mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-pencil me-1"></i> Editar Recurso #<?= $recurso_editar['id_recurso'] ?></h5>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver al Catálogo</a>
                    </div>
                    <form method="POST" action="index.php" class="row g-3">
                        <input type="hidden" name="id_recurso" value="<?= $recurso_editar['id_recurso'] ?>">
                        <div class="col-md-4">
                            <label class="form-label small text-muted">Código Inventario *</label>
                            <input type="text" class="form-control" name="codigo_inventario" value="<?= htmlspecialchars($recurso_editar['codigo_inventario']) ?>" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small text-muted">Título / Nombre *</label>
                            <input type="text" class="form-control" name="titulo_nombre" value="<?= htmlspecialchars($recurso_editar['titulo_nombre']) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Categoría / Tipo *</label>
                            <select class="form-select" name="tipo_recurso" required>
                                <option value="Libro" <?= (strpos($recurso_editar['tipo_recurso'], 'Libro') !== false) ? 'selected' : '' ?>>Libro</option>
                                <option value="Equipo_Audiovisual" <?= (strpos($recurso_editar['tipo_recurso'], 'Equipo') !== false) ? 'selected' : '' ?>>Equipo Audiovisual</option>
                                <option value="Accesorio" <?= (strpos($recurso_editar['tipo_recurso'], 'Accesorio') !== false) ? 'selected' : '' ?>>Accesorio</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">Autor / Fabricante</label>
                            <input type="text" class="form-control" name="autor" value="<?= htmlspecialchars($recurso_editar['autor']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">Editorial / Marca</label>
                            <input type="text" class="form-control" name="editorial" value="<?= htmlspecialchars($recurso_editar['editorial']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">Año Publicación</label>
                            <input type="number" class="form-control" name="anio_publicacion" value="<?= htmlspecialchars($recurso_editar['anio_publicacion']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">ISBN</label>
                            <input type="text" class="form-control" name="isbn" value="<?= htmlspecialchars($recurso_editar['isbn']) ?>">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small text-muted">Características</label>
                            <input type="text" class="form-control" name="caracteristicas" value="<?= htmlspecialchars($recurso_editar['caracteristicas']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">Estado del Recurso</label>
                            <select class="form-select" name="estado">
                                <option value="Disponible" <?= ($recurso_editar['estado'] === 'Disponible') ? 'selected' : '' ?>>Disponible</option>
                                <option value="Prestado" <?= ($recurso_editar['estado'] === 'Prestado') ? 'selected' : '' ?>>Prestado</option>
                                <option value="Mantenimiento" <?= ($recurso_editar['estado'] === 'Mantenimiento') ? 'selected' : '' ?>>Mantenimiento</option>
                                <option value="Dado_Baja" <?= ($recurso_editar['estado'] === 'Dado_Baja') ? 'selected' : '' ?>>Dado de Baja</option>
                            </select>
                        </div>
                        <div class="col-12 mt-4 text-end">
                            <button type="submit" name="actualizar_recurso" class="btn btn-dark"><i class="bi bi-check-circle me-1"></i> Actualizar Recurso</button>
                        </div>
                    </form>
                </div>
            </div>

        <?php elseif ($rol_actual === 'Administrador' && isset($_GET['action']) && $_GET['action'] === 'gestionar_prestamos'): ?>
            <div class="card card-custom mb-4">
                <div class="card-body d-flex justify-content-between align-items-center p-4">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-journal-check me-2"></i> Gestión General de Préstamos (Administrador)
                    </h5>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Volver al Catálogo
                    </a>
                </div>
            </div>

            <div class="card card-custom">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID Préstamo</th>
                                    <th>Usuario</th>
                                    <th>Correo</th>
                                    <th>Recurso</th>
                                    <th>Tipo</th>
                                    <th>Fecha Préstamo</th>
                                    <th>Fecha y Hora Límite</th>
                                    <th>Estado Préstamo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($resultado_prestamos && $resultado_prestamos->num_rows > 0): ?>
                                    <?php while ($p = $resultado_prestamos->fetch_assoc()): ?>
                                        <tr>
                                            <td class="fw-semibold">#<?= $p['id_prestamo'] ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($p['nombre_completo']) ?></td>
                                            <td class="text-muted small"><?= htmlspecialchars($p['correo']) ?></td>
                                            <td><?= htmlspecialchars($p['titulo_nombre']) ?> (<span class="text-secondary"><?= htmlspecialchars($p['codigo_inventario']) ?></span>)</td>
                                            <td><span class="badge bg-secondary"><?= str_replace('_', ' ', htmlspecialchars($p['tipo_recurso'])) ?></span></td>
                                            <td><?= htmlspecialchars($p['fecha_prestamo_f']) ?></td>
                                            <td><span class="fw-bold"><?= htmlspecialchars($p['fecha_limite_f']) ?></span></td>
                                            <td>
                                                <span class="badge <?= ($p['estado_prestamo'] === 'Activo') ? 'bg-success' : 'bg-secondary' ?>">
                                                    <?= htmlspecialchars($p['estado_prestamo']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            No hay registros de préstamos en el sistema.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif (in_array($rol_actual, ['Estudiante', 'Docente']) && isset($_GET['action']) && in_array($_GET['action'], ['mis_prestamos', 'historial_aulas'])): ?>
            <div class="card card-custom mb-4">
                <div class="card-body d-flex justify-content-between align-items-center p-4">
                    <h5 class="fw-bold text-<?= $color_rol ?> mb-0">
                        <i class="bi bi-bookmark-check me-2"></i> <?= ($rol_actual === 'Docente') ? 'Mis Préstamos y Reservas' : 'Mis Préstamos Activos' ?>
                    </h5>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Volver al Catálogo
                    </a>
                </div>
            </div>

            <div class="card card-custom">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Código</th>
                                    <th>Recurso Prestado</th>
                                    <th>Tipo</th>
                                    <th>Fecha de Préstamo</th>
                                    <th>Fecha y Hora Límite</th>
                                    <th class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($resultado_prestamos && $resultado_prestamos->num_rows > 0): ?>
                                    <?php while ($prestamo = $resultado_prestamos->fetch_assoc()): ?>
                                        <tr>
                                            <td class="fw-semibold text-secondary"><?= htmlspecialchars($prestamo['codigo_inventario']) ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($prestamo['titulo_nombre']) ?></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= str_replace('_', ' ', htmlspecialchars($prestamo['tipo_recurso'])) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($prestamo['fecha_prestamo_f']) ?></td>
                                            <td><span class="text-danger fw-semibold"><?= htmlspecialchars($prestamo['fecha_limite_f']) ?></span></td>
                                            <td class="text-center">
                                                <form method="POST" action="index.php?action=<?= $_GET['action'] ?>" class="d-inline form-devolucion">
                                                    <input type="hidden" name="id_prestamo" value="<?= $prestamo['id_prestamo'] ?>">
                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-devolver">
                                                        <i class="bi bi-x-circle"></i> Cancelar / Devolver
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            No tienes préstamos activos en este momento.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif (in_array($rol_actual, ['Estudiante', 'Docente']) && isset($_GET['action']) && $_GET['action'] === 'proximos_vencimientos'): ?>
            <div class="card card-custom mb-4">
                <div class="card-body d-flex justify-content-between align-items-center p-4">
                    <h5 class="fw-bold text-<?= $color_rol ?> mb-0">
                        <i class="bi bi-calendar-event me-2"></i> Próximos Vencimientos (Cuenta Regresiva)
                    </h5>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Volver al Catálogo
                    </a>
                </div>
            </div>

            <div class="card card-custom">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Código</th>
                                    <th>Recurso Prestado</th>
                                    <th>Categoría</th>
                                    <th>Fecha de Préstamo</th>
                                    <th>Fecha y Hora Límite</th>
                                    <th>Tiempo Restante (En Vivo)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($resultado_prestamos && $resultado_prestamos->num_rows > 0): ?>
                                    <?php while ($prestamo = $resultado_prestamos->fetch_assoc()): ?>
                                        <?php 
                                            $timestamp_limite = strtotime($prestamo['fecha_limite_raw']) * 1000;
                                        ?>
                                        <tr>
                                            <td class="fw-semibold text-secondary"><?= htmlspecialchars($prestamo['codigo_inventario']) ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($prestamo['titulo_nombre']) ?></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= str_replace('_', ' ', htmlspecialchars($prestamo['tipo_recurso'])) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($prestamo['fecha_prestamo_f']) ?></td>
                                            <td><span class="fw-bold"><?= htmlspecialchars($prestamo['fecha_limite_f']) ?></span></td>
                                            <td>
                                                <span class="badge bg-warning text-dark countdown-timer fs-6" data-time="<?= $timestamp_limite ?>">
                                                    Calculando...
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            No tienes préstamos activos registrados.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <script>
                function actualizarCuentasRegresivas() {
                    const timers = document.querySelectorAll('.countdown-timer');
                    timers.forEach(timer => {
                        const limite = parseInt(timer.getAttribute('data-time'));
                        const ahora = new Date().getTime();
                        const diferencia = limite - ahora;

                        if (diferencia <= 0) {
                            timer.className = "badge bg-danger fs-6 animate__animated animate__flash";
                            timer.innerText = "¡Vencido!";
                        } else {
                            const horas = Math.floor((diferencia % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                            const minutos = Math.floor((diferencia % (1000 * 60 * 60)) / (1000 * 60));
                            const segundos = Math.floor((diferencia % (1000 * 60)) / 1000);

                            const hStr = String(horas).padStart(1, '0');
                            const mStr = String(minutos).padStart(2, '0');
                            const sStr = String(segundos).padStart(2, '0');

                            timer.innerText = `${hStr}:${mStr}:${sStr} restantes`;
                        }
                    });
                }
                setInterval(actualizarCuentasRegresivas, 1000);
                actualizarCuentasRegresivas();
            </script>

        <?php else: ?>
            <div class="card card-custom mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-secondary mb-3">
                        <i class="bi bi-search me-1"></i> Buscar en el Catálogo
                    </h5>
                    <form method="GET" action="index.php" class="row g-3">
                        <div class="col-md-<?php echo ($rol_actual === 'Estudiante') ? '9' : '5'; ?>">
                            <label for="buscar" class="form-label small text-muted">Título o Autor</label>
                            <input type="text" class="form-control" id="buscar" name="buscar" 
                                   placeholder="Ej: Programación, Epson..." 
                                   value="<?= htmlspecialchars($termino ?? '') ?>">
                        </div>

                        <?php if ($rol_actual !== 'Estudiante'): ?>
                        <div class="col-md-4">
                            <label for="tipo_recurso" class="form-label small text-muted">Categoría (Tipo)</label>
                            <select class="form-select" id="tipo_recurso" name="tipo_recurso">
                                <option value="">Todas las categorías</option>
                                <option value="Libro" <?= (isset($tipo_recurso) && strpos($tipo_recurso, 'Libro') !== false) ? 'selected' : '' ?>>Libro</option>
                                <option value="Equipo_Audiovisual" <?= (isset($tipo_recurso) && strpos($tipo_recurso, 'Equipo') !== false) ? 'selected' : '' ?>>Equipo Audiovisual</option>
                                <option value="Accesorio" <?= (isset($tipo_recurso) && strpos($tipo_recurso, 'Accesorio') !== false) ? 'selected' : '' ?>>Accesorio</option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-<?= $color_rol ?> w-100">Filtrar</button>
                            <a href="index.php" class="btn btn-secondary">Limpiar</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card card-custom">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Código</th>
                                    <th>Recurso</th>
                                    <th>Categoría</th>
                                    <th>Autor / Fabricante</th>
                                    <th>Estado</th>
                                    <th class="text-center">Solicitar / Unidad de Tiempo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($resultado_recursos && $resultado_recursos->num_rows > 0): ?>
                                    <?php while ($recurso = $resultado_recursos->fetch_assoc()): ?>
                                        <tr>
                                            <td class="fw-semibold text-secondary"><?= htmlspecialchars($recurso['codigo_inventario']) ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($recurso['titulo_nombre']) ?></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= str_replace('_', ' ', htmlspecialchars($recurso['tipo_recurso'])) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($recurso['autor'] ?: 'N/A') ?></td>
                                            <td>
                                                <?php
                                                $estado = $recurso['estado'];
                                                $badgeClase = match($estado) {
                                                    'Disponible'   => 'bg-success',
                                                    'Prestado'     => 'bg-warning text-dark',
                                                    'Mantenimiento'=> 'bg-info text-dark',
                                                    'Dado_Baja'    => 'bg-danger',
                                                    default        => 'bg-secondary'
                                                };
                                                ?>
                                                <span class="badge <?= $badgeClase ?>">
                                                    <?= str_replace('_', ' ', htmlspecialchars($estado)) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($rol_actual === 'Administrador'): ?>
                                                    <a href="index.php?action=editar_recurso&id=<?= $recurso['id_recurso'] ?>" class="btn btn-sm btn-outline-dark">
                                                        <i class="bi bi-pencil"></i> Editar
                                                    </a>
                                                <?php elseif (in_array($rol_actual, ['Estudiante', 'Docente'])): ?>
                                                    <?php if ($recurso['estado'] === 'Disponible'): ?>
                                                        <form method="POST" action="index.php" class="d-flex justify-content-center align-items-center gap-1">
                                                            <input type="hidden" name="id_recurso" value="<?= $recurso['id_recurso'] ?>">
                                                            
                                                            <input type="number" name="cantidad_tiempo" min="1" max="60" value="2" class="form-control form-control-sm text-center" style="width: 55px;" title="Cantidad" required>
                                                            
                                                            <select name="unidad_tiempo" class="form-select form-select-sm" style="width: 105px;">
                                                                <option value="Horas" selected>Hora(s)</option>
                                                                <option value="Dias">Día(s)</option>
                                                            </select>

                                                            <button type="submit" name="solicitar_recurso" class="btn btn-sm btn-outline-<?= $color_rol ?>" title="Solicitar préstamo">
                                                                <i class="bi bi-cart-plus"></i> Pedir
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-light text-muted" disabled>No Disponible</button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            No se encontraron recursos disponibles bajo el criterio seleccionado.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </main>

<?php endif; ?>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // --- GESTIÓN DE MODO OSCURO / CLARO ---
        const btnToggleTheme = document.getElementById('btnToggleTheme');
        const themeIcon = document.getElementById('themeIcon');
        const htmlElement = document.documentElement;

        // Cargar tema guardado previamente en el navegador
        const savedTheme = localStorage.getItem('theme') || 'light';
        htmlElement.setAttribute('data-bs-theme', savedTheme);
        updateThemeIcon(savedTheme);

        if (btnToggleTheme) {
            btnToggleTheme.addEventListener('click', () => {
                const currentTheme = htmlElement.getAttribute('data-bs-theme');
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';
                
                htmlElement.setAttribute('data-bs-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                updateThemeIcon(newTheme);
            });
        }

        function updateThemeIcon(theme) {
            if (theme === 'dark') {
                themeIcon.className = 'bi bi-sun-fill';
            } else {
                themeIcon.className = 'bi bi-moon-fill';
            }
        }

        // Disparar alertas animadas de SweetAlert2 según mensajes de PHP
        <?php if (!empty($mensaje_exito)): ?>
            Swal.fire({
                icon: 'success',
                title: '¡Operación Exitosa!',
                html: '<?= addslashes($mensaje_exito) ?>',
                timer: 3500,
                timerProgressBar: true,
                showConfirmButton: false,
                showClass: { popup: 'animate__animated animate__fadeInDown' },
                hideClass: { popup: 'animate__animated animate__fadeOutUp' }
            });
        <?php endif; ?>

        <?php if (!empty($mensaje_error)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Atención',
                text: '<?= addslashes($mensaje_error) ?>',
                confirmButtonColor: '#4f46e5',
                showClass: { popup: 'animate__animated animate__shakeX' }
            });
        <?php endif; ?>

        // Confirmación con SweetAlert2 para devoluciones
        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('btn-devolver') || e.target.closest('.btn-devolver')) {
                const button = e.target.closest('.btn-devolver');
                const form = button.closest('form');
                
                Swal.fire({
                    title: '¿Confirmar devolución?',
                    text: "El recurso volverá a estar disponible inmediatamente en el catálogo.",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, devolver',
                    cancelButtonText: 'Cancelar',
                    showClass: { popup: 'animate__animated animate__fadeInDown' },
                    hideClass: { popup: 'animate__animated animate__fadeOutUp' }
                }).then((result) => {
                    if (result.isConfirmed) {
                        const inputHidden = document.createElement('input');
                        inputHidden.type = 'hidden';
                        inputHidden.name = 'cancelar_prestamo';
                        inputHidden.value = '1';
                        form.appendChild(inputHidden);
                        form.submit();
                    }
                });
            }
        });
    </script>
</body>
</html>
<?php
if (isset($stmt_rec) && $stmt_rec) {
    $stmt_rec->close();
}
if (isset($resultado_prestamos) && is_object($resultado_prestamos) && method_exists($resultado_prestamos, 'close')) {
    $resultado_prestamos->close();
}
$conexion->close();
?>