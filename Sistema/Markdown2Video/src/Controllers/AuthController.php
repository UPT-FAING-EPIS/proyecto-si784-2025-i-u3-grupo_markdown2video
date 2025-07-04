<?php
// src/Controllers/AuthController.php
namespace Dales\Markdown2video\Controllers;

use PDO;
use Dales\Markdown2video\Models\UserModel;

class AuthController {
    private PDO $pdo;
    private UserModel $userModel;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->userModel = new UserModel($this->pdo);
    }

    /**
     * Muestra el formulario de login.
     * (Token CSRF eliminado para esta prueba)
     */
    public function showLoginForm(): void {
        // Ya no generamos ni pasamos $csrf_token para el login
        // if (empty($_SESSION['csrf_token_login'])) {
        //     $_SESSION['csrf_token_login'] = bin2hex(random_bytes(32));
        // }
        // $csrf_token = $_SESSION['csrf_token_login']; // Esta línea se elimina o no se usa

        $base_url = BASE_URL;
        $pageTitle = "Iniciar Sesión";
        $error_message = $_SESSION['error'] ?? null;
        $success_message = $_SESSION['success'] ?? null;
        unset($_SESSION['error'], $_SESSION['success']);

        $viewPath = VIEWS_PATH . 'auth/login.php';
        if (file_exists($viewPath)) {
            // La vista login.php ya no debe esperar $csrf_token
            require_once $viewPath;
        } else {
            error_log("Vista de Login no encontrada: " . $viewPath);
            http_response_code(500); echo "Error interno del servidor (vista login)."; exit;
        }
    }

    /**
     * Procesa el intento de login.
     * (SIN VALIDACIÓN DE TOKEN CSRF)
     */
    public function processLogin(): void {
        error_log("PROCESS_LOGIN: Inicio del método.");
        error_log("PROCESS_LOGIN: (Validación CSRF DESHABILITADA para depuración)");

        // 1. VALIDACIÓN DE TOKEN CSRF - ELIMINADA / COMENTADA
        /*
        if (empty($_POST['csrf_token_login']) || !hash_equals($_SESSION['csrf_token_login'] ?? '', $_POST['csrf_token_login'])) {
            error_log("PROCESS_LOGIN_CSRF_DEBUG: ¡¡FALLO DE TOKEN!! Sesión: " . ($_SESSION['csrf_token_login'] ?? 'NO SET') . " | POST: " . ($_POST['csrf_token_login'] ?? 'NO SET'));
            $_SESSION['error'] = 'Petición inválida o el token ha expirado (login).';
            header('Location: ' . BASE_URL . '/auth/login');
            exit();
        }
        error_log("PROCESS_LOGIN_CSRF_DEBUG: Token CSRF habría sido validado aquí.");
        */


        // 2. OBTENER Y VALIDAR ENTRADAS (email y password)
        $email = trim($_POST['email'] ?? '');
        $password_from_form = $_POST['password'] ?? '';

        error_log("PROCESS_LOGIN: Email del formulario: " . $email);
        // No loguear la contraseña en texto plano en producción.
        // error_log("PROCESS_LOGIN: Password del formulario (DEBUG): " . $password_from_form);

        if (empty($email) || empty($password_from_form)) {
            $_SESSION['error'] = 'El correo electrónico y la contraseña son obligatorios.';
            header('Location: ' . BASE_URL . '/auth/login');
            exit();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'El formato del correo electrónico no es válido.';
            header('Location: ' . BASE_URL . '/auth/login');
            exit();
        }

        // 3. PROCESAR LOGIN (VERIFICACIÓN DE CREDENCIALES)
        try {
            error_log("PROCESS_LOGIN: Buscando usuario por email: " . $email);
            $user = $this->userModel->findByEmail($email);

            if ($user) {
                error_log("PROCESS_LOGIN: Usuario encontrado en BD. ID: " . $user['id'] . ". Email: " . $user['email']);
                // error_log("PROCESS_LOGIN_DEBUG_HASH: Hash en BD: " . $user['password_hash']);

                $isPasswordCorrect = password_verify($password_from_form, $user['password_hash']);
                error_log("PROCESS_LOGIN_DEBUG_VERIFY_RESULT: Resultado de password_verify(): " . ($isPasswordCorrect ? 'TRUE' : 'FALSE'));

                if ($isPasswordCorrect) {
                    error_log("PROCESS_LOGIN: ¡PASSWORD VERIFY EXITOSO! Para usuario ID: " . $user['id']);
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['logged_in'] = true;
                    header('Location: ' . BASE_URL . '/dashboard');
                    exit();
                } else {
                    error_log("PROCESS_LOGIN: FALLO DE PASSWORD_VERIFY para usuario ID: " . ($user['id'] ?? 'DESCONOCIDO'));
                    $_SESSION['error'] = 'Correo electrónico o contraseña incorrectos.';
                    header('Location: ' . BASE_URL . '/auth/login');
                    exit();
                }
            } else {
                error_log("PROCESS_LOGIN: Usuario NO encontrado en BD para email: " . $email);
                $_SESSION['error'] = 'Correo electrónico o contraseña incorrectos.';
                header('Location: ' . BASE_URL . '/auth/login');
                exit();
            }
        } catch (\PDOException $e) {
            error_log("Error de BD en AuthController::processLogin: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            $_SESSION['error'] = 'Error del servidor al intentar iniciar sesión.';
            header('Location: ' . BASE_URL . '/auth/login');
            exit();
        } catch (\Exception $e) {
            error_log("Error inesperado en AuthController::processLogin: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            $_SESSION['error'] = 'Ocurrió un error inesperado.';
            header('Location: ' . BASE_URL . '/auth/login');
            exit();
        }
    }

    /**
     * Cierra la sesión del usuario.
     */
    public function logout(): void {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        header('Location: ' . BASE_URL . '/auth/login');
        exit();
    }

    // --- MÉTODOS PARA REGISTRO (ESTOS MANTENDRÁN SU PROTECCIÓN CSRF) ---
    public function showRegisterForm(): void {
        if (empty($_SESSION['csrf_token_register'])) { 
            $_SESSION['csrf_token_register'] = bin2hex(random_bytes(32)); 
        }
        $csrf_token = $_SESSION['csrf_token_register']; // Variable $csrf_token para la vista de registro
        $base_url = BASE_URL;
        $pageTitle = "Registro de Nuevo Usuario";
        $error_message = $_SESSION['error'] ?? null;
        $form_data = $_SESSION['form_data'] ?? []; 
        unset($_SESSION['error'], $_SESSION['form_data']);
        
        $viewPath = VIEWS_PATH . 'auth/registro.php'; // Ajusta si es necesario
        if (file_exists($viewPath)) { require_once $viewPath; }
        else { error_log("Vista registro no encontrada: " . $viewPath); http_response_code(500); echo "Error."; exit; }
    }
    
    public function processRegistration(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . BASE_URL . '/auth/register'); exit(); }

        // MANTENER VALIDACIÓN CSRF PARA REGISTRO
        if (empty($_POST['csrf_token_register']) || !hash_equals($_SESSION['csrf_token_register'] ?? '', $_POST['csrf_token_register'])) {
            $_SESSION['error'] = 'Petición inválida o el token ha expirado (registro).';
            header('Location: ' . BASE_URL . '/auth/register'); exit();
        }
        // unset($_SESSION['csrf_token_register']);

        // ... (resto de tu lógica de processRegistration con validación de datos, UserModel, etc.) ...
        // ... (el código que te di antes para processRegistration es correcto aquí) ...
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $_SESSION['form_data'] = ['username' => $username, 'email' => $email];
        $errors = [];
        if (empty($username) || strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) { $errors[] = "Usuario inválido."; }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "Email inválido."; }
        if (empty($password) || strlen($password) < 8) { $errors[] = "Contraseña muy corta (mín 8)."; }
        if ($password !== $password_confirm) { $errors[] = "Las contraseñas no coinciden."; }
        if (!empty($errors)) { $_SESSION['error'] = implode('<br>', $errors); header('Location: ' . BASE_URL . '/auth/register'); exit(); }
        try {
            if ($this->userModel->findByEmail($email)) { $_SESSION['error'] = "Email ya registrado."; header('Location: ' . BASE_URL . '/auth/register'); exit(); }
            if ($this->userModel->findByUsername($username)) { $_SESSION['error'] = "Usuario ya existe."; header('Location: ' . BASE_URL . '/auth/register'); exit(); }
            $newUserId = $this->userModel->createUser($username, $email, $password, []);
            if ($newUserId) { unset($_SESSION['form_data']); $_SESSION['success'] = '¡Registro exitoso! Inicia sesión.'; header('Location: ' . BASE_URL . '/auth/login'); exit();
            } else { $_SESSION['error'] = 'Error al crear usuario.'; header('Location: ' . BASE_URL . '/auth/register'); exit(); }
        } catch (\PDOException $e) { error_log("Error BD Reg: ".$e->getMessage()); $_SESSION['error'] = 'Error BD.'; header('Location: ' . BASE_URL . '/auth/register'); exit(); }
          catch (\Exception $e) { error_log("Error Gen Reg: ".$e->getMessage()); $_SESSION['error'] = 'Error inesperado.'; header('Location: ' . BASE_URL . '/auth/register'); exit(); }
    }
}