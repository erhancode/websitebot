/**
 * Site assistant widget.
 *
 * Self-contained: injects its own markup, reads window.BOT_WIDGET_CONFIG
 * (printed by embed/bot-embed.php), talks to api/bot/chat.php.
 *
 * Configuration (all optional):
 *   window.BOT_WIDGET_CONFIG = {
 *     brandName, assistantName, subtitle, avatar, primaryColor, accentColor,
 *     apiChat, lang, position, autoOpen, openDelay, scrollThreshold,
 *     maxHistory, maxInput, showLauncher, hideSelectors,
 *     strings: { en: { welcome: '...' } }   // per-language overrides
 *   }
 *
 * Strings support {brand} and {assistant} placeholders.
 */

(function () {
    'use strict';

    var DEFAULTS = {
        brandName: 'Your Company',
        assistantName: 'Assistant',
        subtitle: 'AI assistant',
        avatar: '/assets/img/bot-avatar.svg',
        primaryColor: '#004d40',
        accentColor: '#c6a15b',
        apiChat: '/api/bot/chat.php',
        lang: 'en',
        position: 'left',
        autoOpen: false,
        openDelay: 25000,
        scrollThreshold: 0.5,
        maxHistory: 6,
        maxInput: 1000,
        showLauncher: true,
        hideSelectors: [],
        strings: {}
    };

    var I18N = {
            en: { welcome: "Hi, I'm {assistant} from {brand}. How can I help you today?", contactMsg: "Thanks. What is the best way for our team to reach you?", placeholder: "Type your message...", thanks: "Thanks, your message is with our team. We will get back to you shortly.", close: "Close", send: "Send" },
            tr: { welcome: "Merhaba, ben {brand} ekibinden {assistant}. Size nasıl yardımcı olabilirim?", contactMsg: "Teşekkürler. Ekibimizin size ulaşabileceği en iyi yol nedir?", placeholder: "Mesajınızı yazın...", thanks: "Teşekkürler, mesajınız ekibimize iletildi. En kısa sürede size dönüş yapacağız.", close: "Kapat", send: "Gönder" },
            de: { welcome: "Hallo, ich bin {assistant} vom Team {brand}. Wie kann ich Ihnen helfen?", contactMsg: "Danke. Wie erreichen wir Sie am besten?", placeholder: "Nachricht eingeben...", thanks: "Danke, Ihre Nachricht liegt bei unserem Team. Wir melden uns in Kürze.", close: "Schließen", send: "Senden" },
            fr: { welcome: "Bonjour, je suis {assistant} de l'équipe {brand}. Comment puis-je vous aider ?", contactMsg: "Merci. Quel est le meilleur moyen de vous joindre ?", placeholder: "Votre message...", thanks: "Merci, votre message a bien été transmis à notre équipe. Nous vous répondrons rapidement.", close: "Fermer", send: "Envoyer" },
            es: { welcome: "Hola, soy {assistant} del equipo de {brand}. ¿En qué puedo ayudarte?", contactMsg: "Gracias. ¿Cuál es la mejor forma de contactarte?", placeholder: "Escribe tu mensaje...", thanks: "Gracias, tu mensaje ya está con nuestro equipo. Te responderemos en breve.", close: "Cerrar", send: "Enviar" },
            it: { welcome: "Ciao, sono {assistant} del team {brand}. Come posso aiutarti?", contactMsg: "Grazie. Qual è il modo migliore per contattarti?", placeholder: "Scrivi il tuo messaggio...", thanks: "Grazie, il tuo messaggio è arrivato al nostro team. Ti risponderemo a breve.", close: "Chiudi", send: "Invia" },
            nl: { welcome: "Hallo, ik ben {assistant} van het team van {brand}. Hoe kan ik je helpen?", contactMsg: "Bedankt. Hoe kunnen we je het beste bereiken?", placeholder: "Typ je bericht...", thanks: "Bedankt, je bericht is bij ons team. We nemen snel contact met je op.", close: "Sluiten", send: "Verzenden" },
            pl: { welcome: "Cześć, jestem {assistant} z zespołu {brand}. W czym mogę pomóc?", contactMsg: "Dziękujemy. Jak najlepiej się z Tobą skontaktować?", placeholder: "Napisz wiadomość...", thanks: "Dziękujemy, Twoja wiadomość trafiła do naszego zespołu. Wkrótce się odezwiemy.", close: "Zamknij", send: "Wyślij" },
            cs: { welcome: "Dobrý den, jsem {assistant} z týmu {brand}. Jak vám mohu pomoci?", contactMsg: "Děkujeme. Jak vás nejlépe kontaktovat?", placeholder: "Napište zprávu...", thanks: "Děkujeme, vaše zpráva je u našeho týmu. Brzy se vám ozveme.", close: "Zavřít", send: "Odeslat" },
            da: { welcome: "Hej, jeg er {assistant} fra {brand}-teamet. Hvordan kan jeg hjælpe?", contactMsg: "Tak. Hvordan kan vi bedst kontakte dig?", placeholder: "Skriv din besked...", thanks: "Tak, din besked er hos vores team. Vi vender tilbage hurtigst muligt.", close: "Luk", send: "Send" },
            sv: { welcome: "Hej, jag är {assistant} från {brand}-teamet. Hur kan jag hjälpa dig?", contactMsg: "Tack. Hur når vi dig bäst?", placeholder: "Skriv ditt meddelande...", thanks: "Tack, ditt meddelande är hos vårt team. Vi återkommer snart.", close: "Stäng", send: "Skicka" },
            no: { welcome: "Hei, jeg er {assistant} fra {brand}-teamet. Hvordan kan jeg hjelpe?", contactMsg: "Takk. Hvordan kan vi best kontakte deg?", placeholder: "Skriv meldingen din...", thanks: "Takk, meldingen din er hos teamet vårt. Vi tar kontakt snart.", close: "Lukk", send: "Send" },
            fi: { welcome: "Hei, olen {assistant} {brand}-tiimistä. Kuinka voin auttaa?", contactMsg: "Kiitos. Miten voimme parhaiten tavoittaa sinut?", placeholder: "Kirjoita viestisi...", thanks: "Kiitos, viestisi on tiimillämme. Otamme sinuun yhteyttä pian.", close: "Sulje", send: "Lähetä" },
            pt: { welcome: "Olá, sou {assistant} da equipa {brand}. Como posso ajudar?", contactMsg: "Obrigado. Qual é a melhor forma de o contactarmos?", placeholder: "Escreva a sua mensagem...", thanks: "Obrigado, a sua mensagem chegou à nossa equipa. Entraremos em contacto em breve.", close: "Fechar", send: "Enviar" },
            ro: { welcome: "Bună, sunt {assistant} din echipa {brand}. Cu ce vă pot ajuta?", contactMsg: "Mulțumim. Care este cea mai bună cale de a vă contacta?", placeholder: "Scrieți mesajul...", thanks: "Mulțumim, mesajul dvs. a ajuns la echipa noastră. Vă vom contacta în curând.", close: "Închide", send: "Trimite" },
            hu: { welcome: "Szia, {assistant} vagyok a {brand} csapatából. Miben segíthetek?", contactMsg: "Köszönjük. Hogyan érhetünk el legkönnyebben?", placeholder: "Írja be üzenetét...", thanks: "Köszönjük, üzenete megérkezett csapatunkhoz. Hamarosan jelentkezünk.", close: "Bezárás", send: "Küldés" },
            el: { welcome: "Γεια σας, είμαι {assistant} από την ομάδα του {brand}. Πώς μπορώ να σας βοηθήσω;", contactMsg: "Ευχαριστούμε. Ποιος είναι ο καλύτερος τρόπος να επικοινωνήσουμε μαζί σας;", placeholder: "Γράψτε το μήνυμά σας...", thanks: "Ευχαριστούμε, το μήνυμά σας έφτασε στην ομάδα μας. Θα επικοινωνήσουμε σύντομα μαζί σας.", close: "Κλείσιμο", send: "Αποστολή" },
            ru: { welcome: "Здравствуйте! Я {assistant} из {brand}. Чем могу помочь?", contactMsg: "Спасибо. Как нам лучше с вами связаться?", placeholder: "Введите сообщение...", thanks: "Спасибо, ваше сообщение передано нашей команде. Мы скоро свяжемся с вами.", close: "Закрыть", send: "Отправить" },
            bg: { welcome: "Здравейте, аз съм {assistant} от екипа на {brand}. С какво мога да помогна?", contactMsg: "Благодаря. Как е най-добре да се свържем с вас?", placeholder: "Напишете съобщение...", thanks: "Благодаря, съобщението ви е при нашия екип. Ще се свържем с вас скоро.", close: "Затвори", send: "Изпрати" },
            sr: { welcome: "Zdravo, ja sam {assistant} iz tima {brand}. Kako mogu da pomognem?", contactMsg: "Hvala. Kako možemo da vas kontaktiramo?", placeholder: "Napišite poruku...", thanks: "Hvala, vaša poruka je stigla do našeg tima. Kontaktiraćemo vas uskoro.", close: "Zatvori", send: "Pošalji" },
            sq: { welcome: "Përshëndetje, jam {assistant} nga ekipi i {brand}. Si mund t'ju ndihmoj?", contactMsg: "Faleminderit. Cila është mënyra më e mirë për t'ju kontaktuar?", placeholder: "Shkruani mesazhin...", thanks: "Faleminderit, mesazhi juaj mbërriti në ekipin tonë. Do t'ju kontaktojmë së shpejti.", close: "Mbyll", send: "Dërgo" },
            az: { welcome: "Salam, mən {brand} komandasından {assistant}. Sizə necə kömək edə bilərəm?", contactMsg: "Təşəkkürlər. Sizinlə necə əlaqə saxlayaq?", placeholder: "Mesajınızı yazın...", thanks: "Təşəkkürlər, mesajınız komandamıza çatdı. Tezliklə sizinlə əlaqə saxlayacağıq.", close: "Bağla", send: "Göndər" },
            ky: { welcome: "Саламатсызбы! Мен {brand} командасынан {assistant}мын. Сизге кантип жардам бере алам?", contactMsg: "Рахмат. Биз сиз менен кантип байланышсак болот?", placeholder: "Билдирүүңүздү жазыңыз...", thanks: "Рахмат, билдирүүңүз командабызга жетти. Жакында сиз менен байланышабыз.", close: "Жабуу", send: "Жөнөтүү" },
            kk: { welcome: "Сәлеметсіз бе! Мен {brand} командасынан {assistant}мын. Қалай көмектесе аламын?", contactMsg: "Рақмет. Сізбен қалай байланыссақ болады?", placeholder: "Хабарламаңызды жазыңыз...", thanks: "Рақмет, хабарламаңыз командамызға жетті. Жақында сізбен байланысамыз.", close: "Жабу", send: "Жіберу" },
            uz: { welcome: "Salom, men {brand} jamoasidan {assistant}. Sizga qanday yordam bera olaman?", contactMsg: "Rahmat. Siz bilan qanday bog'lansak bo'ladi?", placeholder: "Xabaringizni yozing...", thanks: "Rahmat, xabaringiz jamoamizga yetib bordi. Tez orada siz bilan bog'lanamiz.", close: "Yopish", send: "Yuborish" },
            ar: { welcome: "مرحباً، أنا {assistant} من فريق {brand}. كيف يمكنني مساعدتك؟", contactMsg: "شكراً لك. ما أفضل طريقة للتواصل معك؟", placeholder: "اكتب رسالتك...", thanks: "شكراً لك، وصلت رسالتك إلى فريقنا وسنتواصل معك قريباً.", rtl: true, close: "إغلاق", send: "إرسال" },
            fa: { welcome: "سلام، من {assistant} از تیم {brand} هستم. چطور می‌توانم کمک کنم؟", contactMsg: "ممنون. بهترین راه تماس با شما چیست؟", placeholder: "پیام خود را بنویسید...", thanks: "ممنون، پیام شما به تیم ما رسید. به‌زودی با شما تماس می‌گیریم.", rtl: true, close: "بستن", send: "ارسال" },
            'fr-ma': { welcome: "Bonjour, je suis {assistant} de l'équipe {brand}. Comment puis-je vous aider ?", contactMsg: "Merci. Quel est le meilleur moyen de vous joindre ?", placeholder: "Votre message...", thanks: "Merci, votre message a bien été transmis à notre équipe. Nous vous répondrons rapidement.", close: "Fermer", send: "Envoyer" }
        };

    var STORAGE_KEY = 'botw_state_v1';

    function mergeConfig() {
        var incoming = (window.BOT_WIDGET_CONFIG && typeof window.BOT_WIDGET_CONFIG === 'object')
            ? window.BOT_WIDGET_CONFIG
            : {};
        var out = {};
        for (var key in DEFAULTS) {
            if (Object.prototype.hasOwnProperty.call(DEFAULTS, key)) {
                out[key] = Object.prototype.hasOwnProperty.call(incoming, key) && incoming[key] !== undefined && incoming[key] !== null
                    ? incoming[key]
                    : DEFAULTS[key];
            }
        }
        return out;
    }

    var Bot = {
        config: mergeConfig(),
        state: {
            isOpen: false,
            triggered: false,
            lang: 'en',
            messages: [],
            isHalted: false,
            userManuallyTyped: false
        },
        i18n: I18N,

        // ---------------------------------------------------------- helpers
        strings: function () {
            return this.i18n[this.state.lang] || this.i18n.en;
        },

        fill: function (text) {
            var cfg = this.config;
            return String(text || '')
                .replace(/\{brand\}/g, cfg.brandName)
                .replace(/\{assistant\}/g, cfg.assistantName);
        },

        el: function (selector) {
            return document.querySelector('#botw-widget ' + selector);
        },

        // ------------------------------------------------------------ setup
        init: function () {
            this.loadState();
            this.detectLanguage();
            this.render();
            this.applyTheme();
            this.bindEvents();
            this.setupTriggers();
            if (this.state.messages.length > 0) {
                this.restoreChat();
            }
        },

        loadState: function () {
            try {
                var saved = localStorage.getItem(STORAGE_KEY);
                if (saved) {
                    var parsed = JSON.parse(saved);
                    if (parsed && typeof parsed === 'object') {
                        this.state = Object.assign({}, this.state, parsed, { isOpen: false });
                    }
                }
            } catch (e) { /* storage blocked or corrupt */ }
        },

        saveState: function () {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(this.state));
            } catch (e) { /* ignore quota / private mode */ }
        },

        detectLanguage: function () {
            // 1. Explicit language from the host page.
            var explicit = window.BOT_LANG || this.config.lang;
            if (explicit) {
                explicit = String(explicit).toLowerCase();
                if (this.i18n[explicit]) {
                    this.state.lang = explicit;
                    return;
                }
            }

            if (this.state.userManuallyTyped) {
                return;
            }

            // 2. First URL segment (multi-language sites like /de/...).
            var segments = window.location.pathname.toLowerCase().split('/').filter(Boolean);
            for (var i = 0; i < segments.length; i++) {
                if (this.i18n[segments[i]]) {
                    this.state.lang = segments[i];
                    return;
                }
            }

            // 3. Browser language.
            var browser = (navigator.language || 'en').toLowerCase().split('-')[0];
            if (this.i18n[browser]) {
                this.state.lang = browser;
                return;
            }

            // 4. <html lang>.
            var htmlLang = (document.documentElement.lang || '').toLowerCase().replace(/^sr-latn$/, 'sr');
            this.state.lang = this.i18n[htmlLang] ? htmlLang : 'en';
        },

        // ----------------------------------------------------------- render
        render: function () {
            var old = document.getElementById('botw-widget');
            if (old) {
                old.remove();
            }

            var s = this.strings();
            var root = document.createElement('div');
            root.id = 'botw-widget';
            root.setAttribute('data-position', this.config.position === 'right' ? 'right' : 'left');
            if (s.rtl) {
                root.setAttribute('dir', 'rtl');
            }

            root.innerHTML = ''
                + '<button type="button" class="botw-launcher" aria-label="' + this.esc(this.config.assistantName) + '">'
                + '  <img src="' + this.esc(this.config.avatar) + '" alt="">'
                + '  <span class="botw-badge" hidden>1</span>'
                + '</button>'
                + '<section class="botw-window" role="dialog" aria-modal="false" aria-label="' + this.esc(this.config.brandName) + '">'
                + '  <header class="botw-header">'
                + '    <img class="botw-avatar" src="' + this.esc(this.config.avatar) + '" alt="">'
                + '    <div class="botw-title">'
                + '      <h4>' + this.esc(this.config.brandName) + '</h4>'
                + '      <span>' + this.esc(this.config.assistantName + ' - ' + this.config.subtitle) + '</span>'
                + '    </div>'
                + '    <button type="button" class="botw-close" aria-label="' + this.esc(s.close || 'Close') + '">&times;</button>'
                + '  </header>'
                + '  <div class="botw-messages" id="botw-messages"></div>'
                + '  <form class="botw-input-area">'
                + '    <input type="text" id="botw-input" autocomplete="off" aria-label="' + this.esc(this.fill(s.placeholder)) + '" placeholder="' + this.esc(this.fill(s.placeholder)) + '">'
                + '    <button type="submit" class="botw-send" aria-label="' + this.esc(s.send || 'Send') + '">&#10148;</button>'
                + '  </form>'
                + '</section>';

            if (!this.config.showLauncher) {
                root.querySelector('.botw-launcher').hidden = true;
            }

            document.body.appendChild(root);
        },

        esc: function (value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },

        applyTheme: function () {
            var root = document.getElementById('botw-widget');
            if (!root) {
                return;
            }
            root.style.setProperty('--botw-primary', this.config.primaryColor);
            root.style.setProperty('--botw-accent', this.config.accentColor);
        },

        restoreChat: function () {
            var container = document.getElementById('botw-messages');
            if (!container) {
                return;
            }
            var self = this;
            container.innerHTML = '';
            this.state.messages.forEach(function (msg) {
                container.appendChild(self.messageNode(msg.role, msg.text));
            });
            container.scrollTop = container.scrollHeight;
        },

        // ----------------------------------------------------------- events
        bindEvents: function () {
            var self = this;
            var root = document.getElementById('botw-widget');

            root.querySelector('.botw-launcher').addEventListener('click', function () {
                self.toggleWindow();
            });
            root.querySelector('.botw-close').addEventListener('click', function (event) {
                event.stopPropagation();
                self.toggleWindow(false);
            });
            root.querySelector('.botw-input-area').addEventListener('submit', function (event) {
                event.preventDefault();
                self.handleSendMessage();
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && self.state.isOpen) {
                    self.toggleWindow(false);
                }
            });
        },

        setupTriggers: function () {
            if (!this.config.autoOpen) {
                return;
            }
            if (this.state.triggered || this.state.messages.length > 0) {
                return;
            }
            var self = this;
            window.setTimeout(function () {
                self.autoOpen();
            }, this.config.openDelay);

            window.addEventListener('scroll', function () {
                var height = document.documentElement.scrollHeight - window.innerHeight;
                if (height > 0 && window.scrollY / height > self.config.scrollThreshold) {
                    self.autoOpen();
                }
            }, { passive: true });
        },

        autoOpen: function () {
            if (this.state.triggered || this.state.messages.length > 0) {
                return;
            }
            this.state.triggered = true;
            this.pushMessage('bot', this.fill(this.strings().welcome));
            this.toggleWindow(true);
        },

        toggleWindow: function (force) {
            var root = document.getElementById('botw-widget');
            if (!root) {
                return;
            }
            var win = root.querySelector('.botw-window');
            var badge = root.querySelector('.botw-badge');

            this.state.isOpen = (force === undefined) ? !this.state.isOpen : !!force;

            if (this.state.isOpen) {
                win.classList.add('is-open');
                document.body.classList.add('botw-active');
                if (badge) {
                    badge.hidden = true;
                }
                this.togglePageChrome(false);
            } else {
                win.classList.remove('is-open');
                document.body.classList.remove('botw-active');
                this.togglePageChrome(true);
            }
            this.saveState();
        },

        /** Hide (or restore) page elements that would sit under the chat window. */
        togglePageChrome: function (visible) {
            var selectors = this.config.hideSelectors || [];
            selectors.forEach(function (selector) {
                var items = document.querySelectorAll(selector);
                for (var i = 0; i < items.length; i++) {
                    var el = items[i];
                    if (visible) {
                        el.style.opacity = '';
                        el.style.visibility = '';
                        el.style.pointerEvents = '';
                    } else {
                        el.style.opacity = '0';
                        el.style.visibility = 'hidden';
                        el.style.pointerEvents = 'none';
                    }
                }
            });
        },

        // ---------------------------------------------------------- messages
        messageNode: function (role, text) {
            var div = document.createElement('div');
            div.className = 'botw-msg ' + (role === 'user' ? 'user' : 'bot');
            div.textContent = text;
            return div;
        },

        pushMessage: function (role, text) {
            this.hideTyping();
            var container = document.getElementById('botw-messages');
            if (!container) {
                return;
            }
            container.appendChild(this.messageNode(role, text));
            container.scrollTop = container.scrollHeight;

            this.state.messages.push({ role: role, text: text });
            if (role === 'user') {
                this.state.userManuallyTyped = true;
            }
            this.saveState();
        },

        showTyping: function () {
            var container = document.getElementById('botw-messages');
            if (!container || document.getElementById('botw-typing')) {
                return;
            }
            var div = document.createElement('div');
            div.id = 'botw-typing';
            div.className = 'botw-typing';
            div.innerHTML = '<span></span><span></span><span></span>';
            container.appendChild(div);
            container.scrollTop = container.scrollHeight;
        },

        hideTyping: function () {
            var el = document.getElementById('botw-typing');
            if (el) {
                el.remove();
            }
        },

        // ------------------------------------------------------------- send
        handleSendMessage: function () {
            var input = document.getElementById('botw-input');
            if (!input) {
                return;
            }
            var text = input.value.trim().slice(0, this.config.maxInput);
            if (!text) {
                return;
            }

            this.pushMessage('user', text);
            input.value = '';

            var self = this;

            if (this.state.isHalted) {
                window.setTimeout(function () {
                    self.pushMessage('bot', self.fill(self.strings().thanks));
                }, 800);
                return;
            }

            this.showTyping();
            this.requestReply(text);
        },

        requestReply: function (text) {
            var self = this;

            var history = this.state.messages
                .slice(0, -1) // the message being sent is appended on the server
                .filter(function (m, index) {
                    return !(index === 0 && m.role === 'bot');
                })
                .slice(-this.config.maxHistory)
                .map(function (m) {
                    return { role: m.role === 'bot' ? 'assistant' : 'user', content: m.text };
                });

            fetch(this.config.apiChat, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message: text,
                    lang: this.state.lang,
                    url: window.location.href,
                    title: document.title,
                    history: history
                })
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        return { error: 'bad_response' };
                    });
                })
                .then(function (data) {
                    self.hideTyping();
                    if (data && data.reply) {
                        self.pushMessage('bot', data.reply);
                        if (data.is_final) {
                            self.state.isHalted = true;
                        }
                    } else {
                        self.pushMessage('bot', self.fill(self.strings().thanks));
                    }
                })
                .catch(function (error) {
                    self.hideTyping();
                    if (window.console && console.error) {
                        console.error('Bot widget request failed:', error);
                    }
                    self.pushMessage('bot', self.fill(self.strings().contactMsg));
                });
        }
    };

    window.BotWidget = Bot;

    function boot() {
        Bot.init();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
