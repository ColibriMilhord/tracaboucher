<?php
// ============================================================
//  Contenu de l'aide, séparé de sa mise en page.
//
//  Écrit depuis le code, pas de mémoire : chaque règle énoncée ici
//  correspond à un comportement réellement programmé. Si le logiciel
//  change, c'est ce fichier qu'on met à jour — pas une documentation
//  qui vit ailleurs et finit par mentir.
//
//  Chaque chapitre : à quoi ça sert, les étapes dans l'ordre, et les
//  règles que le logiciel applique tout seul.
// ============================================================

function aide_chapitres(): array {
    return [

// ─────────────────────────────────────────────────────────────
'premiers-pas' => [
  'titre' => 'Premiers pas',
  'icone' => 'waving_hand',
  'resume' => "À quoi sert chaque écran, et dans quel ordre on s'en sert.",
  'intro' => "TraçaBoucher tient deux registres que la réglementation impose : ce qui entre "
           . "dans l'atelier, et ce qui en sort. Tout le reste en découle.",
  'etapes' => [
    ['Accueil', "Les chiffres du mois et les lots encore ouverts. C'est le point de départ."],
    ['Atelier', "Là où se passe la journée : ouvrir un lot, l'étiqueter, le clôturer."],
    ['Entrées', "Les réceptions de matière première. Une ligne par livraison."],
    ['Traça', "Pour répondre à la question « d'où vient ce produit ? » — ou l'inverse."],
    ['Exports', "Les registres à présenter en cas de contrôle, et les fichiers de la balance."],
  ],
  'regles' => [
    "Chaque écran est accessible depuis la barre du bas sur téléphone, la colonne de gauche sur ordinateur.",
    "Les écrans Produits, Paramètres, Matériel et Utilisateurs sont réservés aux administrateurs.",
  ],
  'liens' => [['index.php', 'Accueil'], ['atelier.php', 'Atelier']],
],

// ─────────────────────────────────────────────────────────────
'entree' => [
  'titre' => "1. Recevoir une matière première",
  'icone' => 'move_to_inbox',
  'resume' => "Une carcasse, un colis, une livraison de légumes : tout ce qui entre se déclare ici.",
  'intro' => "C'est le premier maillon. Sans entrée enregistrée, on ne peut pas ouvrir de "
           . "fabrication : le logiciel refuse un lot de sortie qui ne viendrait de nulle part.",
  'etapes' => [
    ['Entrées → Nouvelle entrée', "Ou le gros bouton « Nouvelle entrée » sur l'accueil."],
    ['Choisir le type', "Les pastilles colorées : jeune bovin, porc, légumes… Ce sont vos types, réglés dans Paramètres → Matières premières."],
    ['Date et température', "La température est obligatoire. L'écran rappelle la conformité transport : 4 °C ± 2 °C."],
    ['Fournisseur', "Le champ propose les fournisseurs déjà saisis : tapez les premières lettres."],
    ['Compléter si besoin', "Poids, forme (carcasse, vrac, colis), état (frais, congelé), n° de lot du fournisseur, n° d'identification de l'animal."],
    ['Origines', "Né en, élevé en, abattu en, agrément de l'abattoir : pré-remplis depuis vos paramètres, à corriger seulement pour un animal atypique."],
    ['Photo', "Facultative mais précieuse : le bon de livraison, l'étiquette de la carcasse. Réduite automatiquement."],
    ['Enregistrer', "Le numéro de lot d'entrée est attribué : par exemple JB-041026 pour un jeune bovin reçu le 4 octobre."],
  ],
  'regles' => [
    "Le numéro d'entrée est <strong>CODE-JJMMAA</strong>, le code venant du type de matière. Deux réceptions du même type le même jour donnent JB-041026 puis JB-041026-2.",
    "Type, date, température et fournisseur sont obligatoires. Le reste peut être complété plus tard.",
    "Les origines saisies ici suivent le lot jusqu'à l'étiquette du produit fini : c'est ce qui permet d'imprimer « Né en France, élevé en France, abattu en France ».",
    "Si la photo échoue, la saisie n'est pas perdue : elle est enregistrée sans, et le problème est signalé.",
  ],
  'liens' => [['entrees.php', 'Aller aux Entrées']],
],

// ─────────────────────────────────────────────────────────────
'atelier' => [
  'titre' => "2. Fabriquer : ouvrir, étiqueter, clôturer",
  'icone' => 'conveyor_belt',
  'resume' => "Le parcours normal, quand on commence par le logiciel.",
  'intro' => "Pour étiqueter, il faut le numéro de lot. On ouvre donc le lot <strong>avant</strong> "
           . "de fabriquer : le numéro existe aussitôt, on étiquette toute la journée, et on "
           . "clôture à la fin quand on connaît enfin le poids produit.",
  'etapes' => [
    ['Atelier → Ouvrir un lot', "Deux questions seulement : quel produit, et avec quelle matière première."],
    ['Le numéro apparaît', "En grand, avec le bouton Étiquette juste dessous. Par exemple 041026-1."],
    ['Étiqueter', "Autant de fois qu'il y a de barquettes. Le lot reste dans la liste toute la journée."],
    ['Ouvrir d\'autres lots', "Plusieurs lots cohabitent : 041026-1 saucisse, 041026-2 pâté, 041026-3 merguez."],
    ['Clôturer', "En fin de fabrication : la quantité réellement produite et la DLC. Le lot entre au registre."],
  ],
  'regles' => [
    "Le numéro de fabrication est <strong>JJMMAA-occurrence</strong>, avec un préfixe facultatif réglé dans Paramètres → Mon atelier.",
    "L'occurrence compte <strong>tous les lots du jour</strong>, pas par produit : le deuxième lot ouvert est -2, quel qu'il soit.",
    "La catégorie n'entre pas dans le numéro : un numéro de lot ne doit jamais changer, alors qu'une famille de produits peut être renommée.",
    "<strong>Tant qu'un lot est ouvert, il n'a pas de quantité.</strong> Ni l'étiquette ni le registre n'en affichent : c'est la balance qui pèse chaque barquette.",
    "<strong>On ne peut pas clôturer un lot sans matière première.</strong> Un registre qui dit ce qui est sorti sans dire ce qui est entré ne sert à rien en cas de rappel.",
    "Conditionnement, conservation et DLC sont déduits du produit du référentiel : ce sont ses caractéristiques, pas des questions à poser au tablier.",
    "Un lot resté ouvert d'un jour précédent remonte en tête de liste avec un bandeau : il ne disparaît pas.",
  ],
  'liens' => [['atelier.php', "Aller à l'Atelier"], ['fabrications.php', 'Voir le registre']],
],

// ─────────────────────────────────────────────────────────────
'deux-personnes' => [
  'titre' => "3. Travailler à deux dans l'atelier",
  'icone' => 'group',
  'resume' => "Ce qui se passe quand deux téléphones saisissent en même temps.",
  'intro' => "Les deux personnes peuvent ouvrir et clôturer. Le logiciel évite qu'elles se marchent dessus.",
  'etapes' => [
    ['Un collègue ouvre un lot', "Votre écran Atelier l'annonce par un bandeau, dans les quinze secondes."],
    ['Vous continuez votre saisie', "La page ne se recharge pas sous vos doigts : vous affichez le nouveau lot quand vous voulez."],
    ['Deux clôtures du même lot', "La seconde personne est prévenue que c'est déjà fait. Rien n'est écrasé en silence."],
  ],
  'regles' => [
    "Chaque lot garde le nom de qui l'a ouvert et de qui l'a clôturé.",
    "Si la clôture est refusée parce que quelqu'un est passé avant, <strong>aucune donnée n'est modifiée</strong> : la quantité du premier reste.",
  ],
  'liens' => [['atelier.php', "Aller à l'Atelier"]],
],

// ─────────────────────────────────────────────────────────────
'lot-balance' => [
  'titre' => "4. Le lot créé à la balance, pas dans le logiciel",
  'icone' => 'scale',
  'resume' => "Quand le numéro a été attribué avant, à la balance, et que les barquettes le portent déjà.",
  'intro' => "La règle est que le numéro naît dans TraçaBoucher. Mais si vous avez ouvert "
           . "041026-2 directement dans la balance, les barquettes portent déjà ce numéro : "
           . "<strong>c'est lui qui fait foi</strong>, et le registre doit s'y conformer.",
  'etapes' => [
    ['Importer les étiquettes', "Atelier → Importer des étiquettes, ou le lien en haut de l'écran Atelier."],
    ['Les étiquettes sans lot apparaissent', "Regroupées par numéro et par produit, à l'écran Rattacher."],
    ['Créer le lot avec son numéro', "Le bouton « Créer le lot 041026-2 » crée la fabrication <strong>avec le numéro du fichier</strong>, jamais avec un numéro généré."],
    ['Compléter la matière première', "Le lot apparaît dans l'Atelier avec un encadré rouge : cochez les lots d'entrée utilisés."],
    ['Clôturer', "La quantité proposée est la somme des étiquettes pesées. Plus rien à retaper."],
  ],
  'regles' => [
    "<strong>Jamais deux lots au même numéro.</strong> Si 041026-2 désigne déjà autre chose, la création est refusée en nommant le lot existant.",
    "<strong>Conflit signalé.</strong> Si le numéro du fichier désigne déjà un autre produit, un bandeau rouge le dit : le logiciel et la balance ont numéroté chacun de leur côté. Mieux vaut le voir à l'import qu'au contrôle.",
    "Un lot né d'une étiquette <strong>ne connaît pas ses matières premières</strong> : le fichier de la balance n'en dit rien. Il est marqué incomplet et ne peut pas être clôturé tant qu'elles ne sont pas cochées.",
    "Une fois le lot créé, la numérotation du logiciel le voit et saute au suivant : plus aucun risque de réémettre 041026-2.",
  ],
  'liens' => [['import_etiquettes.php', 'Importer des étiquettes'], ['atelier.php', "Aller à l'Atelier"]],
],

// ─────────────────────────────────────────────────────────────
'etiquettes' => [
  'titre' => "5. Les étiquettes",
  'icone' => 'label',
  'resume' => "Les imprimer depuis le logiciel, ou récupérer celles de la balance.",
  'intro' => "Deux choses différentes portent le même mot. L'<strong>étiquette du logiciel</strong> "
           . "est une vue imprimable du lot, pour le dépannage et l'archivage papier. Les "
           . "<strong>étiquettes de la balance</strong> sont celles posées sur les barquettes, "
           . "avec le poids ; on les récupère pour compléter le registre.",
  'etapes' => [
    ['Imprimer depuis le logiciel', "Le bouton Étiquette sur un lot ouvert, ou l'icône imprimante sur une fiche de fabrication ou d'entrée."],
    ['Récupérer celles de la balance', "Atelier → Importer des étiquettes. Quatre écrans : l'agent, le fichier, les colonnes, le rattachement."],
    ['Vérifier les colonnes', "Le logiciel les reconnaît seul. S'il se trompe, le volet « Colonnes du fichier » s'ouvre : on corrige, l'aperçu se recalcule aussitôt."],
    ['Rattacher', "Les étiquettes rejoignent leur lot. Celles qui ne correspondent à rien restent visibles."],
  ],
  'regles' => [
    "L'étiquette d'un lot <strong>ouvert n'affiche pas de quantité</strong> : elle n'est pas encore connue, et un zéro sur une étiquette réglementaire serait un mensonge.",
    "Les mentions d'origine imprimées viennent des lots d'entrée utilisés. Si ces lots n'ont pas la même origine, le logiciel le signale au lieu d'imprimer une origine fausse.",
    "<strong>Réimporter le même fichier ne crée pas de doublon</strong> : chaque étiquette porte une empreinte. Mais deux barquettes de même poids le même jour restent bien deux pesées.",
    "Les formats de fichier sont reconnus par leurs intitulés (français et espagnol — DFS est un logiciel Dibal), sinon par leur contenu, sinon à la main.",
    "Le rattachement se fait sur le numéro de lot ; à défaut sur le produit et la date. <strong>Deux lots du même produit le même jour ne sont jamais départagés automatiquement</strong> : c'est ce que l'occurrence sert à distinguer.",
  ],
  'liens' => [['import_etiquettes.php', 'Importer des étiquettes']],
],

// ─────────────────────────────────────────────────────────────
'tracabilite' => [
  'titre' => "6. Retrouver l'origine d'un produit",
  'icone' => 'account_tree',
  'resume' => "La question du contrôleur, et celle du rappel de lot.",
  'intro' => "Deux sens de lecture, selon la question posée.",
  'etapes' => [
    ['Traça', "Tapez un numéro de lot, ou un nom de produit."],
    ['Traçabilité ascendante', "Partir d'une fabrication et remonter à toutes les matières premières qui y sont entrées."],
    ['Traçabilité descendante', "Partir d'un lot d'entrée et descendre à toutes les fabrications qui en sont issues. C'est la question du rappel : « cette carcasse était douteuse, qu'en a-t-on fait ? »"],
    ['Exporter la fiche', "Le lien d'export sur chaque résultat produit un CSV complet du lot, prêt à remettre."],
  ],
  'regles' => [
    "Le lien entre entrée et fabrication est posé à l'ouverture du lot : c'est pour cela qu'on ne peut pas clôturer sans matière première.",
    "Le libellé du produit est figé dans le registre au moment de la fabrication : renommer un produit plus tard ne réécrit pas l'histoire.",
  ],
  'liens' => [['tracabilite.php', 'Aller à Traça']],
],

// ─────────────────────────────────────────────────────────────
'registres' => [
  'titre' => "7. Les registres et le contrôle",
  'icone' => 'download',
  'resume' => "Ce qu'on présente, et où le prendre.",
  'intro' => "Les exports sont ouverts à tout l'atelier : sortir un registre n'exige pas un compte administrateur.",
  'etapes' => [
    ['Exports', "Depuis la barre du bas ou la colonne de gauche."],
    ['Registre de réception', "Toutes les entrées : date, type, fournisseur, température, origines."],
    ['Registre des fabrications', "Toutes les sorties avec leurs matières premières et leurs mentions d'origine."],
    ['Fiche d\'un lot précis', "Depuis Traça, le lien d'export sur le lot."],
  ],
  'regles' => [
    "Les fichiers sont des CSV séparés par des points-virgules, en UTF-8 avec BOM : ils s'ouvrent directement dans Excel ou LibreOffice en français.",
    "Chaque écran annonce le volume avant le téléchargement : on sait ce qu'on prend.",
    "Un lot encore ouvert apparaît au registre sans quantité, marqué « à peser ».",
  ],
  'liens' => [['exports.php', 'Aller aux Exports']],
],

// ─────────────────────────────────────────────────────────────
'produits' => [
  'titre' => "8. Les produits et la balance",
  'icone' => 'inventory',
  'resume' => "Le catalogue que la balance imprime.",
  'intro' => "Le référentiel produits est la source de vérité, pour le logiciel comme pour la "
           . "balance. <strong>Ne créez jamais un article directement dans DFS</strong> : il serait "
           . "orphelin de la traçabilité.",
  'etapes' => [
    ['Produits', "Réservé aux administrateurs. Un produit = un PLU à quatre chiffres."],
    ['Renseigner', "Libellé, libellé court (celui qui tient sur l'étiquette), famille, prix au kilo, durée de vie en jours."],
    ['Conditionnement et conservation', "Barquette, sous vide, vrac ; froid positif ou congelé. Repris automatiquement à l'ouverture d'un lot."],
    ['Transmettre à la balance', "Soit l'agent le fait seul toutes les deux minutes, soit on exporte le fichier depuis Exports."],
    ['Assistant balance', "L'écran qui vérifie que tout est prêt et explique l'import dans DFS, étape par étape."],
  ],
  'regles' => [
    "Le <strong>PLU</strong> est imposé par DFS : quatre chiffres, unique.",
    "L'<strong>EAN-13</strong> est interne, préfixe 2 (circulation interne GS1 20-29) : aucune démarche GS1 tant que les produits restent en vente directe.",
    "La <strong>durée de vie en jours</strong> sert deux fois : la balance calcule la DLC, et le logiciel la propose à la clôture du lot.",
    "La <strong>classe de traçabilité</strong> « viande bovine » déclenche les mentions du règlement 1760/2000 sur l'étiquette.",
  ],
  'liens' => [['produits.php?de=parametres', 'Produits'], ['guide.php', 'Assistant balance']],
],

// ─────────────────────────────────────────────────────────────
'bio' => [
  'titre' => "9. Composition et mention bio",
  'icone' => 'eco',
  'resume' => "Ce qu'on a le droit d'écrire, et à partir de quand.",
  'intro' => "Pour les préparations — saucisses, pâtés, plats — la composition décide de ce que "
           . "l'étiquette peut annoncer.",
  'etapes' => [
    ['Produits → icône composition', "Sur un produit, l'icône qui ouvre sa recette."],
    ['Lister les ingrédients', "Par poids décroissant, comme l'exige l'étiquetage. Cocher ceux qui sont biologiques."],
    ['Préciser la nature', "Ingrédient agricole, eau, sel, ou autre non agricole."],
    ['Lire le verdict', "Le taux est calculé et la mention autorisée est affichée en clair."],
  ],
  'regles' => [
    "Le taux se calcule <strong>sur les seuls ingrédients d'origine agricole</strong> : l'eau, le sel et les additifs sont exclus des deux termes du rapport, comme le prévoit le règlement (UE) 2018/848.",
    "À partir de <strong>95 %</strong>, l'Eurofeuille est autorisée et le produit peut se dire bio.",
    "En dessous, les ingrédients bio sont signalés par un astérisque dans la liste, mais ni logo ni allégation dans la dénomination.",
    "Sans composition saisie, le produit fonctionne : il n'aura simplement aucune mention bio.",
  ],
  'liens' => [['produits.php?de=parametres', 'Produits']],
],

// ─────────────────────────────────────────────────────────────
'reglages' => [
  'titre' => "10. Les réglages à faire une fois",
  'icone' => 'settings',
  'resume' => "Ce qu'il faut avoir renseigné avant d'imprimer la première étiquette.",
  'intro' => "Cinq onglets dans Paramètres. Une pastille rouge sur un onglet signale qu'il y manque quelque chose d'obligatoire.",
  'etapes' => [
    ['Mon atelier', "Le nom de l'atelier et, si vous le souhaitez, un préfixe pour vos numéros de lot."],
    ['Étiquettes', "Origines, <strong>numéros d'agrément de l'abattoir et de votre atelier</strong>, organisme certificateur bio. L'encadré du bas montre ce que lira le client."],
    ['Matières premières', "Vos types de réception, leur code (préfixe du numéro d'entrée) et leur couleur."],
    ['Comptes', "L'état de la connexion unique et le lien vers le portail CAUSSELOT."],
    ['Système', "Mise à jour de la base, Matériel, Exports, Produits, et la version déployée."],
  ],
  'regles' => [
    "Les <strong>numéros d'agrément sont obligatoires</strong> sur les étiquettes de viande bovine : tant qu'ils manquent, l'assistant balance refuse de déclarer l'installation prête.",
    "Enregistrer depuis un onglet ne touche pas aux réglages des autres.",
    "La catégorie « Viande » sur un type de matière déclenche le contrôle de température 4 °C ± 2 °C à la réception.",
  ],
  'liens' => [['parametres.php?onglet=atelier', 'Paramètres']],
],

// ─────────────────────────────────────────────────────────────
'comptes' => [
  'titre' => "11. Les comptes et le PC de l'atelier",
  'icone' => 'devices',
  'resume' => "Qui peut se connecter, et le pont vers la balance.",
  'intro' => "Les comptes sont communs à tous les services CAUSSELOT : un seul identifiant pour le portail et pour TraçaBoucher.",
  'etapes' => [
    ['Paramètres → Comptes', "L'état de la connexion unique, en vert ou en rouge."],
    ['Créer un compte', "Sur le portail CAUSSELOT, pas ici : les gérer à deux endroits les ferait diverger."],
    ['Paramètres → Système → Matériel', "L'installation de l'agent de la balance, en quatre étapes cochées au fur et à mesure."],
    ['Vérifier', "L'écran Matériel dit quand l'agent a donné signe de vie pour la dernière fois."],
  ],
  'regles' => [
    "Le <strong>jeton</strong> donne un accès en lecture seule aux produits, rien d'autre. Il se révoque et se régénère à tout moment.",
    "L'agent n'écrit <strong>jamais</strong> dans la base de DFS : il dépose un fichier et fait des sauvegardes.",
    "Si la connexion unique est inactive, TraçaBoucher continue de fonctionner sur ses comptes locaux : l'atelier n'est jamais bloqué.",
  ],
  'liens' => [['parametres.php?onglet=comptes', 'Comptes'], ['materiel.php', 'Matériel']],
],

    ];
}

