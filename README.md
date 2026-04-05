# 📡 Duplicate Radar

> Scansione batch dei post duplicati in WordPress — leggero, sicuro, senza dipendenze esterne.  
> Batch scanning for duplicate WordPress posts — lightweight, secure, no external dependencies.

---

## 🇮🇹 Italiano

### Descrizione

**Duplicate Radar** è un plugin WordPress che analizza tutti i post pubblicati e individua possibili duplicati in base a tre criteri selezionabili:

- **Titolo identico** (confronto case-insensitive)
- **Permalink simile** (es. `articolo` vs `articolo-2`)
- **Somiglianza del contenuto** (con soglia percentuale configurabile)

La scansione avviene post per post tramite chiamate AJAX, quindi non blocca il browser e può essere interrotta e riavviata in qualsiasi momento.

### Requisiti

- WordPress 5.8 o superiore
- PHP 7.4 o superiore
- jQuery (incluso in WordPress core)

### Installazione

1. Scarica il file `duplicate-radar.php`
2. Accedi al pannello di amministrazione WordPress
3. Vai su **Plugin → Aggiungi nuovo → Carica plugin**
4. Carica il file `.php` **oppure** copialo manualmente nella cartella:
   ```
   /wp-content/plugins/duplicate-radar/duplicate-radar.php
   ```
5. Attiva il plugin dalla schermata **Plugin → Plugin installati**

### Utilizzo

1. Dal menu amministratore, vai su **Strumenti → Duplicate Radar**
2. Seleziona uno o più criteri di rilevamento
3. Se hai attivato la similitudine del contenuto, imposta la soglia percentuale (default: 80%)
4. Clicca **▶ Avvia scansione**
5. I duplicati rilevati compaiono nella tabella in tempo reale
6. Per ogni coppia trovata puoi: **Modificare**, **Visualizzare** o **Cestinare** ciascun post direttamente dalla tabella
7. Puoi **⏹ Fermare** la scansione in qualsiasi momento e **↺ Ricominciare da zero**

### Note tecniche

- Il confronto del testo usa la funzione PHP `similar_text()`, troncata a 50.000 caratteri per evitare timeout
- Ogni chiamata AJAX è protetta da **nonce WordPress** e da sanitizzazione dell'input lato server
- Il confronto è asimmetrico (A→B ma non B→A) per evitare duplicati nella tabella dei risultati

---

## 🇬🇧 English

### Description

**Duplicate Radar** is a WordPress plugin that scans all published posts and identifies potential duplicates based on three selectable criteria:

- **Identical title** (case-insensitive comparison)
- **Similar permalink** (e.g. `article` vs `article-2`)
- **Content similarity** (with a configurable percentage threshold)

Scanning proceeds post by post via AJAX requests, so it does not block the browser and can be stopped and restarted at any time.

### Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- jQuery (included in WordPress core)

### Installation

1. Download the file `duplicate-radar.php`
2. Log in to the WordPress administration panel
3. Go to **Plugins → Add New → Upload Plugin**
4. Upload the `.php` file **or** copy it manually into:
   ```
   /wp-content/plugins/duplicate-radar/duplicate-radar.php
   ```
5. Activate the plugin from **Plugins → Installed Plugins**

### Usage

1. From the admin menu, go to **Tools → Duplicate Radar**
2. Select one or more detection criteria
3. If you enabled content similarity, set the threshold percentage (default: 80%)
4. Click **▶ Start scan**
5. Detected duplicates appear in the table in real time
6. For each pair found, you can **Edit**, **View**, or **Trash** each post directly from the table
7. You can **⏹ Stop** the scan at any time and **↺ Restart from scratch**

### Technical notes

- Text comparison uses PHP's `similar_text()` function, capped at 50,000 characters to prevent timeouts
- Every AJAX request is protected by a **WordPress nonce** and server-side input sanitization
- Comparison is asymmetric (A→B but not B→A) to avoid duplicate pairs in the results table

---

## License / Licenza

MIT License

Copyright (c) 2025 Salvatore Capolupo

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

**THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.**

---

### ⚠️ Disclaimer / Avvertenza

**EN** — This plugin is released as a free, non-commercial tool for personal and educational use. It has not been audited for security vulnerabilities. On certain server configurations — in particular shared or low-cost hosting environments with restricted execution time, limited memory, or non-standard PHP setups — the plugin may behave unexpectedly, produce incomplete results, or fail silently. The author provides no guarantee of fitness for any particular purpose and accepts no liability for data loss, unintended post deletion, or any other damage arising from the use of this software. Use at your own risk. Always back up your database before performing bulk operations on your WordPress installation.

**IT** — Questo plugin è rilasciato come strumento gratuito e non commerciale, destinato all'uso personale e didattico. Non è stato sottoposto ad audit di sicurezza. Su alcune configurazioni server — in particolare hosting condivisi o di fascia economica con tempi di esecuzione ridotti, memoria limitata o impostazioni PHP non standard — il plugin potrebbe comportarsi in modo inatteso, produrre risultati incompleti o fallire silenziosamente. L'autore non fornisce alcuna garanzia di idoneità per uno scopo specifico e declina ogni responsabilità per perdita di dati, cancellazione involontaria di post o qualsiasi altro danno derivante dall'uso di questo software. Utilizzare a proprio rischio. Eseguire sempre un backup del database prima di effettuare operazioni massive sull'installazione WordPress.
