<?php
class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            // Sur Railway, on utilise getenv() pour récupérer les variables du serveur
            // On garde les valeurs par défaut (localhost, root, etc.) pour ton Laragon local
            $host   = getenv('DB_HOST') ?: 'localhost';
            $port   = getenv('DB_PORT') ?: '3306';
            $dbname = getenv('DB_NAME') ?: 'gestion_locative';
            $user   = getenv('DB_USER') ?: 'root';
            $pass   = getenv('DB_PASSWORD') ?: '';

            try {
                // Ajout du port dans le DSN (important pour Railway)
                $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
                
                self::$instance = new PDO(
                    $dsn,
                    $user, 
                    $pass,
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );
            } catch (PDOException $e) {
                // Message d'erreur plus générique
                die('<div style="font-family:sans-serif;padding:20px;color:red;">
                    <strong>Erreur de connexion à la base de données :</strong> ' . $e->getMessage() . '
                </div>');
            }
        }
        return self::$instance;
    }
}
