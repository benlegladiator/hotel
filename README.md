# 🏨 Alma Hotel — Système de Gestion Hôtelière & Caisse (ERP / PMS)

Application web professionnelle de gestion hôtelière complète avec support PWA et mode Hors-Ligne (Offline-First).

## ✨ Fonctionnalités Principales

- **Tableau de Bord Dynamique :** Suivi en temps réel de l'état des chambres (libres, occupées, en nettoyage, maintenance).
- **Arrivée Express (Walk-in) :** Enregistrement instantané des clients avec formule Nuitée ou Sieste/Passage et encaissement en quelques secondes.
- **Mode Hors-Ligne Résilient (Offline-First) :** 
  - Enregistrement des arrivées et encaissements locaux même sans connexion Internet via IndexedDB.
  - Impression immédiate des reçus de caisse / vouchers en local.
  - Synchronisation automatique et idempotente vers la base de données centrale au retour du réseau.
  - Calcul et suivi du cash physique encaissé hors-ligne (sécurité caisse).
- **Gestion des Chambres & Types :** Tarifs modulables, inventaire et affectation.
- **Gestion du Ménage & Traçabilité :** Suivi des tâches de nettoyage et historique d'occupation.
- **Facturation & Règlements :** Espèces, Mobile Money, Cartes bancaires.
- **PWA Native :** Icônes détourées 100% transparentes, installable sur PC/Mac (barre des tâches) et smartphones (Android / iOS).

## 🚀 Technologies

- **Backend :** PHP 8+ avec PDO MySQL
- **Base de données :** MySQL (InnoDB, UTF8mb4)
- **Frontend :** HTML5, CSS3 (Design System épuré), JavaScript Vanilla (ES6+)
- **Stockage Local :** Service Worker + IndexedDB API
- **Branding & PWA :** Web App Manifest (standalone)
