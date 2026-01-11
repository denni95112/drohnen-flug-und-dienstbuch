<?php
/**
 * Updater Class
 * Handles downloading and installing updates from GitHub releases
 */

class Updater {
    private $projectRoot;
    private $githubOwner;
    private $githubRepo;
    private $currentVersion;
    private $protectedPaths;
    private $backupDir;
    private $tempDir;
    private $debugMode;
    
    /**
     * Constructor
     * @param string $projectRoot Project root directory
     * @param array|null $config Optional configuration
     */
    public function __construct(string $projectRoot, ?array $config = null) {
        $this->projectRoot = realpath($projectRoot) ?: $projectRoot;
        
        // Load version info
        $versionFile = $this->projectRoot . '/includes/version.php';
        if (file_exists($versionFile)) {
            require_once $versionFile;
            $this->githubOwner = defined('GITHUB_REPO_OWNER') ? GITHUB_REPO_OWNER : '';
            $this->githubRepo = defined('GITHUB_REPO_NAME') ? GITHUB_REPO_NAME : '';
            $this->currentVersion = defined('APP_VERSION') ? APP_VERSION : '0.0.0';
        } else {
            throw new Exception('Version file not found: ' . $versionFile);
        }
        
        // Validate required constants
        if (empty($this->githubOwner) || empty($this->githubRepo)) {
            throw new Exception('GitHub repository information not found in version.php. Please define GITHUB_REPO_OWNER and GITHUB_REPO_NAME.');
        }
        
        // Override with config if provided
        if ($config) {
            $this->githubOwner = $config['github_owner'] ?? $this->githubOwner;
            $this->githubRepo = $config['github_repo'] ?? $this->githubRepo;
            $this->currentVersion = $config['current_version'] ?? $this->currentVersion;
        }
        
        // Default protected paths
        $this->protectedPaths = $config['protected_paths'] ?? [
            'config/config.php',
            'config/',
            'uploads/',
            'logs/',
            'manifest.json',
            '.git/',
            '.gitignore',
            'updater/',
            '*.sqlite',
            '*.sqlite3',
            '*.db'
        ];
        
        // Set directories
        $this->backupDir = $config['backup_dir'] ?? $this->projectRoot . '/backups';
        $this->tempDir = $config['temp_dir'] ?? sys_get_temp_dir() . '/updater_' . uniqid();
        
        // Load config to check debugMode
        $configFile = $this->projectRoot . '/config/config.php';
        if (file_exists($configFile)) {
            $appConfig = include $configFile;
            $this->debugMode = isset($appConfig['debugMode']) && $appConfig['debugMode'] === true;
        } else {
            $this->debugMode = false;
        }
        
        // Create directories if needed
        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0755, true);
        }
        if (!is_dir($this->tempDir)) {
            @mkdir($this->tempDir, 0755, true);
        }
    }
    
    /**
     * Log updater message
     * @param string $message Log message
     * @param string $level Log level (INFO, WARNING, ERROR)
     * @param array $context Additional context
     */
    private function log(string $message, string $level = 'INFO', array $context = []): void {
        $logFile = $this->projectRoot . '/logs/updater.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logMessage = "[$timestamp] [$level] $message$contextStr\n";
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Check for available updates
     * @return array Update information
     */
    public function checkForUpdates(): array {
        $this->log('Checking for updates', 'INFO', [
            'current_version' => $this->currentVersion,
            'github_repo' => $this->githubOwner . '/' . $this->githubRepo
        ]);
        
        try {
            // Always use direct API call to bypass cache and get fresh data
            // (checkGitHubVersion has 1-hour cache which might be stale)
            $url = "https://api.github.com/repos/{$this->githubOwner}/{$this->githubRepo}/releases";
            $this->log('Fetching releases from GitHub', 'INFO', ['url' => $url]);
            $response = $this->fetchUrl($url);
            
            if (!$response) {
                // Get error details
                $errorMsg = 'Failed to fetch releases from GitHub';
                $errorDetails = [];
                if (function_exists('curl_init')) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_NOBODY, true);
                    curl_exec($ch);
                    $curlError = curl_error($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if ($curlError) {
                        $errorMsg .= ' (cURL error: ' . $curlError . ')';
                        $errorDetails['curl_error'] = $curlError;
                    } elseif ($httpCode && $httpCode !== 200) {
                        $errorMsg .= ' (HTTP ' . $httpCode . ')';
                        $errorDetails['http_code'] = $httpCode;
                    }
                } elseif (!ini_get('allow_url_fopen')) {
                    $errorMsg .= ' (cURL not available and allow_url_fopen is disabled)';
                    $errorDetails['reason'] = 'no_curl_no_fopen';
                }
                
                $this->log('Failed to fetch releases', 'ERROR', array_merge([
                    'url' => $url,
                    'error' => $errorMsg
                ], $errorDetails));
                
                return [
                    'available' => false,
                    'current_version' => $this->currentVersion,
                    'latest_version' => $this->currentVersion,
                    'release_url' => '',
                    'release_notes' => '',
                    'error' => $errorMsg
                ];
            }
            
            $releases = json_decode($response, true);
            if (!is_array($releases) || empty($releases)) {
                return [
                    'available' => false,
                    'current_version' => $this->currentVersion,
                    'latest_version' => $this->currentVersion,
                    'release_url' => '',
                    'release_notes' => '',
                    'error' => 'No releases found'
                ];
            }
            
            // Find latest non-draft, non-prerelease
            $latestRelease = null;
            foreach ($releases as $release) {
                if (isset($release['draft']) && $release['draft'] === true) {
                    continue;
                }
                if (isset($release['prerelease']) && $release['prerelease'] === true) {
                    continue;
                }
                if (!isset($release['tag_name'])) {
                    continue;
                }
                $latestRelease = $release;
                break;
            }
            
            if (!$latestRelease) {
                return [
                    'available' => false,
                    'current_version' => $this->currentVersion,
                    'latest_version' => $this->currentVersion,
                    'release_url' => '',
                    'release_notes' => '',
                    'error' => 'No valid release found'
                ];
            }
            
            $latestVersion = ltrim($latestRelease['tag_name'], 'v');
            $available = version_compare($latestVersion, $this->currentVersion, '>');
            
            $this->log('Update check completed', 'INFO', [
                'current_version' => $this->currentVersion,
                'latest_version' => $latestVersion,
                'update_available' => $available
            ]);
            
            return [
                'available' => $available,
                'current_version' => $this->currentVersion,
                'latest_version' => $latestVersion,
                'release_url' => $latestRelease['html_url'] ?? "https://github.com/{$this->githubOwner}/{$this->githubRepo}/releases/latest",
                'release_notes' => $latestRelease['body'] ?? '',
                'error' => null
            ];
        } catch (Exception $e) {
            $this->log('Exception during update check', 'ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return [
                'available' => false,
                'current_version' => $this->currentVersion,
                'latest_version' => $this->currentVersion,
                'release_url' => '',
                'release_notes' => '',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Perform update to specified version
     * @param string $version Version to update to
     * @return array Update result
     */
    public function performUpdate(string $version): array {
        $this->log('Starting update process', 'INFO', [
            'target_version' => $version,
            'current_version' => $this->currentVersion
        ]);
        
        $backupPath = null;
        $zipPath = null;
        $extractPath = null;
        
        try {
            // Validate version
            if (!$this->validateVersion($version)) {
                throw new Exception('Invalid version format: ' . $version);
            }
            
            $this->log('Version validated', 'INFO', ['version' => $version]);
            
            // Download release
            $this->log('Downloading release', 'INFO', ['version' => $version]);
            $zipPath = $this->downloadRelease($version);
            if (!$zipPath || !file_exists($zipPath)) {
                throw new Exception('Failed to download release');
            }
            $this->log('Release downloaded', 'INFO', ['zip_path' => $zipPath, 'size' => filesize($zipPath)]);
            
            // Extract release
            $extractPath = $this->tempDir . '/extracted_' . uniqid();
            if (!is_dir($extractPath)) {
                @mkdir($extractPath, 0755, true);
            }
            
            $this->log('Extracting release', 'INFO', ['extract_path' => $extractPath]);
            if (!$this->extractRelease($zipPath, $extractPath)) {
                throw new Exception('Failed to extract release');
            }
            $this->log('Release extracted', 'INFO');
            
            // Create backup
            $this->log('Creating backup', 'INFO');
            $backupPath = $this->backupProtectedFiles();
            $this->log('Backup created', 'INFO', ['backup_path' => $backupPath]);
            
            // Get file lists
            $this->log('Comparing file lists', 'INFO');
            $releaseFiles = $this->getReleaseFileList($extractPath);
            $currentFiles = $this->getCurrentFileList();
            $this->log('File lists generated', 'INFO', [
                'release_files' => count($releaseFiles),
                'current_files' => count($currentFiles)
            ]);
            
            // Delete obsolete files
            $this->log('Removing obsolete files', 'INFO');
            $filesRemoved = 0;
            foreach ($currentFiles as $file) {
                if (!in_array($file, $releaseFiles)) {
                    $fullPath = $this->projectRoot . '/' . $file;
                    if (file_exists($fullPath) && is_file($fullPath)) {
                        if (@unlink($fullPath)) {
                            $filesRemoved++;
                            $this->log('Removed file', 'INFO', ['file' => $file]);
                        } else {
                            $this->log('Failed to remove file', 'WARNING', ['file' => $file]);
                        }
                    }
                }
            }
            $this->log('Obsolete files removed', 'INFO', ['count' => $filesRemoved]);
            
            // Copy new/updated files
            $this->log('Copying new/updated files', 'INFO');
            $filesUpdated = 0;
            $filesFailed = 0;
            foreach ($releaseFiles as $file) {
                $sourcePath = $extractPath . '/' . $file;
                $destPath = $this->projectRoot . '/' . $file;
                
                if (file_exists($sourcePath) && is_file($sourcePath)) {
                    $destDir = dirname($destPath);
                    if (!is_dir($destDir)) {
                        @mkdir($destDir, 0755, true);
                    }
                    
                    if (@copy($sourcePath, $destPath)) {
                        $filesUpdated++;
                        if ($filesUpdated % 10 === 0) {
                            $this->log('Progress update', 'INFO', ['files_updated' => $filesUpdated]);
                        }
                    } else {
                        $filesFailed++;
                        $this->log('Failed to copy file', 'WARNING', ['file' => $file]);
                    }
                }
            }
            $this->log('Files copied', 'INFO', [
                'success' => $filesUpdated,
                'failed' => $filesFailed
            ]);
            
            // Restore protected files
            if ($backupPath) {
                $this->log('Restoring protected files', 'INFO');
                $this->restoreFromBackup($backupPath);
                $this->log('Protected files restored', 'INFO');
            }
            
            // Cleanup
            $this->log('Cleaning up temporary files', 'INFO');
            $this->cleanup($this->tempDir);
            if ($zipPath && file_exists($zipPath)) {
                @unlink($zipPath);
            }
            
            $this->log('Update completed successfully', 'INFO', [
                'files_updated' => $filesUpdated,
                'files_removed' => $filesRemoved,
                'files_failed' => $filesFailed
            ]);
            
            return [
                'success' => true,
                'message' => 'Update completed successfully',
                'files_updated' => $filesUpdated,
                'files_removed' => $filesRemoved,
                'backup_path' => $backupPath,
                'error' => null
            ];
            
        } catch (Exception $e) {
            $this->log('Update failed', 'ERROR', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Rollback on error
            if ($backupPath) {
                try {
                    $this->log('Attempting rollback', 'INFO', ['backup_path' => $backupPath]);
                    $this->restoreFromBackup($backupPath);
                    $this->log('Rollback successful', 'INFO');
                } catch (Exception $rollbackError) {
                    $this->log('Rollback failed', 'ERROR', [
                        'error' => $rollbackError->getMessage(),
                        'file' => $rollbackError->getFile(),
                        'line' => $rollbackError->getLine()
                    ]);
                }
            }
            
            // Cleanup
            $this->log('Cleaning up after failure', 'INFO');
            $this->cleanup($this->tempDir);
            if ($zipPath && file_exists($zipPath)) {
                @unlink($zipPath);
            }
            
            return [
                'success' => false,
                'message' => 'Update failed',
                'files_updated' => 0,
                'files_removed' => 0,
                'backup_path' => $backupPath,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Download release ZIP from GitHub
     * @param string $version Version to download
     * @return string Path to downloaded ZIP file
     */
    private function downloadRelease(string $version): string {
        $zipPath = $this->tempDir . '/release_' . $version . '.zip';
        
        // Try different URL formats
        $urlFormats = $this->getReleaseZipUrlFormats($version);
        
        foreach ($urlFormats as $index => $zipUrl) {
            $this->log('Attempting to download ZIP', 'INFO', [
                'attempt' => $index + 1,
                'total' => count($urlFormats),
                'url' => $zipUrl
            ]);
            
            $content = $this->fetchUrl($zipUrl);
            if ($content) {
                $this->log('ZIP downloaded successfully', 'INFO', ['size' => strlen($content), 'url' => $zipUrl]);
                
                if (@file_put_contents($zipPath, $content) === false) {
                    $this->log('Failed to save ZIP file', 'ERROR', [
                        'path' => $zipPath,
                        'writable' => is_writable(dirname($zipPath))
                    ]);
                    throw new Exception('Failed to save downloaded ZIP to: ' . $zipPath);
                }
                
                $this->log('ZIP saved', 'INFO', ['path' => $zipPath, 'size' => filesize($zipPath)]);
                return $zipPath;
            }
            
            // Check if it's a 404 (wrong URL format) or other error
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $zipUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                if ($this->debugMode) {
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                }
                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 404) {
                    $this->log('URL returned 404, trying next format', 'WARNING', ['url' => $zipUrl]);
                    continue; // Try next URL format
                }
            }
        }
        
        // All URL formats failed
        $this->log('Failed to download ZIP from all URL formats', 'ERROR', [
            'urls_tried' => $urlFormats
        ]);
        
        throw new Exception('Failed to download release ZIP from all attempted URL formats');
    }
    
    /**
     * Get possible release ZIP URL formats
     * @param string $version Version string
     * @return array Array of possible URLs
     */
    private function getReleaseZipUrlFormats(string $version): array {
        $versionTag = $version;
        if (strpos($version, 'v') !== 0) {
            $versionTag = 'v' . $version;
        }
        
        // Try different GitHub URL formats
        return [
            // Standard format: /archive/refs/tags/v1.0.2.zip
            "https://github.com/{$this->githubOwner}/{$this->githubRepo}/archive/refs/tags/{$versionTag}.zip",
            // Alternative format: /archive/v1.0.2.zip
            "https://github.com/{$this->githubOwner}/{$this->githubRepo}/archive/{$versionTag}.zip",
            // Without 'v' prefix: /archive/refs/tags/1.0.2.zip
            "https://github.com/{$this->githubOwner}/{$this->githubRepo}/archive/refs/tags/{$version}.zip",
            // Without 'v' prefix alternative: /archive/1.0.2.zip
            "https://github.com/{$this->githubOwner}/{$this->githubRepo}/archive/{$version}.zip",
        ];
    }
    
    /**
     * Get release ZIP URL (deprecated - use getReleaseZipUrlFormats)
     * @param string $version Version string
     * @return string|null ZIP URL or null on error
     */
    private function getReleaseZipUrl(string $version): ?string {
        $formats = $this->getReleaseZipUrlFormats($version);
        return $formats[0] ?? null;
    }
        
        $this->log('ZIP downloaded successfully', 'INFO', ['size' => strlen($content)]);
        
        if (@file_put_contents($zipPath, $content) === false) {
            $this->log('Failed to save ZIP file', 'ERROR', [
                'path' => $zipPath,
                'writable' => is_writable(dirname($zipPath))
            ]);
            throw new Exception('Failed to save downloaded ZIP to: ' . $zipPath);
        }
        
        $this->log('ZIP saved', 'INFO', ['path' => $zipPath, 'size' => filesize($zipPath)]);
        
        return $zipPath;
    }
    
    /**
     * Get release ZIP URL
     * @param string $version Version string
     * @return string|null ZIP URL or null on error
     */
    private function getReleaseZipUrl(string $version): ?string {
        // GitHub releases use format: https://github.com/{owner}/{repo}/archive/refs/tags/v{version}.zip
        // or without 'v' prefix: https://github.com/{owner}/{repo}/archive/refs/tags/{version}.zip
        // Also try: https://github.com/{owner}/{repo}/archive/v{version}.zip (older format)
        $versionTag = $version;
        if (strpos($version, 'v') !== 0) {
            $versionTag = 'v' . $version;
        }
        
        // Try the standard format first
        $url = "https://github.com/{$this->githubOwner}/{$this->githubRepo}/archive/refs/tags/{$versionTag}.zip";
        $this->log('Generated ZIP URL', 'INFO', ['url' => $url, 'version' => $version, 'version_tag' => $versionTag]);
        
        return $url;
    }
    
    /**
     * Extract ZIP file
     * @param string $zipPath Path to ZIP file
     * @param string $extractPath Path to extract to
     * @return bool Success
     */
    private function extractRelease(string $zipPath, string $extractPath): bool {
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive class not available');
        }
        
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new Exception('Failed to open ZIP file');
        }
        
        // Extract all files
        $zip->extractTo($extractPath);
        $zip->close();
        
        // GitHub ZIPs have a top-level folder with repo name, move contents up
        $extractedDirs = glob($extractPath . '/*', GLOB_ONLYDIR);
        if (!empty($extractedDirs)) {
            $topLevelDir = $extractedDirs[0];
            $files = glob($topLevelDir . '/*');
            foreach ($files as $file) {
                $dest = $extractPath . '/' . basename($file);
                if (is_dir($file)) {
                    $this->copyDirectory($file, $dest);
                } else {
                    @copy($file, $dest);
                }
            }
            $this->deleteDirectory($topLevelDir);
        }
        
        return true;
    }
    
    /**
     * Get list of files in release
     * @param string $extractPath Path to extracted release
     * @return array List of relative file paths
     */
    private function getReleaseFileList(string $extractPath): array {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extractPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($extractPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath);
                
                // Filter out updater folder
                if (strpos($relativePath, 'updater/') === 0) {
                    continue;
                }
                
                $files[] = $relativePath;
            }
        }
        
        return $files;
    }
    
    /**
     * Get list of current files
     * @return array List of relative file paths
     */
    private function getCurrentFileList(): array {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->projectRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($this->projectRoot . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath);
                
                // Filter protected paths
                if ($this->isProtectedPath($relativePath)) {
                    continue;
                }
                
                $files[] = $relativePath;
            }
        }
        
        return $files;
    }
    
    /**
     * Check if path is protected
     * @param string $path Relative file path
     * @return bool True if protected
     */
    private function isProtectedPath(string $path): bool {
        foreach ($this->protectedPaths as $protected) {
            // Exact match
            if ($path === $protected) {
                return true;
            }
            
            // Directory match (ends with /)
            if (substr($protected, -1) === '/' && strpos($path, $protected) === 0) {
                return true;
            }
            
            // Pattern match (wildcard)
            if (strpos($protected, '*') !== false) {
                $pattern = str_replace('*', '.*', preg_quote($protected, '/'));
                if (preg_match('/^' . $pattern . '$/', $path)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Backup protected files
     * @return string Path to backup directory
     */
    private function backupProtectedFiles(): string {
        $backupPath = $this->backupDir . '/backup_' . date('Y-m-d_H-i-s') . '_' . uniqid();
        @mkdir($backupPath, 0755, true);
        
        foreach ($this->protectedPaths as $protected) {
            $sourcePath = $this->projectRoot . '/' . $protected;
            
            if (file_exists($sourcePath)) {
                if (is_file($sourcePath)) {
                    $destPath = $backupPath . '/' . dirname($protected);
                    if (!is_dir($destPath)) {
                        @mkdir($destPath, 0755, true);
                    }
                    @copy($sourcePath, $backupPath . '/' . $protected);
                } elseif (is_dir($sourcePath)) {
                    $this->copyDirectory($sourcePath, $backupPath . '/' . $protected);
                }
            }
        }
        
        return $backupPath;
    }
    
    /**
     * Restore from backup
     * @param string $backupPath Path to backup directory
     * @return bool Success
     */
    private function restoreFromBackup(string $backupPath): bool {
        if (!is_dir($backupPath)) {
            return false;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($backupPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($backupPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath);
                
                $destPath = $this->projectRoot . '/' . $relativePath;
                $destDir = dirname($destPath);
                
                if (!is_dir($destDir)) {
                    @mkdir($destDir, 0755, true);
                }
                
                @copy($file->getPathname(), $destPath);
            }
        }
        
        return true;
    }
    
    /**
     * Cleanup temporary files
     * @param string $dir Directory to clean
     */
    private function cleanup(string $dir): void {
        if (is_dir($dir)) {
            $this->deleteDirectory($dir);
        }
    }
    
    /**
     * Copy directory recursively
     * @param string $source Source directory
     * @param string $dest Destination directory
     */
    private function copyDirectory(string $source, string $dest): void {
        if (!is_dir($dest)) {
            @mkdir($dest, 0755, true);
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            $destPath = $dest . '/' . $iterator->getSubPathName();
            
            if ($file->isDir()) {
                if (!is_dir($destPath)) {
                    @mkdir($destPath, 0755, true);
                }
            } else {
                @copy($file->getPathname(), $destPath);
            }
        }
    }
    
    /**
     * Delete directory recursively
     * @param string $dir Directory to delete
     */
    private function deleteDirectory(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
    
    /**
     * Fetch URL content
     * @param string $url URL to fetch
     * @return string|null Content or null on error
     */
    private function fetchUrl(string $url): ?string {
        $this->log('Fetching URL', 'INFO', ['url' => $url, 'debugMode' => $this->debugMode]);
        
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'User-Agent: Drohnen-Flug-und-Dienstbuch-Updater',
                'Accept: application/vnd.github.v3+json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            
            // Skip SSL verification if debugMode is enabled
            if ($this->debugMode) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                $this->log('SSL verification disabled (debugMode)', 'INFO');
            } else {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            curl_close($ch);
            
            if ($curlError && !$this->debugMode) {
                $this->log('cURL error on first attempt', 'WARNING', [
                    'error' => $curlError,
                    'errno' => $curlErrno,
                    'http_code' => $httpCode
                ]);
                
                // If SSL verification failed, try without verification (less secure but works)
                if (strpos($curlError, 'SSL') !== false || strpos($curlError, 'certificate') !== false || $curlErrno === 60) {
                    $this->log('Retrying without SSL verification', 'INFO');
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'User-Agent: Drohnen-Flug-und-Dienstbuch-Updater',
                        'Accept: application/vnd.github.v3+json'
                    ]);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    curl_close($ch);
                }
            }
            
            if ($httpCode === 200 && $response !== false) {
                $this->log('URL fetched successfully', 'INFO', [
                    'http_code' => $httpCode,
                    'size' => strlen($response)
                ]);
                return $response;
            } else {
                $this->log('Failed to fetch URL', 'ERROR', [
                    'http_code' => $httpCode,
                    'curl_error' => $curlError,
                    'curl_errno' => $curlErrno ?? null
                ]);
            }
        } elseif (ini_get('allow_url_fopen')) {
            $this->log('Using file_get_contents (allow_url_fopen)', 'INFO');
            
            // Skip SSL verification if debugMode is enabled
            $sslVerify = !$this->debugMode;
            if ($this->debugMode) {
                $this->log('SSL verification disabled for file_get_contents (debugMode)', 'INFO');
            }
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: Drohnen-Flug-und-Dienstbuch-Updater',
                        'Accept: application/vnd.github.v3+json'
                    ],
                    'timeout' => 300
                ],
                'ssl' => [
                    'verify_peer' => $sslVerify,
                    'verify_peer_name' => $sslVerify
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            if ($response !== false) {
                $this->log('URL fetched successfully via file_get_contents', 'INFO', ['size' => strlen($response)]);
                return $response;
            }
            
            // Try without SSL verification if first attempt failed and not in debug mode
            if (!$this->debugMode) {
                $this->log('Retrying file_get_contents without SSL verification', 'INFO');
                $context = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'header' => [
                            'User-Agent: Drohnen-Flug-und-Dienstbuch-Updater',
                            'Accept: application/vnd.github.v3+json'
                        ],
                        'timeout' => 300
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false
                    ]
                ]);
                
                $response = @file_get_contents($url, false, $context);
                if ($response !== false) {
                    $this->log('URL fetched successfully via file_get_contents (no SSL)', 'INFO', ['size' => strlen($response)]);
                    return $response;
                }
            }
            
            $this->log('file_get_contents failed', 'ERROR', ['url' => $url]);
        } else {
            $this->log('No method available to fetch URL', 'ERROR', [
                'curl_available' => function_exists('curl_init'),
                'allow_url_fopen' => ini_get('allow_url_fopen')
            ]);
        }
        
        return null;
    }
    
    /**
     * Validate version string
     * @param string $version Version string
     * @return bool True if valid
     */
    private function validateVersion(string $version): bool {
        // Remove 'v' prefix if present
        $version = ltrim($version, 'v');
        // Match semantic versioning: x.y.z
        return preg_match('/^\d+\.\d+\.\d+$/', $version) === 1;
    }
}
