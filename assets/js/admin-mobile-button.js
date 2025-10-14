(function () {
  const sidebar   = document.querySelector('.admin-sidebar');
  const btnOpen   = document.getElementById('btnSidebarOpen');   // hamburger in header
  const btnClose  = document.getElementById('btnSidebarToggle'); // "X" inside sidebar

  if (!sidebar) { console.error('[admin] .admin-sidebar not found'); return; }

  // Backdrop (create once)
  let backdrop = document.querySelector('.sidebar-backdrop');
  if (!backdrop) {
    backdrop = document.createElement('div');
    backdrop.className = 'sidebar-backdrop';
    document.body.appendChild(backdrop);
  }

  // Helpers
  function trapFocus(enable) {
    if (!enable) { sidebar.removeAttribute('tabindex'); return; }
    sidebar.setAttribute('tabindex', '-1'); // ensures it's focusable for a11y
    sidebar.focus({ preventScroll: true });
  }

  function openSidebar() {
    sidebar.classList.add('open');
    document.body.classList.add('sidebar-open');
    backdrop.classList.add('show');

    // Hide hamburger while open (and set ARIA)
    if (btnOpen) {
      btnOpen.classList.add('d-none');
      btnOpen.setAttribute('aria-expanded', 'true');
    }

    trapFocus(true);
    console.debug('[admin] sidebar: OPEN');
  }

  function closeSidebar() {
    sidebar.classList.remove('open');
    document.body.classList.remove('sidebar-open');
    backdrop.classList.remove('show');

    // Show hamburger again (and set ARIA)
    if (btnOpen) {
      btnOpen.classList.remove('d-none');
      btnOpen.setAttribute('aria-expanded', 'false');
      // Return focus to the control that opened it (good a11y)
      btnOpen.focus({ preventScroll: true });
    }

    trapFocus(false);
    console.debug('[admin] sidebar: CLOSE');
  }

  function toggleSidebar() {
    (sidebar.classList.contains('open') ? closeSidebar() : openSidebar());
  }

  // Wire events (click + touch for mobile)
  if (btnOpen) {
    btnOpen.addEventListener('click', openSidebar, { passive: true });
    btnOpen.addEventListener('touchstart', (e) => { e.preventDefault(); openSidebar(); }, { passive: false });
    btnOpen.setAttribute('aria-expanded', 'false');
    btnOpen.setAttribute('aria-controls', 'sidebarGroups'); // optional: points to nav container
  } else {
    // Safety: insert temp opener if missing
    const tmp = document.createElement('button');
    tmp.textContent = 'Open Menu';
    tmp.className = 'btn btn-primary position-fixed';
    tmp.style.cssText = 'bottom:1rem;right:1rem;z-index:3002';
    tmp.addEventListener('click', openSidebar);
    document.body.appendChild(tmp);
    console.warn('[admin] #btnSidebarOpen not found; inserted temp opener.');
  }

  if (btnClose) {
    btnClose.addEventListener('click', toggleSidebar, { passive: true });
    btnClose.addEventListener('touchstart', (e) => { e.preventDefault(); toggleSidebar(); }, { passive: false });
    btnClose.setAttribute('aria-controls', 'sidebarGroups');
    btnClose.setAttribute('aria-expanded', 'true');
  } else {
    console.warn('[admin] #btnSidebarToggle not found; closing via backdrop/ESC only.');
  }

  // Click outside (backdrop) + ESC to close
  backdrop.addEventListener('click', closeSidebar, { passive: true });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar();
  });

  // Prevent hidden sidebar from swallowing taps (belt-and-suspenders if CSS failed)
  // This ensures clicks pass through when the sidebar is translated off-screen.
  const ensurePointerBehavior = () => {
    const isMobile = window.matchMedia('(max-width: 991.98px)').matches;
    if (!isMobile) return;
    if (!sidebar.classList.contains('open')) {
      sidebar.style.pointerEvents = 'none';
    } else {
      sidebar.style.pointerEvents = 'auto';
    }
  };
  ensurePointerBehavior();
  const obs = new MutationObserver(ensurePointerBehavior);
  obs.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
  window.addEventListener('resize', ensurePointerBehavior, { passive: true });

  // Mark JS ready for quick diagnostics
  document.body.classList.add('admin-js-ready');
})();