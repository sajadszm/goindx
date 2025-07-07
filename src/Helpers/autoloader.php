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
            $configFile = dirname(dirname(__FILE__)) . '/config/' . str_replace('\\', '/', $className) . '.php';
            if (file_exists($configFile)) {
                require_once $configFile;
            } else {
                 error_log("Autoloader: Could not load class $className. File not found: $file nor $projectSpecificFile nor $configFile");
            }
        }
    }
});
?>
