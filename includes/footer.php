<?php
// Calculate base path for assets (relative to document root)
// This works whether footer.php is included from root or pages/ directory
$basePath = '';
if (strpos($_SERVER['PHP_SELF'], '/pages/') !== false) {
    $basePath = '../';
}
?>
<footer>
    <p>MIT License - Erstellt von <a href="https://github.com/denni95112">Dennis Bögner</a></p>
    <p>Version <?php echo APP_VERSION; ?> - <a href="<?php echo $basePath; ?>pages/changelog.php">Changelog</a></p>
    <p>GitHub: <a href="https://github.com/denni95112/drohnen-flug-und-dienstbuch">https://github.com/denni95112/drohnen-flug-und-dienstbuch</a></p>
    <?php include __DIR__ . '/buy_me_a_coffee.php'; ?>
</footer>
