<?php
class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $host   = 'localhost';
            $dbname = 'gestion_locative';
            $user   = 'root';
            $pass   = '';

            try {
                self::$instance = new PDO(
                    "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                    $user, $pass,
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );
            } catch (PDOException $e) {
                die('<div style="font-family:sans-serif;padding:20px;color:red;">
                    <strong>Erreur de connexion MySQL :</strong> ' . $e->getMessage() . '
                    <br><small>Vérifie que Laragon est démarré et que la base <em>gestion_locative</em> existe.</small>
                </div>');
            }
        }
        return self::$instance;
    }
}
