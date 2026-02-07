<?php
require_once 'config/database.php';
iniciarSesion();
verificarAutenticacion();

$db = Database::getInstance()->getConnection();
$usuario_id = $_SESSION['usuario_id'];
$usuario_nombre = $_SESSION['usuario_nombre'];

// Obtener configuración de ahorro
$stmt = $db->prepare("SELECT porcentaje_ahorro FROM config_ahorro WHERE id_usuario = ? AND activo = 1 ORDER BY id_config DESC LIMIT 1");
$stmt->execute([$usuario_id]);
$config_ahorro = $stmt->fetch();
$porcentaje_ahorro = $config_ahorro ? $config_ahorro['porcentaje_ahorro'] : 10;

// Obtener parámetros
$tipo_reporte = $_GET['tipo'] ?? 'mensual';
$fecha_fin = date('Y-m-d');

// Calcular fecha de inicio según tipo de reporte
switch($tipo_reporte) {
    case 'mensual':
        $fecha_inicio = date('Y-m-01'); // Primer día del mes actual
        $titulo_periodo = 'Mes Actual - ' . date('F Y');
        break;
    case 'trimestral':
        $fecha_inicio = date('Y-m-d', strtotime('-3 months'));
        $titulo_periodo = 'Últimos 3 Meses';
        break;
    case 'semestral':
        $fecha_inicio = date('Y-m-d', strtotime('-6 months'));
        $titulo_periodo = 'Últimos 6 Meses';
        break;
    case 'anual':
        $fecha_inicio = date('Y-m-d', strtotime('-12 months'));
        $titulo_periodo = 'Último Año';
        break;
    default:
        $fecha_inicio = date('Y-m-01');
        $titulo_periodo = 'Mes Actual - ' . date('F Y');
}

