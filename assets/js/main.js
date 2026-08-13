  // Scroll reveal
  (function(){
    var els = document.querySelectorAll('.reveal');
    if(!('IntersectionObserver' in window)){els.forEach(function(e){e.classList.add('in')});return;}
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(en){ if(en.isIntersecting){ en.target.classList.add('in'); io.unobserve(en.target); } });
    },{threshold:.12, rootMargin:'0px 0px -8% 0px'});
    els.forEach(function(e){io.observe(e)});
  })();

  // Contact form (progressive enhancement over standard POST)
  (function(){
    var form = document.getElementById('contact-form');
    var status = document.getElementById('form-status');
    if(!form) return;
    form.addEventListener('submit', function(ev){
      ev.preventDefault();
      status.className = 'form-status';
      status.textContent = 'Invio in corso…';
      var data = new FormData(form);
      fetch(form.action, {method:'POST', body:data, headers:{'X-Requested-With':'fetch'}})
        .then(function(r){ return r.json().catch(function(){ return {ok:r.ok}; }); })
        .then(function(res){
          if(res && res.ok){
            status.className = 'form-status ok';
            status.textContent = res.msg || 'Grazie, messaggio inviato. Ti rispondo presto.';
            form.reset();
          } else {
            status.className = 'form-status err';
            status.textContent = (res && res.msg) || 'Qualcosa non ha funzionato. Riprova o scrivimi su LinkedIn.';
          }
        })
        .catch(function(){
          status.className = 'form-status err';
          status.textContent = 'Connessione non riuscita. Riprova o scrivimi su LinkedIn.';
        });
    });
  })();
  
  /* ── Fondello: capovolgi la cassa ─────────────────────────────
   Doppio click / doppio tap sul movimento → /caseback.html   */
(function () {
  var cassa = document.querySelector('.movement');
  if (!cassa) return;

  var DEST    = '/caseback.html';
  var ridotto = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // indizio sussurrato: cursore + tooltip nativo SVG
  cassa.style.cursor = 'pointer';
  var hint = document.createElementNS('http://www.w3.org/2000/svg', 'title');
  hint.textContent = 'gira la cassa';
  cassa.insertBefore(hint, cassa.firstChild);

  function capovolgi() {
    if (ridotto) { location.href = DEST; return; }
    document.body.style.transition = 'transform .6s ease';
    document.body.style.transformOrigin = '50% 50%';
    document.body.style.transform = 'perspective(1200px) rotateY(90deg)';
    setTimeout(function () { location.href = DEST; }, 550);
  }

  cassa.addEventListener('dblclick', function (e) {
    e.preventDefault();
    capovolgi();
  });

  var ultimoTap = 0;
  cassa.addEventListener('touchend', function (e) {
    var ora = Date.now();
    if (ora - ultimoTap < 320) { e.preventDefault(); capovolgi(); }
    ultimoTap = ora;
  }, { passive: false });
})();

/* ── Carillon: anteprime della ripetizione minuti ─────────────
   Accodare a /assets/js/main.js. Un solo Audio condiviso:
   nessuna richiesta ai server Apple finché non si preme play. */
(function () {
  var card = document.getElementById('carillon');
  if (!card) return;

  var bottoni = card.querySelectorAll('.martelletto[data-preview]');
  if (!bottoni.length) return;

  var audio = null;
  var attivo = null;   // bottone attualmente in riproduzione

  function ferma() {
    if (audio) { audio.pause(); audio.src = ''; }
    if (attivo) attivo.setAttribute('aria-pressed', 'false');
    attivo = null;
    card.classList.remove('suona');
  }

  bottoni.forEach(function (btn) {
    btn.addEventListener('click', function () {
      // stesso bottone: stop
      if (attivo === btn) { ferma(); return; }
      // altro brano in corso: si ferma prima
      ferma();

      if (!audio) {
        audio = new Audio();
        audio.preload = 'none';
        audio.addEventListener('ended', ferma);
        audio.addEventListener('error', ferma);
      }
      audio.src = btn.getAttribute('data-preview');
      var avvio = audio.play();
      if (avvio && avvio.catch) avvio.catch(ferma);

      attivo = btn;
      btn.setAttribute('aria-pressed', 'true');
      card.classList.add('suona');
    });
  });

  // uscendo dalla pagina, silenzio
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) ferma();
  });
})();

