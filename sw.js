const CACHE_NAME = 'dar-safwa-v1';
const ASSETS_TO_CACHE = [
    './dashboard/index.php',
    './dashboard/daily-followup.php',
    './student/index.php',
    './assets/css/main.css',
    './assets/js/main.js',
    './assets/images/logo.jpg',
    'https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;600;700&family=Amiri:wght@400;600;700&display=swap'
];

// Install Event
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
        .then((cache) => {
            console.log('Opened cache');
            return cache.addAll(ASSETS_TO_CACHE);
        })
    );
});

// Activate Event
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

// Fetch Event (Network First, fallback to cache)
self.addEventListener('fetch', (event) => {
    // We only intercept GET reqs to our domain
    if (event.request.method !== 'GET') return;
    
    // Bypass logout page completely to avoid redirect caching issues
    if (event.request.url.includes('logout.php')) return;

    // We do Network-First so dynamic PHP updates still reflect when online
    event.respondWith(
        fetch(event.request)
        .then((response) => {
            // Cache the latest version
            if (response && response.status === 200 && response.type === 'basic') {
                const responseToCache = response.clone();
                caches.open(CACHE_NAME)
                    .then((cache) => {
                        cache.put(event.request, responseToCache);
                    });
            }
            return response;
        })
        .catch(() => {
            // If offline, return from cache ignoring query string
            return caches.match(event.request, {ignoreSearch: true})
                .then((cachedResponse) => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    // Return a basic offline message if file is not in cache
                    return new Response("أنت حالياً بدون اتصال للإنترنت، وهذه الصفحة غير متوفرة في الـ Offline Mode.", {
                        status: 503,
                        statusText: 'Service Unavailable',
                        headers: new Headers({
                            'Content-Type': 'text/plain; charset=utf-8'
                        })
                    });
                });
        })
    );
});
