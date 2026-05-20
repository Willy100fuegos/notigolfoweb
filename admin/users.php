<?php
session_start();
date_default_timezone_set('America/Mexico_City');
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

requireLogin();

if ($_SESSION['user_role'] !== 'admin') {
    die("Acceso denegado. Solo administradores pueden ver esta página.");
}

// Auto-crear columna display_name si no existe (Manejo ágil)
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN display_name VARCHAR(255) DEFAULT ''");
} catch(PDOException $e) {
    // Probablemente ya existe
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_user') {
        $username = $_POST['username'] ?? '';
        $display_name = $_POST['display_name'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'editor';
        
        if (!empty($username) && !empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, display_name, password_hash, role) VALUES (?, ?, ?, ?)");
            try {
                $stmt->execute([$username, $display_name, $hash, $role]);
                $message = "Usuario creado correctamente.";
            } catch (PDOException $e) {
                $message = "Error: El usuario ya existe o hubo un problema.";
            }
        }
    } elseif ($action === 'edit_user') {
        $user_id = $_POST['user_id'] ?? 0;
        $username = $_POST['username'] ?? '';
        $display_name = $_POST['display_name'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'editor';
        
        if ($user_id) {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, display_name = ?, password_hash = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $display_name, $hash, $role, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, display_name = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $display_name, $role, $user_id]);
            }
            $message = "Usuario actualizado correctamente.";
        }
    }
}

$stmt = $pdo->query("SELECT id, username, display_name, role FROM users ORDER BY id ASC");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - NotiGolfo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            dark: '#2E2B22', menu: '#4A4538', olive: '#5C5748',
                            gold: '#B8860B', red: '#991b1b', accent: '#C0392B',
                            light: '#F5F3EF', beige: '#E8E4DB',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100 font-sans text-gray-800 min-h-screen">

<div class="bg-gray-900 text-gray-200 text-sm py-2 px-4 flex justify-between items-center z-50">
    <div class="flex space-x-4">
        <a href="editor.php" class="hover:text-white transition"><i class="fas fa-arrow-left mr-1"></i> Volver al Editor</a>
    </div>
    <div class="flex items-center space-x-4">
        <span>Hola, <?= htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username']) ?></span>
        <a href="logout.php" class="hover:text-white transition bg-gray-700 px-2 py-1 rounded">Cerrar Sesión</a>
    </div>
</div>

<div class="max-w-4xl mx-auto p-4 md:p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Gestión de Usuarios</h1>
        <p class="text-gray-600 mt-1">Crea y administra usuarios del sistema.</p>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-100 text-green-800 p-3 rounded mb-6 text-sm">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-8">
        <h2 class="text-xl font-bold border-b pb-2 mb-4" id="form-title">Crear Nuevo Usuario</h2>
        <form action="" method="POST" id="user-form">
            <input type="hidden" name="action" id="form-action" value="create_user">
            <input type="hidden" name="user_id" id="form-user_id" value="">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Username</label>
                    <input type="text" name="username" id="form-username" required class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-brand-gold focus:ring-2">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Nombre Público (Display Name)</label>
                    <input type="text" name="display_name" id="form-display_name" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-brand-gold focus:ring-2">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Contraseña</label>
                    <input type="password" name="password" id="form-password" placeholder="Dejar en blanco para no cambiar (si edita)" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-brand-gold focus:ring-2">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Rol</label>
                    <select name="role" id="form-role" class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-brand-gold focus:ring-2">
                        <option value="editor">Editor (Reportero)</option>
                        <option value="admin">Admin (Administrador)</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="resetForm()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50 transition">Cancelar</button>
                <button type="submit" id="form-submit" class="px-4 py-2 bg-brand-dark text-white font-bold rounded hover:bg-brand-menu transition">Crear Usuario</button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
        <h2 class="text-xl font-bold border-b pb-2 mb-4">Usuarios Existentes</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-2 px-4 border-b text-left text-xs font-semibold text-gray-600 uppercase">ID</th>
                        <th class="py-2 px-4 border-b text-left text-xs font-semibold text-gray-600 uppercase">Username</th>
                        <th class="py-2 px-4 border-b text-left text-xs font-semibold text-gray-600 uppercase">Nombre Público</th>
                        <th class="py-2 px-4 border-b text-left text-xs font-semibold text-gray-600 uppercase">Rol</th>
                        <th class="py-2 px-4 border-b text-center text-xs font-semibold text-gray-600 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-2 px-4 border-b text-sm text-gray-600"><?= $u['id'] ?></td>
                        <td class="py-2 px-4 border-b text-sm text-gray-800 font-bold"><?= htmlspecialchars($u['username']) ?></td>
                        <td class="py-2 px-4 border-b text-sm text-gray-600"><?= htmlspecialchars($u['display_name']) ?></td>
                        <td class="py-2 px-4 border-b text-sm">
                            <span class="px-2 py-1 text-xs rounded text-white <?= $u['role'] === 'admin' ? 'bg-red-600' : 'bg-blue-600' ?>">
                                <?= strtoupper($u['role']) ?>
                            </span>
                        </td>
                        <td class="py-2 px-4 border-b text-center">
                            <button onclick="editUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>', '<?= htmlspecialchars($u['display_name']) ?>', '<?= $u['role'] ?>')" class="text-blue-600 hover:underline text-sm"><i class="fas fa-edit"></i> Editar</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function editUser(id, username, display_name, role) {
    document.getElementById('form-title').innerText = 'Editar Usuario #' + id;
    document.getElementById('form-action').value = 'edit_user';
    document.getElementById('form-user_id').value = id;
    document.getElementById('form-username').value = username;
    document.getElementById('form-display_name').value = display_name;
    document.getElementById('form-role').value = role;
    document.getElementById('form-password').required = false;
    document.getElementById('form-submit').innerText = 'Actualizar Usuario';
    window.scrollTo(0, 0);
}

function resetForm() {
    document.getElementById('form-title').innerText = 'Crear Nuevo Usuario';
    document.getElementById('form-action').value = 'create_user';
    document.getElementById('form-user_id').value = '';
    document.getElementById('form-username').value = '';
    document.getElementById('form-display_name').value = '';
    document.getElementById('form-role').value = 'editor';
    document.getElementById('form-password').required = true;
    document.getElementById('form-submit').innerText = 'Crear Usuario';
}
</script>

</body>
</html>
