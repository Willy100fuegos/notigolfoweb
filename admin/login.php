<?php
date_default_timezone_set('America/Mexico_City');
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

if (isLoggedIn()) {
    header('Location: editor.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        die("Error de validación de seguridad (CSRF).");
    }

    if (!empty($username) && !empty($password)) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN display_name VARCHAR(255) DEFAULT ''");
        } catch(PDOException $e) {}

        $stmt = $pdo->prepare("SELECT id, password_hash, role, display_name FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['display_name'] = !empty($user['display_name']) ? $user['display_name'] : $username;
            session_regenerate_id(true);
            header('Location: editor.php');
            exit;
        } else {
            $error = 'Credenciales incorrectas.';
        }
    } else {
        $error = 'Por favor ingresa usuario y contraseña.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NotiGolfo Veracruz</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            dark: '#2E2B22',
                            menu: '#4A4538',
                            olive: '#5C5748',
                            gold: '#B8860B',
                            red: '#991b1b',
                            accent: '#C0392B',
                            light: '#F5F3EF',
                            beige: '#E8E4DB',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        serif: ['Merriweather', 'Georgia', 'serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-brand-light font-sans text-brand-dark min-h-screen flex items-center justify-center p-4">

<div class="max-w-md w-full bg-white p-8 rounded-lg shadow-lg border border-brand-beige">
    <div class="text-center mb-8">
        <img src="http://imgfz.com/i/eFQ0WDT.png" alt="NotiGolfo" class="mx-auto h-20 mb-4 object-contain">
        <p class="text-brand-olive mt-2">Panel de Administración</p>
    </div>

    <?php if ($error): ?>
        <div class="bg-brand-red text-white p-3 rounded mb-4 text-sm">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <div class="mb-4">
            <label for="username" class="block text-sm font-medium text-brand-dark mb-1">Usuario</label>
            <input type="text" id="username" name="username" class="w-full px-4 py-2 border border-brand-beige rounded focus:outline-none focus:border-brand-gold focus:ring-1 focus:ring-brand-gold" required>
        </div>
        
        <div class="mb-6">
            <label for="password" class="block text-sm font-medium text-brand-dark mb-1">Contraseña</label>
            <input type="password" id="password" name="password" class="w-full px-4 py-2 border border-brand-beige rounded focus:outline-none focus:border-brand-gold focus:ring-1 focus:ring-brand-gold" required>
        </div>
        
        <button type="submit" class="w-full bg-brand-dark hover:bg-brand-menu text-white font-medium py-2 px-4 rounded transition duration-200">
            Ingresar
        </button>
    </form>
</div>

</body>
</html>
