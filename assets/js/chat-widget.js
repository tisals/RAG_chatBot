/**
 * JavaScript para el widget de chat frontend - Versión Híbrida RAG
 */

(function($) {
    'use strict';
    
    var chatWindow = $('#rag-chatbot-window');
    var messagesContainer = $('#rag-chatbot-messages');
    var chatInput = $('#rag-chatbot-input');
    var isWaitingForResponse = false;
    
    function init() {
        applyStyling();
        addBotMessage(ragChatbotWidget.welcome_message);
        
        $('#rag-chatbot-toggle').on('click', toggleChat);
        $('#rag-chatbot-close').on('click', toggleChat);
        $('#rag-chatbot-send').on('click', sendMessage);
        
        chatInput.on('keypress', function(e) {
            if (e.which === 13) sendMessage();
        });

        // Delegación de eventos para los botones de FAQs y opciones
        messagesContainer.on('click', '.rag-faq-option', function() {
            var faqId = $(this).data('id');
            var question = $(this).text();
            selectFaq(faqId, question);
        });

        messagesContainer.on('click', '.rag-other-option', function() {
            handleOtherOption();
        });
    }
    
    function applyStyling() {
        var primaryColor = ragChatbotWidget.primary_color;
        $('.chat-message.user .message-content').css('background-color', primaryColor);
    }
    
    function toggleChat() {
        chatWindow.fadeToggle(300);
        if (chatWindow.is(':visible')) chatInput.focus();
    }
    
    // Enviar mensaje de texto libre
    function sendMessage() {
        if (isWaitingForResponse) return;
        var message = chatInput.val().trim();
        if (message === '') return;

        addUserMessage(message);
        chatInput.val('');
        processRequest({
            action: 'rag_chatbot_send',
            message: message
        });
    }

    // Seleccionar una FAQ de la lista
    function selectFaq(id, question) {
        if (isWaitingForResponse) return;
        addUserMessage(question);
        processRequest({
            action: 'rag_chatbot_send',
            type: 'select_faq',
            faq_id: id
        });
    }

    // Seleccionar "Otra opción / Agente"
    function handleOtherOption() {
        if (isWaitingForResponse) return;
        addUserMessage("Quiero hablar con un agente o ver otras opciones");
        processRequest({
            action: 'rag_chatbot_send',
            type: 'other_option'
        });
    }

    // Función central de AJAX
    function processRequest(data) {
        showTypingIndicator();
        isWaitingForResponse = true;

        // Añadimos el nonce a todos los requests
        data.nonce = ragChatbotWidget.nonce;

        $.ajax({
            url: ragChatbotWidget.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                hideTypingIndicator();

                if (response && response.success && response.data) {
                    var data = response.data;

                    // CASO 1: estructura nueva pensada (response / suggestions)
                    if (data.response || data.suggestions) {
                        renderBotResponse(data);
                    }
                    // CASO 2: estructura actual de "no_results"
                    else if (data.message) {
                        addBotMessage(data.message);
                    }
                    // CASO 3: estructura actual de "suggestions" (lo que vimos en el screenshot)
                    else if (data.type === 'suggestions' && Array.isArray(data.suggested_questions)) {
                        renderLegacySuggestions(data);
                    }
                    // CASO 4: formato actual de "answer" (al seleccionar una FAQ)
                    else if (data.type === 'answer' && data.answer) {
                        renderLegacyAnswer(data);
                    }
                    else {
                        addBotMessage('No se pudo interpretar la respuesta del servidor.');
                    }
                } else {
                    addBotMessage('Lo siento, hubo un error. Por favor, inténtalo de nuevo.');
                }

                isWaitingForResponse = false;
            },
        });
    }
    // Renderizar la respuesta de una FAQ seleccionada con formato LEGACY:
    // { type: "answer", answer: "texto...", source: "knowledge_base", ... }
    function renderLegacyAnswer(data) {
        // Aquí sí queremos permitir HTML (enlaces, saltos de línea, etc.)
        var answerHtml = data.answer;

        var messageHtml = '<div class="chat-message bot">' +
            '<div class="message-content">' + answerHtml + '</div>' +
        '</div>';

        messagesContainer.append(messageHtml);
        scrollToBottom();
    }
    
    // Renderizar sugerencias con el formato LEGACY del backend actual:
    // { type: "suggestions", suggested_questions: [{id, question, ...}], has_other_option: true }
    function renderLegacySuggestions(data) {
        // Mensaje principal del bot
        addBotMessage('He encontrado algunas opciones que podrían responder tu pregunta:');

        if (Array.isArray(data.suggested_questions) && data.suggested_questions.length > 0) {
            var optionsHtml = '<div class="chat-options-container">';

            data.suggested_questions.forEach(function(item) {
                optionsHtml += '<button class="rag-faq-option" data-id="' + item.id + '">'
                    + escapeHtml(item.question) +
                    '</button>';
            });

            // Si el backend indica que hay opción de "otra opción"
            if (data.has_other_option) {
                optionsHtml += '<button class="rag-other-option">Ninguna de las anteriores / Hablar con humano</button>';
            }

            optionsHtml += '</div>';
            messagesContainer.append(optionsHtml);
            scrollToBottom();
        } else {
            addBotMessage('No hay sugerencias disponibles en este momento.');
        }
    }
    // Renderiza la respuesta según su estructura
    function renderBotResponse(data) {
        // 1. Si hay texto principal, lo mostramos
        if (data.response) {
            addBotMessage(data.response);
        }

        // 2. Si hay sugerencias (FAQs), creamos botones
        if (data.suggestions && data.suggestions.length > 0) {
            var optionsHtml = '<div class="chat-options-container">';
            data.suggestions.forEach(function(item) {
                optionsHtml += '<button class="rag-faq-option" data-id="' + item.id + '">' + escapeHtml(item.question) + '</button>';
            });
            
            // Añadir siempre la opción de "Ninguna de las anteriores" si hay sugerencias
            optionsHtml += '<button class="rag-other-option">Ninguna de las anteriores / Hablar con humano</button>';
            optionsHtml += '</div>';
            messagesContainer.append(optionsHtml);
            scrollToBottom();
        }
    }
    
    function addUserMessage(message) {
        var messageHtml = '<div class="chat-message user"><div class="message-content">' + escapeHtml(message) + '</div></div>';
        messagesContainer.append(messageHtml);
        scrollToBottom();
    }
    
    function addBotMessage(message) {
        // Permitimos HTML en mensajes del bot para enlaces o formato básico
        var messageHtml = '<div class="chat-message bot"><div class="message-content">' + message + '</div></div>';
        messagesContainer.append(messageHtml);
        scrollToBottom();
    }
    
    function showTypingIndicator() {
        var typingHtml = '<div class="chat-message bot typing-message"><div class="typing-indicator"><span></span><span></span><span></span></div></div>';
        messagesContainer.append(typingHtml);
        scrollToBottom();
    }
    
    function hideTypingIndicator() {
        $('.typing-message').remove();
    }
    
    function scrollToBottom() {
        messagesContainer.animate({ scrollTop: messagesContainer[0].scrollHeight }, 300);
    }
    
    function escapeHtml(text) {
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    $(document).ready(function() {
        init();
    });

})(jQuery);