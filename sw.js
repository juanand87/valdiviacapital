/* sw.js — Service Worker para Web Push Notifications
 * Ubicación: /valdiviacapital/sw.js (raíz del sitio)
 *
 * Estrategia "payload-less push":
 *   1. El servidor envía un POST vacío con cabeceras VAPID firmadas.
 *   2. El SW recibe el evento push sin payload.
 *   3. El SW llama a ./ajax/ultima-hora.php para obtener el contenido.
 *   4. Muestra la notificación con el título y mensaje del servidor.
 */

self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(clients.claim());
});

self.addEventListener('push', function (event) {
    var defaultTitle = 'Valdivia Capital';
    var defaultOpts  = {
        body:               'Última hora: hay nuevas noticias para ti.',
        icon:               'https://valdiviacapital.cl/logovc.png',
        badge:              'https://valdiviacapital.cl/logovc.png',
        tag:                'vc-push',
        renotify:           true,
        requireInteraction: false,
        data:               { url: './' }
    };

    function showNotif(title, opts) {
        return self.registration.showNotification(title, opts);
    }

    /* Con payload (para uso futuro) */
    if (event.data) {
        try {
            var d = event.data.json();
            event.waitUntil(showNotif(d.titulo || defaultTitle, Object.assign({}, defaultOpts, {
                body: d.bajada || defaultOpts.body,
                data: { url: d.url || './' }
            })));
            return;
        } catch (e) { /* continuar con fetch */ }
    }

    /* Sin payload: obtener contenido del servidor */
    event.waitUntil(
        fetch('./ajax/ultima-hora.php', { cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : {}; })
            .then(function (data) {
                return showNotif(data.titulo || defaultTitle, Object.assign({}, defaultOpts, {
                    body: data.bajada || defaultOpts.body,
                    data: { url: data.url || './' }
                }));
            })
            .catch(function () {
                return showNotif(defaultTitle, defaultOpts);
            })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var target = (event.notification.data && event.notification.data.url) || './';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(function (list) {
                for (var i = 0; i < list.length; i++) {
                    if (list[i].url === target && 'focus' in list[i]) {
                        return list[i].focus();
                    }
                }
                if (clients.openWindow) return clients.openWindow(target);
            })
    );
});
