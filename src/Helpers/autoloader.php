<?php

spl_autoload_register(function ($className) {
    // Define the base directory for the namespace prefix
    $baseDir = dirname(__DIR__) . '/'; // This is the /src directory

    // Replace backslashes with forward slashes and append .php
    $file = $baseDir . str_replace('\\', '/', $className) . '.php';

    // Check if the file exists and include it
    if (file_exists($file)) {
        require_once $file;
    } else {
        // Fallback for project-specific non-namespaced classes (e.g. Database in config)
        // This is a simplified autoloader. For a more robust one, consider PSR-4.
        $projectSpecificFile = dirname(dirname(__FILE__)) . '/' . str_replace('\\', '/', $className) . '.php';
        if (file_exists($projectSpecificFile)) {
            require_once $projectSpecificFile;
        } else {
            // For classes like `Database` which is outside `src` but used by `src` classes.
            $baseProjectPath = dirname(dirname(__FILE__)); // Project root

            if ($className === 'Database') {
                $configFile = $baseProjectPath . '/config/database.php'; // Specific fix for Database class
            } else {
                // General config path (if other classes were in config and matched case)
                $configFile = $baseProjectPath . '/config/' . str_replace('\\', '/', $className) . '.php';
            }

            if (file_exists($configFile)) {
                require_once $configFile;
            } else {
                 // Log the paths it tried for the Database class specifically if it failed for Database
                if ($className === 'Database') {
                    $triedPath1 = $baseProjectPath . '/config/database.php'; // lowercase
                    $triedPath2 = $baseProjectPath . '/config/Database.php'; // original case
                    error_log("Autoloader: Could not load class Database. File not found: $file nor $projectSpecificFile nor $triedPath1 nor $triedPath2");
                } else {
                    error_log("Autoloader: Could not load class $className. File not found: $file nor $projectSpecificFile nor " . ($baseProjectPath . '/config/' . str_replace('\\', '/', $className) . '.php'));
                }
            }
        }
    }
});
?>
