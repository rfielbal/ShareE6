# Gestion des amis

La page `/amis`, accessible après connexion via « Mes amis », permet de :

- inviter un utilisateur actif avec son adresse email ;
- accepter ou refuser une demande reçue ;
- annuler une invitation envoyée ;
- consulter ses amis et retirer un ami des deux listes.

## Relations du MCD

Les deux relations sont déclarées directement dans `User`, sans nouvelle entité :

| Relation | Table de liaison | Collections dans User |
| --- | --- | --- |
| DemandeAmi | `user_demande` | `demander` (envoyées), `usersDemande` (reçues) |
| AccepterDemande | `user_accepter` | `accepter` (amis), `userAccepte` (côté inverse) |

Comme dans le projet Share de référence, une amitié acceptée est enregistrée dans les deux sens. L’acceptation enlève l’invitation, et la suppression retire les deux liens d’amitié. Supprimer un compte supprime automatiquement ses liens grâce aux clés étrangères, sans supprimer les autres comptes.

Une personne ne peut pas s’inviter, doubler une invitation, inviter un ami existant ou accepter une invitation destinée à quelqu’un d’autre. Une invitation croisée doit être acceptée explicitement. Les changements utilisent POST avec un jeton CSRF ; les deux comptes sont verrouillés pendant la transaction pour sérialiser les demandes simultanées. Les relations ne sont pas copiées dans la session de connexion.

## Mise en place sur le nuage

Depuis la racine de cette version de ShareE6, avec PHP et les dépendances installées :

```bash
composer install
php bin/console doctrine:migrations:migrate
php bin/console doctrine:schema:validate
php bin/console lint:twig templates/amis
```

La migration `Version20261123160000` crée uniquement les deux tables de liaison. Vérifier la base configurée avant de lancer la migration. Elle n’a pas été exécutée sur le PC.

## Vérification

Tests unitaires (sans base) :

```bash
php bin/phpunit tests/Unit/UserAmisTest.php
```

Les tests fonctionnels exigent la base MariaDB dédiée `share_e6_test`, comme les autres tests du projet. Ils nettoient cette base et refusent de travailler sur une autre base. Après configuration de l’environnement de test :

```bash
php bin/console doctrine:migrations:migrate --env=test
php bin/phpunit tests/Functional/AmisTest.php
```

Vérification manuelle avec deux comptes : envoyer une invitation depuis A, l’accepter depuis B, constater l’apparition dans les deux listes, puis supprimer depuis A et vérifier que B ne voit plus A. Essayer également un refus, une annulation, une invitation à soi-même et un double envoi.

Les tests PHP/Symfony restent à exécuter sur un environnement disposant de PHP ; le contrôle intelligent des applications Windows reste activé.
