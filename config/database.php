<?php
class Database {
    private static ?PDO $instance = null;
    private static array $columnExistsCache = [];
    private static ?array $tableExistsCache = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            // Supporte les variables Railway avec ou sans underscore.
            $host = getenv('MYSQLHOST') ?: 'localhost';
            $port = getenv('MYSQLPORT') ?: '3306';
            $dbname =(getenv('MYSQL_DATABASE') ?: 'gestion_locative');
            $user = getenv('MYSQLUSER') ?: 'root';
            $pass = getenv('MYSQLPASSWORD') ?: '';

            try {
                $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
                self::$instance = new PDO(
                    $dsn,
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
                self::assertCoreSchema(self::$instance);
            } catch (PDOException $e) {
                die('<div style="font-family:sans-serif;padding:20px;color:red;">\n                    <strong>Erreur de connexion à la base de données :</strong> ' . $e->getMessage() . '\n                </div>');
            }
        }

        return self::$instance;
    }

    public static function tableHasColumn(string $table, string $column): bool {
        $key = strtolower($table . '.' . $column);
        if (array_key_exists($key, self::$columnExistsCache)) {
            return self::$columnExistsCache[$key];
        }

        $db = self::getInstance();
        $st = $db->prepare(
            "SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?"
        );
        $st->execute([$table, $column]);

        $exists = ((int) $st->fetchColumn()) > 0;
        self::$columnExistsCache[$key] = $exists;

        return $exists;
    }

    public static function quittancePathColumn(): string {
        return self::tableHasColumn('quittances', 'pdf_path') ? 'pdf_path' : 'fichier';
    }

    public static function quittanceHasDateEmission(): bool {
        return self::tableHasColumn('quittances', 'date_emission');
    }

    private static function assertCoreSchema(PDO $db): void {
        $required = ['maisons', 'chambres', 'locataires', 'paiements', 'quittances'];
        $missing = [];

        foreach ($required as $table) {
            if (!self::tableExists($db, $table)) {
                $missing[] = $table;
            }
        }

        if ($missing === []) {
            return;
        }

        $list = htmlspecialchars(implode(', ', $missing), ENT_QUOTES, 'UTF-8');
        die('<div style="font-family:system-ui,sans-serif;padding:20px;line-height:1.5;color:#7f1d1d;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;max-width:900px;margin:20px auto;">'
            . '<h2 style="margin:0 0 10px 0;">Base non initialisée</h2>'
            . '<p style="margin:0 0 8px 0;">Tables manquantes: <strong>' . $list . '</strong></p>'
            . '<p style="margin:0;">Importez le schéma SQL sur Railway (fichier <code>gestion_locative.sql</code> ou <code>gestion_locative.no_definer.sql</code>), puis redéployez.</p>'
            . '</div>');
    }

    private static function tableExists(PDO $db, string $table): bool {
        if (self::$tableExistsCache === null) {
            self::$tableExistsCache = [];
        }
        if (array_key_exists($table, self::$tableExistsCache)) {
            return self::$tableExistsCache[$table];
        }

        $st = $db->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        $st->execute([$table]);
        $exists = ((int) $st->fetchColumn()) > 0;
        self::$tableExistsCache[$table] = $exists;
        return $exists;
    }
}
