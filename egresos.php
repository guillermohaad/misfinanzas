<?php
require_once 'config/database.php';
iniciarSesion();
verificarAutenticacion();

$db = Database::getInstance()->getConnection();
$usuario_id = $_SESSION['usuario_id'];

$mensaje = '';
$tipo_mensaje = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'duplicar_todos') {
        // Obtener todos los egresos actuales del usuario
        $stmt = $db->prepare("SELECT * FROM egresos WHERE id_usuario = ?");
        $stmt->execute([$usuario_id]);
        $egresos_duplicar = $stmt->fetchAll();
        
        $contador = 0;
        foreach ($egresos_duplicar as $egreso) {
            $stmt = $db->prepare("INSERT INTO egresos (id_usuario, fecha, mes, descripcion, empresa, categoria, monto) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([
                $usuario_id,
                $egreso['fecha'],
                $egreso['mes'],
                $egreso['descripcion'],
                $egreso['empresa'],
                $egreso['categoria'],
                $egreso['monto']
            ])) {
                $contador++;
            }
        }
        
        if ($contador > 0) {
            $mensaje = "Se duplicaron exitosamente $contador egresos.";
            $tipo_mensaje = "success";
            registrarLog('egresos_duplicados', "Duplicados $contador egresos", $usuario_id);
        }
    } else    
    
    if ($accion === 'agregar' || $accion === 'editar') {
        $fecha = Security::sanitize($_POST['fecha']);
        $descripcion = Security::sanitize($_POST['descripcion']);
        $empresa = Security::sanitize($_POST['empresa']);
        $categoria = Security::sanitize($_POST['categoria']);
        $monto = floatval($_POST['monto']);
        
        if (empty($fecha) || empty($descripcion) || empty($categoria) || $monto <= 0) {
            $mensaje = "Por favor complete todos los campos obligatorios correctamente.";
            $tipo_mensaje = "danger";
        } else {
            $mes = date('F Y', strtotime($fecha));
            
            if ($accion === 'agregar') {
                $stmt = $db->prepare("INSERT INTO egresos (id_usuario, fecha, mes, descripcion, empresa, categoria, monto) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$usuario_id, $fecha, $mes, $descripcion, $empresa, $categoria, $monto])) {
                    $mensaje = "Egreso registrado exitosamente.";
                    $tipo_mensaje = "success";
                    registrarLog('egreso_agregado', "Nuevo egreso: $descripcion - $$monto", $usuario_id);
                }
            } elseif ($accion === 'editar') {
                $id_egreso = intval($_POST['id_egreso']);
                $stmt = $db->prepare("UPDATE egresos SET fecha = ?, mes = ?, descripcion = ?, empresa = ?, categoria = ?, monto = ? WHERE id_egreso = ? AND id_usuario = ?");
                if ($stmt->execute([$fecha, $mes, $descripcion, $empresa, $categoria, $monto, $id_egreso, $usuario_id])) {
                    $mensaje = "Egreso actualizado exitosamente.";
                    $tipo_mensaje = "success";
                    registrarLog('egreso_editado', "Egreso editado: $descripcion", $usuario_id);
                }
            }
        }
    } elseif ($accion === 'eliminar') {
        $id_egreso = intval($_POST['id_egreso']);
        $stmt = $db->prepare("DELETE FROM egresos WHERE id_egreso = ? AND id_usuario = ?");
        if ($stmt->execute([$id_egreso, $usuario_id])) {
            $mensaje = "Egreso eliminado exitosamente.";
            $tipo_mensaje = "success";
            registrarLog('egreso_eliminado', "Egreso eliminado ID: $id_egreso", $usuario_id);
        }
    }
}

// Obtener todos los egresos
$stmt = $db->prepare("SELECT * FROM egresos WHERE id_usuario = ? ORDER BY fecha DESC");
$stmt->execute([$usuario_id]);
$egresos = $stmt->fetchAll();

