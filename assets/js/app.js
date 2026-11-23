/**
 * ScoreLive : JavaScript (vanilla, sans dépendance).
 *
 * - thème clair / sombre mémorisé dans le navigateur
 * - onglets, filtres de l'accueil, notifications
 * - rafraîchissement automatique des matchs en direct toutes les 30 s (fetch → JSON)
 *   avec clignotement de la ligne quand le score change
 * - favoris sans rechargement, recherche instantanée, service worker (PWA)
 */
(function () {
  'use strict';

  var body = document.body;
  var LIVE_URL = body.dataset.liveUrl || '';
  var SEARCH_URL = body.dataset.searchUrl || '';
  var THEME_KEY = 'scorelive-theme';

  function $(selector, root) { return (root || document).querySelector(selector); }
  function $all(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
  function csrfToken() {
    var meta = $('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  /* ------------------------------------------------------------------ */
  /* Thème clair / sombre                                                */
  /* ------------------------------------------------------------------ */
  function currentTheme() {
    return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
  }

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    var meta = $('meta[name="theme-color"]');
    if (meta) { meta.setAttribute('content', theme === 'light' ? '#f1f4f8' : '#0a0f16'); }
    $all('.theme-toggle').forEach(function (btn) {
      btn.setAttribute('aria-label', theme === 'light' ? 'Passer au thème sombre' : 'Passer au thème clair');
    });
  }

  applyTheme(currentTheme());

  document.addEventListener('click', function (event) {
    var toggle = event.target.closest('[data-theme-toggle]');
    if (!toggle) { return; }
    var next = currentTheme() === 'light' ? 'dark' : 'light';
    applyTheme(next);
    try { localStorage.setItem(THEME_KEY, next); } catch (e) { /* navigation privée */ }
  });

  /* ------------------------------------------------------------------ */
  /* Logos : image de remplacement si le chargement échoue               */
  /* ------------------------------------------------------------------ */
  function useFallback(img) {
    var fallback = img.getAttribute('data-fallback');
    if (fallback && img.getAttribute('src') !== fallback) {
      img.setAttribute('src', fallback);
    }
  }
  document.addEventListener('error', function (event) {
    if (event.target instanceof HTMLImageElement) { useFallback(event.target); }
  }, true);
  $all('img[data-fallback]').forEach(function (img) {
    if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) { useFallback(img); }
  });

  /* ------------------------------------------------------------------ */
  /* Notifications                                                       */
  /* ------------------------------------------------------------------ */
  var toastRegion = $('[data-toasts]');

  function toast(message, options) {
    options = options || {};
    if (!toastRegion || !message) { return; }
    var el = document.createElement('div');
    el.className = 'toast' + (options.type ? ' toast--' + options.type : '');
    var text = document.createElement('span');
    text.textContent = message;
    el.appendChild(text);
    if (options.action) {
      var button = document.createElement('button');
      button.type = 'button';
      button.textContent = options.action.label;
      button.addEventListener('click', options.action.onClick);
      el.appendChild(button);
    }
    toastRegion.appendChild(el);
    setTimeout(function () {
      el.classList.add('is-leaving');
      setTimeout(function () { el.remove(); }, 300);
    }, options.duration || 3800);
  }

  /* ------------------------------------------------------------------ */
  /* Onglets                                                             */
  /* ------------------------------------------------------------------ */
  $all('[data-tabs]').forEach(function (root) {
    var list = $('.tabs__list', root);
    var tabs = $all('[data-tab]', root);
    if (!list || !tabs.length) { return; }
    var names = tabs.map(function (t) { return t.dataset.tab; });

    tabs.forEach(function (tab) {
      var panel = $('[data-panel="' + tab.dataset.tab + '"]', root);
      tab.id = 'tab-' + tab.dataset.tab;
      if (panel) {
        panel.id = 'panel-' + tab.dataset.tab;
        tab.setAttribute('aria-controls', panel.id);
        panel.setAttribute('aria-labelledby', tab.id);
      }
    });

    function select(name, focus) {
      if (names.indexOf(name) === -1) { name = names[0]; }
      tabs.forEach(function (tab) {
        var active = tab.dataset.tab === name;
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
        tab.tabIndex = active ? 0 : -1;
        if (active && focus) { tab.focus(); }
      });
      $all('[data-panel]', root).forEach(function (panel) {
        panel.classList.toggle('is-active', panel.dataset.panel === name);
      });
    }

    list.addEventListener('click', function (event) {
      var tab = event.target.closest('[data-tab]');
      if (!tab) { return; }
      select(tab.dataset.tab);
      history.replaceState(null, '', location.pathname + location.search + '#' + tab.dataset.tab);
    });

    list.addEventListener('keydown', function (event) {
      var index = names.indexOf(document.activeElement && document.activeElement.dataset.tab);
      if (index === -1) { return; }
      var next = null;
      if (event.key === 'ArrowRight') { next = names[(index + 1) % names.length]; }
      if (event.key === 'ArrowLeft') { next = names[(index - 1 + names.length) % names.length]; }
      if (event.key === 'Home') { next = names[0]; }
      if (event.key === 'End') { next = names[names.length - 1]; }
      if (next) { event.preventDefault(); select(next, true); }
    });

    list.hidden = false;
    root.classList.add('is-ready');
    var hash = location.hash.replace('#', '');
    select(names.indexOf(hash) !== -1 ? hash : (root.dataset.tabsDefault || names[0]));
  });

  /* ------------------------------------------------------------------ */
  /* Filtres de l'accueil (Tous / En direct / Terminés / À venir)        */
  /* ------------------------------------------------------------------ */
  var board = $('[data-match-board]');
  var filterBar = $('[data-filters]');

  function refreshEmptyState() {
    if (!board) { return; }
    var state = board.dataset.filterState || 'all';
    var visible = $all('.match', board).filter(function (row) {
      return state === 'all' || row.dataset.state === state;
    }).length;
    var empty = $('[data-filter-empty]', board);
    if (!empty) { return; }
    empty.hidden = visible > 0;
    try {
      var texts = JSON.parse(board.dataset.emptyTexts || '{}');
      var label = $('[data-filter-empty-text]', empty);
      if (label && texts[state]) { label.textContent = texts[state]; }
    } catch (e) { /* ignore */ }
  }

  function setFilter(state) {
    if (!board || !filterBar) { return; }
    board.dataset.filterState = state;
    $all('[data-filter]', filterBar).forEach(function (chip) {
      var active = chip.dataset.filter === state;
      chip.classList.toggle('is-active', active);
      chip.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    refreshEmptyState();
  }

  function updateCounts() {
    if (!board) { return; }
    var counts = { all: 0, live: 0, finished: 0, upcoming: 0 };
    $all('.match', board).forEach(function (row) {
      counts.all++;
      if (counts[row.dataset.state] !== undefined) { counts[row.dataset.state]++; }
    });
    if (filterBar) {
      $all('[data-count]', filterBar).forEach(function (el) {
        el.textContent = counts[el.dataset.count] || 0;
      });
    }
    $all('[data-group]', board).forEach(function (group) {
      ['live', 'finished', 'upcoming'].forEach(function (state) {
        group.setAttribute('data-has-' + state, $all('.match[data-state="' + state + '"]', group).length);
      });
    });
    refreshEmptyState();
  }

  if (board && filterBar) {
    filterBar.addEventListener('click', function (event) {
      var chip = event.target.closest('[data-filter]');
      if (!chip) { return; }
      event.preventDefault();
      setFilter(chip.dataset.filter);
      var url = new URL(chip.href, location.href);
      history.replaceState(null, '', url.pathname + url.search);
    });
    var reset = $('[data-filter-reset]', board);
    if (reset) {
      reset.addEventListener('click', function (event) {
        event.preventDefault();
        setFilter('all');
        var url = new URL(reset.href, location.href);
        history.replaceState(null, '', url.pathname + url.search);
      });
    }
  }

  /* ------------------------------------------------------------------ */
  /* Rafraîchissement en direct (toutes les 30 s)                        */
  /* ------------------------------------------------------------------ */
  var statusBox = $('[data-live-status]');

  function setStatus(kind, info) {
    if (!statusBox) { return; }
    statusBox.hidden = false;
    statusBox.classList.toggle('is-loading', kind === 'loading');
    statusBox.classList.toggle('is-error', kind === 'error');
    var text = $('[data-live-status-text]', statusBox);
    if (!text) { return; }
    if (kind === 'idle') {
      text.textContent = 'Actualisation auto · 30 s';
    } else if (kind === 'ok') {
      var time = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      text.textContent = 'Mis à jour à ' + time + (info && info.stale ? ' (cache)' : '');
    } else if (kind === 'error') {
      text.textContent = 'Connexion perdue, nouvel essai…';
    } else if (kind === 'offline') {
      text.textContent = 'Hors connexion';
    }
  }

  function flash(el) {
    el.classList.remove('is-flashing');
    void el.offsetWidth; // relance l'animation
    el.classList.add('is-flashing');
    setTimeout(function () { el.classList.remove('is-flashing'); }, 3200);
  }

  var Live = {
    timer: null,
    inflight: false,
    interval: 30000,

    elements: function () { return $all('[data-match-id]'); },

    /** Un match est en cours, ou commence (ou aurait dû commencer) dans les 15 minutes. */
    needsPolling: function () {
      var now = Date.now();
      return this.elements().some(function (el) {
        if (el.dataset.state === 'live') { return true; }
        if (el.dataset.state !== 'upcoming' || !el.dataset.kickoff) { return false; }
        var kickoff = Date.parse(el.dataset.kickoff);
        return !isNaN(kickoff) && kickoff - now < 15 * 60000 && now - kickoff < 4 * 3600000;
      });
    },

    /** Délai avant le prochain coup d'envoi (moins 15 min), plafonné à 6 h. */
    nextCheckDelay: function () {
      var now = Date.now();
      var soonest = Infinity;
      this.elements().forEach(function (el) {
        if (el.dataset.state !== 'upcoming' || !el.dataset.kickoff) { return; }
        var kickoff = Date.parse(el.dataset.kickoff);
        if (!isNaN(kickoff) && kickoff > now) { soonest = Math.min(soonest, kickoff - 15 * 60000 - now); }
      });
      return soonest === Infinity ? null : Math.min(Math.max(soonest, 30000), 6 * 3600000);
    },

    start: function () {
      if (!LIVE_URL || !this.elements().length) { return; }
      document.addEventListener('visibilitychange', this.onVisibility.bind(this));
      window.addEventListener('online', function () { toast('Connexion rétablie'); Live.tick(); });
      window.addEventListener('offline', function () { setStatus('offline'); toast('Hors connexion : les scores ne sont plus mis à jour.'); });
      this.schedule(false);
    },

    schedule: function (immediate) {
      clearTimeout(this.timer);
      if (document.hidden) { return; }
      if (this.needsPolling()) {
        if (!statusBox || statusBox.hidden) { setStatus('idle'); }
        this.timer = setTimeout(this.tick.bind(this), immediate ? 0 : this.interval);
      } else {
        var delay = this.nextCheckDelay();
        if (delay !== null) { this.timer = setTimeout(this.schedule.bind(this, true), delay); }
      }
    },

    tick: function () {
      var self = this;
      this.refresh().then(function () { self.schedule(false); });
    },

    onVisibility: function () {
      if (document.hidden) {
        clearTimeout(this.timer);
      } else {
        this.schedule(true);
      }
    },

    refresh: function () {
      var self = this;
      if (this.inflight) { return Promise.resolve(); }
      this.inflight = true;
      setStatus('loading');

      var ids = [];
      this.elements().forEach(function (el) {
        if (ids.indexOf(el.dataset.matchId) === -1) { ids.push(el.dataset.matchId); }
      });
      var url = LIVE_URL + (LIVE_URL.indexOf('?') === -1 ? '?' : '&') + 'ids=' + encodeURIComponent(ids.join(','));

      return fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (!data || !data.ok) { throw new Error((data && data.message) || 'Réponse invalide'); }
          if (data.refresh) { self.interval = Math.max(15, Number(data.refresh)) * 1000; }
          self.apply(data.matches || {});
          setStatus('ok', { stale: data.stale });
        })
        .catch(function () { setStatus('error'); })
        .then(function () { self.inflight = false; });
    },

    apply: function (matches) {
      var stateChanged = false;
      var goals = 0;
      var onMatchPage = false;

      this.elements().forEach(function (el) {
        var d = matches[el.dataset.matchId];
        if (!d) { return; }
        var isPage = el.hasAttribute('data-match-page');
        var prefix = isPage ? 'scoreboard--' : 'match--';

        var homeEl = $('[data-el="home"]', el);
        var awayEl = $('[data-el="away"]', el);
        var prevHome = homeEl ? homeEl.textContent.trim() : '';
        var prevAway = awayEl ? awayEl.textContent.trim() : '';
        var nextHome = d.home === null || d.home === undefined ? '-' : String(d.home);
        var nextAway = d.away === null || d.away === undefined ? '-' : String(d.away);
        var kickoffOnly = prevHome === '-' && prevAway === '-' && nextHome === '0' && nextAway === '0';
        var scoreChanged = homeEl && awayEl && (prevHome !== nextHome || prevAway !== nextAway) && !kickoffOnly;

        if (homeEl) { homeEl.textContent = nextHome; homeEl.classList.toggle('is-winner', !!d.homeWinner); }
        if (awayEl) { awayEl.textContent = nextAway; awayEl.classList.toggle('is-winner', !!d.awayWinner); }

        var homeTeam = $('[data-el="home-team"]', el);
        var awayTeam = $('[data-el="away-team"]', el);
        if (homeTeam) { homeTeam.classList.toggle('is-winner', !!d.homeWinner); }
        if (awayTeam) { awayTeam.classList.toggle('is-winner', !!d.awayWinner); }

        var clock = $('[data-el="clock"]', el);
        if (clock) {
          clock.textContent = isPage && d.state === 'upcoming' ? d.label : d.clock;
          clock.classList.toggle('has-tick', !!d.tick);
        }
        var badge = $('[data-el="badge"]', el);
        if (badge) { badge.hidden = d.state !== 'live'; }

        var note = $('[data-el="note"]', el);
        if (note) {
          if (note.dataset.noteMode === 'full') {
            var parts = [];
            if (d.ht && d.state !== 'upcoming') { parts.push('MT ' + d.ht); }
            if (d.note) { parts.push(d.note); }
            note.textContent = parts.join(' · ');
          } else {
            note.textContent = d.note || '';
          }
        }

        if (el.dataset.state !== d.state) {
          el.classList.remove(prefix + el.dataset.state);
          el.classList.add(prefix + d.state);
          el.dataset.state = d.state;
          stateChanged = true;
        }
        if (scoreChanged) {
          flash(el);
          goals++;
          if (isPage) { onMatchPage = true; }
        }
      });

      if (stateChanged || goals) { updateCounts(); }
      if (onMatchPage) {
        toast('But ! Le score a changé.', {
          duration: 8000,
          action: { label: 'Voir le détail', onClick: function () { location.reload(); } }
        });
      } else if (goals) {
        toast(goals > 1 ? goals + ' scores mis à jour' : 'But ! Un score vient de changer.');
      }
    }
  };

  Live.start();

  /* ------------------------------------------------------------------ */
  /* Favoris (étoile) sans rechargement                                  */
  /* ------------------------------------------------------------------ */
  function setFavoriteState(form, active) {
    var button = $('button', form);
    if (!button) { return; }
    button.classList.toggle('is-active', active);
    button.setAttribute('aria-pressed', active ? 'true' : 'false');
    button.setAttribute('aria-label', active ? button.dataset.labelOn : button.dataset.labelOff);
    button.title = active ? 'Retirer des favoris' : 'Ajouter aux favoris';
    var text = $('[data-fav-text]', button);
    if (text) { text.textContent = active ? text.dataset.textOn : text.dataset.textOff; }
  }

  document.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-fav-form]');
    if (!form || !window.fetch || !window.FormData) { return; }
    event.preventDefault();
    var button = $('button', form);
    if (button.classList.contains('is-busy')) { return; }
    button.classList.add('is-busy');

    var type = $('[name="type"]', form).value;
    var id = $('[name="id"]', form).value;

    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-CSRF-Token': csrfToken() }
    })
      .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (data) {
          return { status: response.status, data: data };
        });
      })
      .then(function (result) {
        var data = result.data || {};
        if (result.status === 401 && data.login) {
          location.href = data.login + '?retour=' + encodeURIComponent(location.pathname + location.search);
          return;
        }
        if (!data.ok) { throw new Error(data.message || 'Action impossible pour le moment.'); }
        $all('[data-fav-form]').forEach(function (other) {
          if ($('[name="type"]', other).value === type && $('[name="id"]', other).value === id) {
            setFavoriteState(other, data.active);
          }
        });
        toast(data.message);
        var card = form.closest('[data-fav-card]');
        if (card && !data.active) {
          card.classList.add('is-removing');
          setTimeout(function () { card.remove(); }, 260);
        }
      })
      .catch(function (error) { toast(error.message, { type: 'error' }); })
      .then(function () { button.classList.remove('is-busy'); });
  });

  /* ------------------------------------------------------------------ */
  /* Recherche instantanée                                               */
  /* ------------------------------------------------------------------ */
  var searchInput = $('[data-search-input]');
  var searchResults = $('[data-search-results]');
  var ICONS = {
    trophy: '<path d="M7.8 4h8.4v5.2a4.2 4.2 0 0 1-8.4 0z"/><path d="M7.8 6H4.6a3.2 3.2 0 0 0 3.4 4M16.2 6h3.2a3.2 3.2 0 0 1-3.4 4M12 13.4V17M8.4 20.4h7.2M9.8 17h4.4"/>',
    shield: '<path d="M12 3l7.2 2.9v5.3c0 4.6-3.1 8.3-7.2 9.9-4.1-1.6-7.2-5.3-7.2-9.9V5.9z"/>',
    right: '<path d="M9 5.5l6.5 6.5L9 18.5"/>',
    search: '<circle cx="10.8" cy="10.8" r="6.3"/><path d="M20 20l-4.6-4.6"/>'
  };

  function svgIcon(name, extraClass) {
    var span = document.createElement('span');
    span.innerHTML = '<svg class="icon icon--' + name + (extraClass ? ' ' + extraClass : '') + '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICONS[name] + '</svg>';
    return span.firstChild;
  }

  function placeholderSrc() {
    var any = $('img[data-fallback]');
    return any ? any.getAttribute('data-fallback') : '';
  }

  function resultItem(item, imageUrl, isEmblem) {
    var li = document.createElement('li');
    var a = document.createElement('a');
    a.className = 'result';
    a.href = item.url;
    var img = document.createElement('img');
    img.className = 'crest crest--md' + (isEmblem ? ' crest--emblem' : '');
    img.width = 28;
    img.height = 28;
    img.alt = '';
    img.loading = 'lazy';
    img.referrerPolicy = 'no-referrer';
    var fallback = placeholderSrc();
    img.setAttribute('data-fallback', fallback);
    img.src = /^https?:\/\//i.test(imageUrl || '') ? imageUrl : fallback;
    var text = document.createElement('span');
    text.className = 'result__text';
    var name = document.createElement('span');
    name.className = 'result__name';
    name.textContent = item.name;
    var meta = document.createElement('span');
    meta.className = 'result__meta';
    meta.textContent = item.area || '';
    text.appendChild(name);
    text.appendChild(meta);
    a.appendChild(img);
    a.appendChild(text);
    a.appendChild(svgIcon('right', 'result__chevron'));
    li.appendChild(a);
    return li;
  }

  function resultSection(title, iconName, items, imageKey) {
    var fragment = document.createDocumentFragment();
    if (!items.length) { return fragment; }
    var heading = document.createElement('h2');
    heading.className = 'section-title';
    heading.appendChild(svgIcon(iconName));
    heading.appendChild(document.createTextNode(' ' + title));
    var list = document.createElement('ul');
    list.className = 'result-list card card--flush';
    items.forEach(function (item) { list.appendChild(resultItem(item, item[imageKey], imageKey === 'emblem')); });
    fragment.appendChild(heading);
    fragment.appendChild(list);
    return fragment;
  }

  function renderSearch(data, q) {
    searchResults.replaceChildren();
    if (!data.competitions.length && !data.teams.length) {
      var empty = document.createElement('div');
      empty.className = 'empty';
      var icon = document.createElement('div');
      icon.className = 'empty__icon';
      icon.appendChild(svgIcon('search'));
      var title = document.createElement('p');
      title.className = 'empty__title';
      title.textContent = 'Aucun résultat pour « ' + q + ' »';
      var hint = document.createElement('p');
      hint.className = 'empty__text';
      hint.textContent = 'Vérifiez l’orthographe ou essayez le nom court (« Barça », « Man City »…).';
      empty.appendChild(icon);
      empty.appendChild(title);
      empty.appendChild(hint);
      searchResults.appendChild(empty);
      return;
    }
    searchResults.appendChild(resultSection('Compétitions', 'trophy', data.competitions, 'emblem'));
    searchResults.appendChild(resultSection('Équipes', 'shield', data.teams, 'crest'));
  }

  if (searchInput && searchResults && SEARCH_URL && window.fetch) {
    var searchTimer = null;
    var controller = null;
    searchInput.addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function () {
        var q = searchInput.value.trim();
        if (q.length < 2) { return; }
        if (controller && controller.abort) { controller.abort(); }
        controller = window.AbortController ? new AbortController() : null;
        fetch(SEARCH_URL + '?q=' + encodeURIComponent(q), {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
          signal: controller ? controller.signal : undefined
        })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            if (!data || !data.ok) { return; }
            renderSearch(data, q);
            history.replaceState(null, '', location.pathname + '?q=' + encodeURIComponent(q));
          })
          .catch(function () { /* requête annulée ou réseau indisponible */ });
      }, 220);
    });
  }

  /* Raccourci clavier « / » : focus sur la recherche */
  document.addEventListener('keydown', function (event) {
    if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey) { return; }
    var tag = (document.activeElement && document.activeElement.tagName) || '';
    if (tag === 'INPUT' || tag === 'TEXTAREA') { return; }
    var input = $('[data-search-input]') || $('.topsearch__input');
    if (input && input.offsetParent !== null) { event.preventDefault(); input.focus(); }
  });

  /* ------------------------------------------------------------------ */
  /* Compte à rebours (quota API atteint) puis rechargement              */
  /* ------------------------------------------------------------------ */
  var countdown = $('[data-countdown]');
  if (countdown) {
    var remaining = parseInt(countdown.dataset.countdown, 10) || 30;
    var countdownTimer = setInterval(function () {
      remaining--;
      countdown.textContent = Math.max(0, remaining);
      if (remaining <= 0) { clearInterval(countdownTimer); location.reload(); }
    }, 1000);
  }

  /* ------------------------------------------------------------------ */
  /* PWA : service worker (contexte sécurisé uniquement : localhost/HTTPS) */
  /* ------------------------------------------------------------------ */
  if ('serviceWorker' in navigator && window.isSecureContext && body.dataset.swUrl) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(body.dataset.swUrl, { scope: body.dataset.scope || './' })
        .catch(function () { /* l'application fonctionne aussi sans service worker */ });
    });
  }
})();
