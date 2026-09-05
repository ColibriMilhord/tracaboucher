<?php
/**
 * Champ photo réutilisable.
 * Variables attendues avant l'inclusion :
 *   $photo_actuelle (?string) chemin relatif de la photo déjà enregistrée
 *   $photo_aide     (?string) texte d'aide sous le libellé
 */
$photo_actuelle = $photo_actuelle ?? null;
$photo_aide     = $photo_aide ?? 'Bon de livraison, étiquette, DLC du fournisseur…';
?>
<div class="border-t border-outline-variant pt-4" data-photo>
  <label class="block text-sm font-semibold mb-1">
    Photo <span class="text-xs font-normal text-on-surface-variant">facultatif</span>
  </label>
  <p class="text-xs text-on-surface-variant mb-3"><?= h($photo_aide) ?></p>

  <!-- Sans attribut capture : le téléphone propose lui-même Galerie / Fichiers.
       La prise de vue passe par l'appareil photo intégré à l'application (bouton
       ci-dessous), qui sait dire pourquoi la caméra ne démarre pas. -->
  <input type="file" name="photo" accept="image/*,.pdf,.heic,.heif" class="hidden" data-photo-input>
  <input type="hidden" name="photo_data" data-photo-data>

  <div class="flex flex-col sm:flex-row gap-2" data-photo-boutons>
    <button type="button" data-photo-camera
            class="hidden flex-1 min-h-[52px] rounded-xl bg-primary-container text-on-primary-container
                   items-center justify-center gap-2 text-sm font-semibold active:opacity-90 transition">
      <span class="material-symbols-outlined">photo_camera</span>Prendre une photo
    </button>
    <button type="button" data-photo-ouvrir
            class="flex-1 min-h-[52px] rounded-xl border-2 border-dashed border-outline-variant
                   flex items-center justify-center gap-2 text-sm font-semibold text-on-surface-variant
                   active:bg-surface-container-low transition">
      <span class="material-symbols-outlined">attach_file</span>Choisir un fichier
    </button>
  </div>

  <div class="hidden mt-3" data-photo-apercu>
    <div class="flex items-center gap-3 bg-surface-container-low rounded-xl p-3">
      <img alt="Aperçu" data-photo-img class="w-16 h-16 object-cover rounded-lg bg-surface-container">
      <div class="flex-1 min-w-0 text-sm">
        <div class="font-semibold" data-photo-nom></div>
        <div class="text-xs text-on-surface-variant" data-photo-poids></div>
      </div>
      <button type="button" data-photo-retirer
              class="material-symbols-outlined text-error p-2 rounded-full active:bg-error-container">delete</button>
    </div>
  </div>

  <p class="hidden mt-2 text-sm bg-error-container text-on-error-container rounded-lg px-3 py-2" data-photo-erreur></p>
  <p class="hidden mt-2 text-xs text-on-surface-variant" data-photo-travail>Préparation de la photo…</p>

  <?php if ($photo_actuelle): ?>
  <p class="text-xs mt-3">
    <a class="text-primary underline font-semibold" href="<?= UPLOAD_URL . h($photo_actuelle) ?>" target="_blank" rel="noopener">
      Voir la photo actuelle
    </a> — en ajouter une nouvelle la remplacera.
  </p>
  <?php endif ?>
</div>
