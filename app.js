/* TraçaBoucher — comportements mobiles
   1. Champ photo : réduction dans le navigateur + repli sur l'envoi brut
   2. Sélecteur de lots d'entrée : recherche plein écran au lieu d'une liste déroulante */
(function () {
  'use strict';

  var TAILLE_MAX = 1600;   // côté le plus long, en pixels
  var QUALITE    = 0.82;

  /* ============================================================
     1. CHAMP PHOTO
     ============================================================ */
  function initPhoto(bloc) {
    var input    = bloc.querySelector('[data-photo-input]');
    var champ    = bloc.querySelector('[data-photo-data]');
    var ouvrir   = bloc.querySelector('[data-photo-ouvrir]');
    var boutons  = bloc.querySelector('[data-photo-boutons]');
    var apercu   = bloc.querySelector('[data-photo-apercu]');
    var img      = bloc.querySelector('[data-photo-img]');
    var nom      = bloc.querySelector('[data-photo-nom]');
    var poids    = bloc.querySelector('[data-photo-poids]');
    var retirer  = bloc.querySelector('[data-photo-retirer]');
    var erreur   = bloc.querySelector('[data-photo-erreur]');
    var travail  = bloc.querySelector('[data-photo-travail]');
    if (!input || !champ) return;

    function message(el, texte) {
      if (texte) { el.textContent = texte; el.classList.remove('hidden'); }
      else { el.textContent = ''; el.classList.add('hidden'); }
    }
    function afficherErreur(texte) { message(erreur, texte); }
    function occupe(actif) {
      message(travail, actif ? 'Préparation de la photo…' : '');
      ouvrir.disabled = actif;
      ouvrir.classList.toggle('opacity-50', actif);
    }
    function poidsTexte(o) {
      return o < 1048576 ? Math.round(o / 1024) + ' Ko' : (o / 1048576).toFixed(1) + ' Mo';
    }

    function reinitialiser() {
      input.value = '';
      input.disabled = false;
      champ.value = '';
      apercu.classList.add('hidden');
      boutons.classList.remove('hidden');
      img.removeAttribute('src');
      afficherErreur('');
    }

    function montrer(nomFichier, source, tailleTexte) {
      nom.textContent = nomFichier;
      poids.textContent = tailleTexte;
      if (source) { img.src = source; img.classList.remove('hidden'); }
      else { img.classList.add('hidden'); }
      apercu.classList.remove('hidden');
      boutons.classList.add('hidden');
    }

    ouvrir.addEventListener('click', function () { afficherErreur(''); input.click(); });
    retirer.addEventListener('click', reinitialiser);

    // Appareil photo intégré : n'apparaît que si le navigateur sait le faire.
    var camera = bloc.querySelector('[data-photo-camera]');
    if (camera && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
      camera.classList.remove('hidden');
      camera.classList.add('flex');
      camera.addEventListener('click', function () {
        afficherErreur('');
        ouvrirCamera(function (dataUrl, l, h, souci) {
          if (souci) { afficherErreur(souci); return; }
          if (!dataUrl) return;
          var octets = Math.round((dataUrl.length - dataUrl.indexOf(',') - 1) * 0.75);
          champ.value = dataUrl;
          input.value = '';
          input.disabled = true;
          montrer('Photo prise à l\'instant', dataUrl, l + '×' + h + ' · ' + poidsTexte(octets));
        });
      });
    }

    input.addEventListener('change', function () {
      var f = input.files && input.files[0];
      if (!f) return;
      afficherErreur('');

      // Les PDF partent tels quels, sans passer par le canvas.
      if (f.type === 'application/pdf' || /\.pdf$/i.test(f.name)) {
        champ.value = '';
        input.disabled = false;
        montrer(f.name, null, poidsTexte(f.size));
        return;
      }

      occupe(true);
      reduire(f, function (dataUrl, largeur, hauteur) {
        occupe(false);
        if (!dataUrl) {
          // Le navigateur n'a pas su décoder l'image (HEIC sur Android, fichier
          // exotique…) : on laisse le fichier d'origine partir tel quel.
          champ.value = '';
          input.disabled = false;
          montrer(f.name, null, poidsTexte(f.size) + ' — envoi de l\'original');
          return;
        }
        var octets = Math.round((dataUrl.length - dataUrl.indexOf(',') - 1) * 0.75);
        champ.value = dataUrl;
        input.disabled = true;   // évite d'envoyer aussi l'original, souvent très lourd
        montrer(f.name, dataUrl, largeur + '×' + hauteur + ' · ' + poidsTexte(octets));
      });
    });

    // Filet de sécurité : si le champ caché est vide, le fichier doit pouvoir partir.
    var form = bloc.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        if (!champ.value && input.files && input.files.length) input.disabled = false;
      });
    }
  }

  /* Décode le fichier, le réduit, et ne rend le résultat que s'il est visiblement
     correct. Sur iOS, une photo d'appareil (12 Mpx et plus) peut faire vider le
     canvas par le système : le JPEG produit sort alors entièrement noir, faute de
     transparence. On préfère renvoyer null — l'original part tel quel. */
  function reduire(fichier, fini) {
    decoder(fichier, function (source, l, h) {
      if (!source || !l || !h) return fini(null);
      try {
        var cible = dessinerReduit(source, l, h);
        if (source.close) source.close();
        if (!cible) return fini(null);
        var data = cible.canvas.toDataURL('image/jpeg', QUALITE);
        if (data.indexOf('data:image/jpeg') !== 0) return fini(null);
        fini(data, cible.l, cible.h);
      } catch (e) {
        fini(null);
      }
    });
  }

  // createImageBitmap gère l'orientation EXIF et décode hors du fil principal ;
  // repli sur <img> pour les navigateurs plus anciens.
  function decoder(fichier, fini) {
    var rendu = false;
    function une(source, l, h) { if (!rendu) { rendu = true; fini(source, l, h); } }

    if (typeof createImageBitmap === 'function') {
      var p;
      try { p = createImageBitmap(fichier, { imageOrientation: 'from-image' }); }
      catch (e) { p = null; }
      if (p && typeof p.then === 'function') {
        p.then(function (bmp) { une(bmp, bmp.width, bmp.height); })
         .catch(function () { parImage(fichier, une); });
        setTimeout(function () { if (!rendu) parImage(fichier, une); }, 15000);
        return;
      }
    }
    parImage(fichier, une);
  }

  function parImage(fichier, fini) {
    var url = URL.createObjectURL(fichier);
    var image = new Image();
    var fait = false;
    function sortir(source, l, h) {
      if (fait) return;
      fait = true;
      clearTimeout(minuteur);
      URL.revokeObjectURL(url);
      fini(source, l, h);
    }
    var minuteur = setTimeout(function () { sortir(null); }, 15000);
    image.onerror = function () { sortir(null); };
    image.onload  = function () { sortir(image, image.naturalWidth, image.naturalHeight); };
    image.src = url;
  }

  /* Réduction par moitiés successives : un seul drawImage very grand ratio
     sature la mémoire des téléphones et rend justement des images vides. */
  function dessinerReduit(source, l, h) {
    var ratio = Math.min(1, TAILLE_MAX / Math.max(l, h));
    var cl = Math.max(1, Math.round(l * ratio));
    var ch = Math.max(1, Math.round(h * ratio));

    var courant = source, cw = l, cy = h, temporaire = null;
    while (cw > cl * 2 && cy > ch * 2) {
      var demi = neufCanvas(Math.round(cw / 2), Math.round(cy / 2));
      demi.ctx.drawImage(courant, 0, 0, demi.canvas.width, demi.canvas.height);
      if (temporaire) temporaire.width = temporaire.height = 1;   // libère l'étape précédente
      temporaire = demi.canvas;
      courant = demi.canvas; cw = demi.canvas.width; cy = demi.canvas.height;
    }

    var cible = neufCanvas(cl, ch);
    cible.ctx.drawImage(courant, 0, 0, cl, ch);
    if (temporaire) temporaire.width = temporaire.height = 1;

    if (!canvasExploitable(cible.ctx, cl, ch)) return null;
    return { canvas: cible.canvas, l: cl, h: ch };
  }

  function neufCanvas(l, h) {
    var c = document.createElement('canvas');
    c.width = l; c.height = h;
    var ctx = c.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, l, h);
    return { canvas: c, ctx: ctx };
  }

  /* Vrai/faux : le canvas contient-il autre chose qu'une surface vide ?
     Un canvas purgé par le système se relit tout noir ou tout transparent. */
  function canvasExploitable(ctx, l, h) {
    var pas = 8, vus = 0, opaques = 0, sombres = 0, mini = 255, maxi = 0;
    try {
      for (var y = 1; y < pas; y++) {
        for (var x = 1; x < pas; x++) {
          var d = ctx.getImageData(Math.floor(l * x / pas), Math.floor(h * y / pas), 1, 1).data;
          vus++;
          if (d[3] > 8) opaques++;
          var lum = 0.299 * d[0] + 0.587 * d[1] + 0.114 * d[2];
          if (lum < 8) sombres++;
          if (lum < mini) mini = lum;
          if (lum > maxi) maxi = lum;
        }
      }
    } catch (e) {
      return true;   // canvas « teinté » : on ne peut pas lire, on fait confiance
    }
    if (!vus) return false;
    if (opaques < vus) return false;              // pixels transparents → JPEG noir
    if (sombres === vus && maxi - mini < 4) return false;  // uniformément noir
    return true;
  }

  /* ============================================================
     1 bis. APPAREIL PHOTO INTÉGRÉ
     Évite l'appli caméra du système, dont le viseur reste parfois noir,
     et permet de dire précisément ce qui coince.
     ============================================================ */
  function messageCamera(e) {
    var n = (e && e.name) || '';
    if (n === 'NotAllowedError' || n === 'PermissionDeniedError') {
      return 'Accès à la caméra refusé pour ce site. Dans Chrome : appuyez sur l\'icône à gauche ' +
             'de l\'adresse, puis Autorisations > Caméra > Autoriser, et rechargez la page.';
    }
    if (n === 'NotReadableError' || n === 'TrackStartError' || n === 'AbortError') {
      return 'La caméra est déjà utilisée par une autre application. Fermez l\'appli Appareil photo ' +
             '(et toute appli de visio) depuis les applications récentes, puis réessayez.';
    }
    if (n === 'NotFoundError' || n === 'DevicesNotFoundError' || n === 'OverconstrainedError') {
      return 'Aucune caméra utilisable n\'a été détectée sur cet appareil.';
    }
    if (n === 'SecurityError') return 'La caméra exige une connexion sécurisée (https).';
    return 'Impossible de démarrer la caméra' + (n ? ' (' + n + ')' : '') + '.';
  }

  function ouvrirCamera(fini) {
    var flux = null, arriere = true, rendu = false;

    var vue = document.createElement('div');
    vue.className = 'fixed inset-0 z-[110] bg-black flex flex-col';
    vue.innerHTML =
      '<div class="flex-1 relative overflow-hidden">' +
        '<video data-video playsinline autoplay muted class="absolute inset-0 w-full h-full object-contain"></video>' +
        '<p data-alerte class="hidden absolute left-3 right-3 top-3 text-sm bg-white/95 text-[#93000a] rounded-xl px-3 py-2"></p>' +
      '</div>' +
      '<div class="flex items-center justify-between px-6 py-5 bg-black" style="padding-bottom:calc(1.25rem + env(safe-area-inset-bottom,0px))">' +
        '<button type="button" data-annuler class="text-white text-sm font-semibold px-3 py-2">Annuler</button>' +
        '<button type="button" data-declencher aria-label="Prendre la photo" ' +
                'class="w-[72px] h-[72px] rounded-full bg-white border-4 border-white/40 active:scale-95 transition"></button>' +
        '<button type="button" data-basculer aria-label="Changer de caméra" ' +
                'class="material-symbols-outlined text-white p-3">flip_camera_android</button>' +
      '</div>';
    document.body.appendChild(vue);
    document.body.style.overflow = 'hidden';

    var video   = vue.querySelector('[data-video]');
    var alerte  = vue.querySelector('[data-alerte]');
    var minuteurNoir = null;

    function prevenir(texte) {
      if (texte) { alerte.textContent = texte; alerte.classList.remove('hidden'); }
      else { alerte.classList.add('hidden'); }
    }
    function couper() {
      if (minuteurNoir) clearTimeout(minuteurNoir);
      if (flux) flux.getTracks().forEach(function (t) { t.stop(); });
      flux = null;
    }
    function fermer(dataUrl, l, h, souci) {
      if (rendu) return;
      rendu = true;
      couper();
      vue.remove();
      document.body.style.overflow = '';
      fini(dataUrl || null, l, h, souci || null);
    }

    function demarrer() {
      couper();
      prevenir('');
      var voeu = { video: { facingMode: arriere ? { ideal: 'environment' } : { ideal: 'user' },
                            width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false };
      navigator.mediaDevices.getUserMedia(voeu).then(installer).catch(function (e) {
        // Contrainte trop stricte : on retente sans préciser la caméra.
        if (e && e.name === 'OverconstrainedError') {
          navigator.mediaDevices.getUserMedia({ video: true, audio: false })
            .then(installer).catch(function (e2) { fermer(null, 0, 0, messageCamera(e2)); });
          return;
        }
        fermer(null, 0, 0, messageCamera(e));
      });
    }

    function installer(s) {
      flux = s;
      video.srcObject = s;
      var jouer = video.play();
      if (jouer && jouer.catch) jouer.catch(function () {});
      // Si l'image reste noire au bout de 2 s, on le dit au lieu de laisser croire à un bug.
      minuteurNoir = setTimeout(function () {
        var essai = saisir(320);
        if (essai && !essai.exploitable) {
          prevenir('Le viseur reste noir : la caméra est probablement occupée par une autre ' +
                   'application, ou masquée. Essayez « changer de caméra », ou fermez les autres applis.');
        }
      }, 2000);
    }

    // Capture l'image courante du flux. cote = 0 → pleine résolution réduite à TAILLE_MAX.
    function saisir(cote) {
      var l = video.videoWidth, h = video.videoHeight;
      if (!l || !h) return null;
      var max = cote || TAILLE_MAX;
      var ratio = Math.min(1, max / Math.max(l, h));
      var cl = Math.max(1, Math.round(l * ratio)), ch = Math.max(1, Math.round(h * ratio));
      var c = neufCanvas(cl, ch);
      c.ctx.drawImage(video, 0, 0, cl, ch);
      return { canvas: c.canvas, ctx: c.ctx, l: cl, h: ch,
               exploitable: canvasExploitable(c.ctx, cl, ch) };
    }

    vue.querySelector('[data-annuler]').addEventListener('click', function () { fermer(null); });
    vue.querySelector('[data-basculer]').addEventListener('click', function () {
      arriere = !arriere;
      demarrer();
    });
    vue.querySelector('[data-declencher]').addEventListener('click', function () {
      var prise = saisir(0);
      if (!prise) { prevenir('La caméra n\'a pas encore d\'image. Patientez une seconde.'); return; }
      if (!prise.exploitable) {
        prevenir('L\'image capturée est noire. Fermez les autres applications qui utilisent ' +
                 'la caméra, ou passez par « Choisir un fichier ».');
        return;
      }
      fermer(prise.canvas.toDataURL('image/jpeg', QUALITE), prise.l, prise.h);
    });

    demarrer();
  }

  /* ============================================================
     2. SÉLECTEUR DE LOTS D'ENTRÉE
     ============================================================ */
  function initLots(zone) {
    var source = document.getElementById('lots-dispo');
    var lignes = zone.querySelector('#lignes');
    var ajout  = document.getElementById('btn_ajout');
    if (!source || !lignes) return;
    var lots;
    try { lots = JSON.parse(source.textContent); } catch (e) { lots = []; }

    // Gabarit capturé avant toute modification, pour fabriquer les lignes suivantes.
    var gabarit = lignes.querySelector('.ligne');
    var gabaritHTML = gabarit ? gabarit.outerHTML : null;

    if (ajout && gabaritHTML) {
      ajout.addEventListener('click', function () {
        var hote = document.createElement('div');
        hote.innerHTML = gabaritHTML;
        var neuve = hote.firstElementChild;
        var s = neuve.querySelector('select[name="entree_id[]"]');
        var q = neuve.querySelector('input[name="entree_qte[]"]');
        if (s) { s.selectedIndex = 0; s.removeAttribute('data-branche'); }
        if (q) q.value = '';
        lignes.appendChild(neuve);
        // Sans lot en base, on garde la liste déroulante native telle quelle.
        if (lots.length) brancher(neuve);
      });
    }

    if (!lots.length) return;

    var overlay = construireOverlay();
    document.body.appendChild(overlay.racine);
    var cibleCourante = null;

    function brancher(ligne) {
      var select = ligne.querySelector('select[name="entree_id[]"]');
      if (!select || select.dataset.branche) return;
      select.dataset.branche = '1';
      select.classList.add('hidden');

      var bouton = document.createElement('button');
      bouton.type = 'button';
      bouton.className = 'flex-1 min-h-[52px] text-left px-4 py-3 rounded-xl border-2 ' +
                         'border-outline-variant text-sm active:bg-surface-container-low transition';
      select.parentNode.insertBefore(bouton, select);

      function rafraichir() {
        var v = select.value;
        if (!v) {
          bouton.innerHTML = '<span class="text-on-surface-variant">Choisir un lot d\'entrée…</span>';
          return;
        }
        var l = lots.filter(function (x) { return String(x.id) === String(v); })[0];
        bouton.innerHTML = l
          ? '<span class="lot-badge font-bold">' + echapper(l.num) + '</span>' +
            '<span class="block text-xs text-on-surface-variant">' +
            echapper(l.type) + ' · ' + echapper(l.fourn) + '</span>'
          : echapper(select.options[select.selectedIndex].text);
      }
      bouton.addEventListener('click', function () {
        cibleCourante = { select: select, rafraichir: rafraichir };
        overlay.ouvrir();
      });
      rafraichir();
    }

    zone.querySelectorAll('.ligne').forEach(brancher);

    function construireOverlay() {
      var racine = document.createElement('div');
      racine.className = 'hidden fixed inset-0 z-[100] bg-surface flex flex-col';
      racine.innerHTML =
        '<div class="flex items-center gap-2 p-3 border-b border-outline-variant">' +
          '<button type="button" data-fermer class="material-symbols-outlined p-2 rounded-full active:bg-surface-container">close</button>' +
          '<input type="search" data-recherche placeholder="N° de lot, type, fournisseur…" ' +
                 'class="flex-1 rounded-full border-outline-variant text-base">' +
        '</div>' +
        '<div data-liste class="flex-1 overflow-y-auto p-3 flex flex-col gap-2"></div>';

      var liste     = racine.querySelector('[data-liste]');
      var recherche = racine.querySelector('[data-recherche]');

      function dessiner() {
        var q = recherche.value.trim().toLowerCase();
        var vus = q
          ? lots.filter(function (l) {
              return (l.num + ' ' + l.type + ' ' + l.fourn).toLowerCase().indexOf(q) !== -1;
            })
          : lots;
        liste.innerHTML = '';
        if (!vus.length) {
          liste.innerHTML = '<p class="text-sm text-on-surface-variant text-center py-8">Aucun lot ne correspond.</p>';
          return;
        }
        vus.slice(0, 200).forEach(function (l) {
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'text-left w-full px-4 py-3 rounded-xl border border-outline-variant ' +
                        'active:bg-surface-container-low';
          b.innerHTML = '<span class="lot-badge font-bold text-primary">' + echapper(l.num) + '</span>' +
                        '<span class="block text-xs text-on-surface-variant">' +
                        echapper(l.type) + ' · ' + echapper(l.fourn) + ' · ' + echapper(l.date) + '</span>';
          b.addEventListener('click', function () {
            if (cibleCourante) {
              cibleCourante.select.value = String(l.id);
              cibleCourante.rafraichir();
            }
            fermer();
          });
          liste.appendChild(b);
        });
      }
      function ouvrir() {
        recherche.value = '';
        dessiner();
        racine.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
      }
      function fermer() {
        racine.classList.add('hidden');
        document.body.style.overflow = '';
        cibleCourante = null;
      }
      racine.querySelector('[data-fermer]').addEventListener('click', fermer);
      recherche.addEventListener('input', dessiner);
      return { racine: racine, ouvrir: ouvrir };
    }
  }

  function echapper(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  /* ============================================================
     3. PRÉ-REMPLISSAGE DEPUIS LE RÉFÉRENTIEL PRODUITS
     Choisir un produit renseigne sa DLC, son conditionnement et sa
     conservation habituels — champs qui restent modifiables.
     ============================================================ */
  function initProduits(select) {
    var infos;
    try { infos = JSON.parse(select.getAttribute('data-produits')); } catch (e) { return; }
    var form = select.closest('form');
    if (!form) return;

    select.addEventListener('change', function () {
      var p = infos[select.value];
      if (!p) return;

      var dlc = form.querySelector('input[name="dlc"]');
      if (dlc && !dlc.value && p.dlc != null) {
        var d = new Date();
        var base = form.querySelector('input[name="date_fabrication"]');
        if (base && base.value) d = new Date(base.value + 'T00:00:00');
        d.setDate(d.getDate() + p.dlc);
        dlc.value = d.getFullYear() + '-' +
                    String(d.getMonth() + 1).padStart(2, '0') + '-' +
                    String(d.getDate()).padStart(2, '0');
        var repli = form.querySelector('details');
        if (repli) repli.open = true;   // le champ est dans les options repliées
      }
      cocher(form, 'conditionnement', p.cond);
      cocher(form, 'conservation', p.consv);
    });
  }

  // Ne coche que si l'opérateur n'a rien choisi lui-même.
  function cocher(form, nom, valeur) {
    if (!valeur) return;
    var boutons = form.querySelectorAll('input[name="' + nom + '"]');
    var deja = false;
    Array.prototype.forEach.call(boutons, function (b) { if (b.checked) deja = true; });
    if (deja) return;
    Array.prototype.forEach.call(boutons, function (b) { if (b.value === valeur) b.checked = true; });
  }

  document.querySelectorAll('[data-photo]').forEach(initPhoto);
  document.querySelectorAll('[data-lots]').forEach(initLots);
  document.querySelectorAll('select[data-produits]').forEach(initProduits);
})();
