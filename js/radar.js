(function($) {
    var cfg = drData;

    function appendMatch(m) {
        var row = $(`<tr>
            <td>${m.p1.title} <small>(#${m.p1.id})</small></td>
            <td>
                <strong>${m.p2.title}</strong> <small>(#${m.p2.id})</small>
                <div class="row-actions">
                    <span class="trash"><a class="dr-trash-link" data-id="${m.p2.id}">Cestina Ora</a></span>
                </div>
            </td>
            <td><mark>${m.reason}</mark></td>
        </tr>`);
        $('#dr-tbody').append(row);
        $('#dr-table').show();
    }

    // Gestione Cestina Asincrono
    $(document).on('click', '.dr-trash-link', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var postId = $btn.data('id');
        var $row = $btn.closest('tr');

        if (!confirm('Spostare questo post nel cestino?')) return;

        $.post(cfg.ajaxUrl, {
            action: 'dr_trash_post',
            nonce: cfg.nonce,
            post_id: postId
        }, function(res) {
            if (res.success) {
                $row.addClass('dr-row-fading');
                setTimeout(() => $row.remove(), 500);
            } else {
                alert(res.data || cfg.labels.error);
            }
        });
    });

    // Avvio scansione
    $('#dr-start').on('click', function() {
        // Blocca il bottone per evitare click multipli
        $(this).prop('disabled', true).text('Scansione in corso...');
        
        $('#dr-tbody').empty();
        $('#dr-progress-wrap').show();
        $('#dr-status').text('Inizializzazione...');
        $('#dr-bar').css('width', '0%');
        
        scan(0);
    });

    function scan(offset) {
        // Raccoglie tutti i parametri dalla UI
        var isTitle   = $('#dr-check-title').is(':checked') ? 1 : 0;
        var isSlug    = $('#dr-check-slug').is(':checked') ? 1 : 0;
        var isContent = $('#dr-check-content').is(':checked') ? 1 : 0;
        var threshold = $('#dr-threshold').val();

        $.post(cfg.ajaxUrl, {
            action: 'dr_scan',
            nonce: cfg.nonce,
            offset: offset,
            check_title: isTitle,
            check_slug: isSlug,
            check_content: isContent,
            threshold: threshold
        }, function(res) {
            if (res.success) {
                // Stampa eventuali duplicati trovati
                res.data.matches.forEach(appendMatch);
                
                // Gestione e aggiornamento della Barra di Stato e Percentuale
                var total = res.data.total;
                var current = offset + 1;
                var progress = total > 0 ? Math.round((current / total) * 100) : 100;
                
                // Aggiornamento grafico
                $('#dr-bar').css('width', progress + '%');
                $('#dr-status').text(`Analisi post ${current} di ${total} (${progress}%)...`);

                // Loop ricorsivo o fine
                if (current < total) {
                    scan(current);
                } else {
                    $('#dr-status').text(cfg.labels.done + ` (100%)`);
                    $('#dr-start').prop('disabled', false).text('Avvia nuova scansione');
                }
            } else {
                $('#dr-status').text(res.data || cfg.labels.error);
                $('#dr-start').prop('disabled', false).text('Riprova');
            }
        }).fail(function() {
            // Gestione del timeout o errore server 500
            $('#dr-status').text("Errore di rete o server sovraccarico. Riduci i criteri di ricerca.");
            $('#dr-start').prop('disabled', false).text('Riprova');
        });
    }
})(jQuery);
