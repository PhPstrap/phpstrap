<?php
/**
 * Module Scanner - Auto-Discovery System (Clean Version)
 * Scans the modules directory and registers new modules
 * Returns data instead of echoing output
 */

function scanAndRegisterModules() {
    try {
        $pdo = getDbConnection();
        $modulesDir = '../modules';
        $registered = 0;
        $errors = [];
        $skipped = [];
        
        if (!is_dir($modulesDir)) {
            return [
                'success' => false, 
                'message' => 'Modules directory not found',
                'registered' => 0,
                'errors' => ['Modules directory does not exist: ' . $modulesDir],
                'skipped' => []
            ];
        }
        
        // Get currently registered modules
        $stmt = $pdo->query("SELECT name FROM modules");
        $existingModules = array_column($stmt->fetchAll(), 'name');
        
        // Scan modules directory
        $directories = array_filter(glob($modulesDir . '/*'), 'is_dir');
        
        foreach ($directories as $moduleDir) {
            $moduleName = basename($moduleDir);
            $manifestFile = $moduleDir . '/module.json';
            
            // Skip if already registered
            if (in_array($moduleName, $existingModules)) {
                $skipped[] = $moduleName . ' (already registered)';
                continue;
            }
            
            // Check for module.json manifest
            if (!file_exists($manifestFile)) {
                $errors[] = "Missing module.json in {$moduleName}";
                continue;
            }
            
            try {
                // Read and parse manifest
                $manifestData = json_decode(file_get_contents($manifestFile), true);
                
                if (!$manifestData) {
                    $errors[] = "Invalid JSON in {$moduleName}/module.json";
                    continue;
                }
                
                // Validate required fields
                $requiredFields = ['name', 'title', 'description', 'version'];
                foreach ($requiredFields as $field) {
                    if (empty($manifestData[$field])) {
                        $errors[] = "Missing required field '{$field}' in {$moduleName}";
                        continue 2;
                    }
                }
                
                // Register module in database
                $stmt = $pdo->prepare("
                    INSERT INTO modules (
                        name, title, description, version, required_version, author, 
                        author_url, enabled, auto_enable, dependencies, settings, 
                        hooks, permissions, install_path, namespace, priority, 
                        tags, license, is_core, is_commercial, price, status, 
                        installed_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                
                $success = $stmt->execute([
                    $manifestData['name'],
                    $manifestData['title'],
                    $manifestData['description'],
                    $manifestData['version'],
                    $manifestData['required_version'] ?? '1.0.0',
                    $manifestData['author'] ?? '',
                    $manifestData['author_url'] ?? '',
                    0, // disabled by default
                    $manifestData['auto_enable'] ?? 0,
                    json_encode($manifestData['dependencies'] ?? []),
                    json_encode($manifestData['settings'] ?? []),
                    json_encode($manifestData['hooks'] ?? []),
                    json_encode($manifestData['permissions'] ?? []),
                    $manifestData['install_path'] ?? "modules/{$moduleName}",
                    $manifestData['namespace'] ?? '',
                    $manifestData['priority'] ?? 10,
                    json_encode($manifestData['tags'] ?? []),
                    $manifestData['license'] ?? 'MIT',
                    $manifestData['is_core'] ?? 0,
                    $manifestData['is_commercial'] ?? 0,
                    $manifestData['price'] ?? 0.00,
                    'inactive'
                ]);
                
                if ($success) {
                    $registered++;
                } else {
                    $errors[] = "Failed to register {$moduleName} in database";
                }
                
            } catch (Exception $e) {
                $errors[] = "Error processing {$moduleName}: " . $e->getMessage();
            }
        }
        
        return [
            'success' => true,
            'registered' => $registered,
            'errors' => $errors,
            'skipped' => $skipped,
            'message' => "Scan completed successfully"
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Scanner error: ' . $e->getMessage(),
            'registered' => 0,
            'errors' => [$e->getMessage()],
            'skipped' => []
        ];
    }
}

/**
 * Get list of modules in filesystem (for comparison)
 */
function getFilesystemModules() {
    $modulesDir = '../modules';
    $modules = [];
    
    if (!is_dir($modulesDir)) {
        return [];
    }
    
    $directories = array_filter(glob($modulesDir . '/*'), 'is_dir');
    
    foreach ($directories as $moduleDir) {
        $moduleName = basename($moduleDir);
        $manifestFile = $moduleDir . '/module.json';
        
        $moduleInfo = [
            'name' => $moduleName,
            'path' => $moduleDir,
            'has_manifest' => file_exists($manifestFile),
            'manifest_data' => null
        ];
        
        if ($moduleInfo['has_manifest']) {
            try {
                $manifestData = json_decode(file_get_contents($manifestFile), true);
                $moduleInfo['manifest_data'] = $manifestData;
            } catch (Exception $e) {
                $moduleInfo['manifest_error'] = $e->getMessage();
            }
        }
        
        $modules[] = $moduleInfo;
    }
    
    return $modules;
}

/**
 * Check if a module exists in filesystem but not in database
 */
function findUnregisteredModules() {
    try {
        $pdo = getDbConnection();
        
        // Get registered modules
        $stmt = $pdo->query("SELECT name FROM modules");
        $registeredModules = array_column($stmt->fetchAll(), 'name');
        
        // Get filesystem modules
        $filesystemModules = getFilesystemModules();
        
        $unregistered = [];
        foreach ($filesystemModules as $module) {
            if (!in_array($module['name'], $registeredModules) && $module['has_manifest']) {
                $unregistered[] = $module;
            }
        }
        
        return $unregistered;
        
    } catch (Exception $e) {
        return [];
    }
}
?>