// Obtener datos de ingresos DETALLADOS (por descripción)
$stmt = $db->prepare("
    SELECT descripcion, SUM(monto) as total, frecuencia
    FROM ingresos 
    WHERE id_usuario = ? AND fecha BETWEEN ? AND ?
    GROUP BY descripcion, frecuencia
    ORDER BY frecuencia, total DESC
");
$stmt->execute([$usuario_id, $fecha_inicio, $fecha_fin]);
$detalle_ingresos = $stmt->fetchAll();

// Obtener datos de egresos DETALLADOS (por descripción)
$stmt = $db->prepare("
    SELECT descripcion, SUM(monto) as total, categoria
    FROM egresos 
    WHERE id_usuario = ? AND fecha BETWEEN ? AND ?
    GROUP BY descripcion, categoria
    ORDER BY categoria, total DESC
");
$stmt->execute([$usuario_id, $fecha_inicio, $fecha_fin]);
$detalle_egresos = $stmt->fetchAll();

// Obtener datos de ingresos por mes para gráficas
$stmt = $db->prepare("
    SELECT DATE_FORMAT(fecha, '%Y-%m') as periodo, 
           DATE_FORMAT(fecha, '%M %Y') as mes_nombre,
           SUM(monto) as total
    FROM ingresos 
    WHERE id_usuario = ? AND fecha BETWEEN ? AND ?
    GROUP BY periodo
    ORDER BY periodo ASC
");
$stmt->execute([$usuario_id, $fecha_inicio, $fecha_fin]);
$ingresos_mensuales = $stmt->fetchAll();

// Obtener datos de egresos por mes para gráficas
$stmt = $db->prepare("
    SELECT DATE_FORMAT(fecha, '%Y-%m') as periodo,
           DATE_FORMAT(fecha, '%M %Y') as mes_nombre,
           SUM(monto) as total
    FROM egresos 
    WHERE id_usuario = ? AND fecha BETWEEN ? AND ?
    GROUP BY periodo
    ORDER BY periodo ASC
");
$stmt->execute([$usuario_id, $fecha_inicio, $fecha_fin]);
$egresos_mensuales = $stmt->fetchAll();

// Organizar datos por periodo
$stmt = $db->prepare("
    SELECT DISTINCT DATE_FORMAT(fecha, '%Y-%m') as periodo,
           DATE_FORMAT(fecha, '%M %Y') as mes_nombre
    FROM (
        SELECT fecha FROM ingresos WHERE id_usuario = ? AND fecha BETWEEN ? AND ?
        UNION
        SELECT fecha FROM egresos WHERE id_usuario = ? AND fecha BETWEEN ? AND ?
    ) as fechas
    ORDER BY periodo ASC
");
$stmt->execute([$usuario_id, $fecha_inicio, $fecha_fin, $usuario_id, $fecha_inicio, $fecha_fin]);
$periodos_lista = $stmt->fetchAll();

// Calcular totales
$total_ingresos = 0;
$total_egresos = 0;

foreach($detalle_ingresos as $ingreso) {
    $total_ingresos += $ingreso['total'];
}

foreach($detalle_egresos as $egreso) {
    $total_egresos += $egreso['total'];
}

// Calcular ahorro total
$total_ahorro = ($total_ingresos * $porcentaje_ahorro) / 100;
$balance_total = $total_ingresos - $total_egresos - $total_ahorro;

// Preparar datos para gráficas
$datos_mensuales = [];
foreach($periodos_lista as $periodo) {
    $per = $periodo['periodo'];
    
    $ingresos_mes = 0;
    foreach($ingresos_mensuales as $ing) {
        if ($ing['periodo'] == $per) {
            $ingresos_mes = $ing['total'];
            break;
        }
    }
    
    $egresos_mes = 0;
    foreach($egresos_mensuales as $egr) {
        if ($egr['periodo'] == $per) {
            $egresos_mes = $egr['total'];
            break;
        }
    }
    
    // Calcular ahorro del mes
    $ahorro_mes = ($ingresos_mes * $porcentaje_ahorro) / 100;
    
    $datos_mensuales[] = [
        'periodo' => $per,
        'mes' => $periodo['mes_nombre'],
        'ingresos' => $ingresos_mes,
        'egresos' => $egresos_mes,
        'ahorro' => $ahorro_mes,
        'balance' => $ingresos_mes - $egresos_mes - $ahorro_mes
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema de Finanzas Personales</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
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
        @media print {
            .no-print {
                display: none !important;
            }
            .card {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark no-print">
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
                        <a class="nav-link active" href="reportes.php">
                            <i class="bi bi-graph-up"></i> Reportes
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center text-white">
                    <i class="bi bi-person-circle me-2"></i>
                    <span class="me-3"><?php echo htmlspecialchars($usuario_nombre); ?></span>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right"></i> Salir
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="container-fluid mt-4" id="contenidoReporte">
        <div class="row mb-4">
            <div class="col-md-6">
                <h2><i class="bi bi-graph-up"></i> Reportes Financieros</h2>
                <p class="text-muted"><?php echo $titulo_periodo; ?></p>
            </div>
            <div class="col-md-6 text-end no-print">
                <div class="btn-group me-2">
                    <a href="?tipo=mensual" class="btn btn-<?php echo $tipo_reporte === 'mensual' ? 'primary' : 'outline-primary'; ?>">
                        Mensual
                    </a>
                    <a href="?tipo=trimestral" class="btn btn-<?php echo $tipo_reporte === 'trimestral' ? 'primary' : 'outline-primary'; ?>">
                        Trimestral
                    </a>
                    <a href="?tipo=semestral" class="btn btn-<?php echo $tipo_reporte === 'semestral' ? 'primary' : 'outline-primary'; ?>">
                        Semestral
                    </a>
                    <a href="?tipo=anual" class="btn btn-<?php echo $tipo_reporte === 'anual' ? 'primary' : 'outline-primary'; ?>">
                        Anual
                    </a>
                </div>
                <button class="btn btn-danger" onclick="exportarPDF()">
                    <i class="bi bi-file-pdf"></i> Exportar PDF
                </button>
                <button class="btn btn-success" onclick="exportarExcel()">
                    <i class="bi bi-file-excel"></i> Exportar Excel
                </button>
            </div>
        </div>

        <!-- Resumen Ejecutivo -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body text-center">
                        <h6>Total Ingresos</h6>
                        <h2>$<?php echo number_format($total_ingresos, 0, ',', '.'); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-danger text-white">
                    <div class="card-body text-center">
                        <h6>Total Egresos</h6>
                        <h2>$<?php echo number_format($total_egresos, 0, ',', '.'); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body text-center">
                        <h6>Total Ahorros (<?php echo $porcentaje_ahorro; ?>%)</h6>
                        <h2>$<?php echo number_format($total_ahorro, 0, ',', '.'); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-<?php echo $balance_total >= 0 ? 'primary' : 'warning'; ?> text-white">
                    <div class="card-body text-center">
                        <h6>Balance</h6>
                        <h2>$<?php echo number_format($balance_total, 0, ',', '.'); ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLA DE RESUMEN FINANCIERO (igual que dashboard) -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card stat-card">
                    <div class="card-header text-white text-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <h5 class="mb-0"><i class="bi bi-table"></i> RESUMEN O EPITOME FINANCIERO</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0" id="tablaEpitome">
                                <thead class="table-dark text-center">
                                    <tr>
                                        <th style="width: 50px;">ITEM</th>
                                        <th>DESCRIPCION</th>
                                        <th style="width: 150px;">INGRESOS</th>
                                        <th style="width: 150px;">EGRESOS</th>
                                        <th style="width: 100px;">%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $item = 1;
                                    
                                    // Mostrar Ingresos
                                    foreach($detalle_ingresos as $ingreso): 
                                        $porcentaje = $total_ingresos > 0 ? ($ingreso['total'] / $total_ingresos * 100) : 0;
                                    ?>
                                    <tr>
                                        <td class="text-center"><?php echo $item++; ?></td>
                                        <td><?php echo htmlspecialchars($ingreso['descripcion']); ?></td>
                                        <td class="text-end text-success fw-bold">$<?php echo number_format($ingreso['total'], 0, ',', '.'); ?></td>
                                        <td class="text-end"></td>
                                        <td class="text-center"><?php echo number_format($porcentaje, 2); ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <!-- Línea vacía separadora -->
                                    <?php if (!empty($detalle_ingresos) && !empty($detalle_egresos)): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $item++; ?></td>
                                        <td colspan="4"></td>
                                    </tr>
                                    <?php endif; ?>
                                    
                                    <!-- Mostrar Egresos -->
                                    <?php foreach($detalle_egresos as $egreso): 
                                        $porcentaje = $total_ingresos > 0 ? ($egreso['total'] / $total_ingresos * 100) : 0;
                                    ?>
                                    <tr>
                                        <td class="text-center"><?php echo $item++; ?></td>
                                        <td><?php echo htmlspecialchars($egreso['descripcion']); ?></td>
                                        <td class="text-end"></td>
                                        <td class="text-end text-danger fw-bold">$<?php echo number_format($egreso['total'], 0, ',', '.'); ?></td>
                                        <td class="text-center"><?php echo number_format($porcentaje, 2); ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <!-- Línea vacía antes del ahorro -->
                                    <tr>
                                        <td class="text-center"><?php echo $item++; ?></td>
                                        <td colspan="4"></td>
                                    </tr>
                                    
                                    <!-- Ahorro Programado -->
                                    <tr>
                                        <td class="text-center"><?php echo $item++; ?></td>
                                        <td><strong>Ahorro programado</strong></td>
                                        <td class="text-end"></td>
                                        <td class="text-end text-warning fw-bold">$<?php echo number_format($total_ahorro, 0, ',', '.'); ?></td>
                                        <td class="text-center"><?php echo number_format($total_ahorro / max($total_ingresos, 1) * 100, 2); ?>%</td>
                                    </tr>
                                    
                                    <!-- Líneas vacías finales -->
                                    <tr>
                                        <td class="text-center"><?php echo $item++; ?></td>
                                        <td colspan="4"></td>
                                    </tr>
                                    <tr>
                                        <td class="text-center"><?php echo $item++; ?></td>
                                        <td colspan="4"></td>
                                    </tr>
                                    
                                    <!-- Subtotales recuadros -->
                                    <tr class="table-warning">
                                        <td class="text-center"><?php echo $item++; ?></td>
                                        <td><strong>Subtotales</strong></td>
                                        <td class="text-end fw-bold">$<?php echo number_format($total_ingresos, 0, ',', '.'); ?></td>
                                        <td class="text-end fw-bold">$<?php echo number_format($total_egresos, 0, ',', '.'); ?></td>
                                        <td class="text-center fw-bold"><?php echo number_format(($total_egresos) / max($total_ingresos, 1) * 100, 2); ?>%</td>
                                    </tr>
                                    
                                    <!-- Balance General -->
                                    <tr class="table-success">
                                        <td class="text-center"><?php echo $item++; ?></td>
                                        <td><strong>Balance General</strong></td>
                                        <td class="text-end fw-bold fs-5 <?php echo $balance_total >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            $<?php echo number_format($balance_total, 0, ',', '.'); ?>
                                        </td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficas -->
        <?php if (count($datos_mensuales) > 1): ?>
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card stat-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Evolución Mensual</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="chartEvolucion"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card stat-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Distribución</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="chartDistribucion"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const datosMensuales = <?php echo json_encode($datos_mensuales); ?>;
        
        <?php if (count($datos_mensuales) > 1): ?>
        // Gráfica de Evolución Mensual
        const ctxEvolucion = document.getElementById('chartEvolucion').getContext('2d');
        new Chart(ctxEvolucion, {
            type: 'bar',
            data: {
                labels: datosMensuales.map(d => d.mes),
                datasets: [
                    {
                        label: 'Ingresos',
                        data: datosMensuales.map(d => d.ingresos),
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 2
                    },
                    {
                        label: 'Egresos',
                        data: datosMensuales.map(d => d.egresos),
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 2
                    },
                    {
                        label: 'Balance',
                        data: datosMensuales.map(d => d.balance),
                        type: 'line',
                        borderColor: 'rgba(102, 126, 234, 1)',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': $' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Gráfica de Distribución
        const totalIngresos = <?php echo $total_ingresos; ?>;
        const totalEgresos = <?php echo $total_egresos; ?>;
        
        const ctxDistribucion = document.getElementById('chartDistribucion').getContext('2d');
        new Chart(ctxDistribucion, {
            type: 'doughnut',
            data: {
                labels: ['Ingresos', 'Egresos'],
                datasets: [{
                    data: [totalIngresos, totalEgresos],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(220, 53, 69, 0.8)'
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': $' + value.toLocaleString() + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
        // Función para exportar a PDF
        function exportarPDF() {
            try {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');
                
                doc.setFontSize(18);
                doc.setFont(undefined, 'bold');
                doc.text('RESUMEN O EPITOME FINANCIERO', 105, 15, { align: 'center' });
                
                doc.setFontSize(11);
                doc.setFont(undefined, 'normal');
                doc.text('Usuario: <?php echo addslashes($usuario_nombre); ?>', 105, 22, { align: 'center' });
                doc.text('<?php echo $titulo_periodo; ?>', 105, 28, { align: 'center' });
                doc.text('Fecha: <?php echo date("d/m/Y"); ?>', 105, 34, { align: 'center' });
                
                doc.setLineWidth(0.5);
                doc.line(20, 38, 190, 38);
                
                let yPos = 45;
                
                doc.setFontSize(9);
                doc.setFont(undefined, 'bold');
                doc.text('ITEM', 25, yPos, { align: 'center' });
                doc.text('DESCRIPCION', 60, yPos);
                doc.text('INGRESOS', 115, yPos, { align: 'right' });
                doc.text('EGRESOS', 150, yPos, { align: 'right' });
                doc.text('%', 180, yPos, { align: 'right' });
                
                yPos += 2;
                doc.line(20, yPos, 190, yPos);
                yPos += 5;
                
                doc.setFont(undefined, 'normal');
                let item = 1;
                
                <?php
                foreach($detalle_ingresos as $ingreso): 
                    $porcentaje = $total_ingresos > 0 ? ($ingreso['total'] / $total_ingresos * 100) : 0;
                    $descripcion_clean = preg_replace('/[^a-zA-Z0-9\s\-\_]/', '', $ingreso['descripcion']);
                    $descripcion_clean = substr($descripcion_clean, 0, 40);
                ?>
                if (yPos > 270) {
                    doc.addPage();
                    yPos = 20;
                }
                doc.text(item.toString(), 25, yPos, { align: 'center' });
                doc.text('<?php echo $descripcion_clean; ?>', 35, yPos);
                doc.setTextColor(40, 167, 69);
                doc.text('$<?php echo number_format($ingreso["total"], 0, ".", ","); ?>', 115, yPos, { align: 'right' });
                doc.setTextColor(0, 0, 0);
                doc.text('<?php echo number_format($porcentaje, 2); ?>%', 180, yPos, { align: 'right' });
                yPos += 5;
                item++;
                <?php endforeach; ?>
                
                if (yPos > 270) {
                    doc.addPage();
                    yPos = 20;
                }
                yPos += 2;
                doc.text(item.toString(), 25, yPos, { align: 'center' });
                yPos += 5;
                item++;
                
                <?php 
                foreach($detalle_egresos as $egreso): 
                    $porcentaje = $total_ingresos > 0 ? ($egreso['total'] / $total_ingresos * 100) : 0;
                    $descripcion_clean = preg_replace('/[^a-zA-Z0-9\s\-\_]/', '', $egreso['descripcion']);
                    $descripcion_clean = substr($descripcion_clean, 0, 40);
                ?>
                if (yPos > 270) {
                    doc.addPage();
                    yPos = 20;
                }
                doc.text(item.toString(), 25, yPos, { align: 'center' });
                doc.text('<?php echo $descripcion_clean; ?>', 35, yPos);
                doc.setTextColor(220, 53, 69);
                doc.text('$<?php echo number_format($egreso["total"], 0, ".", ","); ?>', 150, yPos, { align: 'right' });
                doc.setTextColor(0, 0, 0);
                doc.text('<?php echo number_format($porcentaje, 2); ?>%', 180, yPos, { align: 'right' });
                yPos += 5;
                item++;
                <?php endforeach; ?>
                
                if (yPos > 270) {
                    doc.addPage();
                    yPos = 20;
                }
                yPos += 2;
                doc.text(item.toString(), 25, yPos, { align: 'center' });
                yPos += 5;
                item++;
                
                doc.text(item.toString(), 25, yPos, { align: 'center' });
                doc.setFont(undefined, 'bold');
                doc.text('Ahorro programado', 35, yPos);
                doc.setFont(undefined, 'normal');
                doc.setTextColor(255, 193, 7);
                doc.text('$<?php echo number_format($total_ahorro, 0, ".", ","); ?>', 150, yPos, { align: 'right' });
                doc.setTextColor(0, 0, 0);
                doc.text('<?php echo number_format($total_ahorro / max($total_ingresos, 1) * 100, 2); ?>%', 180, yPos, { align: 'right' });
                yPos += 8;
                item++;
                
                doc.text(item.toString(), 25, yPos, { align: 'center' });
                yPos += 5;
                item++;
                
                doc.text(item.toString(), 25, yPos, { align: 'center' });
                yPos += 5;
                item++;
                
                doc.setLineWidth(0.3);
                doc.line(20, yPos, 190, yPos);
                yPos += 5;
                
                doc.setFont(undefined, 'bold');
                doc.text(item.toString(), 25, yPos, { align: 'center' });
                doc.text('Subtotales', 35, yPos);
                doc.text('$<?php echo number_format($total_ingresos, 0, ".", ","); ?>', 115, yPos, { align: 'right' });
                doc.text('$<?php echo number_format($total_egresos, 0, ".", ","); ?>', 150, yPos, { align: 'right' });
                doc.text('<?php echo number_format(($total_egresos) / max($total_ingresos, 1) * 100, 2); ?>%', 180, yPos, { align: 'right' });
                yPos += 6;
                item++;
                
                doc.setLineWidth(0.5);
                doc.line(20, yPos, 190, yPos);
                yPos += 5;
                
                doc.setFontSize(11);
                doc.text(item.toString(), 25, yPos, { align: 'center' });
                doc.text('Balance General', 35, yPos);
                <?php if ($balance_total >= 0): ?>
                doc.setTextColor(40, 167, 69);
                <?php else: ?>
                doc.setTextColor(220, 53, 69);
                <?php endif; ?>
                doc.text('$<?php echo number_format($balance_total, 0, ".", ","); ?>', 115, yPos, { align: 'right' });
                
                doc.save('reporte_financiero_<?php echo $usuario_nombre; ?>_<?php echo date("Y-m-d"); ?>.pdf');
                alert('PDF generado exitosamente');
            } catch (error) {
                console.error('Error al generar PDF:', error);
                alert('Error al generar PDF: ' + error.message);
            }
        }
        
        // Función para exportar a Excel
        function exportarExcel() {
            try {
                const datos = [
                    ['RESUMEN O EPITOME FINANCIERO'],
                    ['Usuario: <?php echo addslashes($usuario_nombre); ?>'],
                    ['<?php echo $titulo_periodo; ?>'],
                    ['Fecha: <?php echo date("d/m/Y"); ?>'],
                    [],
                    ['ITEM', 'DESCRIPCION', 'INGRESOS', 'EGRESOS', '%']
                ];
                
                let item = 1;
                
                <?php
                foreach($detalle_ingresos as $ingreso): 
                    $porcentaje = $total_ingresos > 0 ? ($ingreso['total'] / $total_ingresos * 100) : 0;
                    $descripcion_clean = preg_replace('/[^a-zA-Z0-9\s\-\_]/', '', $ingreso['descripcion']);
                ?>
                datos.push([
                    item++,
                    '<?php echo $descripcion_clean; ?>',
                    <?php echo $ingreso["total"]; ?>,
                    '',
                    <?php echo number_format($porcentaje, 2, '.', ''); ?>
                ]);
                <?php endforeach; ?>
                
                datos.push([item++, '', '', '', '']);
                
                <?php 
                foreach($detalle_egresos as $egreso): 
                    $porcentaje = $total_ingresos > 0 ? ($egreso['total'] / $total_ingresos * 100) : 0;
                    $descripcion_clean = preg_replace('/[^a-zA-Z0-9\s\-\_]/', '', $egreso['descripcion']);
                ?>
                datos.push([
                    item++,
                    '<?php echo $descripcion_clean; ?>',
                    '',
                    <?php echo $egreso["total"]; ?>,
                    <?php echo number_format($porcentaje, 2, '.', ''); ?>
                ]);
                <?php endforeach; ?>
                
                datos.push([item++, '', '', '', '']);
                
                datos.push([
                    item++,
                    'Ahorro programado',
                    '',
                    <?php echo $total_ahorro; ?>,
                    <?php echo number_format($total_ahorro / max($total_ingresos, 1) * 100, 2, '.', ''); ?>
                ]);
                
                datos.push([item++, '', '', '', '']);
                datos.push([item++, '', '', '', '']);
                
                datos.push([
                    item++,
                    'Subtotales',
                    <?php echo $total_ingresos; ?>,
                    <?php echo $total_egresos; ?>,
                    <?php echo number_format(($total_egresos) / max($total_ingresos, 1) * 100, 2, '.', ''); ?>
                ]);
                
                datos.push([
                    item++,
                    'Balance General',
                    <?php echo $balance_total; ?>,
                    '',
                    ''
                ]);
                
                const wb = XLSX.utils.book_new();
                const ws = XLSX.utils.aoa_to_sheet(datos);
                
                ws['!cols'] = [
                    { wch: 6 },
                    { wch: 40 },
                    { wch: 15 },
                    { wch: 15 },
                    { wch: 10 }
                ];
                
                XLSX.utils.book_append_sheet(wb, ws, 'Reporte');
                XLSX.writeFile(wb, 'reporte_financiero_<?php echo $usuario_nombre; ?>_<?php echo date("Y-m-d"); ?>.xlsx');
                alert('Excel generado exitosamente');
            } catch (error) {
                console.error('Error al generar Excel:', error);
                alert('Error al generar Excel: ' + error.message);
            }
        }
    </script>
</body>
</html>