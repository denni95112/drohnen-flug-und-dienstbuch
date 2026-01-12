<?php
require_once __DIR__ . '/../includes/error_reporting.php';
require_once __DIR__ . '/../includes/security_headers.php';
require __DIR__ . '/../includes/auth.php';
requireAuth();

$config = include __DIR__ . '/../config/config.php';
if (isset($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

require_once __DIR__ . '/../includes/version.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Über - <?php echo $config['navigation_title']; ?></title>
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="../css/about.css?v=<?php echo APP_VERSION; ?>">
    <link rel="manifest" href="../manifest.json">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main>
        <h1>Über</h1>
        
        <div class="about-container">
            <!-- Author Section -->
            <section class="about-section">
                <h2>Autor</h2>
                <div class="about-content">
                    <p><strong>Dennis Bögner</strong></p>
                    <p>
                        <a href="https://github.com/denni95112" target="_blank" rel="noopener noreferrer" class="github-link">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                            </svg>
                            GitHub: @denni95112
                        </a>
                    </p>
                </div>
            </section>

            <!-- Version Section -->
            <section class="about-section">
                <h2>Version</h2>
                <div class="about-content">
                    <p class="version-badge">Version <?php echo htmlspecialchars(APP_VERSION); ?></p>
                    <p>
                        <a href="changelog.php" class="changelog-link">Changelog anzeigen</a>
                    </p>
                </div>
            </section>

            <!-- Related Projects Section -->
            <section class="about-section">
                <h2>Verwandte Projekte</h2>
                <div class="about-content">
                    <div class="project-list">
                        <div class="project-item">
                            <h3>Drohnen-Einsatztagebuch</h3>
                            <p class="project-description">Einsatzdokumentation für BOS Drohneneinheiten</p>
                            <a href="https://github.com/denni95112/drohnen-einsatztagebuch" 
                               target="_blank" 
                               rel="noopener noreferrer" 
                               class="project-link">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                                GitHub Repository
                            </a>
                        </div>

                        <div class="project-item">
                            <h3>Drohnen-Flug-und-Dienstbuch</h3>
                            <p class="project-description">Ein Dashboard zur Verwaltung von Drohnenflügen, Diensten und Einsätzen für BOS</p>
                            <a href="https://github.com/denni95112/drohnen-flug-und-dienstbuch" 
                               target="_blank" 
                               rel="noopener noreferrer" 
                               class="project-link">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                                GitHub Repository
                            </a>
                        </div>

                        <div class="project-item">
                            <h3>Drone Mission Mapper</h3>
                            <p class="project-description">Coming soon - Noch nicht öffentlich verfügbar</p>
                            <span class="project-link coming-soon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                                GitHub Repository (Coming Soon)
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- License Section -->
            <section class="about-section">
                <h2>Lizenz</h2>
                <div class="about-content">
                    <p><strong>MIT License</strong></p>
                    <p class="license-text">
                        Copyright (c) 2025 Dennis Bögner
                    </p>
                    <p class="license-text">
                        Permission is hereby granted, free of charge, to any person obtaining a copy
                        of this software and associated documentation files (the "Software"), to deal
                        in the Software without restriction, including without limitation the rights
                        to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
                        copies of the Software, and to permit persons to whom the Software is
                        furnished to do so, subject to the following conditions:
                    </p>
                    <p class="license-text">
                        The above copyright notice and this permission notice shall be included in all
                        copies or substantial portions of the Software.
                    </p>
                    <p class="license-text">
                        THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
                        IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
                        FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
                        AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
                        LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
                        OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
                        SOFTWARE.
                    </p>
                </div>
            </section>

            <!-- Support Section -->
            <section class="about-section">
                <h2>Unterstützung</h2>
                <div class="about-content">
                    <p>Wenn Ihnen dieses Projekt gefällt und Sie den Entwickler unterstützen möchten:</p>
                    <?php include __DIR__ . '/../includes/buy_me_a_coffee.php'; ?>
                </div>
            </section>
        </div>
    </main>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
