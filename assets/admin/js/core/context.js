(function () {
  const params = new URLSearchParams(window.location.search);
  const compatibilityTabMap = {
    cache: 'preload',
    expert: 'advanced_rules',
    advanced: 'advanced_rules',
    'advanced-rules': 'advanced_rules',
    assets: 'advanced_rules',
    addons: 'tools',
    integrations: 'tools',
    insights: 'overview',
    observability: 'overview'
  };
  const allowedTabs = new Set([
    'overview',
    'optimization',
    'media',
    'preload',
    'advanced_rules',
    'database',
    'cdn',
    'heartbeat',
    'tools'
  ]);

  function normalizeTab(tab) {
    tab = String(tab || '').trim();
    if (compatibilityTabMap[tab]) return compatibilityTabMap[tab];
    return allowedTabs.has(tab) ? tab : 'overview';
  }

  const page = params.get('page');
  const explicitTab = params.get('tab');
  const currentTab = normalizeTab(explicitTab || 'overview');

  window.ucpAdminCore = window.ucpAdminCore || {};
  window.ucpAdminCore.params = params;
  window.ucpAdminCore.page = page;
  window.ucpAdminCore.allowedTabs = allowedTabs;
  window.ucpAdminCore.normalizeTab = normalizeTab;
  window.ucpAdminCore.currentTab = currentTab;

  if (page === 'ultracache-pro') {
    try {
      if (explicitTab && allowedTabs.has(currentTab)) {
        window.localStorage.setItem('ucpCurrentTab', currentTab);
      } else {
        const rememberedTab = normalizeTab(window.localStorage.getItem('ucpCurrentTab'));
        if (!allowedTabs.has(rememberedTab)) {
          window.localStorage.removeItem('ucpCurrentTab');
        }
      }
    } catch (e) {}
  }
})();
