# OpenMage Nexi XPay Build
## Modulo di Pagamento per OpenMage / Magento 1

---

## Compatibilità

| Piattaforma | Versione | PHP |
|---|---|---|
| Magento 1.9 | 1.9.x | 7.0 — 7.4 |
| OpenMage LTS | 20.x | 8.1 — 8.5 |

Compatibile con **FireCheckout** (checkout personalizzato).

---

## Descrizione

Modulo di pagamento con form embedded (senza redirect) per carte di credito tramite il gateway **XPay Build** di Nexi.

#### Funzionalità supportate:

- Form di Pagamento con **inserimento carta al checkout**
- **OneClick / Carte Salvate** — il cliente registrato può salvare la carta e pagare con un clic agli acquisti successivi
- Contabilizzazione **Immediata** (cattura automatica) o **Differita** (solo autorizzazione)
- **Backend** Cattura pagamento e contabilizzazione su Nexi in modalità Differita
- **Backend** Nota di credito online e storno su Nexi (ricordarsi di creare la nota di credito sulla fattura)
- **Backend** Informazioni complete nella transazione
- **Dev** Log con dettaglio Api in `var/log/nexi_xpaybuild.log`

---

## Requisiti

- Magento 1.9.x o OpenMage LTS 20.x
- PHP 7.4 o superiore
- Credenziali Nexi XPay attive (Alias + MAC Key)

---

## Installazione

### Install via Composer
```bash
composer require empiricompany/openmage-nexi-xpaybuild
```

### Manual
1. Copia i file `app/*` `skin/*` nella root di Magento/OpenMage mantenendo la struttura delle directory.

Svuota la cache: **System → Cache Management → Flush All**.

---

## Configurazione

Vai in **System → Configuration → Payment Methods → Nexi XPay Build**.

| Campo | Descrizione | Default |
|---|---|---|
| Enable | Abilita o disabilita il modulo | Disabilitato |
| Title | Etichetta visibile al cliente nel checkout | Nexi XPay Build |
| Environment | Test / Produzione | Test |
| XPay Alias | Alias | Fornito da Nexi |
| MAC Key | Chiave per il calcolo mac | Fornito da Nexi |
| XPay Card Form Style | SPLIT_CARD: 3 campi separati (PAN, Expiry, CVV) o CARD: form unificata | CARD Unificata |
| Accounting Type | Immediata (cattura subito) / Differita (solo autorizzazione) | Immediata |
| Enable OneClick | Abilita il salvataggio carte per i clienti registrati | Abilitato |
| New Order Status | Nuovo stato per ordini creati | Processing |
| Payment from Applicable Countries | Restrizione metodo di pagamento per paese | Tutti i Paesi Permessi |
| Payment from Specific Countries | Specifica paesi consentiti per il pagamento | Tutti |
| Sort Order | Ordine di visualizzazione nel checkout | 10 |

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
