document.addEventListener('DOMContentLoaded', function () {
  const core = window.ucpAdminCore || {};
  const params = core.params || new URLSearchParams(window.location.search);
  const map = {
    purged: 'UltraCache: cache geleegd.',
    preloaded: 'UltraCache: cache opgewarmd.',
    import: 'UltraCache: instellingen geladen.',
    cloud_sync: 'UltraCache: cloud synchronisatie uitgevoerd.',
    db_cleanup: 'UltraCache: database opgeruimd.',
    preset: 'UltraCache: veilige start toegepast.',
    seeded: 'UltraCache: voorbeeldtaken toegevoegd.',
    jobs: 'UltraCache: wachtrij verwerkt.',
    health: 'UltraCache: controle vernieuwd.',
    onboarding: 'UltraCache: installatie afgerond.',
    maintenance: 'UltraCache: onderhoud uitgevoerd.',
    'settings-updated': (window.ucpAdmin && ucpAdmin.messages && ucpAdmin.messages.saved) ? ucpAdmin.messages.saved : 'UltraCache: instellingen opgeslagen.'
  };

  function createNotice(message) {
    const div = document.createElement('div');
    div.className = 'notice notice-success is-dismissible';
    const p = document.createElement('p');
    p.textContent = message;
    div.appendChild(p);
    return div;
  }

  for (const key in map) {
    if (params.has(key) && !document.querySelector('.notice') && (key !== 'settings-updated' || params.has('settings-updated'))) {
      const shell = document.querySelector('.ucp-wrap') || document.querySelector('.wrap');
      if (shell) {
        shell.insertBefore(createNotice(map[key]), shell.firstChild);
      }
      break;
    }
  }
});
