<?php
require_once 'config/database.php';
iniciarSesion();
verificarAutenticacion();

$db = Database::getInstance()->getConnection();
$usuario_id = $_SESSION['usuario_id'];

$mensaje = '';
$tipo_mensaje = '';

// Procesar acciones (agregar, editar, eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'duplicar_todos') {
        // Obtener todos los ingresos actuales del usuario
        $stmt = $db->prepare("SELECT * FROM ingresos WHERE id_usuario = ?");
        $stmt->execute([$usuario_id]);
        $ingresos_duplicar = $stmt->fetchAll();
        
        $contador = 0;
        foreach ($ingresos_duplicar as $ingreso) {
            $stmt = $db->prepare("INSERT INTO ingresos (id_usuario, fecha, mes, descripcion, empresa, frecuencia, monto) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([
                $usuario_id,
                $ingreso['fecha'],
                $ingreso['mes'],
                $ingreso['descripcion'],
                $ingreso['empresa'],
                $ingreso['frecuencia'],
                $ingreso['monto']
            ])) {
                $contador++;
            }
        }
        
        if ($contador > 0) {
            $mensaje = "Se duplicaron exitosamente $contador ingresos.";
            $tipo_mensaje = "success";
            registrarLog('ingresos_duplicados', "Duplicados $contador ingresos", $usuario_id);
        }
    } else
    
    if ($accion === 'agregar' || $accion === 'editar') {
        $fecha = Security::sanitize($_POST['fecha']);
        $descripcion = Security::sanitize($_POST['descripcion']);
        $empresa = Security::sanitize($_POST['empresa']);
        $frecuencia = Security::sanitize($_POST['frecuencia']);
        $monto = floatval($_POST['monto']);
        
        if (empty($fecha) || empty($descripcion) || empty($frecuencia) || $monto <= 0) {
            $mensaje = "Por favor complete todos los campos obligatorios correctamente.";
            $tipo_mensaje = "danger";
        } else {
            $mes = date('F Y', strtotime($fecha));
            
            if ($accion === 'agregar') {
                $stmt = $db->prepare("INSERT INTO ingresos (id_usuario, fecha, mes, descripcion, empresa, frecuencia, monto) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$usuario_id, $fecha, $mes, $descripcion, $empresa, $frecuencia, $monto])) {
                    $mensaje = "Ingreso registrado exitosamente.";
                    $tipo_mensaje = "success";
                    registrarLog('ingreso_agregado', "Nuevo ingreso: $descripcion - $$monto", $usuario_id);
                }
            } elseif ($accion === 'editar') {
                $id_ingreso = intval($_POST['id_ingreso']);
                $stmt = $db->prepare("UPDATE ingresos SET fecha = ?, mes = ?, descripcion = ?, empresa = ?, frecuencia = ?, monto = ? WHERE id_ingreso = ? AND id_usuario = ?");
                if ($stmt->execute([$fecha, $mes, $descripcion, $empresa, $frecuencia, $monto, $id_ingreso, $usuario_id])) {
                    $mensaje = "Ingreso actualizado exitosamente.";
                    $tipo_mensaje = "success";
                    registrarLog('ingreso_editado', "Ingreso editado: $descripcion", $usuario_id);
                }
            }
        }
    } elseif ($accion === 'eliminar') {
        $id_ingreso = intval($_POST['id_ingreso']);
        $stmt = $db->prepare("DELETE FROM ingresos WHERE id_ingreso = ? AND id_usuario = ?");
        if ($stmt->execute([$id_ingreso, $usuario_id])) {
            $mensaje = "Ingreso eliminado exitosamente.";
            $tipo_mensaje = "success";
            registrarLog('ingreso_eliminado', "Ingreso eliminado ID: $id_ingreso", $usuario_id);
        }
    }
}

// Obtener todos los ingresos del usuario
$stmt = $db->prepare("SELECT * FROM ingresos WHERE id_usuario = ? ORDER BY fecha DESC");
$stmt->execute([$usuario_id]);
$ingresos = $stmt->fetchAll();

