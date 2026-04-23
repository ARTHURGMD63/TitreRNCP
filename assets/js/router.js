/* StudentLink — SPA Router */

const NAV_PAGES = ['/explore.php', '/squads.php', '/wallet.php', '/profil.php'];
const NAV_ORDER = { '/explore.php': 0, '/squads.php': 1, '/wallet.php': 2, '/profil.php': 3 };

let currentPath = location.pathname;
let isNavigating = false;

function getNavIndex(path) {
  const key = Object.keys(NAV_ORDER).find(k => path.includes(k.replace('/', '')));
  return key ? NAV_ORDER[key] : -1;
}

function currentNavIndex() {
  return getNavIndex(currentPath);
}

async function navigate(url, push = true) {
  if (isNavigating || url === currentPath) return;
  isNavigating = true;

  const targetPath = new URL(url, location.origin).pathname;
  const fromIndex = currentNavIndex();
  const toIndex = getNavIndex(targetPath);
  const dir = (toIndex >= fromIndex) ? 1 : -1;

  const shell = document.querySelector('.app-shell');
  if (!shell) { location.href = url; return; }

  // Animate out
  shell.style.transition = 'transform 220ms cubic-bezier(0.4,0,0.2,1), opacity 180ms ease';
  shell.style.transform = `translateX(${dir * -40}px)`;
  shell.style.opacity = '0';

  try {
    const res = await fetch(url, { headers: { 'X-SPA': '1' } });
    if (res.redirected) {
      location.href = res.url;
      return;
    }
    const html = await res.text();
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');

    const newShell = doc.querySelector('.app-shell');
    const newTitle = doc.querySelector('title')?.textContent || '';

    if (!newShell) { location.href = url; return; }

    // Prepare new content offscreen
    newShell.style.transform = `translateX(${dir * 40}px)`;
    newShell.style.opacity = '0';
    newShell.style.transition = 'none';

    await new Promise(r => setTimeout(r, 200));

    shell.replaceWith(newShell);
    document.title = newTitle;

    // Copy new styles if any inline <style> tags differ
    doc.querySelectorAll('style').forEach(s => {
      if (!document.head.querySelector(`style[data-page="${targetPath}"]`)) {
        const clone = s.cloneNode(true);
        clone.dataset.page = targetPath;
        document.head.appendChild(clone);
      }
    });

    // Animate in
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        newShell.style.transition = 'transform 220ms cubic-bezier(0.4,0,0.2,1), opacity 180ms ease';
        newShell.style.transform = 'translateX(0)';
        newShell.style.opacity = '1';
      });
    });

    if (push) history.pushState({ path: url }, '', url);
    currentPath = targetPath;

    // Re-init page scripts
    setTimeout(() => {
      initPageScripts();
      isNavigating = false;
    }, 240);

  } catch (e) {
    location.href = url;
    isNavigating = false;
  }
}

function initPageScripts() {
  // Re-attach all event listeners from app.js
  if (typeof window.initApp === 'function') window.initApp();
}

function interceptLinks() {
  document.addEventListener('click', e => {
    const a = e.target.closest('a[href]');
    if (!a) return;
    const href = a.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('mailto') || href.startsWith('http')) return;
    if (a.hasAttribute('download') || a.target === '_blank') return;

    // Only intercept student nav pages
    const isNavPage = NAV_PAGES.some(p => href.includes(p.replace('/', '')));
    if (!isNavPage) return;

    e.preventDefault();
    navigate(href);
  }, true);
}

window.addEventListener('popstate', e => {
  if (e.state?.path) navigate(e.state.path, false);
});

// Init
document.addEventListener('DOMContentLoaded', () => {
  history.replaceState({ path: location.href }, '', location.href);
  interceptLinks();
  // Set initial page shell style
  const shell = document.querySelector('.app-shell');
  if (shell) {
    shell.style.opacity = '1';
    shell.style.transform = 'translateX(0)';
  }
});
