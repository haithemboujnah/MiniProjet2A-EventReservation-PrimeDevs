# EventManagement - Application de Gestion d'Événements

## 📋 Description
EventManagement est une application web complète de gestion et de réservation d'événements (Sports & Loisirs) développée avec Symfony. Elle permet aux :
- **Utilisateurs** : de consulter des événements et effectuer des réservations en ligne
- **Administrateurs** : de gérer les événements et réservations via une interface sécurisée

L'application utilise des mécanismes modernes d'authentification combinant **JWT** pour l’autorisation stateless et **Passkeys (WebAuthn/FIDO2)** pour une authentification forte sans mot de passe.

## ✨ Fonctionnalités

### Côté Utilisateur
- ✅ Inscription et authentification sécurisées (JWT + Passkeys)
- ✅ Consultation des événements avec filtres (à venir, passés)
- ✅ Détails complets d'un événement (description, date, lieu, image)
- ✅ Réservation en ligne avec validation
- ✅ Tableau de bord personnel des réservations
- ✅ Gestion des passkeys pour une authentification sans mot de passe

### Côté Administrateur
- ✅ Authentification sécurisée dédiée
- ✅ Dashboard avec statistiques en temps réel
- ✅ CRUD complet sur les événements
- ✅ Consultation et gestion des réservations
- ✅ Interface admin responsive et moderne

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