# EventManagement - Application de Gestion d'Événements

## 📋 Description
EventManagement est une application web complète de gestion et de réservation d'événements (Sports & Loisirs) développée avec Symfony. Elle permet aux :
- **Utilisateurs** : de consulter des événements et effectuer des réservations en ligne
- **Administrateurs** : de gérer les événements et réservations via une interface sécurisée

L'application utilise des mécanismes modernes d'authentification combinant **JWT** pour l’autorisation stateless et **Passkeys (WebAuthn/FIDO2)** pour une authentification forte sans mot de passe.

## ✨ Fonctionnalités

### Côté Utilisateur
- ✅ Inscription et authentification sécurisées (JWT + Passkeys)
- ✅ Consultation des événements avec filtres (à venir, passés, recherche)
- ✅ Détails complets d'un événement (description, date, lieu, image)
- ✅ Réservation en ligne avec validation
- ✅ Tableau de bord personnel des réservations (à venir / passées)
- ✅ Gestion des passkeys (WebAuthn) pour une authentification sans mot de passe
- ✅ Profil utilisateur avec affichage et rafraîchissement du token JWT
- ✅ Déconnexion sécurisée avec invalidation des tokens
- ✅ Empêchement du retour arrière après déconnexion

### Côté Administrateur
- ✅ Authentification sécurisée dédiée (formulaire admin)
- ✅ Dashboard avec statistiques en temps réel (total événements, réservations, etc.)
- ✅ CRUD complet sur les événements
- ✅ Consultation et gestion des réservations par événement
- ✅ Interface admin responsive et moderne avec sidebar rétractable
- ✅ Profil administrateur avec informations personnelles
- ✅ Notifications en temps réel (icône avec compteur de nouvelles réservations)
- ✅ Modal de notifications avec liste des réservations récentes
- ✅ Marquage des notifications comme lues
- ✅ Filtres et recherche sur la liste des événements
- ✅ Pagination pour les listes d'événements
- ✅ Gestion des tokens JWT pour l'admin
- ✅ Déconnexion sécurisée avec protection contre le retour arrière

## 🛠️ Technologies

### Back-end
- PHP 8.1+ / Symfony 6.4+
- MySQL 8.0
- Doctrine ORM
- JWT (LexikJWTAuth) + Gesdinet Refresh Token
- WebAuthn (Passkeys)

### Front-end
- Twig (moteur de templates)
- Bootstrap 5 et Bootstrap Icons
- JavaScript (ES6+)
- WebAuthn API (passkeys)

### Outils
- Composer (gestion des dépendances)
- Git (versioning)
- Docker (conteneurisation)
- phpMyAdmin / Mailhog (développement)

## 🚀 Installation

### 1. Prérequis
- PHP 8.1+ avec extensions nécessaires
- MySQL 8.0 ou PostgreSQL 14+
- Node.js et npm
- Git
- Docker (optionnel)
- Navigateur compatible WebAuthn (Chrome 108+, Firefox 113+, Safari 16+)

### 2. Installation manuelle
1. Cloner le projet :  
```bash
git clone https://github.com/haithemboujnah/MiniProjet2A-EventReservation-PrimeDevs.git
cd event-management
```

### 5. Comptes par défaut 

Admin : username: admin / password: admin123 
Utilisateur : username: haithem@gmail.com / password: haithem123 

Site : http://127.0.0.1:8000/

## 👥 Équipe de développement
Haithem Boujnah [Grp2] | Développeur Fullstack | haithemboujnah1@gmail.com