(() => {
  const installButton = document.querySelector('[data-pwa-install]');
  const installHelp = document.querySelector('[data-pwa-install-help]');
  const standalone = window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', async () => {
      try {
        const manifestLink = document.querySelector('link[rel="manifest"]');
        if (!manifestLink) {
          return;
        }

        const manifestUrl = new URL(manifestLink.href, window.location.href);
        const appBase = new URL('.', manifestUrl);
        const serviceWorkerUrl = new URL('service-worker.js', appBase);
        const scope = appBase.pathname;
        const registrations = await navigator.serviceWorker.getRegistrations();

        await Promise.all(registrations
          .filter((registration) => new URL(registration.scope).pathname.includes('/A-rider/public/build/'))
          .map((registration) => registration.unregister()));

        await navigator.serviceWorker.register(serviceWorkerUrl.href, { scope });
      } catch (error) {
        console.warn('[MotoBook PWA] Service worker registration failed.', error);
      }
    });
  }

  if (!installButton || standalone) {
    return;
  }

  const isAppleMobile = /iPhone|iPad|iPod/.test(window.navigator.userAgent)
    || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
  let installPrompt = null;

  installButton.hidden = false;

  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    installPrompt = event;
    installButton.textContent = 'Install MotoBook';
  });

  installButton.addEventListener('click', async () => {
    if (installPrompt) {
      installPrompt.prompt();
      await installPrompt.userChoice;
      installPrompt = null;
      installButton.hidden = true;
      if (installHelp) {
        installHelp.hidden = true;
      }
      return;
    }

    if (installHelp) {
      installHelp.textContent = isAppleMobile
        ? 'In Safari, tap Share, choose “Add to Home Screen,” then tap Add.'
        : 'Open your browser menu and choose “Install app” or “Add to Home Screen.”';
      installHelp.hidden = false;
    }
  });

  window.addEventListener('appinstalled', () => {
    installButton.hidden = true;
    if (installHelp) {
      installHelp.hidden = true;
    }
  });
})();