// Calcular totales
$stmt = $db->prepare("SELECT frecuencia, SUM(monto) as total FROM ingresos WHERE id_usuario = ? GROUP BY frecuencia");
$stmt->execute([$usuario_id]);
$totales_frecuencia = [];
while ($row = $stmt->fetch()) {
    $totales_frecuencia[$row['frecuencia']] = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresos - Sistema de Finanzas Personales</title>
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
                        <a class="nav-link active" href="ingresos.php">
                            <i class="bi bi-cash-coin"></i> Ingresos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="egresos.php">
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
                <h2><i class="bi bi-cash-coin"></i> Gestión de Ingresos</h2>
                <p class="text-muted">Administra tus fuentes de ingreso</p>
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
            <div class="col-md-4">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <h6 class="mb-1">Ingresos Fijos</h6>
                        <h3>$<?php echo number_format($totales_frecuencia['fijo'] ?? 0, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <h6 class="mb-1">Ingresos Ocasionales</h6>
                        <h3>$<?php echo number_format($totales_frecuencia['ocasional'] ?? 0, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <h6 class="mb-1">Total Ingresos</h6>
                        <h3>$<?php echo number_format(array_sum($totales_frecuencia), 2); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botón Agregar -->
        <div class="mb-3">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalIngreso" onclick="limpiarFormulario()">
                <i class="bi bi-plus-circle"></i> Agregar Ingreso
            </button>
        </div>

        <!-- Tabla de Ingresos -->
        <div class="card stat-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaIngresos" class="table table-hover">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Mes</th>
                                <th>Descripción</th>
                                <th>Empresa</th>
                                <th>Frecuencia</th>
                                <th>Monto</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($ingresos as $ingreso): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($ingreso['fecha'])); ?></td>
                                    <td><?php echo htmlspecialchars($ingreso['mes']); ?></td>
                                    <td><?php echo htmlspecialchars($ingreso['descripcion']); ?></td>
                                    <td><?php echo htmlspecialchars($ingreso['empresa']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $ingreso['frecuencia'] === 'fijo' ? 'success' : 'info'; ?>">
                                            <?php echo ucfirst($ingreso['frecuencia']); ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-success">$<?php echo number_format($ingreso['monto'], 2); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editarIngreso(<?php echo htmlspecialchars(json_encode($ingreso)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="eliminarIngreso(<?php echo $ingreso['id_ingreso']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <button class="btn btn-primary" onclick="duplicarTodosIngresos()">
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

    <!-- Modal para Agregar/Editar Ingreso -->
    <div class="modal fade" id="modalIngreso" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tituloModal">Agregar Ingreso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    
                </div>
                
                <form method="POST" id="formIngreso">
                    <div class="modal-body">
                        <input type="hidden" name="accion" id="accion" value="agregar">
                        <input type="hidden" name="id_ingreso" id="id_ingreso">
                        
                        <div class="mb-3">
                            <label for="fecha" class="form-label">Fecha *</label>
                            <input type="date" class="form-control" name="fecha" id="fecha" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción *</label>
                            <input type="text" class="form-control" name="descripcion" id="descripcion" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="empresa" class="form-label">Empresa/Fuente</label>
                            <input type="text" class="form-control" name="empresa" id="empresa">
                        </div>
                        
                        <div class="mb-3">
                            <label for="frecuencia" class="form-label">Frecuencia *</label>
                            <select class="form-select" name="frecuencia" id="frecuencia" required>
                                <option value="">Seleccione...</option>
                                <option value="fijo">Fijo</option>
                                <option value="ocasional">Ocasional</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="monto" class="form-label">Monto *</label>
                            <input type="number" class="form-control" name="monto" id="monto" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Guardar</button>
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
            $('#tablaIngresos').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                },
                order: [[0, 'desc']]
            });
            
            // Establecer fecha actual por defecto
            document.getElementById('fecha').valueAsDate = new Date();
        });
        
        function limpiarFormulario() {
            document.getElementById('formIngreso').reset();
            document.getElementById('accion').value = 'agregar';
            document.getElementById('tituloModal').textContent = 'Agregar Ingreso';
            document.getElementById('fecha').valueAsDate = new Date();
        }
        
        function editarIngreso(ingreso) {
            document.getElementById('accion').value = 'editar';
            document.getElementById('id_ingreso').value = ingreso.id_ingreso;
            document.getElementById('fecha').value = ingreso.fecha;
            document.getElementById('descripcion').value = ingreso.descripcion;
            document.getElementById('empresa').value = ingreso.empresa;
            document.getElementById('frecuencia').value = ingreso.frecuencia;
            document.getElementById('monto').value = ingreso.monto;
            document.getElementById('tituloModal').textContent = 'Editar Ingreso';
            
            var modal = new bootstrap.Modal(document.getElementById('modalIngreso'));
            modal.show();
        }
        
        function eliminarIngreso(id) {
            if (confirm('¿Está seguro de eliminar este ingreso?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="accion" value="eliminar">' +
                                '<input type="hidden" name="id_ingreso" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function duplicarTodosIngresos() {
            if (confirm('¿Está seguro de duplicar TODOS los ingresos? Esto creará copias de cada ingreso registrado.')) {
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