/** Les règles qui gouvernent tout, rassemblées sur une page. */
function aide_memo(): array {
    return [
        'Les numéros de lot' => [
            "Entrée : <strong>CODE-JJMMAA</strong> (JB-041026), puis -2, -3 pour la même journée.",
            "Fabrication : <strong>JJMMAA-occurrence</strong> (041026-1, -2, -3), préfixe facultatif.",
            "L'occurrence compte tous les lots du jour, pas par produit.",
            "Un numéro ne <strong>change jamais</strong> : c'est pourquoi la catégorie n'y entre pas.",
            "Le logiciel propose toujours le premier numéro libre de la journée. Supprimer le dernier lot d'un jour <strong>libère donc son numéro</strong> : si des étiquettes sont déjà parties, corrigez le lot, ne le supprimez pas.",
            "Si le numéro a été attribué à la balance, <strong>c'est lui qui fait foi</strong>.",
        ],
        'Ce que le logiciel refuse' => [
            "Ouvrir une fabrication sans matière première.",
            "Clôturer un lot dont les matières premières ne sont pas renseignées.",
            "Créer deux lots portant le même numéro.",
            "Écraser la clôture faite par un collègue quelques secondes plus tôt.",
            "Trancher entre deux lots du même produit ouverts le même jour.",
        ],
        'Ce qui est figé, ce qui ne l\'est pas' => [
            "Un lot <strong>ouvert</strong> se modifie librement, depuis l'Atelier.",
            "Un lot <strong>clôturé</strong> quitte l'Atelier : il n'y a plus rien à y faire. Il reste corrigeable depuis le registre des Fabrications, délibérément — on y va exprès.",
            "Toute personne de l'atelier peut corriger ou supprimer une fabrication au registre : ce n'est pas réservé à l'administrateur.",
            "Le libellé du produit est recopié dans le registre : le renommer plus tard ne réécrit pas l'histoire.",
            "Les réglages d'origine suivent le lot d'entrée, pas l'inverse.",
        ],
        'Les répétitions sans risque' => [
            "Réimporter le même relevé d'étiquettes : aucun doublon créé.",
            "Relancer la mise à jour de la base : les migrations déjà passées sont ignorées.",
            "Régénérer le jeton de la balance : l'ancien cesse simplement de fonctionner.",
        ],
    ];
}