// Calcular totales por categoría
$stmt = $db->prepare("SELECT categoria, SUM(monto) as total FROM egresos WHERE id_usuario = ? GROUP BY categoria");
$stmt->execute([$usuario_id]);
$totales_categoria = [];
while ($row = $stmt->fetch()) {
    $totales_categoria[$row['categoria']] = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Egresos - Sistema de Finanzas Personales</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-wallet2"></i> Finanzas Personales
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ingresos.php">
                            <i class="bi bi-cash-coin"></i> Ingresos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="egresos.php">
                            <i class="bi bi-cart"></i> Egresos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ahorros.php">
                            <i class="bi bi-piggy-bank"></i> Ahorros
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="presupuesto.php">
                            <i class="bi bi-calculator"></i> Presupuesto
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reportes.php">
                            <i class="bi bi-graph-up"></i> Reportes
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center text-white">
                    <i class="bi bi-person-circle me-2"></i>
                    <span class="me-3"><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></span>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Salir
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="bi bi-cart"></i> Gestión de Egresos</h2>
                <p class="text-muted">Controla tus gastos y mantén tu presupuesto bajo control</p>
            </div>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Tarjetas de Resumen -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-danger text-white">
                    <div class="card-body">
                        <h6 class="mb-1">Gastos Fijos</h6>
                        <h3>$<?php echo number_format($totales_categoria['fijo'] ?? 0, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <h6 class="mb-1">Gastos Ocasionales</h6>
                        <h3>$<?php echo number_format($totales_categoria['ocasional'] ?? 0, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-secondary text-white">
                    <div class="card-body">
                        <h6 class="mb-1">Gastos Hormiga</h6>
                        <h3>$<?php echo number_format($totales_categoria['hormiga'] ?? 0, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-dark text-white">
                    <div class="card-body">
                        <h6 class="mb-1">Total Egresos</h6>
                        <h3>$<?php echo number_format(array_sum($totales_categoria), 2); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botón Agregar -->
        <div class="mb-3">
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalEgreso" onclick="limpiarFormulario()">
                <i class="bi bi-plus-circle"></i> Agregar Egreso
            </button>
        </div>

        <!-- Tabla de Egresos -->
        <div class="card stat-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaEgresos" class="table table-hover">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Mes</th>
                                <th>Descripción</th>
                                <th>Empresa</th>
                                <th>Categoría</th>
                                <th>Monto</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($egresos as $egreso): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($egreso['fecha'])); ?></td>
                                    <td><?php echo htmlspecialchars($egreso['mes']); ?></td>
                                    <td><?php echo htmlspecialchars($egreso['descripcion']); ?></td>
                                    <td><?php echo htmlspecialchars($egreso['empresa']); ?></td>
                                    <td>
                                        <?php
                                        $badge_color = 'secondary';
                                        if ($egreso['categoria'] === 'fijo') $badge_color = 'danger';
                                        elseif ($egreso['categoria'] === 'ocasional') $badge_color = 'warning';
                                        ?>
                                        <span class="badge bg-<?php echo $badge_color; ?>">
                                            <?php echo ucfirst($egreso['categoria']); ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-danger">$<?php echo number_format($egreso['monto'], 2); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editarEgreso(<?php echo htmlspecialchars(json_encode($egreso)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="eliminarEgreso(<?php echo $egreso['id_egreso']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <button class="btn btn-primary" onclick="duplicarTodosEgresos()">
                                            <i class="bi bi-files"></i> Duplicar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Agregar/Editar Egreso -->
    <div class="modal fade" id="modalEgreso" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tituloModal">Agregar Egreso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    
                </div>
                <form method="POST" id="formEgreso">
                    <div class="modal-body">
                        <input type="hidden" name="accion" id="accion" value="agregar">
                        <input type="hidden" name="id_egreso" id="id_egreso">
                        
                        <div class="mb-3">
                            <label for="fecha" class="form-label">Fecha *</label>
                            <input type="date" class="form-control" name="fecha" id="fecha" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción *</label>
                            <input type="text" class="form-control" name="descripcion" id="descripcion" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="empresa" class="form-label">Empresa/Establecimiento</label>
                            <input type="text" class="form-control" name="empresa" id="empresa">
                        </div>
                        
                        <div class="mb-3">
                            <label for="categoria" class="form-label">Categoría *</label>
                            <select class="form-select" name="categoria" id="categoria" required>
                                <option value="">Seleccione...</option>
                                <option value="fijo">Fijo (Servicios, renta, Deuda Bancaria, Tarjeta de credito, etc.)</option>
                                <option value="ocasional">Ocasional (Compras grandes)</option>
                                <option value="hormiga">Hormiga (Gastos pequeños)</option>
                            </select>
                            <small class="text-muted">Los gastos hormiga son pequeños gastos diarios que suelen pasar desapercibidos</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="monto" class="form-label">Monto *</label>
                            <input type="number" class="form-control" name="monto" id="monto" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#tablaEgresos').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                },
                order: [[0, 'desc']]
            });
            
            document.getElementById('fecha').valueAsDate = new Date();
        });
        
        function limpiarFormulario() {
            document.getElementById('formEgreso').reset();
            document.getElementById('accion').value = 'agregar';
            document.getElementById('tituloModal').textContent = 'Agregar Egreso';
            document.getElementById('fecha').valueAsDate = new Date();
        }
        
        function editarEgreso(egreso) {
            document.getElementById('accion').value = 'editar';
            document.getElementById('id_egreso').value = egreso.id_egreso;
            document.getElementById('fecha').value = egreso.fecha;
            document.getElementById('descripcion').value = egreso.descripcion;
            document.getElementById('empresa').value = egreso.empresa;
            document.getElementById('categoria').value = egreso.categoria;
            document.getElementById('monto').value = egreso.monto;
            document.getElementById('tituloModal').textContent = 'Editar Egreso';
            
            var modal = new bootstrap.Modal(document.getElementById('modalEgreso'));
            modal.show();
        }
        
        function eliminarEgreso(id) {
            if (confirm('¿Está seguro de eliminar este egreso?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="accion" value="eliminar">' +
                                '<input type="hidden" name="id_egreso" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function duplicarTodosEgresos() {
            if (confirm('¿Está seguro de duplicar TODOS los egresos? Esto creará copias de cada egreso registrado.')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="accion" value="duplicar_todos">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>