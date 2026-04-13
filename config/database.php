<?php
class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            // Sur Railway, on utilise getenv() pour récupérer les variables du serveur
            // On garde les valeurs par défaut (localhost, root, etc.) pour ton Laragon local
           // On récupère les noms exacts fournis par Railway
            $host   = getenv('MYSQLHOST') ?: 'localhost';
            $port   = getenv('MYSQLPORT') ?: '3306';
            $dbname = getenv('MYSQL_DATABASE') ?: 'railway'; // Attention au "_"
            $user   = getenv('MYSQLUSER') ?: 'root';
            $pass   = getenv('MYSQLPASSWORD') ?: '';

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
