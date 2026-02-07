<?php
require_once 'config/database.php';
iniciarSesion();

// Si ya está autenticado, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit();
}

$errores = [];
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitizar datos
    $nombre = Security::sanitize($_POST['nombre'] ?? '');
    $correo = Security::sanitize($_POST['correo'] ?? '');
    $telefono = Security::sanitize($_POST['telefono'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';
    
    // Validaciones
    if (empty($nombre)) {
        $errores[] = "El nombre es obligatorio.";
    } elseif (strlen($nombre) < 3) {
        $errores[] = "El nombre debe tener al menos 3 caracteres.";
    }
    
    if (empty($correo)) {
        $errores[] = "El correo electrónico es obligatorio.";
    } elseif (!Security::validateEmail($correo)) {
        $errores[] = "El correo electrónico no es válido.";
    }
    
    if (!empty($telefono) && !Security::validatePhone($telefono)) {
        $errores[] = "El formato del teléfono no es válido.";
    }
    
    if (empty($password)) {
        $errores[] = "La contraseña es obligatoria.";
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errores[] = "La contraseña debe tener al menos " . PASSWORD_MIN_LENGTH . " caracteres.";
    } elseif ($password !== $confirmar_password) {
        $errores[] = "Las contraseñas no coinciden.";
    }
    
    // Validar fortaleza de contraseña
    if (!empty($password)) {
        if (!preg_match('/[A-Z]/', $password)) {
            $errores[] = "La contraseña debe contener al menos una mayúscula.";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errores[] = "La contraseña debe contener al menos una minúscula.";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errores[] = "La contraseña debe contener al menos un número.";
        }
    }
    
    // Si no hay errores, proceder con el registro
    if (empty($errores)) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Verificar si el correo ya existe
            $stmt = $db->prepare("SELECT id_usuario FROM usuarios WHERE correo = ?");
            $stmt->execute([$correo]);
            
            if ($stmt->fetch()) {
                $errores[] = "Este correo electrónico ya está registrado.";
            } else {
                // Hash de la contraseña
                $password_hash = Security::hashPassword($password);
                
                // Insertar usuario
                $stmt = $db->prepare("INSERT INTO usuarios (nombre, correo, telefono, password) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nombre, $correo, $telefono, $password_hash]);
                
                // Obtener la fecha de registro
                $fecha_registro = date('Y-m-d H:i:s');
                
                // Registrar actividad
                registrarLog('registro', "Nuevo usuario registrado: $correo");
                
                // Enviar notificación a Telegram
                TelegramNotification::enviarNotificacionRegistro($nombre, $correo, $telefono, $fecha_registro);
                
                $exito = true;
            }
        } catch (PDOException $e) {
            error_log("Error en registro: " . $e->getMessage());
            $errores[] = "Error al procesar el registro. Por favor, intente nuevamente.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Sistema de Finanzas Personales</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .registro-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        .logo-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-header i {
            font-size: 60px;
            color: #667eea;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 3px;
            transition: all 0.3s;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="registro-card mx-auto">
            <div class="logo-header">
                <i class="bi bi-wallet2"></i>
                <h2 class="mt-3">Sistema de Finanzas</h2>
                <p class="text-muted">Crea tu cuenta</p>
            </div>
            
            <?php if ($exito): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill"></i>
                    ¡Registro exitoso! <a href="login.php" class="alert-link">Inicia sesión aquí</a>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errores)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <ul class="mb-0 mt-2">
                        <?php foreach($errores as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="formRegistro">
                <div class="mb-3">
                    <label for="nombre" class="form-label">Nombre completo *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="nombre" name="nombre" 
                               value="<?php echo htmlspecialchars($nombre ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="correo" class="form-label">Correo electrónico *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="correo" name="correo" 
                               value="<?php echo htmlspecialchars($correo ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="telefono" class="form-label">Teléfono <small class="text-muted">(opcional)</small></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                        <input type="tel" class="form-control" id="telefono" name="telefono" 
                               value="<?php echo htmlspecialchars($telefono ?? ''); ?>" 
                               placeholder="Ej: +57 3001234567">
                    </div>
                    <small class="text-muted">Formato: +57 3001234567 o 3001234567</small>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength" id="passwordStrength"></div>
                    <small class="text-muted">Mínimo 8 caracteres, incluye mayúsculas, minúsculas y números</small>
                </div>
                
                <div class="mb-3">
                    <label for="confirmar_password" class="form-label">Confirmar contraseña *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" class="form-control" id="confirmar_password" name="confirmar_password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="bi bi-person-plus"></i> Registrarse
                </button>
            </form>
            
            <div class="text-center mt-3">
                <p class="mb-0">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle mostrar/ocultar contraseña
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
        
        // Validador de fortaleza de contraseña
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            strengthBar.style.width = (strength * 20) + '%';
            
            if (strength <= 2) {
                strengthBar.style.backgroundColor = '#dc3545';
            } else if (strength === 3) {
                strengthBar.style.backgroundColor = '#ffc107';
            } else {
                strengthBar.style.backgroundColor = '#28a745';
            }
        });
    </script>
</body>
</html>