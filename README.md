# MakeYourCount 💸

**MakeYourCount** est une application web auto-hébergée de gestion de dépenses partagées, inspirée de Tricount.  
Elle permet de créer des groupes, ajouter des dépenses, suivre les soldes de chacun et visualiser le détail des dépenses, le tout avec une interface claire et responsive.

---

## ✨ Fonctionnalités

### 👥 Groupes
- Création de groupes
- Rejoindre un groupe via un **code**
- Liste des membres du groupe
- Statistiques globales par groupe

### 💶 Dépenses
- Ajout de dépenses
- Choix du **payeur**
- Sélection des **participants**
- Répartition automatique et équitable
- Regroupement des dépenses par **date**
- Détail d’une dépense cliquable :
  - descriptif
  - date lisible (ex : *vendredi 9 janvier 2026*)
  - payeur
  - participants et parts
- **Édition** d’une dépense existante

### 📊 Soldes & statistiques
- Solde par membre (payé − dû)
- Membres triés du plus débiteur au plus créditeur
- Total des dépenses du groupe
- Total des dépenses personnelles (part réelle, indépendamment du payeur)
- Podium des plus gros “consommateurs” (basé sur les parts)

### 🔐 Comptes utilisateurs
- Inscription / connexion
- Mot de passe sécurisé (`password_hash`)
- Validation du mot de passe en temps réel
- Affichage / masquage du mot de passe (icône œil)
- **Changement de mot de passe** une fois connecté
- Protection CSRF
- Sessions sécurisées

---

## 🛠️ Stack technique

- **Backend** : PHP 8+
- **Base de données** : MySQL / MariaDB
- **Frontend** :
  - HTML / CSS custom
  - UI inspirée DSFR (version allégée)
  - JavaScript vanilla
- **Serveur** :
  - Apache (Linux / OVH mutualisé compatible)

---

## 📁 Structure du projet
MakeYourCount/
├── assets/
│ ├── style.css
│ ├── dsfr-lite.css
│ └── password_validation.js
├── config/
│ └── database.php
├── includes/
│ ├── layout.php
│ └── csrf.php
├── index.php
├── dashboard.php
├── group_create.php
├── group_view.php
├── expense_add.php
├── expense_view.php
├── expense_edit.php
├── login.php
├── register.php
├── password_change.php
├── logout.php
└── README.md

## ⚙️ Installation

### 1️⃣ Prérequis
- PHP ≥ 8.0
- MySQL / MariaDB
- Apache avec `mod_rewrite`
- Accès à phpMyAdmin (recommandé)

### 2️⃣ Base de données

Créer une base et un utilisateur MySQL :

```sql
CREATE DATABASE makeyourcount CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'myc_user'@'localhost' IDENTIFIED BY 'mot_de_passe_fort';
GRANT ALL PRIVILEGES ON makeyourcount.* TO 'myc_user'@'localhost';
FLUSH PRIVILEGES;
Importer ensuite le schéma SQL du projet.

### 3️⃣ Configuration
Éditer config/database.php :

return [
  'host' => 'localhost',
  'db' => 'makeyourcount',
  'user' => 'myc_user',
  'pass' => 'mot_de_passe_fort',
  'charset' => 'utf8mb4',
];

### 4️⃣ Lancement
Placer le projet dans le dossier web (/var/www/html ou équivalent)
Puis accéder à :

http://localhost/MakeYourCount/
🔒 Sécurité
Hashage des mots de passe avec password_hash

Vérification avec password_verify

Tokens CSRF sur tous les formulaires sensibles

Accès restreint aux groupes (vérification d’appartenance)

Aucune information sensible exposée côté client

### 🔄 Migration depuis Tricount
Deux approches possibles :

Import des soldes (recommandé)

Création de dépenses de migration basées sur les totaux par personne

Le projet est conçu pour accepter des ajustements propres sans polluer l’historique.

## 🚀 Améliorations possibles
Répartition personnalisée des parts

Historique des modifications de dépenses

Export CSV / PDF

Notifications

Mode PWA

API REST

## 👤 Auteur
Projet développé par Matthieu Dupe
Projet personnel / expérimental – auto-hébergé

## 📄 Licence
Projet libre pour usage personnel et éducatif.
À adapter selon vos besoins.
