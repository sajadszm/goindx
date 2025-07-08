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
            $srcPath = dirname(dirname(__FILE__)); // This is PROJECT_ROOT/src
            $projectRootPath = dirname($srcPath); // This is PROJECT_ROOT

            if ($className === 'Database') {
                $configFile = $projectRootPath . '/config/database.php'; // Correct path to PROJECT_ROOT/config/database.php
            } else {
                // General config path for other non-namespaced classes if any
                $configFile = $projectRootPath . '/config/' . str_replace('\\', '/', $className) . '.php';
            }

            if (file_exists($configFile)) {
                require_once $configFile;
            } else {
                 // Log the paths it tried for the Database class specifically if it failed for Database
                if ($className === 'Database') {
                    // $file is for namespaced classes: PROJECT_ROOT/src/Database.php
                    // $projectSpecificFile is for non-namespaced classes in PROJECT_ROOT: PROJECT_ROOT/Database.php
                    // $configFile is the one we just constructed: PROJECT_ROOT/config/database.php
                    $correctPathForDatabase = $projectRootPath . '/config/database.php';
                    $altPathForDatabase = $projectRootPath . '/config/Database.php'; // if case was different
                    error_log("Autoloader: Could not load class Database. Tried: $file, $projectSpecificFile, $correctPathForDatabase, $altPathForDatabase");
                } else {
                    error_log("Autoloader: Could not load class $className. File not found: $file nor $projectSpecificFile nor " . ($projectRootPath . '/config/' . str_replace('\\', '/', $className) . '.php'));
                }
            }
        }
    }
});
?>
