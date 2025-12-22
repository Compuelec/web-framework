<?php

/**
 * Script to run the parent_page migration
 * Execute from command line: php run_migration.php
 */

// Load configuration
$configPath = __DIR__ . '/cms/config.php';
if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/cms/config.example.php';
}

if (!file_exists($configPath)) {
    die("Error: Configuration file not found. Please ensure cms/config.php or cms/config.example.php exists.\n");
}

$config = require $configPath;
$dbConfig = $config['database'] ?? [];

if (empty($dbConfig['host']) || empty($dbConfig['name'])) {
    die("Error: Database configuration is incomplete in config file.\n");
}

// Migration file path
$migrationFile = __DIR__ . '/migrations/add_parent_page_column.sql';

if (!file_exists($migrationFile)) {
    die("Error: Migration file not found: $migrationFile\n");
}

try {
    // Connect to database
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['user'] ?? 'root', $dbConfig['pass'] ?? '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database: {$dbConfig['name']}\n";
    echo "Running migration: add_parent_page_column.sql\n\n";
    
    // Read migration file
    $sql = file_get_contents($migrationFile);
    
    // Execute the migration SQL directly
    // The SQL file already has checks to prevent errors if column/index exists
    echo "Executing migration SQL...\n";
    
    try {
        $pdo->exec($sql);
        
        // Verify the column was added
        $stmt = $pdo->query("SHOW COLUMNS FROM pages LIKE 'parent_page'");
        $columnExists = $stmt->fetch();
        
        if ($columnExists) {
            echo "\n✓ Migration completed successfully!\n";
            echo "✓ The 'parent_page' column has been added to the 'pages' table.\n";
            
            // Check if index exists
            $stmt = $pdo->query("SHOW INDEX FROM pages WHERE Key_name = 'idx_parent_page'");
            $indexExists = $stmt->fetch();
            
            if ($indexExists) {
                echo "✓ The 'idx_parent_page' index has been created.\n";
            }
        } else {
            echo "\n⚠ Warning: Migration executed but column 'parent_page' was not found.\n";
            echo "Please check the database manually.\n";
        }
        
    } catch (PDOException $e) {
        // Check if error is because column already exists
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "\n✓ Column 'parent_page' already exists. Migration not needed.\n";
        } else {
            throw $e;
        }
    }
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}

