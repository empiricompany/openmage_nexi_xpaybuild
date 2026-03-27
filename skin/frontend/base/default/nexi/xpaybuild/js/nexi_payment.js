/**
 * Nexi XPay Build — OpenMage LTS
 * ES6 — Chrome 49+ / Firefox 45+ / Safari 9+ / Edge 15+
 * No optional chaining, no nullish coalescing.
 */
(function (w) {
    'use strict';

    // Read and print timestamp query string for cache-bust verification
    const _src     = document.currentScript && document.currentScript.src;
    const _tsMatch = _src && _src.match(/[?&]timestamp=(\d+)/);
    if (_tsMatch) {
        console.log('[NexiPayment] JS timestamp: '
            + new Date(parseInt(_tsMatch[1], 10) * 1000).toISOString()
            + ' (' + _tsMatch[1] + ')');
    }

    // ── BASE ──────────────────────────────────────────────────────────────────

    class NexiGatewayBase {

        _configure(cfg) {
            this._paymentSessionUrl = cfg.paymentSessionUrl || '';
            this._saveNonceUrl      = cfg.saveNonceUrl      || '';
            this._oneClick          = !!cfg.oneClick;
            this.oneClickCvv        = !!cfg.oneClickCvv;
            this._isLoggedIn        = !!cfg.isLoggedIn;
            this._environment       = cfg.environment || XPay.Environments.INTEG;
            this._methodCode        = cfg.methodCode  || 'nexi_xpaybuild';

            if (!this._paymentSessionUrl) this.log('paymentSessionUrl non configurato.', 'error');
        }

        _reset() {
            this._setLoadWaiting(false);
        }

        _setVisible(v) {
            const el = document.getElementById(this.formId);
            if (!el) return;
            el.style.display = v ? '' : 'none';
            if (v) this._enableInputs();
        }

        _disableInputs() {
            const el = document.getElementById(this.formId);
            if (!el) return;
            el.querySelectorAll('input, select, textarea').forEach(i => { i.disabled = true; });
        }

        _enableInputs() {
            if (document.querySelector('.nexi-card-form .loading')) return false;
            const el = document.getElementById(this.formId);
            if (!el) return false;
            el.querySelectorAll('input, select, textarea').forEach(i => { i.disabled = false; });
            return true;
        }

        _setLoadWaiting(flag) {
            this._loading = !!flag;
            if (typeof checkout !== 'undefined' && checkout && typeof checkout.setLoadWaiting === 'function') {
                checkout.setLoadWaiting(flag ? 'payment' : false);
            }
        }

        isNexiSelected() {
            if (!this._methodCode) return false;
            if (typeof payment !== 'undefined' && payment && typeof payment.currentMethod === 'string' && payment.currentMethod.length > 0) {
                return payment.currentMethod === this._methodCode;
            }
            const radios = document.querySelectorAll('input[type=radio][name="payment[method]"]');
            for (let i = 0; i < radios.length; i++) {
                if (radios[i].checked && radios[i].value === this._methodCode) return true;
            }
            return false;
        }

        _hidden(id, val) {
            const el = document.getElementById(id);
            if (el) el.value = (val !== null && val !== undefined) ? String(val) : '';
        }

        showError(msg) {
            const el = document.getElementById('nexi-error');
            if (!el) return;
            el.textContent = msg;
            el.style.display = '';
            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        hideError() {
            const el = document.getElementById('nexi-error');
            if (el) { el.textContent = ''; el.style.display = 'none'; }
        }

        log(msg, level) {
            if (typeof console === 'undefined') return;
            const p = this.logPrefix;
            if (level === 'error')    console.error(p + ' ' + msg);
            else if (level === 'warn') console.warn(p + ' ' + msg);
            else                       console.log(p + ' ' + msg);
        }

        _showCardForm() {
            const oc = document.getElementById('xpay-oneclick-hidden');
            if (oc) oc.style.display = 'none';
            const sc = document.getElementById('nexi-save-card-container');
            if (sc) sc.style.display = '';            
            const br = document.querySelector('.nexi-accepted-brands');
            if (br) br.style.display = '';
            const sf = document.querySelector('.nexi-card-form');
            if (sf) sf.style.display = '';
        }

        _hideCardForm() {
            const oc = document.getElementById('xpay-oneclick-hidden');
            if (oc) oc.style.display = this.oneClickCvv ? '' : 'none';
            const sc = document.getElementById('nexi-save-card-container');
            if (sc) sc.style.display = 'none';
            const br = document.querySelector('.nexi-accepted-brands');
            if (br) br.style.display = 'none';
            const sf = document.querySelector('.nexi-card-form');
            if (sf) sf.style.display = 'none';
        }

        _proceed() {
            this._afterFetch();
            if (this._proceedFn) { this._proceedFn(); return; }
            if (typeof review  !== 'undefined' && review && review.save)   { review.save();  return; }
            if (typeof payment !== 'undefined' && payment && payment.save) { payment.save(); return; }
            const btn = document.querySelector('.btn-checkout, .place-order-btn');
            if (btn) btn.click();
        }

        _savedCardId() {
            const el = document.querySelector('input[name="nexi_saved_card"]:checked');
            return el ? (parseInt(el.value, 10) || 0) : 0;
        }

        _fetchBuildData(savedCardId, saveCard, retry) {

            savedCardId = savedCardId || 0;
            saveCard    = !!saveCard;
            retry       = retry || 0;
            if (retry === 0 && this._loading) return Promise.resolve();

            this.hideError();
            this._beforeFetch();

            const fkEl   = document.getElementById('form_key') || document.querySelector('input[name="form_key"]');
            const fk     = fkEl ? fkEl.value : '';
            const params = 'saved_card_id=' + encodeURIComponent(savedCardId) +
                           '&save_card='    + (saveCard ? 1 : 0) +
                           '&form_key='     + encodeURIComponent(fk);

            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', this._paymentSessionUrl, true);
                xhr.setRequestHeader('Content-Type',     'application/x-www-form-urlencoded');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.onreadystatechange = () => {
                    if (xhr.readyState !== 4) return;
                    if (xhr.status === 200) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            if (data.error || data.error_msg) {
                                this._formError(data.error_msg || data.message || 'Errore caricamento form.');
                                reject(new Error(data.error_msg || data.message || 'Errore caricamento form.'));
                                return;
                            }
                            this.initGateway(data);
                            resolve(data);
                        } catch (ex) {
                            this._formError('Errore risposta server.');
                            reject(ex);
                        }
                    } else if ((xhr.status === 503 || xhr.status === 429) && retry < 3) {
                        this._afterFetch();
                        setTimeout(() => {
                            this._fetchBuildData(savedCardId, saveCard, retry + 1).then(resolve).catch(reject);
                        }, 2000 * Math.pow(2, retry));
                    } else {
                        this._formError('Errore HTTP ' + xhr.status + '.');
                        reject(new Error('HTTP ' + xhr.status));
                    }
                };
                xhr.send(params);
            });
        }

        _formError(msg) {
            this._afterFetch();
            this.showError(msg);
            this.log(msg, 'error');
        }

        _beforeFetch() {
            this._setLoadWaiting(true);
            this._disableInputs();
        }

        _afterFetch() {
            this._setLoadWaiting(false);
            this._enableInputs();
        }

        initGateway(data)  { throw new Error('abstract'); }
        handleSubmit(orig) { throw new Error('abstract'); }
        getXpayEnvironment() { return this._environment == 'test' ? XPay.Environments.INTEG : XPay.Environments.PROD; }
    }

    // ── XPAY ──────────────────────────────────────────────────────────────────

    class NexiPaymentXPay extends NexiGatewayBase {

        constructor(cfg) {
            if (typeof XPay === 'undefined') { this.log('XPay SDK non disponibile al momento dell\'inizializzazione.', 'error'); }
            super();

            // Singleton: FC esegue new più volte → reinit sull'istanza esistente
            if (w.NexiPayment instanceof NexiPaymentXPay) {
                w.NexiPayment._update(cfg);
                return w.NexiPayment;
            }

            this.formId       = 'nexi-payment-form';
            this.logPrefix    = '[NexiPayment]';
            this._cardForm    = null;
            this._cardStyle   = 'CARD';
            this._buildData   = null;
            this._proceedFn   = null;
            this._selectedCardId = 0;

            this._configure(cfg);
            this._attachListeners();
            w.NexiPayment = this;
            this._patchSubmit();
            this._start();
        }

        // Chiamato da FC su ogni reinject del blocco pagamento
        _update(cfg) {
            this._configure(cfg);
            this._reset();
            this._cardForm  = null;
            this._buildData = null;
            this._patchSubmit();
            this._start();
        }

        _start() {
            const form = document.getElementById(this.formId);
            if (!form) {
                this.log('Form non trovato, attendo reinject...', 'warn');
                return;
            }
            this._setVisible(this.isNexiSelected());
            if (!this.isNexiSelected()) return;

            const savedId = this._savedCardId();
            this._selectedCardId = savedId;
            if (savedId > 0) {
                this._hideCardForm();
            }

            if (!this._loading) {
                this._fetchBuildData(savedId, false, 0);
            } else {
            }
        }

        _attachListeners() {
            document.addEventListener('change', e => {
                if (!e.target) return;
                if (e.target.name === 'payment[method]') {
                    const selected = e.target.value === 'nexi_xpaybuild';
                    this._setVisible(selected);
                    if (selected && !this._loading) {
                        this._reinitializeXPay(); 
                    }
                }

                if (e.target.name === 'nexi_saved_card')
                    this._onCardSwitch(parseInt(e.target.value, 10) || 0);

                if (e.target.id === 'nexi-save-card' && this._cardForm && typeof XPay !== 'undefined' && XPay.updateConfig) {
                    XPay.updateConfig(this._cardForm, {
                        serviceType: 'paga_oc3d',
                        requestType: e.target.checked ? 'PP' : 'PA'
                    });
                }
            });

            window.addEventListener('XPay_Ready', e => {
                this.log('XPay_Ready [' + this._cardStyle + ']' + (e.detail ? ' detail=' + e.detail : ''));
                if (e.detail) {
                    const f = document.querySelector('.nexi-build-field__' + String(e.detail).toLowerCase() + '.loading');
                    if (f) f.classList.remove('loading');
                    if (this._enableInputs()) {
                        this._setLoadWaiting(false);
                    }
                }
            });

            window.addEventListener('XPay_Nonce', e => {
                if (e.detail && e.detail.esito === 'OK' && e.detail.xpayNonce) {
                    this._hidden('nexi-xpay-nonce', e.detail.xpayNonce);
                    this._hidden('nexi-xpay-cod-trans', this._buildData ? this._buildData.codTrans : '');
                    this._hidden('nexi-saved-card-id', this._selectedCardId);
                    this._saveNonceXhr(
                        e.detail.xpayNonce,
                        this._buildData ? this._buildData.codTrans : '',
                        (this._buildData && this._buildData.savedCardToken) || '',
                        e.detail.brand    || '',
                        e.detail.pan      || '',
                        e.detail.scadenza || ''
                    ).then(() => {
                        this.log('Nonce salvato correttamente, procedo con submit');
                        this._proceed();
                    }).catch(err => {
                        this._formError('Errore salvataggio nonce: ' + (err && err.message ? err.message : err));
                    });
                    this.log('Nonce di pagamento ricevuto, procedo con submit');
                } else {
                    const codice = e.detail && e.detail.errore ? String(e.detail.errore.codice) : '';
                    const msg    = e.detail && e.detail.errore ? e.detail.errore.messaggio : 'Nonce di pagamento non valido.';
                    if (codice === '5' || codice === '9') {
                        // [5] Timestamp scaduto, [9] codTrans duplicato → rigenera sessione XPay silenziosamente
                        this.log('XPay_Nonce errore [' + codice + '] — rigenero sessione (nuovo timestamp/codTrans)');
                        this._reinitializeXPay();
                    } else if (codice === '600') {
                        // Utente ha annullato il pagamento — mostra messaggio e attende retry esplicito
                        this._formError('Pagamento annullato.');
                    } else {
                        this._formError('[' + codice + '] ' + msg);
                    }
                }
            });

            window.addEventListener('XPay_Card_Error', e => {
                if (e.detail && 'errorMessage' in e.detail) {
                    if (e.detail.errorMessage) this.showError(e.detail.errorMessage);
                    else this.hideError();
                } else {
                    this.hideError();
                }
                this._afterFetch();
            });

            if (typeof document.observe === 'function') {
                document.observe('firecheckout:setResponseAfter', e => {
                    if (!this.isNexiSelected()) return;
                    const response = e.memo && e.memo.response;
                    if (!response || response.success || response.redirect) return;
                    const nonceEl = document.getElementById('nexi-xpay-nonce');
                    if (!nonceEl || !nonceEl.value) return;
                    if (typeof checkout === 'undefined' || !checkout || !checkout.urls || !checkout.urls.payment_method) return;
                    const url = (e.memo.url || '').toString();
                    if (checkout.urls.save && url.indexOf(checkout.urls.save) === -1) return;
                    this.log('FC: errore pagamento (nonce stale) — ricarico sezione payment via checkout.update()');
                    this._reinitializeXPay();
                });
                document.observe('firecheckout:updateAfter', e => {
                    if (!this.isNexiSelected()) return;
                    this._patchSubmit();
                });
            }
        }

        _onCardSwitch(cardId) {
            this.log('Switching to card ID ' + cardId);
            this._selectedCardId = cardId;
            this._hidden('nexi-saved-card-id', cardId);  // SYNC: Sincronizza hidden input
            if (cardId === 0) {
                this._showCardForm();
            } else {
                this._hideCardForm();
            }
            this._reinitializeXPay();
        }

        _patchSubmit() {
            if (typeof window.payment !== 'undefined' && window.payment && window.payment.save && !window.payment._nexiPatched) {
                const orig = window.payment.save.bind(window.payment);
                window.payment.save = () => {
                    if (!this.isNexiSelected()) return orig();
                    return this.handleSubmit(orig);
                };
                window.payment._nexiPatched = true;
            }

            Array.from(document.getElementsByClassName('btn-checkout')).forEach((btn, i) => {
                if (btn._nexiPatched) return;
                const orig = btn.onclick;
                btn.onclick = e => {
                    if (!this.isNexiSelected()) return orig ? orig.call(btn, e) : true;
                    if (e && e.preventDefault) e.preventDefault();

                    if (typeof checkout !== 'undefined' && checkout) {
                        if (checkout.loadWaiting !== false) {
                            return false;
                        }
                        if (typeof checkout.validate === 'function' && !checkout.validate()) {
                            return false;
                        }
                    }

                    this.handleSubmit(() => { if (orig) orig.call(btn, e); });
                    return false;
                };

                btn._nexiPatched = true;
            });
        }


        _beforeFetch() {
            super._beforeFetch();
            this._hidden('nexi-xpay-nonce',     '');
            this._hidden('nexi-xpay-cod-trans',  '');
            this._hidden('nexi-xpay-timestamp',  '');
        }

        initGateway(data) {
            this._buildData  = data;
            this._cardStyle  = data.cardFormStyle === 'SPLIT_CARD' ? 'SPLIT_CARD' : 'CARD';

            this._hidden('nexi-xpay-cod-trans', data.codTrans  || '');
            this._hidden('nexi-xpay-timestamp', data.timeStamp || '');

            const ids = ['xpay-pan', 'xpay-expiry', 'xpay-cvv', 'xpay-oneclick-hidden', 'xpay-pan-expiry-cvv-card'];
            for (let i = 0; i < ids.length; i++) {
                const c = document.getElementById(ids[i]);
                if (c) c.innerHTML = '';
            }
            this._configureXPay(data);
        }

        _configureXPay(data) {
            if (typeof XPay === 'undefined') { this._formError('XPay SDK non disponibile.'); return; }
            try {
                XPay.init();

                if (data.savedCardToken) {
                    this._hideCardForm();
                    // OneClick Pagamenti Successivi https://ecommerce.nexi.it/specifiche-tecniche/build/pagamentooneclick.html
                    XPay.setConfig({
                        baseConfig:    { apiKey: data.alias, enviroment: this.getXpayEnvironment() },
                        paymentParams: { amount: data.importo, transactionId: data.codTrans, currency: data.divisa, timeStamp: data.timeStamp, mac: data.mac },
                        language:      data.language || XPay.LANGUAGE.ITA,
                        customParams:  { 
                            // Codice univoco assegnato dal merchant per l'abbinamento con l'archivio contenente i dati sensibili della carta di credito 
                            num_contratto: data.savedCardToken
                        },
                        serviceType:   'paga_oc3d',
                        requestType:   'PR'
                    });
                    this._cardForm = XPay.create(XPay.OPERATION_TYPES.CARD, data.style || {});
                    const el = document.getElementById('xpay-oneclick-hidden');
                    if (el) { el.classList.add('loading'); this._cardForm.mount(el.id); }
                } else {
                    this._showCardForm();
                    XPay.setConfig({
                        baseConfig:    { apiKey: data.alias, enviroment: this.getXpayEnvironment() },
                        paymentParams: { amount: data.importo, transactionId: data.codTrans, currency: data.divisa, timeStamp: data.timeStamp, mac: data.mac },
                        language: data.language || XPay.LANGUAGE.ITA
                    });

                    if (this._cardStyle === 'CARD') {
                        this._cardForm = XPay.create(XPay.OPERATION_TYPES.CARD, data.style || {});
                        const el = document.getElementById('xpay-pan-expiry-cvv-card');
                        if (el) { el.classList.add('loading'); this._cardForm.mount(el.id); }
                    } else {
                        this._cardForm = XPay.create(XPay.OPERATION_TYPES.SPLIT_CARD, data.style || {});
                        const splitIds = ['xpay-pan', 'xpay-expiry', 'xpay-cvv'];
                        for (let si = 0; si < splitIds.length; si++) {
                            const se = document.getElementById(splitIds[si]);
                            if (se) se.classList.add('loading');
                        }
                        this._cardForm.mount('xpay-pan', 'xpay-expiry', 'xpay-cvv');
                    }
                }

            } catch (ex) {
                this.log('_configureXPay: ' + (ex && ex.message ? ex.message : ex), 'error');
                this._formError('Errore inizializzazione XPay.');
            }
        }

        _reinitializeXPay() {
            this._reset();
            this._cardForm  = null;
            this._buildData = null;
            const savedId = this._savedCardId();
            this._fetchBuildData(savedId, false, 0);
        }

        handleSubmit(orig) {
            this.hideError();
            if (this._loading) {
                this.showError('Caricamento in corso. Attendere.');
                return;
            }
            const nonceEl = document.getElementById('nexi-xpay-nonce');
            if (nonceEl && nonceEl.value) {
                this.log('Nonce stale rilevato (tentativo precedente fallito) — re-inizializzazione sessione per retry');
                this._reinitializeXPay();
                return;
            }

            if (typeof XPay === 'undefined') { this._formError('XPay SDK non disponibile.'); return; }
            if(!this._cardForm) { this._formError('Modulo XPay non inizializzato.'); return; }
            this._beforeFetch();
            this._proceedFn  = orig;
            XPay.createNonce(this.formId, this._cardForm);
        }



        _saveNonceXhr(nonce, codTrans, savedToken, brand, pan, scadenza) {
            const fkEl  = document.getElementById('form_key') || document.querySelector('input[name="form_key"]');
            const fk    = fkEl ? fkEl.value : '';
            const params = 'xpay_nonce='        + encodeURIComponent(nonce) +
                           '&xpay_cod_trans='   + encodeURIComponent(codTrans  || '') +
                           '&saved_card_token=' + encodeURIComponent(savedToken || '') +
                           '&xpay_brand='       + encodeURIComponent(brand     || '') +
                           '&xpay_pan='         + encodeURIComponent(pan       || '') +
                           '&xpay_scadenza='    + encodeURIComponent(scadenza  || '') +
                           '&form_key='         + encodeURIComponent(fk);

            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', this._saveNonceUrl, true);
                xhr.setRequestHeader('Content-Type',     'application/x-www-form-urlencoded');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.onreadystatechange = () => {
                    if (xhr.readyState !== 4) {
                        return;
                    }
                    if (xhr.status === 200) {
                        this.log('Nonce salvato correttamente in sessione');
                        resolve();
                    } else {
                        reject(new Error('HTTP ' + xhr.status + ' — Errore salvataggio dati di pagamento.'));
                    }
                };
                xhr.send(params);
            });
        }

    }

    // ── EXPORT ────────────────────────────────────────────────────────────────

    w.NexiPaymentXPay = NexiPaymentXPay;

}(window));
