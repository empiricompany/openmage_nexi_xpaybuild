# Nexi XPay Build — Modulo di Pagamento per OpenMage / Magento 1

---

## Compatibilità

| Piattaforma | Versione | PHP |
|---|---|---|
| Magento 1.9 | 1.9.x | 7.0 — 7.4 |
| OpenMage LTS | 20.x | 8.1 — 8.5 |

Compatibile con **FireCheckout** (checkout personalizzato).

---

## Descrizione

Modulo di pagamento con form embedded (senza redirect) per carte di credito tramite il gateway **XPay Build** di Nexi. Autenticazione tramite Alias e MAC Key.

Funzionalità supportate:

- Pagamento standard con inserimento carta al checkout
- **OneClick / Carte Salvate** — il cliente registrato può salvare la carta e pagare con un clic agli acquisti successivi
- Contabilizzazione **Immediata** (cattura automatica) o **Differita** (solo autorizzazione)

---

## Requisiti

- Magento 1.9.x o OpenMage LTS 20.x
- PHP 7.4 o superiore
- Credenziali Nexi XPay attive (Alias + MAC Key)

---

## Installazione

1. Copia i file nella root di Magento/OpenMage mantenendo la struttura delle directory.
2. Svuota la cache: **System → Cache Management → Flush All**.
3. Il modulo crea automaticamente le tabelle necessarie al database al primo avvio.

---

## Configurazione

Vai in **System → Configuration → Payment Methods → Nexi XPay Build**.

| Campo | Descrizione |
|---|---|
| Enable | Abilita o disabilita il modulo |
| Title | Etichetta visibile al cliente nel checkout |
| Environment | Test / Produzione |
| XPay Alias | Fornito da Nexi |
| MAC Key | Fornita da Nexi |
| XPay Card Form Style | SPLIT (3 campi) o CARD (unificata) |
| Accounting Type | Immediata (cattura subito) / Differita (solo autorizzazione) |
| Enable OneClick | Abilita il salvataggio carte per i clienti registrati |
| Sort Order | Ordine di visualizzazione nel checkout |

---

## Funzionalità OneClick / Carte Salvate

Quando abilitata, la funzione OneClick permette ai clienti registrati di:

- Salvare la carta al momento del pagamento (consenso esplicito)
- Visualizzare e gestire le carte salvate dalla propria area account (**Account → Le mie carte**)
- Rimuovere una carta salvata in qualsiasi momento
- Pagare con una carta già salvata senza reinserire i dati

I token delle carte sono memorizzati in forma cifrata e non contengono dati sensibili del titolare della carta.

---

## Licenza

Open Software License (OSL) v. 3.0
