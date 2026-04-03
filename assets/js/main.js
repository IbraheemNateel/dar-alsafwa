(() => {
  const toggleBtn = document.getElementById('sidebarToggle');
  const overlay = document.getElementById('sidebarOverlay');

  let lastTouchAt = 0;
  let lastToggleAt = 0;
  const toggleGuardMs = 350;

  const open = () => {
    document.body.classList.add('sidebar-open');
    if (overlay) overlay.hidden = false;
  };

  const close = () => {
    document.body.classList.remove('sidebar-open');
    if (overlay) overlay.hidden = true;
  };

  const toggle = () => {
    const now = Date.now();
    if (now - lastToggleAt < toggleGuardMs) return;
    lastToggleAt = now;
    if (document.body.classList.contains('sidebar-open')) close();
    else open();
  };

  // Direct listener (most reliable). Suppress click right after touch to avoid double-toggle.
  if (toggleBtn) {
    toggleBtn.addEventListener('click', (e) => {
      if (Date.now() - lastTouchAt < 700) return;
      e.preventDefault();
      e.stopPropagation();
      toggle();
    });
  }

  // Fallback: event delegation (handles cases where button is overlapped or re-rendered)
  document.addEventListener('click', (e) => {
    if (Date.now() - lastTouchAt < 700) return;
    const target = e.target && e.target.closest ? e.target.closest('#sidebarToggle') : null;
    if (target) toggle();
  });

  // Pointer fallback (some mobile browsers rely more on pointer events)
  document.addEventListener('pointerup', (e) => {
    if (Date.now() - lastTouchAt < 700) return;
    const target = e.target && e.target.closest ? e.target.closest('#sidebarToggle') : null;
    if (target) {
      e.preventDefault();
      e.stopPropagation();
      toggle();
    }
  });

  document.addEventListener(
    'touchstart',
    (e) => {
      const target = e.target && e.target.closest ? e.target.closest('#sidebarToggle') : null;
      if (target) {
        lastTouchAt = Date.now();
        toggle();
      }
    },
    { passive: true }
  );

  if (overlay) {
    overlay.addEventListener('click', close);
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') close();
  });

  // Swipe to close (right-side drawer)
  let startX = null;
  let startY = null;
  const threshold = 60;
  const maxYDelta = 70;

  document.addEventListener(
    'touchstart',
    (e) => {
      const t = e.touches && e.touches[0];
      if (!t) return;
      startX = t.clientX;
      startY = t.clientY;
    },
    { passive: true }
  );

  document.addEventListener(
    'touchend',
    (e) => {
      if (startX == null || startY == null) return;
      const t = e.changedTouches && e.changedTouches[0];
      if (!t) return;
      const dx = t.clientX - startX;
      const dy = t.clientY - startY;

      if (document.body.classList.contains('sidebar-open')) {
        if (dx < -threshold && Math.abs(dy) < maxYDelta) close();
      }

      startX = null;
      startY = null;
    },
    { passive: true }
  );
})();
