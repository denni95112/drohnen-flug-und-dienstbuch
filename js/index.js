// Extracted JavaScript from index.php
if ('serviceWorker' in navigator) {
    // Use relative path to avoid redirect issues
    const swPath = './service-worker.js';
    navigator.serviceWorker.register(swPath)
        .then((registration) => {
            console.log('ServiceWorker registered:', registration);
        })
        .catch((error) => {
            // Only log if it's not a redirect error (which is common with some server configs)
            if (!error.message.includes('redirect')) {
                console.warn('ServiceWorker registration failed:', error.message);
            }
        });
}

