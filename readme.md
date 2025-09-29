# TLDR Generator

Un plugin WordPress qui génère automatiquement des résumés TLDR (Too Long; Didn't Read) pour vos articles en utilisant l'intelligence artificielle (OpenAI ou Gemini).

<img width="1716" height="694" alt="image" src="https://github.com/user-attachments/assets/054a6fe8-e690-440f-954c-b6a8cffb676c" />


## 🚀 Fonctionnalités

### ✨ Génération automatique
- **Génération à la publication** : Les résumés TLDR sont créés automatiquement lors de la première publication d'un article
- **Génération à la sauvegarde** : Création automatique lors de la première sauvegarde d'un brouillon avec contenu
- **Génération forcée** : Option pour forcer la régénération via une case à cocher dans l'éditeur

### 🤖 Support multi-LLM
- **OpenAI** : Support complet avec récupération automatique des modèles disponibles
- **Gemini** : Intégration avec l'API Google Gemini
- **Test de connexion** : Vérification de la validité des clés API
- **Rafraîchissement des modèles** : Mise à jour automatique de la liste des modèles disponibles

### 📝 Interface d'édition
- **Metabox dédiée** : Interface dans l'éditeur d'articles pour visualiser et modifier les résumés
- **Édition manuelle** : Possibilité de modifier manuellement les résumés générés
- **Bloc Gutenberg** : Bloc personnalisable pour afficher les résumés dans le contenu

### 🔧 Gestion avancée
- **Génération en lot** : Traitement automatique de tous les articles existants sans résumé
- **Barre de progression** : Suivi en temps réel du traitement par lots
- **Prompt personnalisable** : Modèle de prompt entièrement configurable
- **Débogage intégré** : Logs détaillés pour le diagnostic des problèmes

## 📦 Installation

1. Téléchargez le plugin et placez-le dans `/wp-content/plugins/tldr-generator/`
2. Activez le plugin dans l'administration WordPress
3. Configurez vos paramètres dans **Réglages > TLDR Generator**

## ⚙️ Configuration

### 1. Configuration de l'API

Rendez-vous dans **Réglages > TLDR Generator** :

1. **Fournisseur LLM** : Choisissez entre OpenAI ou Gemini
2. **Clé API** : Saisissez votre clé API du fournisseur choisi
3. **Modèle** : Sélectionnez le modèle à utiliser (la liste se met à jour automatiquement)
4. **Test de connexion** : Vérifiez que votre configuration fonctionne

### 2. Personnalisation du prompt

Le prompt par défaut génère une liste de 4 à 6 points clés :

```
À partir de ce contenu, génère moi un TLDR de 4 à 6 points clés permettant de comprendre les idées principales évoquées. N'explique ce que tu fais, je veux directement le résultat sous forme de liste.
Titre : %%titre%%.
Contenu :
%%contenu%%.
```

**Variables disponibles** :
- `%%titre%%` : Le titre de l'article
- `%%contenu%%` : Le contenu nettoyé de l'article

### 3. Génération en lot

Pour traiter vos articles existants :

1. Allez dans **Réglages > TLDR Generator**
2. Section **Génération en lot**
3. Cliquez sur **Générer tous les TLDR manquants**
4. Suivez la progression avec la barre de progression

## 🎨 Utilisation du bloc Gutenberg

### Insertion du bloc
1. Dans l'éditeur Gutenberg, cliquez sur **+** pour ajouter un bloc
2. Recherchez "Résumé TLDR" ou naviguez dans la catégorie "Texte"
3. Insérez le bloc où vous souhaitez afficher le résumé

__ASTUCE :__ vous pouvez utiliser le "Site builder" du thème Astra Pro pour insérer automatiquement le bloc dans vos articles, ou le plugin https://github.com/effi10/effi-block-inserter si vous n'utilisez pas un thème permettant de le faire.

### Personnalisation
Le bloc supporte toutes les options de style WordPress :
- **Couleurs** : Texte, arrière-plan
- **Typographie** : Taille de police, hauteur de ligne
- **Espacement** : Marges, padding
- **Bordures** : Couleur, style, largeur, rayon

<img width="283" height="862" alt="image" src="https://github.com/user-attachments/assets/9a85765d-6a96-463c-be93-ce7e34d5c449" />


## 🔧 Fonctionnement technique

### Hooks WordPress utilisés
- `transition_post_status` : Détection de la publication initiale
- `save_post` : Gestion de la sauvegarde et génération conditionnelle
- `add_meta_boxes` : Ajout de la metabox dans l'éditeur
- `init` : Enregistrement du bloc Gutenberg

### Stockage des données
- **Clé meta** : `_tldr_summary_text`
- **Options** : `tldr_settings` (configuration du plugin)

### Sécurité
- Vérification des nonces pour toutes les actions AJAX
- Sanitisation des données utilisateur
- Vérification des permissions utilisateur
- Échappement des sorties HTML

## 🐛 Débogage

### Activation des logs
Ajoutez dans votre `wp-config.php` :

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

### Consultation des logs
Les logs sont disponibles dans `wp-content/debug.log`. Recherchez les entrées `[TLDR Debug]` pour suivre :
- Les appels API
- La génération des résumés
- Les erreurs de configuration
- Le traitement par lots

### Messages d'erreur courants

**"Configuration incomplète"**
- Vérifiez que tous les champs sont remplis (fournisseur, clé API, modèle, prompt)

**"Échec de la connexion à OpenAI/Gemini"**
- Vérifiez votre clé API
- Testez la connexion avec le bouton dédié
- Consultez les logs pour plus de détails

**"Le bloc a rencontré une erreur"**
- Vérifiez que le script éditeur est bien chargé
- Consultez la console du navigateur (F12)

## 📋 Prérequis

- **WordPress** : 5.0 ou supérieur
- **PHP** : 7.4 ou supérieur
- **Gutenberg** : Activé (pour le bloc)
- **Connexion internet** : Pour les appels API

## 🔑 Clés API

### OpenAI
1. Créez un compte sur [platform.openai.com](https://platform.openai.com)
2. Générez une clé API dans la section "API Keys"
3. Ajoutez du crédit à votre compte pour utiliser l'API

### Gemini
1. Créez un projet sur [Google AI Studio](https://aistudio.google.com)
2. Générez une clé API
3. Activez l'API Gemini pour votre projet

## 🚨 Limitations

- **Quotas API** : Respectez les limites de votre fournisseur LLM
- **Taille du contenu** : Les articles très longs peuvent être tronqués selon les limites du modèle
- **Coût** : Chaque génération consomme des tokens de votre quota API
- **Langue** : Optimisé pour le français, mais fonctionne dans d'autres langues

## 🔄 Mise à jour

Le plugin préserve vos réglages lors des mises à jour. Les résumés existants ne sont jamais supprimés automatiquement.

## 📞 Support

Pour signaler un bug ou demander une fonctionnalité :
1. Activez le mode débogage
2. Reproduisez le problème
3. Consultez les logs dans `wp-content/debug.log`
4. Fournissez les informations de débogage pertinentes

## 📄 Licence

Ce plugin est distribué sous licence GPL-2.0-or-later.

---

**Version** : 1.0.0  
**Testé jusqu'à** : WordPress 6.4  
**Auteur** : Cédric pour Ares in live



