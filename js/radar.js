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
                alert(cfg.labels.error);
            }
        });
    });

    // Loop di scansione (Logica simile alla precedente ma pulita)
    $('#dr-start').on('click', function() {
        $('#dr-tbody').empty();
        $('#dr-progress-wrap').show();
        scan(0);
    });

    function scan(offset) {
        $.post(cfg.ajaxUrl, {
            action: 'dr_scan',
            nonce: cfg.nonce,
            offset: offset,
            check_title: $('#dr-check-title').is(':checked') ? 1 : 0
        }, function(res) {
            if (res.success) {
                res.data.matches.forEach(appendMatch);
                var progress = Math.round(((offset + 1) / res.data.total) * 100);
                $('#dr-bar').css('width', progress + '%');
                if (offset + 1 < res.data.total) scan(offset + 1);
                else $('#dr-status').text(cfg.labels.done);
            }
        });
    }
})(jQuery);
