# PROJETBERNABE - Application de gestion d'une entreprise de depannage

Application web (HTML, CSS, PHP, MySQL) couplee a la base `entreprise_depannage`
du TP Base de donnees.

## Installation sous WampServer

1. Copier le dossier `PROJETBERNABE` dans `C:\wamp\www\` (ou `C:\wamp64\www\`).
2. Demarrer WampServer (icone verte).
3. Ouvrir phpMyAdmin (`http://localhost/phpmyadmin`), onglet **Importer**,
   choisir `database/bernabe.sql` puis **Executer**.
   Le script cree la base, les 11 tables et le jeu de donnees.
4. Si besoin, adapter les identifiants dans `config/database.php`
   (par defaut : utilisateur `root`, mot de passe vide).
5. Ouvrir `http://localhost/PROJETBERNABE/`.

PHP 8.1 ou plus recent est requis (fourni par les versions recentes de WampServer).

## Arborescence

```
PROJETBERNABE/
|-- index.html            Site vitrine (page d'accueil)
|-- dashboard.php         Tableau de bord (indicateurs)
|-- services.php          Services
|-- fonctions.php         Fonctions et grille salariale
|-- employes.php          Employes, hierarchie, salaire
|-- materiels.php         Materiels et composition
|-- qualifications.php    Qualifications employe / materiel
|-- clients.php           Clients
|-- interventions.php     Interventions, actes, participations
|-- rapports.php          Requetes R1 a R9 + export CSV
|-- config/database.php   Connexion PDO
|-- includes/             Gabarits et fonctions communes
|-- assets/css/style.css  Feuille de style
|-- assets/js/app.js      Comportements d'interface
`-- database/bernabe.sql  Script de creation et d'insertion
```

## Fonctionnalites

- Creation, consultation, modification et suppression pour chaque table.
- Salaire calcule a partir de la fonction et de l'anciennete (grille salariale).
- Hierarchie des employes avec controle des boucles (un employe ne peut pas
  etre son propre superieur, directement ou indirectement).
- Decomposition recursive du materiel avec controle des compositions circulaires.
- Fiche intervention : actes, cout total, intervenants, dates de participation.
- Rapports R1 a R9 executes en direct, requete SQL affichee, export CSV.

## Securite

- Requetes preparees PDO (protection contre l'injection SQL).
- Echappement systematique des sorties HTML (protection XSS).
- Jeton CSRF sur tous les formulaires.
- Suppressions protegees par les cles etrangeres, avec message explicite.

## Remarque sur le script SQL

La contrainte `CHECK (id_superieur <> id_employe)` du sujet a ete retiree :
MySQL 8 refuse un CHECK portant sur une colonne `AUTO_INCREMENT` (erreur 3818).
La regle est verifiee par l'application dans `employes.php`.
