/**
 * assets/js/push.js — Cliente de Web Push Notifications
 *
 * Registra el Service Worker y gestiona la suscripción push del usuario.
 * Muestra un toast no intrusivo para solicitar permiso tras 6 s de visita.
 *
 * Requiere en la página:
 *   <script>window.VAPID_PUBLIC_KEY = '<?php echo VAPID_PUBLIC_KEY; ?>';</script>
 */
(function () {
    'use strict';

    var STORAGE_ASKED = 'vc_push_asked';
    var COOLDOWN_MS   = 7 * 24 * 3600 * 1000;   // no volver a preguntar 7 días

    /* La página debe inyectar la clave pública VAPID como var global */
    var pubKey = (typeof window.VAPID_PUBLIC_KEY === 'string') ? window.VAPID_PUBLIC_KEY.trim() : '';
    if (!pubKey) return;   /* VAPID no configurado — no hacer nada */

    /* Verificar soporte del navegador */
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

    /* ── Helpers ─────────────────────────────────────────────── */

    /** Convierte base64url → Uint8Array (requerido por applicationServerKey) */
    function urlB64ToUint8(b64u) {
        var padding = '='.repeat((4 - b64u.length % 4) % 4);
        var base64  = (b64u + padding).replace(/-/g, '+').replace(/_/g, '/');
        var binary  = atob(base64);
        var bytes   = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
        return bytes;
    }

    /* ── Suscripción ─────────────────────────────────────────── */

    /** Suscribe al usuario y guarda el endpoint en el servidor */
    function subscribe(reg) {
        return reg.pushManager.subscribe({
            userVisibleOnly:      true,
            applicationServerKey: urlB64ToUint8(pubKey)
        })
        .then(function (sub) {
            return fetch('ajax/push-subscribe.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ action: 'subscribe', subscription: sub.toJSON() })
            });
        })
        .then(function (r) { return r.ok ? r.json() : null; })
        .catch(function (e) {
            /* NotAllowedError = el usuario rechazó; otros errores = loguear */
            if (e && e.name !== 'NotAllowedError') {
                console.warn('[Push] Error al suscribir:', e);
            }
        });
    }

    /* ── Toast de opt-in ─────────────────────────────────────── */

    function maybeShowToast(reg) {
        /* Si ya tiene permiso concedido → re-suscribir silenciosamente si hace falta */
        if (Notification.permission === 'granted') {
            reg.pushManager.getSubscription().then(function (sub) {
                if (!sub) subscribe(reg);
            });
            return;
        }

        /* Si el usuario bloqueó notificaciones → no insistir */
        if (Notification.permission === 'denied') return;

        /* Si se preguntó recientemente → respetar el cooldown */
        var asked = localStorage.getItem(STORAGE_ASKED);
        if (asked && (Date.now() - parseInt(asked, 10)) < COOLDOWN_MS) return;

        var toast    = document.getElementById('push-toast');
        var btnAllow = document.getElementById('push-allow');
        var btnDism  = document.getElementById('push-dismiss');
        if (!toast || !btnAllow || !btnDism) return;

        /* Mostrar tras 6 segundos */
        var timer = setTimeout(function () { toast.classList.add('show'); }, 6000);

        btnAllow.addEventListener('click', function () {
            clearTimeout(timer);
            toast.classList.remove('show');
            localStorage.setItem(STORAGE_ASKED, Date.now().toString());
            Notification.requestPermission().then(function (perm) {
                if (perm === 'granted') subscribe(reg);
            });
        }, { once: true });

        btnDism.addEventListener('click', function () {
            clearTimeout(timer);
            toast.classList.remove('show');
            localStorage.setItem(STORAGE_ASKED, Date.now().toString());
        }, { once: true });
    }

    /* ── Registro del Service Worker ─────────────────────────── */

    navigator.serviceWorker.register('sw.js')
        .then(function (reg) {
            maybeShowToast(reg);
        })
        .catch(function (e) {
            if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
                console.warn('[Push] SW requiere HTTPS:', e);
            }
        });

}());
