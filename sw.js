/* ===================================================================
   MigraSense — sw.js (Service Worker)
   - Recibe los avisos que envía el servidor (Web Push) y los muestra aunque
     MigraSense esté cerrada: recordatorio diario, resumen semanal y alerta
     de posible migraña.
   - Al tocar una notificación abre/enfoca MigraSense en la pantalla correcta
     (el aviso diario abre el registro de síntomas prodrómicos).
   - Si el navegador cambia la suscripción por su cuenta, la renueva en el
     servidor para que los avisos no se corten.
   No guarda páginas en caché: siempre se ve la última versión de la app.
   =================================================================== */
const API = 'https://migrasence-api.onrender.com/api';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));

/* ---------- Aviso enviado por el servidor: { title, body, url, tag } ---------- */
self.addEventListener('push', event => {
  let d = {};
  try { d = event.data ? event.data.json() : {}; } catch (e) { d = { body: event.data ? event.data.text() : '' }; }
  const alerta = d.tag === 'migrasense-alerta';
  event.waitUntil(self.registration.showNotification(d.title || 'MigraSense', {
    body: d.body || '',
    icon: 'assets/img/logo-256.png',
    badge: 'assets/img/favicon-64.png',
    tag: d.tag || 'migrasense-push',
    renotify: true,                    // si llega otro aviso del mismo tipo, vuelve a sonar
    requireInteraction: alerta,        // la alerta de migraña no desaparece sola (escritorio)
    lang: 'es',
    data: { url: d.url || 'dashboard.html' }
  }));
});

/* ---------- Tocar la notificación ---------- */
self.addEventListener('notificationclick', event => {
  event.notification.close();
  const ruta = (event.notification.data && event.notification.data.url) || 'dashboard.html';
  const destino = new URL(ruta, self.registration.scope).href;
  event.waitUntil((async () => {
    const ventanas = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const w = ventanas.find(x => x.url.startsWith(self.registration.scope));
    if (!w) return self.clients.openWindow(destino);
    // Hay una ventana de MigraSense abierta: se la lleva a la pantalla del aviso.
    // navigate() falla si esa ventana aún no está controlada por este Service Worker;
    // en ese caso se le pide a la propia página que cambie (app.js escucha el mensaje).
    try { if ('focus' in w) await w.focus(); } catch (e) { /* algunos navegadores no dejan enfocar */ }
    try {
      const r = await w.navigate(destino);
      if (r) return;
    } catch (e) { /* se intenta con el mensaje */ }
    try { w.postMessage({ tipo: 'ms-ir', url: destino }); } catch (e) { return self.clients.openWindow(destino); }
  })());
});

/* ---------- El navegador renovó la suscripción: se actualiza en el servidor ---------- */
self.addEventListener('pushsubscriptionchange', event => {
  event.waitUntil((async () => {
    try {
      const anterior = event.oldSubscription;
      let nueva = event.newSubscription;
      if (!nueva && anterior && anterior.options) {
        nueva = await self.registration.pushManager.subscribe(anterior.options);   // misma clave del servidor
      }
      if (!anterior || !nueva) return;   // sin la dirección anterior: se re-suscribe al abrir la app
      const j = nueva.toJSON();
      await fetch(API + '/push/renovar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ anterior: anterior.endpoint, endpoint: j.endpoint, keys: j.keys })
      });
    } catch (e) { /* al abrir MigraSense se vuelve a suscribir sola */ }
  })());
});
