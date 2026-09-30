/* ===================================================================
   MigraSense — sw.js (Service Worker)
   - Muestra las notificaciones de los recordatorios en móvil y escritorio
     (en Android, Chrome solo permite notificaciones vía Service Worker).
   - Al tocar una notificación abre/enfoca MigraSense en la pantalla correcta.
   - Incluye el manejador 'push' listo para cuando el backend envíe Web Push.
   No guarda nada en caché: siempre se ve la última versión de la app.
   =================================================================== */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const ruta = (event.notification.data && event.notification.data.url) || 'dashboard.html';
  const destino = new URL(ruta, self.registration.scope).href;
  event.waitUntil((async () => {
    const ventanas = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const w of ventanas) {
      if (w.url.startsWith(self.registration.scope) && 'focus' in w) {
        try { await w.navigate(destino); } catch (e) { /* si no puede navegar, solo enfoca */ }
        return w.focus();
      }
    }
    return self.clients.openWindow(destino);
  })());
});

// Futuro: notificaciones push enviadas por el servidor ({ title, body, url, tag })
self.addEventListener('push', event => {
  let d = {};
  try { d = event.data ? event.data.json() : {}; } catch (e) { d = { body: event.data ? event.data.text() : '' }; }
  event.waitUntil(self.registration.showNotification(d.title || 'MigraSense', {
    body: d.body || '', icon: 'assets/img/logo-256.png', tag: d.tag || 'migrasense-push',
    lang: 'es', data: { url: d.url || 'dashboard.html' }
  }));
});
