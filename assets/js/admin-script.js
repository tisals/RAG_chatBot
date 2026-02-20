/**
 * JavaScript para el panel de administración
 * 
 * @package RAG_Chatbot
 */

jQuery(document).ready(function($) {
// Task 8: Persistencia de pestañas con localStorage
$('.nav-tab-wrapper a').on('click', function(e) {
    e.preventDefault();
    var tab_id = $(this).attr('href');

    $('.nav-tab-wrapper a').removeClass('nav-tab-active');
    $('.tab-content').hide();

    $(this).addClass('nav-tab-active');
    $(tab_id).show();

    // Guardar estado
    localStorage.setItem('rag_chatbot_active_tab', tab_id);
    window.location.hash = tab_id;
});

// Restaurar pestaña al cargar
var activeTab = window.location.hash || localStorage.getItem('rag_chatbot_active_tab');
if (activeTab && $(activeTab).length) {
    $('.nav-tab-wrapper a[href="' + activeTab + '"]').click();
} else {
    $('.nav-tab-wrapper a:first').click();
}

    // Activar la primera pestaña por defecto o la que esté en el hash de la URL
    var hash = window.location.hash;
    if (hash && $(hash).length) {
        $('.nav-tab-wrapper a[href="' + hash + '"]').click();
    } else {
        $('.nav-tab-wrapper a:first').click();
    }

    // Manejo del modal de añadir/editar FAQ
$('#add-knowledge-btn').on('click', function() {
    $('#knowledge-modal').show();
    $('#modal-title').text('Añadir Nueva FAQ');
    $('#knowledge-form')[0].reset(); // Limpiar formulario
    $('#knowledge-id').val(''); // Asegurarse de que el ID esté vacío para añadir
});

// IMPORTANTE: usar delegación por si la tabla se recarga dinámicamente
$('#knowledge-table').on('click', '.edit-knowledge', function () {
    var id = $(this).data('id');

    $.post(ragChatbotAdmin.ajax_url, {
        action: 'rag_get_knowledge',
        nonce: ragChatbotAdmin.nonce,
        id: id
    }, function (response) {

        console.log('RESPUESTA AJAX FAQ:', response); // para que veas qué llega en consola

        var item = null;

        // Caso estándar: success + data
        if (response && response.success && response.data) {
            item = response.data;
        }
        // Caso que estás viendo ahora: solo viene "data" sin success
        else if (response && response.data && response.data.id) {
            item = response.data;
        }

        if (item && item.id) {
            $('#knowledge-id').val(item.id || '');
            $('#knowledge-question').val(item.question || '');
            $('#knowledge-answer').val(item.answer || '');
            $('#knowledge-category').val(item.category || '');
            $('#knowledge-source').val(item.source || '');
            $('#knowledge-url').val(item.source_url || '');

            $('#modal-title').text('Editar FAQ');
            $('#knowledge-modal').fadeIn(200);
        } else {
            alert('Error al cargar la FAQ.');
            console.error('Respuesta inválida al cargar FAQ:', response);
        }
    }).fail(function (xhr, status, error) {
        alert('Error de conexión al cargar la FAQ.');
        console.error('Error AJAX FAQ:', status, error);
    });
});

    // Manejo del modal de importar FAQs
    $('#import-knowledge-btn').on('click', function() {
        $('#import-modal').show();
        $('#import-form')[0].reset(); // Limpiar formulario
    });

    // Manejo del modal de añadir/editar API
    $('#add-api-btn').on('click', function() {
        $('#api-modal').show();
        $('#api-modal-title').text('Añadir Nueva API');
        $('#api-form')[0].reset(); // Limpiar formulario
        $('#api-id').val(''); // Asegurarse de que el ID esté vacío para añadir
        $('#api-method').val('POST'); // Default
        $('#api-active').prop('checked', false); // Default
    });

    $('.edit-api').on('click', function() {
        var id = $(this).data('id');
        var row = $(this).closest('tr');
        $('#api-id').val(id);
        $('#api-name').val(row.find('td:nth-child(2)').text());
        $('#api-base-url').val(row.find('td:nth-child(3)').text());
        $('#api-method').val(row.find('td:nth-child(4)').text());
        $('#api-active').prop('checked', row.find('td:nth-child(5)').text() === 'Sí');

        $('#api-modal').show();
        $('#api-modal-title').text('Editar API');
    });


    // Cerrar modales
    $('.rag-modal-close, .cancel-modal').on('click', function() {
        $(this).closest('.rag-modal').hide();
    });

    // Cerrar modal al hacer clic fuera
    $(window).on('click', function(event) {
        if ($(event.target).hasClass('rag-modal')) {
            $(event.target).hide();
        }
    });

    // Manejo del formulario de importación
    $('#import-form').on('submit', function(e) {
        e.preventDefault();
        
        var formElement = this;
        var formData = new FormData(formElement);

        // Aseguramos que action y nonce son los correctos
        formData.delete('action');
        formData.append('action', 'rag_import_faq');
        formData.delete('nonce');
        formData.append('nonce', ragChatbotAdmin.nonce);

        // Debug útil
        console.log('--- FormData import ---');
        formData.forEach((value, key) => console.log(key, value));

        $.ajax({
            url: ragChatbotAdmin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('Response AJAX import:', response);
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Error desconocido'));
                }
            },
            error: function(xhr) {
                console.error('AJAX error import:', xhr.status, xhr.responseText);
                alert('Error al procesar la solicitud');
            }
        });
    });

    // Manejo del formulario de API
    $('#api-form').on('submit', function(e) {
        e.preventDefault();

        var formData = $(this).serialize();
        formData += '&action=rag_chatbot_save_api';
        formData += '&nonce=' + ragChatbotAdmin.nonce;

        $.post(ragChatbotAdmin.ajax_url, formData, function(response) {
            if (response.success) {
                alert(response.data);
                location.reload(); // Recargar la página para ver los cambios
            } else {
                alert('Error: ' + response.data);
            }
        });
    });

    // Manejo del formulario de Knowledge Base
    $('#knowledge-form').on('submit', function (e) {
        e.preventDefault();

        var id = $('#knowledge-id').val();
        
        // Si hay ID, es edición; si no, es creación
        var action = id ? 'rag_update_knowledge' : 'rag_add_knowledge';

        var formData = {
            action: action,
            nonce: ragChatbotAdmin.nonce,
            id: id,
            question: $('#knowledge-question').val(),
            answer: $('#knowledge-answer').val(),
            category: $('#knowledge-category').val(),
            source: $('#knowledge-source').val(),
            source_url: $('#knowledge-url').val()
        };

        $.post(ragChatbotAdmin.ajax_url, formData, function (response) {
            if (response && response.success) {
                alert('FAQ guardada correctamente.');
                location.reload(); // recarga la tabla
            } else {
                var msg = (response && response.data && response.data.message) 
                    ? response.data.message 
                    : 'Error desconocido';
                alert('Error al guardar la FAQ: ' + msg);
                console.error('Error al guardar FAQ:', response);
            }
        }).fail(function (xhr, status, error) {
            alert('Error de conexión al guardar la FAQ.');
            console.error('Error AJAX guardar FAQ:', status, error, xhr.responseText);
        });
    });

    // Manejo del botón de eliminar Knowledge
    $('.delete-knowledge').on('click', function() {
        if (confirm('¿Estás seguro de que quieres eliminar este registro?')) {
            var id = $(this).data('id');
            $.post(ragChatbotAdmin.ajax_url, {
                action: 'rag_chatbot_delete_knowledge',
                nonce: ragChatbotAdmin.nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    alert(response.data);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            });
        }
    });

    // Manejo del botón de eliminar API
    $('.delete-api').on('click', function() {
        if (confirm('¿Estás seguro de que quieres eliminar esta API?')) {
            var id = $(this).data('id');
            $.post(ragChatbotAdmin.ajax_url, {
                action: 'rag_chatbot_delete_api',
                nonce: ragChatbotAdmin.nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    alert(response.data);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            });
        }
    });

    // Manejo del botón de probar API
    $('.test-api').on('click', function() {
        var id = $(this).data('id');
        if (confirm('¿Quieres probar esta API con un mensaje de prueba?')) {
            $.post(ragChatbotAdmin.ajax_url, {
                action: 'rag_chatbot_test_api',
                nonce: ragChatbotAdmin.nonce,
                id: id,
                test_message: 'Hola, ¿cómo estás?' // Mensaje de prueba
            }, function(response) {
                if (response.success) {
                    alert('API Test Exitosa: ' + response.data);
                } else {
                    alert('API Test Fallida: ' + response.data);
                }
            });
        }
    });
    // AJAX Regenerar Token (Task 1)
    $('#rag-regenerate-token').on('click', function() {
        if (!confirm('¿Regenerar el token? La comunicación con n8n se cortará hasta que actualices el nuevo valor allá.')) return;

        var $btn = $(this);
        $btn.attr('disabled', true).text('Generando...');

        $.post(ragChatbotAdmin.ajax_url, {
            action: 'rag_regenerate_token',
            nonce: ragChatbotAdmin.nonce
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                // Mostrar temporalmente el token para que lo copien
                $('#rag_agent_token_display').val(response.data.token);
            } else {
                alert('Error: ' + response.data.message);
            }
            $btn.attr('disabled', false).text('Regenerar Token');
        });
    });
});