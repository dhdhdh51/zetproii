/**
 * BharatAI Business OS - Embeddable AI Chatbot Widget
 *
 * Usage (on any external website):
 *   <script src="https://yourdomain.com/assets/js/chat-widget.js" data-widget-key="cbw_xxx"></script>
 *
 * This script is fully self-contained (no dependencies) and communicates
 * only with the public /api/chat/widget-*.php endpoints via fetch. It
 * never has access to any API keys - all AI calls happen server-side.
 */
(function () {
    'use strict';

    var scriptTag = document.currentScript || (function () {
        var scripts = document.getElementsByTagName('script');
        return scripts[scripts.length - 1];
    })();

    var widgetKey = scriptTag.getAttribute('data-widget-key');
    if (!widgetKey) {
        console.error('[BharatAI Chat Widget] Missing data-widget-key attribute.');
        return;
    }

    var apiBase = (function () {
        var src = scriptTag.src;
        var url = new URL(src);
        return url.origin;
    })();

    var state = { sessionUuid: null, botName: 'AI Assistant', primaryColor: '#4f46e5', leadCollectionEnabled: false, requiredFields: [], leadCaptured: false };

    var css = `
        .bharatai-widget-launcher { position: fixed; bottom: 20px; right: 20px; width: 58px; height: 58px; border-radius: 50%;
            background: ${'{{color}}'}; color: #fff; display: flex; align-items: center; justify-content: center; cursor: pointer;
            box-shadow: 0 6px 20px rgba(0,0,0,0.25); z-index: 999998; border: none; }
        .bharatai-widget-panel { position: fixed; bottom: 90px; right: 20px; width: 340px; max-width: calc(100vw - 40px); height: 480px;
            max-height: calc(100vh - 120px); background: #fff; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: none; flex-direction: column; overflow: hidden; z-index: 999999; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .bharatai-widget-panel.open { display: flex; }
        .bharatai-widget-header { background: ${'{{color}}'}; color: #fff; padding: 14px 16px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
        .bharatai-widget-messages { flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 8px; background: #f7f7fb; }
        .bharatai-bubble { max-width: 80%; padding: 9px 13px; border-radius: 12px; font-size: 13.5px; line-height: 1.4; }
        .bharatai-bubble.bot { background: #fff; align-self: flex-start; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .bharatai-bubble.user { background: ${'{{color}}'}; color: #fff; align-self: flex-end; }
        .bharatai-widget-input-row { display: flex; gap: 6px; padding: 10px; border-top: 1px solid #eee; }
        .bharatai-widget-input-row input { flex: 1; border: 1px solid #ddd; border-radius: 8px; padding: 8px 10px; font-size: 13.5px; }
        .bharatai-widget-input-row button { background: ${'{{color}}'}; color: #fff; border: none; border-radius: 8px; padding: 8px 12px; cursor: pointer; }
        .bharatai-close-btn { background: none; border: none; color: #fff; cursor: pointer; font-size: 18px; }
        .bharatai-lead-form { padding: 12px; background: #fff; border-top: 1px solid #eee; }
        .bharatai-lead-form input { width: 100%; margin-bottom: 6px; padding: 8px 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 13px; box-sizing: border-box; }
        .bharatai-lead-form button { width: 100%; background: ${'{{color}}'}; color: #fff; border: none; border-radius: 8px; padding: 9px; cursor: pointer; font-weight: 600; }
    `;

    function injectStyles(color) {
        var style = document.createElement('style');
        style.textContent = css.split('{{color}}').join(color);
        document.head.appendChild(style);
    }

    function createDom() {
        var launcher = document.createElement('button');
        launcher.className = 'bharatai-widget-launcher';
        launcher.innerHTML = '💬';
        launcher.setAttribute('aria-label', 'Open chat');

        var panel = document.createElement('div');
        panel.className = 'bharatai-widget-panel';
        panel.innerHTML =
            '<div class="bharatai-widget-header"><span id="bharatai-bot-name">Chat</span><button class="bharatai-close-btn" id="bharatai-close">&times;</button></div>' +
            '<div class="bharatai-widget-messages" id="bharatai-messages"></div>' +
            '<div class="bharatai-widget-input-row"><input id="bharatai-input" placeholder="Type a message..."><button id="bharatai-send">&#9658;</button></div>';

        document.body.appendChild(launcher);
        document.body.appendChild(panel);

        launcher.addEventListener('click', function () {
            panel.classList.toggle('open');
            if (panel.classList.contains('open') && !state.sessionUuid) {
                initSession();
            }
        });
        panel.querySelector('#bharatai-close').addEventListener('click', function () {
            panel.classList.remove('open');
        });
        panel.querySelector('#bharatai-send').addEventListener('click', sendMessage);
        panel.querySelector('#bharatai-input').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') sendMessage();
        });
    }

    function addBubble(text, who) {
        var messages = document.getElementById('bharatai-messages');
        var bubble = document.createElement('div');
        bubble.className = 'bharatai-bubble ' + who;
        bubble.textContent = text;
        messages.appendChild(bubble);
        messages.scrollTop = messages.scrollHeight;
    }

    function showLeadForm() {
        var messages = document.getElementById('bharatai-messages');
        var wrap = document.createElement('div');
        wrap.className = 'bharatai-lead-form';
        wrap.innerHTML =
            '<input id="bharatai-lead-name" placeholder="Your name">' +
            '<input id="bharatai-lead-email" placeholder="Your email">' +
            '<input id="bharatai-lead-phone" placeholder="Your phone (optional)">' +
            '<button id="bharatai-lead-submit">Send my details</button>';
        messages.appendChild(wrap);
        messages.scrollTop = messages.scrollHeight;

        wrap.querySelector('#bharatai-lead-submit').addEventListener('click', async function () {
            var name = document.getElementById('bharatai-lead-name').value.trim();
            var email = document.getElementById('bharatai-lead-email').value.trim();
            var phone = document.getElementById('bharatai-lead-phone').value.trim();
            if (!name) return;

            try {
                var res = await fetch(apiBase + '/api/chat/widget-lead.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ key: widgetKey, session_uuid: state.sessionUuid, name: name, email: email, phone: phone }),
                });
                var json = await res.json();
                wrap.remove();
                addBubble(json.success ? 'Thanks! We will get back to you shortly.' : (json.message || 'Something went wrong.'), 'bot');
                state.leadCaptured = true;
            } catch (err) {
                addBubble('Something went wrong submitting your details.', 'bot');
            }
        });
    }

    async function initSession() {
        try {
            var res = await fetch(apiBase + '/api/chat/widget-init.php?key=' + encodeURIComponent(widgetKey) + '&url=' + encodeURIComponent(window.location.href));
            var json = await res.json();
            if (!json.success) {
                addBubble('This chat is currently unavailable.', 'bot');
                return;
            }
            var data = json.data;
            state.sessionUuid = data.session_uuid;
            state.botName = data.bot_name;
            state.leadCollectionEnabled = data.lead_collection_enabled;
            document.getElementById('bharatai-bot-name').textContent = data.bot_name;
            addBubble(data.welcome_message, 'bot');
        } catch (err) {
            addBubble('Unable to connect. Please try again later.', 'bot');
        }
    }

    async function sendMessage() {
        var input = document.getElementById('bharatai-input');
        var text = input.value.trim();
        if (!text || !state.sessionUuid) return;
        addBubble(text, 'user');
        input.value = '';

        try {
            var res = await fetch(apiBase + '/api/chat/widget-message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ key: widgetKey, session_uuid: state.sessionUuid, message: text }),
            });
            var json = await res.json();
            addBubble(json.success ? json.data.reply : (json.message || 'Sorry, something went wrong.'), 'bot');

            if (state.leadCollectionEnabled && !state.leadCaptured && /interested|price|quote|call me|contact/i.test(text)) {
                showLeadForm();
            }
        } catch (err) {
            addBubble('Connection error. Please try again.', 'bot');
        }
    }

    function boot() {
        injectStyles('#4f46e5');
        createDom();
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        boot();
    } else {
        document.addEventListener('DOMContentLoaded', boot);
    }
})();
