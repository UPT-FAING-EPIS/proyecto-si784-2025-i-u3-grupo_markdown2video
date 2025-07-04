<?php
// src/Controllers/ImageController.php
namespace Dales\Markdown2video\Controllers;

use Dales\Markdown2video\Models\ImageModel;
use PDO;

class ImageController {
    private ?PDO $pdo;
    private ?ImageModel $imageModel = null;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo;
        // Iniciar sesión si no está iniciada
        if (session_status() === PHP_SESSION_NONE) { session_start(); }

        if ($this->pdo) {
            $this->imageModel = new ImageModel($this->pdo);
        }
    }

    /**
     * Sirve una imagen desde la base de datos.
     * Ruta: /image/serve/{image_name}
     */
    public function serve(string $imageName): void {
        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || !$this->imageModel) {
            http_response_code(403); // Prohibido
            echo "Acceso denegado.";
            exit;
        }

        $userId = $_SESSION['user_id'];
        $imageName = urldecode($imageName);

        $image = $this->imageModel->getImageByNameAndUserId($imageName, $userId);

        if ($image) {
            header("Content-Type: " . $image['mime_type']);
            // Opcional: Cachear la imagen en el navegador del cliente
            header("Cache-Control: max-age=2592000"); // Cache por 30 días
            header("Pragma: public");
            echo $image['image_data'];
        } else {
            http_response_code(404);
            echo "Imagen no encontrada.";
        }
        exit;
    }
}