// Extracted JavaScript from index.php
if ('serviceWorker' in navigator) {
    console.log("index service")
    navigator.serviceWorker.register('/service-worker.js')
        .then((registration) => {
            console.log('ServiceWorker registered:', registration);
        })
        .catch((error) => {
            console.error('ServiceWorker registration failed:', error);
        });
}

