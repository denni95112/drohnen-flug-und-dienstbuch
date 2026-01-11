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
        
        // Create directories if needed
        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0755, true);
        }
        if (!is_dir($this->tempDir)) {
            @mkdir($this->tempDir, 0755, true);
        }
    }
    
    /**
     * Check for available updates
     * @return array Update information
     */
    public function checkForUpdates(): array {
        try {
            // Always use direct API call to bypass cache and get fresh data
            // (checkGitHubVersion has 1-hour cache which might be stale)
            $url = "https://api.github.com/repos/{$this->githubOwner}/{$this->githubRepo}/releases";
            $response = $this->fetchUrl($url);
            
            if (!$response) {
                // Get error details
                $errorMsg = 'Failed to fetch releases from GitHub';
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
                    } elseif ($httpCode && $httpCode !== 200) {
                        $errorMsg .= ' (HTTP ' . $httpCode . ')';
                    }
                } elseif (!ini_get('allow_url_fopen')) {
                    $errorMsg .= ' (cURL not available and allow_url_fopen is disabled)';
                }
                
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
            
            return [
                'available' => $available,
                'current_version' => $this->currentVersion,
                'latest_version' => $latestVersion,
                'release_url' => $latestRelease['html_url'] ?? "https://github.com/{$this->githubOwner}/{$this->githubRepo}/releases/latest",
                'release_notes' => $latestRelease['body'] ?? '',
                'error' => null
            ];
        } catch (Exception $e) {
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
        $backupPath = null;
        $zipPath = null;
        $extractPath = null;
        
        try {
            // Validate version
            if (!$this->validateVersion($version)) {
                throw new Exception('Invalid version format');
            }
            
            // Download release
            $zipPath = $this->downloadRelease($version);
            if (!$zipPath || !file_exists($zipPath)) {
                throw new Exception('Failed to download release');
            }
            
            // Extract release
            $extractPath = $this->tempDir . '/extracted_' . uniqid();
            if (!is_dir($extractPath)) {
                @mkdir($extractPath, 0755, true);
            }
            
            if (!$this->extractRelease($zipPath, $extractPath)) {
                throw new Exception('Failed to extract release');
            }
            
            // Create backup
            $backupPath = $this->backupProtectedFiles();
            
            // Get file lists
            $releaseFiles = $this->getReleaseFileList($extractPath);
            $currentFiles = $this->getCurrentFileList();
            
            // Delete obsolete files
            $filesRemoved = 0;
            foreach ($currentFiles as $file) {
                if (!in_array($file, $releaseFiles)) {
                    $fullPath = $this->projectRoot . '/' . $file;
                    if (file_exists($fullPath) && is_file($fullPath)) {
                        @unlink($fullPath);
                        $filesRemoved++;
                    }
                }
            }
            
            // Copy new/updated files
            $filesUpdated = 0;
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
                    }
                }
            }
            
            // Restore protected files
            if ($backupPath) {
                $this->restoreFromBackup($backupPath);
            }
            
            // Cleanup
            $this->cleanup($this->tempDir);
            if ($zipPath && file_exists($zipPath)) {
                @unlink($zipPath);
            }
            
            return [
                'success' => true,
                'message' => 'Update completed successfully',
                'files_updated' => $filesUpdated,
                'files_removed' => $filesRemoved,
                'backup_path' => $backupPath,
                'error' => null
            ];
            
        } catch (Exception $e) {
            // Rollback on error
            if ($backupPath) {
                try {
                    $this->restoreFromBackup($backupPath);
                } catch (Exception $rollbackError) {
                    // Log rollback error but don't throw
                    error_log('Rollback failed: ' . $rollbackError->getMessage());
                }
            }
            
            // Cleanup
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
        $zipUrl = $this->getReleaseZipUrl($version);
        if (!$zipUrl) {
            throw new Exception('Could not determine ZIP URL for version');
        }
        
        $zipPath = $this->tempDir . '/release_' . $version . '.zip';
        
        $content = $this->fetchUrl($zipUrl);
        if (!$content) {
            throw new Exception('Failed to download release ZIP');
        }
        
        if (@file_put_contents($zipPath, $content) === false) {
            throw new Exception('Failed to save downloaded ZIP');
        }
        
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
        $versionTag = $version;
        if (strpos($version, 'v') !== 0) {
            $versionTag = 'v' . $version;
        }
        
        return "https://github.com/{$this->githubOwner}/{$this->githubRepo}/archive/refs/tags/{$versionTag}.zip";
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
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                // If SSL verification failed, try without verification (less secure but works)
                if (strpos($curlError, 'SSL') !== false || strpos($curlError, 'certificate') !== false) {
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
                    curl_close($ch);
                }
            }
            
            if ($httpCode === 200 && $response !== false) {
                return $response;
            }
        } elseif (ini_get('allow_url_fopen')) {
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
                    'verify_peer' => true,
                    'verify_peer_name' => true
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            if ($response !== false) {
                return $response;
            }
            
            // Try without SSL verification if first attempt failed
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
                return $response;
            }
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
